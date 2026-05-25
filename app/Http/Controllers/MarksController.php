<?php

namespace App\Http\Controllers;

use App\Models\AcademicSession;
use App\Models\Exam;
use App\Models\ExamSubject;
use App\Models\Mark;
use App\Models\MarksSubmission;
use App\Models\Section;
use App\Models\Student;
use App\Models\Subject;
use App\Models\SubjectTeacher;
use App\Models\User;
use App\Notifications\MarksSubmittedNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Inertia\Inertia;
use Inertia\Response;

class MarksController extends Controller
{
    /**
     * The single source of truth for "may this user enter/edit marks for
     * this (subject, section)". Three avenues:
     *  1. Super / school admin — always.
     *  2. Subject teacher with an active SubjectTeacher row for the pair.
     *  3. Class teacher of the section — many schools want the class teacher
     *     to be able to fill in marks for any subject in their section (e.g.
     *     when the subject teacher is absent or hasn't been assigned yet).
     *     The class-teacher role is seeded with `marks.enter`, so honoring
     *     section assignment here keeps the policy and the role consistent.
     */
    protected function canEnterMarks($user, int $subjectId, int $sectionId): bool
    {
        if ($user->isSuperAdmin() || $user->isSchoolAdmin()) {
            return true;
        }

        $isSubjectTeacher = SubjectTeacher::where('user_id', $user->id)
            ->where('subject_id', $subjectId)
            ->where('section_id', $sectionId)
            ->where('is_active', true)
            ->exists();
        if ($isSubjectTeacher) return true;

        return Section::where('id', $sectionId)
            ->where('class_teacher_id', $user->id)
            ->where('is_active', true)
            ->exists();
    }

    public function index(Request $request): Response
    {
        $user = $request->user();
        $currentSession = AcademicSession::currentSession();

        // What can this user enter?
        //   - Subject teachers: their (subject, section) pairs.
        //   - Class teachers: every subject in the section(s) they lead — the
        //     class teacher is responsible for getting all marks entered for
        //     their section and can fill in for absent subject teachers.
        // canEnterMarks() applies the same two-avenue rule on the entry page.
        $isAdmin = $user->isSuperAdmin() || $user->isSchoolAdmin();
        $teacherAssignments = $isAdmin ? collect() : SubjectTeacher::where('user_id', $user->id)
            ->where('is_active', true)
            ->when($currentSession, fn ($q) => $q->where('academic_session_id', $currentSession->id))
            ->get();
        $classTeacherSectionIds = $isAdmin ? collect() : Section::where('class_teacher_id', $user->id)
            ->active()
            ->pluck('id');

        $examsRaw = Exam::query()
            ->where('status', 'marks_entry')
            ->when($currentSession, fn ($q) => $q->where('academic_session_id', $currentSession->id))
            ->when(!$user->isSuperAdmin(), fn ($q) => $q->visibleToSchool($user->school_id))
            ->with(['examType', 'academicSession', 'examSubjects.subject', 'examSubjects.schoolClass'])
            ->latest()
            ->get();

        // Batch load all sections in one query (scoped by school if needed)
        $classIds = $examsRaw->pluck('examSubjects')->flatten()->pluck('school_class_id')->unique();
        $allSections = Section::whereIn('school_class_id', $classIds)
            ->when(!$user->isSuperAdmin() && $user->isSchoolAdmin(), fn ($q) => $q->whereHas('schoolClass', fn ($q2) => $q2->where('school_id', $user->school_id)))
            ->active()->get()
            ->groupBy('school_class_id');

        $sectionIds = $allSections->flatten(1)->pluck('id');

        // Batch load all submissions (keyed by exam_id-subject_id-section_id)
        $submissionsMap = MarksSubmission::whereIn('exam_id', $examsRaw->pluck('id'))
            ->whereIn('section_id', $sectionIds)
            ->get()
            ->keyBy(fn ($s) => "{$s->exam_id}-{$s->subject_id}-{$s->section_id}");

        // Batch load student counts per section
        $studentCountMap = Student::whereIn('section_id', $sectionIds)
            ->where('status', 'active')
            ->when($currentSession, fn ($q) => $q->where('academic_session_id', $currentSession->id))
            ->selectRaw('section_id, COUNT(*) as cnt')
            ->groupBy('section_id')
            ->pluck('cnt', 'section_id');

        // For admin view: pre-load all subject-teacher assignments for the
        // classes/sections in scope so each row carries the responsible
        // teacher's name. Lets DDO/Principal filter "by teacher" on the page.
        $adminTeacherMap = collect();
        if ($isAdmin) {
            $adminTeacherMap = SubjectTeacher::query()
                ->whereIn('school_class_id', $classIds)
                ->where('is_active', true)
                ->when($currentSession, fn ($q) => $q->where('academic_session_id', $currentSession->id))
                ->with('user:id,name')
                ->get()
                ->keyBy(fn ($st) => "{$st->subject_id}|{$st->school_class_id}|{$st->section_id}");
        }

        $exams = $examsRaw->map(function ($exam) use ($isAdmin, $teacherAssignments, $allSections, $submissionsMap, $studentCountMap, $adminTeacherMap) {
            $assignments = [];

            foreach ($exam->examSubjects as $es) {
                $sections = $allSections->get($es->school_class_id, collect());

                foreach ($sections as $sec) {
                    // Non-admins see (subject, section) pairs either assigned
                    // to them as subject teacher OR every subject in any
                    // section they lead as class teacher.
                    if (!$isAdmin) {
                        $isMySubject = $teacherAssignments->where('subject_id', $es->subject_id)
                            ->where('section_id', $sec->id)->isNotEmpty();
                        $isMySection = $classTeacherSectionIds->contains($sec->id);
                        if (!$isMySubject && !$isMySection) continue;
                    }

                    $submission = $submissionsMap->get("{$exam->id}-{$es->subject_id}-{$sec->id}");
                    $teacherCell = $isAdmin
                        ? $adminTeacherMap->get("{$es->subject_id}|{$es->school_class_id}|{$sec->id}")
                        : null;

                    $assignments[] = [
                        'id' => $es->id . '-' . $sec->id,
                        'subject_id' => $es->subject_id,
                        'subject_name' => $es->subject?->name,
                        'class_id' => $es->school_class_id,
                        'class_name' => $es->schoolClass?->name,
                        'section_id' => $sec->id,
                        'section_name' => $sec->name,
                        'teacher_id' => $teacherCell?->user_id,
                        'teacher_name' => $teacherCell?->user?->name,
                        'student_count' => (int) ($studentCountMap[$sec->id] ?? 0),
                        'status' => $submission?->status,
                    ];
                }
            }

            return [
                'id' => $exam->id,
                'name' => $exam->name,
                'exam_type' => $exam->examType?->name,
                'academic_session' => $exam->academicSession?->name,
                'is_locked' => $exam->is_locked,
                'assignments' => $assignments,
            ];
        })
        ->filter(fn ($exam) => count($exam['assignments']) > 0)
        ->values();

        return Inertia::render('Marks/Index', [
            'exams' => $exams,
            'isAdmin' => $isAdmin,
        ]);
    }

    public function entry(Request $request, int $exam, int $subject, int $section): Response
    {
        $user = $request->user();
        $examModel = Exam::with(['examSubjects'])->findOrFail($exam);
        $sectionModel = Section::with(['schoolClass'])->findOrFail($section);
        $currentSession = AcademicSession::currentSession();

        // Marks entry is assignment-scoped. Class teachers do NOT get entry
        // to their section's other subjects — they monitor read-only at
        // /my-class. They keep entry only for subjects they're assigned.
        if (!$this->canEnterMarks($user, $subject, $section)) {
            abort(403, 'You can only enter marks for subjects assigned to you.');
        }

        $examSubject = ExamSubject::where('exam_id', $exam)
            ->where('subject_id', $subject)
            ->where('school_class_id', $sectionModel->school_class_id)
            ->firstOrFail();

        $students = Student::where('section_id', $section)
            ->active()
            ->when($currentSession, fn ($q) => $q->where('academic_session_id', $currentSession->id))
            ->orderBy('roll_no')
            ->orderBy('name')
            ->get();

        $existingMarks = Mark::where('exam_id', $exam)
            ->where('subject_id', $subject)
            ->where('section_id', $section)
            ->get()
            ->keyBy('student_id');

        $submission = MarksSubmission::where('exam_id', $exam)
            ->where('subject_id', $subject)
            ->where('section_id', $section)
            ->first();

        $subjectModel = Subject::findOrFail($subject);

        return Inertia::render('Marks/Entry', [
            'exam' => $examModel,
            'examSubject' => $examSubject,
            'subject' => $subjectModel,
            'schoolClass' => $sectionModel->schoolClass,
            'section' => $sectionModel,
            'students' => $students,
            'existingMarks' => $existingMarks,
            'isSubmitted' => $submission && $submission->status === 'submitted',
        ]);
    }

    public function store(Request $request, Exam $exam): RedirectResponse
    {
        $data = $request->validate([
            'subject_id' => ['required', 'exists:subjects,id'],
            'section_id' => ['required', 'exists:sections,id'],
            'school_class_id' => ['nullable', 'exists:school_classes,id'],
            'marks' => ['required', 'array', 'min:1'],
            'marks.*.student_id' => ['required', 'exists:students,id'],
            'marks.*.marks_obtained' => ['nullable', 'numeric', 'min:0'],
            'marks.*.is_absent' => ['boolean'],
            'marks.*.remarks' => ['nullable', 'string', 'max:255'],
        ]);

        $data['exam_id'] = $exam->id;
        $data['status'] = 'draft';
        $user = $request->user();
        $currentSession = AcademicSession::currentSession();

        // Assignment-scoped (this endpoint previously had NO access check).
        if (!$this->canEnterMarks($user, (int) $data['subject_id'], (int) $data['section_id'])) {
            abort(403, 'You can only enter marks for subjects assigned to you.');
        }

        if (!$exam->isMarksEntryOpen()) {
            return redirect()->back()->with('error', 'Marks entry is not open for this exam.');
        }

        $examSubject = ExamSubject::where('exam_id', $exam->id)
            ->where('subject_id', $data['subject_id'])
            ->firstOrFail();

        $section = Section::with('schoolClass')->findOrFail($data['section_id']);

        // Marks can never exceed the subject's total — reject, don't clamp.
        $maxMarks = (float) $examSubject->total_marks;
        foreach ($data['marks'] as $m) {
            if (empty($m['is_absent']) && isset($m['marks_obtained']) && $m['marks_obtained'] !== null
                && (float) $m['marks_obtained'] > $maxMarks) {
                return redirect()->back()->withErrors([
                    'marks' => "Marks cannot exceed the subject total of {$maxMarks}.",
                ]);
            }
        }

        foreach ($data['marks'] as $markData) {
            Mark::updateOrCreate(
                [
                    'exam_id' => $exam->id,
                    'subject_id' => $data['subject_id'],
                    'student_id' => $markData['student_id'],
                    'section_id' => $data['section_id'],
                ],
                [
                    'exam_subject_id' => $examSubject->id,
                    'school_id' => $section->schoolClass->school_id,
                    'school_class_id' => $section->school_class_id,
                    'academic_session_id' => $currentSession?->id,
                    'marks_obtained' => ($markData['is_absent'] ?? false) ? 0 : ($markData['marks_obtained'] ?? 0),
                    'total_marks' => $examSubject->total_marks,
                    'grace_marks' => 0,
                    'is_absent' => $markData['is_absent'] ?? false,
                    'remarks' => $markData['remarks'] ?? null,
                    'status' => $data['status'],
                    'entered_by' => $user->id,
                    'submitted_at' => null,
                ]
            );
        }

        return redirect()->back()->with('success', 'Marks saved as draft.');
    }

    /**
     * AJAX-friendly autosave for the spreadsheet grid. Saves dirty rows as draft and
     * returns a small JSON payload — no page reload, no flash messages.
     */
    public function autosave(Request $request, Exam $exam): JsonResponse
    {
        $data = $request->validate([
            'subject_id' => ['required', 'exists:subjects,id'],
            'section_id' => ['required', 'exists:sections,id'],
            'marks' => ['required', 'array'],
            'marks.*.student_id' => ['required', 'exists:students,id'],
            'marks.*.marks_obtained' => ['nullable', 'numeric', 'min:0'],
            'marks.*.is_absent' => ['boolean'],
            'marks.*.remarks' => ['nullable', 'string', 'max:255'],
        ]);

        if (!$exam->isMarksEntryOpen()) {
            return response()->json(['error' => 'Marks entry is not open for this exam.'], 422);
        }

        $user = $request->user();
        $currentSession = AcademicSession::currentSession();

        $examSubject = ExamSubject::where('exam_id', $exam->id)
            ->where('subject_id', $data['subject_id'])
            ->firstOrFail();

        $section = Section::with('schoolClass')->findOrFail($data['section_id']);

        // Assignment-scoped: class teachers cannot autosave other subjects.
        if (!$this->canEnterMarks($user, (int) $data['subject_id'], (int) $data['section_id'])) {
            return response()->json(['error' => 'You can only enter marks for subjects assigned to you.'], 403);
        }

        // Reject any mark above the subject total before autosaving.
        $maxMarks = (float) $examSubject->total_marks;
        foreach ($data['marks'] as $m) {
            if (empty($m['is_absent']) && isset($m['marks_obtained']) && $m['marks_obtained'] !== null
                && $m['marks_obtained'] !== '' && (float) $m['marks_obtained'] > $maxMarks) {
                return response()->json([
                    'error' => "Marks cannot exceed the subject total of {$maxMarks}.",
                ], 422);
            }
        }

        // Skip rows that are completely blank (no marks AND not absent) — nothing to draft yet.
        $saved = 0;
        foreach ($data['marks'] as $markData) {
            $hasMarks = isset($markData['marks_obtained']) && $markData['marks_obtained'] !== null && $markData['marks_obtained'] !== '';
            $isAbsent = (bool) ($markData['is_absent'] ?? false);
            if (!$hasMarks && !$isAbsent) {
                continue;
            }

            // Don't overwrite already-submitted marks via autosave.
            $existing = Mark::where('exam_id', $exam->id)
                ->where('subject_id', $data['subject_id'])
                ->where('student_id', $markData['student_id'])
                ->where('section_id', $data['section_id'])
                ->first();

            if ($existing && $existing->status === 'submitted') {
                continue;
            }

            Mark::updateOrCreate(
                [
                    'exam_id' => $exam->id,
                    'subject_id' => $data['subject_id'],
                    'student_id' => $markData['student_id'],
                    'section_id' => $data['section_id'],
                ],
                [
                    'exam_subject_id' => $examSubject->id,
                    'school_id' => $section->schoolClass->school_id,
                    'school_class_id' => $section->school_class_id,
                    'academic_session_id' => $currentSession?->id,
                    'marks_obtained' => $isAbsent ? 0 : (float) $markData['marks_obtained'],
                    'total_marks' => $examSubject->total_marks,
                    'grace_marks' => 0,
                    'is_absent' => $isAbsent,
                    'remarks' => $markData['remarks'] ?? null,
                    'status' => 'draft',
                    'entered_by' => $user->id,
                    'submitted_at' => null,
                ]
            );
            $saved++;
        }

        return response()->json([
            'saved' => $saved,
            'saved_at' => now()->toIso8601String(),
        ]);
    }

    public function submit(int $exam, int $subject, int $section): RedirectResponse
    {
        $user = request()->user();
        $examModel = Exam::findOrFail($exam);
        $sectionModel = Section::with('schoolClass')->findOrFail($section);

        // Check if marks entry is open
        if (!$examModel->isMarksEntryOpen()) {
            return redirect()->back()->with('error', 'Marks entry is not open for this exam.');
        }

        // Assignment-scoped submit. Class teachers monitor read-only and
        // cannot submit subjects they aren't assigned to teach.
        if (!$this->canEnterMarks($user, $subject, $section)) {
            abort(403, 'You can only submit marks for subjects assigned to you.');
        }

        // Verify all students have marks entered
        $studentCount = Student::where('section_id', $section)
            ->active()
            ->when(AcademicSession::currentSession(), fn ($q, $s) => $q->where('academic_session_id', $s->id))
            ->count();

        $marksCount = Mark::where('exam_id', $exam)
            ->where('subject_id', $subject)
            ->where('section_id', $section)
            ->count();

        if ($marksCount < $studentCount) {
            return redirect()->back()->with('error', 'All students must have marks entered before submission.');
        }

        Mark::where('exam_id', $exam)
            ->where('subject_id', $subject)
            ->where('section_id', $section)
            ->where('status', 'draft')
            ->update(['status' => 'submitted', 'submitted_at' => now()]);

        MarksSubmission::updateOrCreate(
            ['exam_id' => $exam, 'subject_id' => $subject, 'section_id' => $section],
            [
                'school_class_id' => $sectionModel->school_class_id,
                'school_id' => $sectionModel->schoolClass->school_id,
                'submitted_by' => $user->id,
                'status' => 'submitted',
                'submitted_at' => now(),
            ]
        );

        // ─── Notify the people who care that marks just got submitted ───
        // - Class teacher of this section: needs to know all subjects are coming in
        // - School admin (Principal): tracks completion across the whole school
        // We swallow notification failures (e.g. no email driver, dead push
        // subscriptions) so the marks submission itself isn't blocked.
        try {
            $subjectModel = Subject::find($subject);
            if ($subjectModel) {
                $recipients = collect();
                if ($sectionModel->class_teacher_id && $sectionModel->class_teacher_id !== $user->id) {
                    $ct = User::find($sectionModel->class_teacher_id);
                    if ($ct) $recipients->push($ct);
                }
                $schoolAdmins = User::where('school_id', $sectionModel->schoolClass->school_id)
                    ->whereHas('roles', fn ($q) => $q->where('name', 'school-admin'))
                    ->where('id', '!=', $user->id)
                    ->get();
                $recipients = $recipients->merge($schoolAdmins)->unique('id');

                if ($recipients->isNotEmpty()) {
                    Notification::send(
                        $recipients,
                        new MarksSubmittedNotification($examModel, $subjectModel, $sectionModel, $user->name)
                    );
                }
            }
        } catch (\Throwable $e) {
            \Log::warning('MarksSubmittedNotification failed: ' . $e->getMessage());
        }

        return redirect()->back()->with('success', 'Marks submitted successfully.');
    }
}
