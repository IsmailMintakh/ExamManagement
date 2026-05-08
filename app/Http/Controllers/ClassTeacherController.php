<?php

namespace App\Http\Controllers;

use App\Models\AcademicSession;
use App\Models\Exam;
use App\Models\ExamSubject;
use App\Models\Mark;
use App\Models\MarksSubmission;
use App\Models\Result;
use App\Models\Section;
use App\Models\Student;
use App\Models\SubjectTeacher;
use App\Models\TimeSlot;
use App\Models\TimetableEntry;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * "My Class" hub for class teachers. Every action is strictly scoped to the
 * sections this user is the class_teacher_id of.
 */
class ClassTeacherController extends Controller
{
    /**
     * Guard: load the sections this user is responsible for, abort if none.
     * Returns the Section collection (eager-loaded with schoolClass + school).
     */
    protected function mySections($user)
    {
        if (!$user) abort(403);

        $sections = Section::with(['schoolClass.school'])
            ->where('class_teacher_id', $user->id)
            ->active()
            ->get();

        if ($sections->isEmpty()) {
            abort(403, 'You are not currently assigned as a class teacher to any section.');
        }

        return $sections;
    }

    /**
     * Overview hub — picks the first (or requested) section and returns snapshot data.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();
        $sections = $this->mySections($user);

        $activeSectionId = $request->integer('section') ?: $sections->first()->id;
        $section = $sections->firstWhere('id', $activeSectionId) ?? $sections->first();

        $currentSession = AcademicSession::currentSession();

        // Students in this section
        $students = Student::where('section_id', $section->id)
            ->active()
            ->when($currentSession, fn ($q) => $q->where('academic_session_id', $currentSession->id))
            ->orderBy('roll_no')
            ->orderBy('name')
            ->get(['id', 'name', 'roll_no', 'admission_no', 'father_name', 'guardian_phone', 'gender', 'status']);

        // Exams in marks_entry or completed for this class
        $exams = Exam::query()
            ->where('status', '!=', 'draft')
            ->when($currentSession, fn ($q) => $q->where('academic_session_id', $currentSession->id))
            ->whereHas('schools', fn ($q) => $q->where('schools.id', $section->schoolClass->school_id))
            ->whereHas('examSubjects', fn ($q) => $q->where('school_class_id', $section->school_class_id))
            ->with(['examType', 'examSubjects' => fn ($q) => $q->where('school_class_id', $section->school_class_id)->with('subject')])
            ->latest('start_date')
            ->get();

        // Marks-entry status per exam × subject for THIS section
        $marksStatus = [];
        foreach ($exams as $exam) {
            $subjectRows = [];
            foreach ($exam->examSubjects as $es) {
                $submission = MarksSubmission::where('exam_id', $exam->id)
                    ->where('subject_id', $es->subject_id)
                    ->where('section_id', $section->id)
                    ->first();

                $enteredCount = Mark::where('exam_id', $exam->id)
                    ->where('subject_id', $es->subject_id)
                    ->where('section_id', $section->id)
                    ->count();

                $assignedTeacher = SubjectTeacher::where('subject_id', $es->subject_id)
                    ->where('section_id', $section->id)
                    ->where('is_active', true)
                    ->with('user:id,name,email')
                    ->first();

                $subjectRows[] = [
                    'subject_id' => $es->subject_id,
                    'subject_name' => $es->subject?->name,
                    'subject_code' => $es->subject?->code,
                    'total_marks' => (float) $es->total_marks,
                    'assigned_teacher' => $assignedTeacher?->user?->name,
                    'assigned_teacher_email' => $assignedTeacher?->user?->email,
                    'students_entered' => $enteredCount,
                    'students_total' => $students->count(),
                    'status' => $submission?->status ?? 'pending',
                    'submitted_at' => $submission?->submitted_at,
                    'submitted_by_me' => $submission?->submitted_by === $user->id,
                ];
            }
            $marksStatus[] = [
                'exam_id' => $exam->id,
                'exam_name' => $exam->name,
                'exam_type' => $exam->examType?->name,
                'status' => $exam->status,
                'start_date' => $exam->start_date,
                'subjects' => $subjectRows,
            ];
        }

        // Latest results for this section (best-effort summary)
        $latestResults = Result::where('section_id', $section->id)
            ->when($currentSession, fn ($q) => $q->where('academic_session_id', $currentSession->id))
            ->with(['student:id,name,roll_no', 'exam:id,name'])
            ->latest('updated_at')
            ->limit(50)
            ->get()
            ->map(fn ($r) => [
                'id' => $r->id,
                'student_id' => $r->student_id,
                'student_name' => $r->student?->name,
                'roll_no' => $r->student?->roll_no,
                'exam_id' => $r->exam_id,
                'exam_name' => $r->exam?->name,
                'total_marks' => (float) $r->total_marks,
                'obtained_marks' => (float) $r->obtained_marks,
                'percentage' => (float) $r->percentage,
                'grade' => $r->grade,
                'position' => $r->position,
                'is_passed' => (bool) $r->is_passed,
            ]);

        // Aggregate stats for the section
        $resultsForStats = Result::where('section_id', $section->id)
            ->when($currentSession, fn ($q) => $q->where('academic_session_id', $currentSession->id))
            ->get();

        $stats = [
            'student_count' => $students->count(),
            'exam_count' => $exams->count(),
            'pending_marks_subjects' => collect($marksStatus)
                ->flatMap(fn ($e) => $e['subjects'])
                ->filter(fn ($s) => $s['status'] !== 'submitted')
                ->count(),
            'avg_pass_rate' => $resultsForStats->count()
                ? round($resultsForStats->where('is_passed', true)->count() / $resultsForStats->count() * 100, 1)
                : null,
            'avg_percentage' => $resultsForStats->count()
                ? round($resultsForStats->avg('percentage'), 1)
                : null,
            'top_performer' => $resultsForStats->sortByDesc('percentage')->first()
                ? [
                    'name' => $resultsForStats->sortByDesc('percentage')->first()->student?->name,
                    'percentage' => round($resultsForStats->sortByDesc('percentage')->first()->percentage, 1),
                ]
                : null,
        ];

        // All subject teachers assigned to this section (so class teacher can see the full team)
        $sectionTeam = SubjectTeacher::where('section_id', $section->id)
            ->where('is_active', true)
            ->with(['user:id,name,email', 'subject:id,name,code'])
            ->get()
            ->map(fn ($st) => [
                'teacher_name' => $st->user?->name,
                'teacher_email' => $st->user?->email,
                'subject_name' => $st->subject?->name,
                'subject_code' => $st->subject?->code,
            ]);

        // ─── "My Subjects" — what THIS user teaches as a subject teacher ───
        // Many class teachers also teach subjects in other sections. Show
        // those assignments, the marks-entry progress on each, and the
        // latest published results so the user has one screen for "the
        // classes I lead" + "the subjects I teach".
        $myAssignments = SubjectTeacher::where('user_id', $user->id)
            ->where('is_active', true)
            ->when($currentSession, fn ($q) => $q->where('academic_session_id', $currentSession->id))
            ->with([
                'subject:id,name,code',
                'schoolClass:id,name',
                'section:id,name,school_class_id',
            ])
            ->get();

        // For each assignment, what's the latest exam's marks-submission state
        // (so the user can see "Math · Class V-A · Mid-Term: 23/25 entered, submitted").
        $mySubjectStatus = $myAssignments->map(function ($a) use ($currentSession) {
            $latestExam = Exam::query()
                ->where('status', '!=', 'draft')
                ->when($currentSession, fn ($q) => $q->where('academic_session_id', $currentSession->id))
                ->whereHas('examSubjects', fn ($q) => $q
                    ->where('subject_id', $a->subject_id)
                    ->where('school_class_id', $a->school_class_id))
                ->latest('start_date')
                ->first();

            $studentsTotal = Student::where('section_id', $a->section_id)->active()->count();
            $entered = $latestExam
                ? Mark::where('exam_id', $latestExam->id)
                    ->where('subject_id', $a->subject_id)
                    ->where('section_id', $a->section_id)
                    ->count()
                : 0;
            $submission = $latestExam
                ? MarksSubmission::where('exam_id', $latestExam->id)
                    ->where('subject_id', $a->subject_id)
                    ->where('section_id', $a->section_id)
                    ->first()
                : null;

            return [
                'subject_id' => $a->subject_id,
                'subject_name' => $a->subject?->name,
                'subject_code' => $a->subject?->code,
                'class_name' => $a->schoolClass?->name,
                'section_id' => $a->section_id,
                'section_name' => $a->section?->name,
                'latest_exam' => $latestExam ? [
                    'id' => $latestExam->id,
                    'name' => $latestExam->name,
                ] : null,
                'students_total' => $studentsTotal,
                'students_entered' => $entered,
                'submission_status' => $submission?->status ?? ($entered > 0 ? 'draft' : 'pending'),
            ];
        });

        // Latest results for the subjects + sections this user teaches —
        // pulled from the section's results, then narrowed to subjects in
        // their assignment set. Best-effort: shows top 30.
        $mySectionIds = $myAssignments->pluck('section_id')->unique();
        $mySubjectIds = $myAssignments->pluck('subject_id')->unique();
        $myRecentResults = Result::query()
            ->whereIn('section_id', $mySectionIds)
            ->when($currentSession, fn ($q) => $q->where('academic_session_id', $currentSession->id))
            ->with(['student:id,name,roll_no', 'exam:id,name', 'section:id,name', 'schoolClass:id,name'])
            ->latest('updated_at')
            ->limit(30)
            ->get()
            ->map(function ($r) use ($mySubjectIds) {
                // Pull just the per-subject rows that belong to subjects
                // this user teaches, from the JSON snapshot on the result.
                $subjectMarks = collect($r->subject_results ?? [])
                    ->filter(fn ($sr) => isset($sr['subject_id']) && $mySubjectIds->contains($sr['subject_id']))
                    ->map(fn ($sr) => [
                        'subject_id' => $sr['subject_id'] ?? null,
                        'subject_name' => $sr['subject_name'] ?? null,
                        'obtained_marks' => $sr['effective_marks'] ?? $sr['marks_obtained'] ?? null,
                        'total_marks' => $sr['total_marks'] ?? null,
                        'is_passed' => $sr['is_passed'] ?? null,
                    ])
                    ->values();

                return [
                    'id' => $r->id,
                    'student_name' => $r->student?->name,
                    'roll_no' => $r->student?->roll_no,
                    'exam_name' => $r->exam?->name,
                    'class_name' => $r->schoolClass?->name,
                    'section_name' => $r->section?->name,
                    'overall_percentage' => (float) $r->percentage,
                    'overall_grade' => $r->grade,
                    'subject_marks' => $subjectMarks,
                ];
            })
            // Drop rows where none of the user's subjects appear (e.g. results
            // for sections they teach a different subject in).
            ->filter(fn ($r) => count($r['subject_marks']) > 0)
            ->values();

        // Available exams that target this section's class — used by the
        // Actions panel to drop into the right report URL. Sorted recent-first.
        $availableExams = Exam::query()
            ->whereHas('examSubjects', fn ($q) => $q->where('school_class_id', $section->school_class_id))
            ->whereHas('schools', fn ($q) => $q->where('schools.id', $section->schoolClass->school_id))
            ->where('status', '!=', 'draft')
            ->orderByDesc('start_date')
            ->limit(20)
            ->get(['id', 'name', 'start_date'])
            ->map(fn ($e) => [
                'id' => $e->id,
                'name' => $e->name,
                'start_date' => $e->start_date?->format('d M Y'),
            ]);

        // ─── Section timetable for "My Class Timetable" panel ───
        // Class teacher should see their entire section's weekly grid with
        // all subject teachers visible — printable as a PDF.
        $schoolId = $section->schoolClass->school_id;
        $timetableSlots = TimeSlot::where('school_id', $schoolId)->ordered()->get();
        $timetableEntries = TimetableEntry::where('section_id', $section->id)
            ->with(['subject:id,name,code', 'teacher:id,name'])
            ->get()
            ->keyBy(fn ($e) => $e->weekday . '|' . $e->time_slot_id);

        return Inertia::render('ClassTeacher/Index', [
            'timetable' => [
                'has_schedule' => $timetableSlots->isNotEmpty(),
                'slots' => $timetableSlots,
                'entries' => $timetableEntries,
            ],
            'sections' => $sections->map(fn ($s) => [
                'id' => $s->id,
                'name' => $s->name,
                'class_name' => $s->schoolClass?->name,
                'school_name' => $s->schoolClass?->school?->name,
            ]),
            'availableExams' => $availableExams,
            'activeSection' => [
                'id' => $section->id,
                'name' => $section->name,
                'class_name' => $section->schoolClass?->name,
                'school_name' => $section->schoolClass?->school?->name,
                'school_class_id' => $section->school_class_id,
                'capacity' => $section->capacity,
            ],
            'students' => $students,
            'marksStatus' => $marksStatus,
            'latestResults' => $latestResults,
            'sectionTeam' => $sectionTeam,
            'stats' => $stats,
            'currentSession' => $currentSession ? ['id' => $currentSession->id, 'name' => $currentSession->name] : null,
            'mySubjectStatus' => $mySubjectStatus,
            'myRecentResults' => $myRecentResults,
        ]);
    }
}
