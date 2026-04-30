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

        return Inertia::render('ClassTeacher/Index', [
            'sections' => $sections->map(fn ($s) => [
                'id' => $s->id,
                'name' => $s->name,
                'class_name' => $s->schoolClass?->name,
                'school_name' => $s->schoolClass?->school?->name,
            ]),
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
        ]);
    }
}
