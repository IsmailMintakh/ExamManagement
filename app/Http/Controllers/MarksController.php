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
     * Single source of truth for "may this user enter/edit marks for this
     * (subject, section)". Strict assignment-based RBAC:
     *  1. Super / school admin — always.
     *  2. Teacher with an active SubjectTeacher row for (subject, section).
     *
     * Class-teacher-of-section is NOT a marks-entry avenue. A class teacher
     * who doesn't ALSO hold a SubjectTeacher row for the subject sees the
     * marks status read-only on their /my-class hub, but can't open the
     * entry page for it. This prevents an unassigned class teacher from
     * silently overriding another teacher's grades.
     */
    protected function canEnterMarks($user, int $subjectId, int $sectionId): bool
    {
        if ($user->isSuperAdmin() || $user->isSchoolAdmin()) {
            return true;
        }

        return SubjectTeacher::where('user_id', $user->id)
            ->where('subject_id', $subjectId)
            ->where('section_id', $sectionId)
            ->where('is_active', true)
            ->exists();
    }

    public function index(Request $request): Response
    {
        $user = $request->user();
        $currentSession = AcademicSession::currentSession();

        // What can this user enter?
        //   - Admins (super / school): everything in scope.
        //   - Teachers: only (subject, section) pairs they have an active
        //     SubjectTeacher row for. Being class teacher of a section does
        //     NOT grant marks entry — that's read-only via /my-class.
        $isAdmin = $user->isSuperAdmin() || $user->isSchoolAdmin();
        $teacherAssignments = $isAdmin ? collect() : SubjectTeacher::where('user_id', $user->id)
            ->where('is_active', true)
            ->when($currentSession, fn ($q) => $q->where('academic_session_id', $currentSession->id))
            ->get();

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

        // Bulk-load soft-deleted Mark counts per (exam, subject, section)
        // so the index can flag rows that have hidden marks (and link the
        // teacher straight to the entry page where the restore banner
        // appears). One grouped query covers every assignment we render.
        $trashedMarksMap = Mark::onlyTrashed()
            ->whereIn('exam_id', $examsRaw->pluck('id'))
            ->whereIn('section_id', $sectionIds)
            ->selectRaw('exam_id, subject_id, section_id, COUNT(*) as cnt')
            ->groupBy('exam_id', 'subject_id', 'section_id')
            ->get()
            ->mapWithKeys(fn ($r) => ["{$r->exam_id}-{$r->subject_id}-{$r->section_id}" => (int) $r->cnt]);

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

        $exams = $examsRaw->map(function ($exam) use ($isAdmin, $teacherAssignments, $allSections, $submissionsMap, $studentCountMap, $adminTeacherMap, $trashedMarksMap) {
            $assignments = [];

            foreach ($exam->examSubjects as $es) {
                $sections = $allSections->get($es->school_class_id, collect());

                foreach ($sections as $sec) {
                    // Skip sections that were explicitly excluded from this
                    // subject on the exam-edit page. No marks-entry cell,
                    // no submission expectation.
                    if (!$es->appliesToSection((int) $sec->id)) continue;

                    // Non-admins only see (subject, section) pairs they have
                    // an active SubjectTeacher row for. Class-teacher-of-
                    // section is not a marks-entry avenue — strictly
                    // assignment-based RBAC.
                    //
                    // This also handles the primary/higher separation: a
                    // primary teacher has no SubjectTeacher row on Class 8,
                    // a Class 8 teacher has no SubjectTeacher row on Class 2,
                    // so neither will see the other's exams here.
                    if (!$isAdmin) {
                        $isMySubject = $teacherAssignments->where('subject_id', $es->subject_id)
                            ->where('section_id', $sec->id)->isNotEmpty();
                        if (!$isMySubject) continue;
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
                        // Hidden Mark count for this row — drives the
                        // "N hidden" indicator + restore prompt in the list.
                        'deleted_marks_count' => (int) ($trashedMarksMap["{$exam->id}-{$es->subject_id}-{$sec->id}"] ?? 0),
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

        // Respect per-subject "excluded sections" — if this section was
        // ticked off in the exam-subject picker, marks entry doesn't
        // apply and we abort instead of showing a phantom paper.
        if (!$examSubject->appliesToSection($section)) {
            abort(404, 'This section does not take this subject in the current exam.');
        }

        $students = Student::where('section_id', $section)
            ->active()
            ->when($currentSession, fn ($q) => $q->where('academic_session_id', $currentSession->id))
            ->orderBy('roll_no')
            ->orderBy('name')
            ->get();

        // Auto-restore — silently un-trash any soft-deleted Marks for
        // this (exam, subject, section) BEFORE we read the live set.
        // Reason: earlier remove-subject / remove-class endpoints called
        // Mark::delete() which only set deleted_at, so the rows are still
        // physically present in the DB but filtered out by the default
        // query. We don't ask the user to click anything — if data is
        // recoverable we just bring it back. Live rows that already exist
        // for the same student win (newest entry rule), so re-entered
        // marks are never overwritten.
        $autoRestoredCount = 0;
        $trashedForThisPaper = Mark::onlyTrashed()
            ->where('exam_id', $exam)
            ->where('subject_id', $subject)
            ->where('section_id', $section)
            ->get(['id', 'student_id', 'status']);
        if ($trashedForThisPaper->isNotEmpty()) {
            $liveStudentIds = Mark::where('exam_id', $exam)
                ->where('subject_id', $subject)
                ->where('section_id', $section)
                ->pluck('student_id')
                ->flip();
            $restorableIds = $trashedForThisPaper
                ->filter(fn ($m) => !$liveStudentIds->has($m->student_id))
                ->pluck('id');
            if ($restorableIds->isNotEmpty()) {
                $autoRestoredCount = Mark::onlyTrashed()
                    ->whereIn('id', $restorableIds)
                    ->update(['deleted_at' => null]);
                // If any restored mark was previously submitted, rebuild
                // the submission row so the "Submitted" badge stays
                // truthful and the post-submit edit policy still applies.
                $needsSubmission = Mark::whereIn('id', $restorableIds)
                    ->where('status', 'submitted')
                    ->exists();
                if ($needsSubmission) {
                    MarksSubmission::updateOrCreate(
                        ['exam_id' => $exam, 'subject_id' => $subject, 'section_id' => $section],
                        ['status' => 'submitted', 'submitted_at' => now(), 'submitted_by' => $user->id]
                    );
                }
            }
        }

        $existingMarks = Mark::where('exam_id', $exam)
            ->where('subject_id', $subject)
            ->where('section_id', $section)
            ->get()
            ->keyBy('student_id');

        $submission = MarksSubmission::where('exam_id', $exam)
            ->where('subject_id', $subject)
            ->where('section_id', $section)
            ->first();

        // Any leftover trashed rows for this paper after auto-restore
        // (only happens if every trashed row has a newer live counterpart).
        // Kept as a prop for transparency, but the banner is rarely shown
        // now that auto-restore runs on every page load.
        $deletedMarksCount = Mark::onlyTrashed()
            ->where('exam_id', $exam)
            ->where('subject_id', $subject)
            ->where('section_id', $section)
            ->count();

        $subjectModel = Subject::findOrFail($subject);
        $isSubmitted = $submission && $submission->status === 'submitted';

        // Admins can always edit submitted marks. For teachers we consult
        // the per-exam post-submit edit policy ('none' | 'all' | 'specific')
        // set by the admin in the Exam Edit screen.
        $isAdmin = $user->isSuperAdmin() || $user->isSchoolAdmin();
        $canEditAfterSubmit = $isAdmin
            || $examModel->allowsPostSubmitEdit($sectionModel->school_class_id, $section);

        return Inertia::render('Marks/Entry', [
            'exam' => $examModel,
            'examSubject' => $examSubject,
            'subject' => $subjectModel,
            'schoolClass' => $sectionModel->schoolClass,
            'section' => $sectionModel,
            'students' => $students,
            'existingMarks' => $existingMarks,
            'isSubmitted' => $isSubmitted,
            'canEditAfterSubmit' => $canEditAfterSubmit,
            'deletedMarksCount' => $deletedMarksCount,
            'autoRestoredCount' => $autoRestoredCount,
        ]);
    }

    /**
     * Restore soft-deleted marks for THIS (exam, subject, section) only.
     * Scoped variant of ExamController::restoreDeletedMarks — keeps the
     * blast radius tight so a teacher recovering one subject doesn't
     * accidentally bring back marks for a different paper. Same safety
     * rule: skip any (student, subject) where a live (re-entered) mark
     * already exists.
     */
    public function restoreDeletedMarks(Request $request, int $exam, int $subject, int $section): RedirectResponse
    {
        $user = $request->user();
        // Admins always; teachers only when they're the assigned subject
        // teacher for this (subject, section). Reuse the existing gate.
        if (!$this->canEnterMarks($user, $subject, $section)) {
            abort(403, 'You can only restore marks for subjects assigned to you.');
        }

        $trashed = Mark::onlyTrashed()
            ->where('exam_id', $exam)
            ->where('subject_id', $subject)
            ->where('section_id', $section)
            ->get(['id', 'student_id']);

        if ($trashed->isEmpty()) {
            return back()->with('info', 'No deleted marks to restore for this subject.');
        }

        $liveStudentIds = Mark::where('exam_id', $exam)
            ->where('subject_id', $subject)
            ->where('section_id', $section)
            ->pluck('student_id')
            ->flip();

        $toRestoreIds = $trashed->filter(fn ($m) => !$liveStudentIds->has($m->student_id))->pluck('id');
        $skipped = $trashed->count() - $toRestoreIds->count();
        $restored = 0;

        if ($toRestoreIds->isNotEmpty()) {
            $restored = Mark::onlyTrashed()
                ->whereIn('id', $toRestoreIds)
                ->update(['deleted_at' => null]);
        }

        // Rebuild the submission record if any restored Marks are in
        // 'submitted' status — submission row may have been hard-deleted.
        $hasSubmittedRestored = Mark::whereIn('id', $toRestoreIds)
            ->where('status', 'submitted')
            ->exists();
        if ($hasSubmittedRestored) {
            MarksSubmission::updateOrCreate(
                ['exam_id' => $exam, 'subject_id' => $subject, 'section_id' => $section],
                ['status' => 'submitted', 'submitted_at' => now(), 'submitted_by' => $user->id]
            );
        }

        $msg = "Restored {$restored} mark".($restored === 1 ? '' : 's').'.';
        if ($skipped > 0) {
            $msg .= ' '.$skipped.' skipped — newer marks already exist for those students.';
        }
        return back()->with('success', $msg);
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
        $user = $request->user();
        $currentSession = AcademicSession::currentSession();

        // Assignment-scoped (this endpoint previously had NO access check).
        if (!$this->canEnterMarks($user, (int) $data['subject_id'], (int) $data['section_id'])) {
            abort(403, 'You can only enter marks for subjects assigned to you.');
        }

        $examSubject = ExamSubject::where('exam_id', $exam->id)
            ->where('subject_id', $data['subject_id'])
            ->firstOrFail();

        $section = Section::with('schoolClass')->findOrFail($data['section_id']);

        // Are any of the marks we're saving already in 'submitted' status?
        // If so we're in the post-submission EDIT path, which has its own
        // gate (admin always allowed, teachers only when the exam's
        // post_submit_edit_policy permits this class+section). Marks-entry
        // doesn't need to be "open" for the edit path — that lock is for
        // the original entry flow only.
        $alreadySubmittedCount = Mark::where('exam_id', $exam->id)
            ->where('subject_id', $data['subject_id'])
            ->where('section_id', $data['section_id'])
            ->where('status', 'submitted')
            ->count();
        $isEditingSubmitted = $alreadySubmittedCount > 0;

        if ($isEditingSubmitted) {
            $isAdmin = $user->isSuperAdmin() || $user->isSchoolAdmin();
            if (!$isAdmin && !$exam->allowsPostSubmitEdit((int) $section->school_class_id, (int) $data['section_id'])) {
                abort(403, 'Marks for this section have already been submitted. Ask your administrator to enable post-submission edits.');
            }
        } else {
            // Normal draft save still requires marks-entry to be open.
            if (!$exam->isMarksEntryOpen()) {
                return redirect()->back()->with('error', 'Marks entry is not open for this exam.');
            }
        }

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

        // Snapshot BEFORE writing edits so we can roll back if something
        // goes wrong. This is the paranoia layer for the "teachers lost
        // all their marks" incidents.
        \App\Services\MarkSnapshotService::capture(
            $exam->id, (int) $data['subject_id'], (int) $data['section_id'],
            'pre_store', $user->id
        );

        foreach ($data['marks'] as $markData) {
            // withTrashed: see soft-deleted rows so we can revive them on
            // save instead of triggering a unique-constraint violation.
            // The marks_unique index on (exam_id, student_id, subject_id)
            // does NOT include deleted_at — without withTrashed, the row
            // is "invisible" to updateOrCreate but the index still
            // claims the slot, and the INSERT fails with 1062.
            $existing = Mark::withTrashed()
                ->where('exam_id', $exam->id)
                ->where('subject_id', $data['subject_id'])
                ->where('student_id', $markData['student_id'])
                ->where('section_id', $data['section_id'])
                ->first();

            // Preserve 'submitted' status for marks being edited post-submit
            // — the row was submitted once, the edit just revises the numbers,
            // it doesn't unsubmit. Otherwise default to draft for fresh entry.
            $rowStatus = $existing && $existing->status === 'submitted' ? 'submitted' : 'draft';

            // firstOrNew + manual deleted_at write — `deleted_at` is not
            // in $fillable, so mass-assignment via updateOrCreate silently
            // drops it. Direct property write bypasses the guard.
            $mark = $existing ?: new Mark([
                'exam_id' => $exam->id,
                'subject_id' => $data['subject_id'],
                'student_id' => $markData['student_id'],
                'section_id' => $data['section_id'],
            ]);
            $mark->fill([
                'exam_id' => $exam->id,
                'subject_id' => $data['subject_id'],
                'student_id' => $markData['student_id'],
                'section_id' => $data['section_id'],
                'exam_subject_id' => $examSubject->id,
                'school_id' => $section->schoolClass->school_id,
                'school_class_id' => $section->school_class_id,
                'academic_session_id' => $currentSession?->id,
                'marks_obtained' => ($markData['is_absent'] ?? false) ? 0 : ($markData['marks_obtained'] ?? 0),
                'total_marks' => $examSubject->total_marks,
                'grace_marks' => 0,
                'is_absent' => $markData['is_absent'] ?? false,
                'remarks' => $markData['remarks'] ?? null,
                'status' => $rowStatus,
                'entered_by' => $user->id,
                'submitted_at' => $rowStatus === 'submitted'
                    ? ($existing?->submitted_at ?? now())
                    : null,
            ]);
            $mark->deleted_at = null;
            $mark->save();
        }

        // Post-submission edit path → cascade through ResultProcessingService
        // so percentage / grade / pass-fail / positions all reflect the new
        // numbers. Same service the normal generate flow uses.
        if ($isEditingSubmitted) {
            app(\App\Services\ResultProcessingService::class)
                ->generateResults($exam, (int) $section->school_class_id, (int) $data['section_id']);
            return redirect()->back()->with('success',
                'Marks updated successfully. Result calculations have been refreshed.');
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

        // Throttled snapshot BEFORE any autosave writes. Only fires if the
        // last autosave snapshot for this paper is older than the throttle
        // window (60s) — keeps a rolling per-session backup without
        // flooding the table at the 2.5-second autosave cadence.
        \App\Services\MarkSnapshotService::captureIfDue(
            $exam->id, (int) $data['subject_id'], (int) $data['section_id'], $user->id
        );

        // Skip rows that are completely blank (no marks AND not absent) — nothing to draft yet.
        $saved = 0;
        foreach ($data['marks'] as $markData) {
            $hasMarks = isset($markData['marks_obtained']) && $markData['marks_obtained'] !== null && $markData['marks_obtained'] !== '';
            $isAbsent = (bool) ($markData['is_absent'] ?? false);
            if (!$hasMarks && !$isAbsent) {
                continue;
            }

            // Don't overwrite already-submitted marks via autosave.
            // withTrashed so we see a soft-deleted row (the unique
            // constraint marks_unique still occupies its slot without
            // deleted_at in the key — see store() comment).
            $existing = Mark::withTrashed()
                ->where('exam_id', $exam->id)
                ->where('subject_id', $data['subject_id'])
                ->where('student_id', $markData['student_id'])
                ->where('section_id', $data['section_id'])
                ->first();

            if ($existing && $existing->status === 'submitted' && !$existing->trashed()) {
                continue;
            }

            // Same pattern as store(): firstOrNew + direct deleted_at
            // write so reviving a trashed row works (deleted_at is not
            // fillable so mass-assignment via updateOrCreate would be
            // dropped).
            $mark = $existing ?: new Mark([
                'exam_id' => $exam->id,
                'subject_id' => $data['subject_id'],
                'student_id' => $markData['student_id'],
                'section_id' => $data['section_id'],
            ]);
            $reviveStatus = $existing && $existing->status === 'submitted' ? 'submitted' : 'draft';
            $mark->fill([
                'exam_id' => $exam->id,
                'subject_id' => $data['subject_id'],
                'student_id' => $markData['student_id'],
                'section_id' => $data['section_id'],
                'exam_subject_id' => $examSubject->id,
                'school_id' => $section->schoolClass->school_id,
                'school_class_id' => $section->school_class_id,
                'academic_session_id' => $currentSession?->id,
                'marks_obtained' => $isAbsent ? 0 : (float) $markData['marks_obtained'],
                'total_marks' => $examSubject->total_marks,
                'grace_marks' => 0,
                'is_absent' => $isAbsent,
                'remarks' => $markData['remarks'] ?? null,
                'status' => $reviveStatus,
                'entered_by' => $user->id,
                'submitted_at' => $reviveStatus === 'submitted'
                    ? $existing->submitted_at
                    : null,
            ]);
            $mark->deleted_at = null;
            $mark->save();
            $saved++;
        }

        return response()->json([
            'saved' => $saved,
            'saved_at' => now()->toIso8601String(),
        ]);
    }

    /**
     * Partial submit: flip every current DRAFT mark for this paper to
     * "submitted" (no "all students must have marks" gate). Powers the
     * "Submit drafts (N)" button on the marks entry page, which appears
     * when the teacher has autosaved values for some students but the
     * usual Submit button is blocked because a few students are still
     * missing marks. Cascades to result regeneration like submit() does.
     */
    public function submitDrafts(int $exam, int $subject, int $section): RedirectResponse
    {
        $user = request()->user();
        $examModel = Exam::findOrFail($exam);
        $sectionModel = Section::with('schoolClass')->findOrFail($section);

        if (!$examModel->isMarksEntryOpen()) {
            return redirect()->back()->with('error', 'Marks entry is not open for this exam.');
        }

        if (!$this->canEnterMarks($user, $subject, $section)) {
            abort(403, 'You can only submit marks for subjects assigned to you.');
        }

        \App\Services\MarkSnapshotService::capture(
            $exam, $subject, $section, 'pre_submit_drafts', $user->id
        );

        $flipped = Mark::where('exam_id', $exam)
            ->where('subject_id', $subject)
            ->where('section_id', $section)
            ->where('status', 'draft')
            ->update(['status' => 'submitted', 'submitted_at' => now()]);

        if ($flipped === 0) {
            return redirect()->back()->with('info', 'No draft marks to submit for this paper.');
        }

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

        try {
            app(\App\Services\ResultProcessingService::class)
                ->generateResults($examModel, (int) $sectionModel->school_class_id, $section);
        } catch (\Throwable $e) {
            \Log::warning('Result regeneration after submitDrafts failed: ' . $e->getMessage());
        }

        return redirect()->back()->with('success', "Submitted {$flipped} draft mark(s). Results updated.");
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

        \App\Services\MarkSnapshotService::capture(
            $exam, $subject, $section, 'pre_submit', $user->id
        );

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

        // A stable "post-submit" checkpoint teachers can always roll back to.
        \App\Services\MarkSnapshotService::capture(
            $exam, $subject, $section, 'post_submit', $user->id,
            'Finalized submission — safe restore point.'
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

        // ─── Cascade to Result table ───
        // Without this, submitting a subject only flips Mark.status to
        // "submitted" — the Result rows keep their old subject_results
        // JSON so the new subject is missing from the /results view and
        // the grand total. Re-running generateResults after every submit
        // makes results reflect the marks that were just finalized.
        // The service is read-only on Marks and idempotent, so this is
        // cheap and safe to call whether Results exist yet or not.
        try {
            app(\App\Services\ResultProcessingService::class)
                ->generateResults($examModel, (int) $sectionModel->school_class_id, $section);
        } catch (\Throwable $e) {
            \Log::warning('Result regeneration after submit failed: ' . $e->getMessage());
        }

        return redirect()->back()->with('success', 'Marks submitted successfully.');
    }

    /**
     * List every marks snapshot for a (exam, subject, section), newest
     * first. Powers the "Marks History" modal on the entry page — teachers
     * see what backups exist and can restore any one of them.
     */
    public function snapshotList(Request $request, int $exam, int $subject, int $section): JsonResponse
    {
        $user = $request->user();

        // Same access gate as marks entry — assignment-scoped for
        // teachers, unrestricted for admins.
        if (!$this->canEnterMarks($user, $subject, $section)) {
            abort(403, 'You can only view snapshots for subjects assigned to you.');
        }

        $snapshots = \App\Services\MarkSnapshotService::forPaper($exam, $subject, $section)
            ->map(fn ($s) => [
                'id' => $s->id,
                'taken_at' => $s->taken_at?->toIso8601String(),
                'taken_at_human' => $s->taken_at?->diffForHumans(),
                'taken_by' => $s->takenBy?->name,
                'trigger' => $s->trigger,
                'student_count' => $s->student_count,
                'notes' => $s->notes,
                'preview' => collect($s->payload)->take(3)->map(fn ($r) => [
                    'student_id' => $r['student_id'] ?? null,
                    'marks_obtained' => $r['marks_obtained'] ?? null,
                    'is_absent' => $r['is_absent'] ?? false,
                    'status' => $r['status'] ?? null,
                ])->values()->all(),
            ])->values();

        return response()->json(['snapshots' => $snapshots]);
    }

    /**
     * Restore a specific snapshot. Wraps MarkSnapshotService::restore
     * which auto-captures a pre_restore snapshot first, so the restore
     * is itself undoable.
     */
    public function snapshotRestore(Request $request, int $exam, int $subject, int $section, int $snapshotId): RedirectResponse
    {
        $user = $request->user();

        if (!$this->canEnterMarks($user, $subject, $section)) {
            abort(403, 'You can only restore snapshots for subjects assigned to you.');
        }

        $snap = \App\Models\MarkSnapshot::where('exam_id', $exam)
            ->where('subject_id', $subject)
            ->where('section_id', $section)
            ->findOrFail($snapshotId);

        $restored = \App\Services\MarkSnapshotService::restore($snap, $user->id);

        // Cascade to Results so the restore is reflected everywhere.
        try {
            $examModel = Exam::findOrFail($exam);
            $sectionModel = Section::with('schoolClass')->findOrFail($section);
            app(\App\Services\ResultProcessingService::class)
                ->generateResults($examModel, (int) $sectionModel->school_class_id, $section);
        } catch (\Throwable $e) {
            \Log::warning('snapshot restore: result regen failed: '.$e->getMessage());
        }

        return redirect()->back()->with('success',
            "Restored {$restored} mark(s) from the snapshot. A pre-restore backup was also taken automatically, so you can undo this if needed.");
    }
}
