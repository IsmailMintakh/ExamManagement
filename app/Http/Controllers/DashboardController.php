<?php

namespace App\Http\Controllers;

use App\Models\AcademicSession;
use App\Models\Exam;
use App\Models\MarksSubmission;
use App\Models\Result;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Student;
use App\Models\SubjectTeacher;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $currentSession = AcademicSession::currentSession();

        if ($user->isSuperAdmin()) {
            return $this->superAdminDashboard($currentSession);
        }

        if ($user->isSchoolAdmin()) {
            return $this->schoolAdminDashboard($user, $currentSession);
        }

        if ($user->isClassTeacher()) {
            return $this->classTeacherDashboard($user, $currentSession);
        }

        if ($user->hasRole('student')) {
            return redirect()->route('student-portal.dashboard');
        }

        if ($user->hasRole('parent')) {
            return redirect()->route('parent-portal.dashboard');
        }

        return $this->subjectTeacherDashboard($user, $currentSession);
    }

    private function superAdminDashboard(?AcademicSession $currentSession): Response
    {
        $user = request()->user();
        $needsAttention = $this->needsAttentionFor($user, $currentSession);
        $setupStatus = $this->setupStatus($user);
        try {
            $totalSchools = School::active()->count();
            $totalStudents = Student::active()
                ->when($currentSession, fn ($q) => $q->where('academic_session_id', $currentSession->id))
                ->count();
            $totalTeachers = User::role(['class-teacher', 'subject-teacher'])->count();
            $totalExams = Exam::when($currentSession, fn ($q) => $q->where('academic_session_id', $currentSession->id))
                ->count();

            $results = Result::when($currentSession, fn ($q) => $q->where('academic_session_id', $currentSession->id));
            $totalResults = $results->count();
            $passRate = $totalResults > 0
                ? round($results->clone()->where('is_passed', true)->count() / $totalResults * 100, 1)
                : 0;

            $pendingResults = Exam::when($currentSession, fn ($q) => $q->where('academic_session_id', $currentSession->id))
                ->where('status', 'marks_entry')
                ->count();

            $recentExams = Exam::when($currentSession, fn ($q) => $q->where('academic_session_id', $currentSession->id))
                ->with('examType')
                ->latest()
                ->take(5)
                ->get()
                ->map(fn ($exam) => [
                    'id' => $exam->id,
                    'name' => $exam->name,
                    'type' => $exam->examType?->name,
                    'status' => $exam->status,
                    'start_date' => $exam->start_date?->format('M d, Y'),
                    'end_date' => $exam->end_date?->format('M d, Y'),
                ]);

            // ─── N+1 fix: single grouped query for pass-rate stats ───
            // Was: one Result count + one is_passed count per school (2N queries).
            // Now: one GROUP BY query, joined in memory by school_id.
            $passRateBySchool = Result::query()
                ->when($currentSession, fn ($q) => $q->where('academic_session_id', $currentSession->id))
                ->groupBy('school_id')
                ->selectRaw('school_id, COUNT(*) as total, SUM(CASE WHEN is_passed = 1 THEN 1 ELSE 0 END) as passed')
                ->get()
                ->keyBy('school_id');

            $schoolWiseComparison = School::active()
                ->withCount([
                    'students' => fn ($q) => $q->active()
                        ->when($currentSession, fn ($q2) => $q2->where('academic_session_id', $currentSession->id)),
                ])
                ->get()
                ->map(function ($school) use ($passRateBySchool) {
                    $stat = $passRateBySchool->get($school->id);
                    $school->pass_percentage = $stat && $stat->total > 0
                        ? round(($stat->passed / $stat->total) * 100, 1)
                        : 0;
                    return $school;
                });
        } catch (\Exception $e) {
            report($e);
            $totalSchools = $totalStudents = $totalTeachers = $totalExams = $passRate = $pendingResults = 0;
            $recentExams = collect();
            $schoolWiseComparison = collect();
        }

        return Inertia::render('Dashboard', [
            'role' => 'super-admin',
            'stats' => [
                'totalSchools' => $totalSchools,
                'totalStudents' => $totalStudents,
                'totalTeachers' => $totalTeachers,
                'totalExams' => $totalExams,
                'passRate' => $passRate,
                'pendingResults' => $pendingResults,
            ],
            'recentExams' => $recentExams,
            'schoolWiseComparison' => $schoolWiseComparison,
            'needsAttention' => $needsAttention,
            'setupStatus' => $setupStatus,
            'currentSession' => $currentSession,
        ]);
    }

    private function schoolAdminDashboard($user, ?AcademicSession $currentSession): Response
    {
        $needsAttention = $this->needsAttentionFor($user, $currentSession);
        $setupStatus = $this->setupStatus($user);
        try {
            $schoolId = $user->school_id;

            $totalStudents = Student::where('school_id', $schoolId)->active()
                ->when($currentSession, fn ($q) => $q->where('academic_session_id', $currentSession->id))
                ->count();

            $totalClasses = SchoolClass::where('school_id', $schoolId)->active()->count();

            $totalSections = Section::whereHas('schoolClass', fn ($q) => $q->where('school_id', $schoolId))
                ->active()->count();

            $totalTeachers = User::where('school_id', $schoolId)
                ->role(['class-teacher', 'subject-teacher'])
                ->count();

            $activeExams = Exam::whereHas('schools', fn ($q) => $q->where('schools.id', $schoolId))
                ->where('status', 'marks_entry')
                ->when($currentSession, fn ($q) => $q->where('academic_session_id', $currentSession->id))
                ->count();

            $results = Result::where('school_id', $schoolId)
                ->when($currentSession, fn ($q) => $q->where('academic_session_id', $currentSession->id));
            $totalResults = $results->count();
            $passRate = $totalResults > 0
                ? round($results->clone()->where('is_passed', true)->count() / $totalResults * 100, 1)
                : 0;

            $recentExams = Exam::whereHas('schools', fn ($q) => $q->where('schools.id', $schoolId))
                ->when($currentSession, fn ($q) => $q->where('academic_session_id', $currentSession->id))
                ->with('examType')
                ->latest()
                ->take(5)
                ->get()
                ->map(fn ($exam) => [
                    'id' => $exam->id,
                    'name' => $exam->name,
                    'type' => $exam->examType?->name,
                    'status' => $exam->status,
                    'start_date' => $exam->start_date?->format('M d, Y'),
                    'end_date' => $exam->end_date?->format('M d, Y'),
                ]);

            // ─── N+1 fix: two grouped queries instead of 3 per class ───
            // Was: per class, ran 3 queries (Result count, is_passed count,
            // Student count). With 12 classes that was 36 extra queries.
            // Now: one Result-group-by + one Student-group-by, then merged.
            $classIds = SchoolClass::where('school_id', $schoolId)->active()->pluck('id');

            $passRateByClass = Result::query()
                ->whereIn('school_class_id', $classIds)
                ->when($currentSession, fn ($q) => $q->where('academic_session_id', $currentSession->id))
                ->groupBy('school_class_id')
                ->selectRaw('school_class_id, COUNT(*) as total, SUM(CASE WHEN is_passed = 1 THEN 1 ELSE 0 END) as passed')
                ->get()
                ->keyBy('school_class_id');

            $studentCountByClass = Student::query()
                ->whereIn('school_class_id', $classIds)
                ->where('status', 'active')
                ->when($currentSession, fn ($q) => $q->where('academic_session_id', $currentSession->id))
                ->groupBy('school_class_id')
                ->selectRaw('school_class_id, COUNT(*) as cnt')
                ->pluck('cnt', 'school_class_id');

            $classWisePerformance = SchoolClass::where('school_id', $schoolId)
                ->active()
                ->with('sections')
                ->get()
                ->map(function ($class) use ($passRateByClass, $studentCountByClass) {
                    $stat = $passRateByClass->get($class->id);
                    $class->pass_percentage = $stat && $stat->total > 0
                        ? round(($stat->passed / $stat->total) * 100, 1)
                        : 0;
                    $class->total_students = (int) ($studentCountByClass[$class->id] ?? 0);
                    return $class;
                });
        } catch (\Exception $e) {
            report($e);
            $totalStudents = $totalClasses = $totalSections = $totalTeachers = $activeExams = $passRate = 0;
            $recentExams = collect();
            $classWisePerformance = collect();
        }

        return Inertia::render('Dashboard', [
            'role' => 'school-admin',
            'stats' => [
                'totalStudents' => $totalStudents,
                'totalClasses' => $totalClasses,
                'totalSections' => $totalSections,
                'totalTeachers' => $totalTeachers,
                'activeExams' => $activeExams,
                'passRate' => $passRate,
            ],
            'recentExams' => $recentExams,
            'classWisePerformance' => $classWisePerformance,
            'needsAttention' => $needsAttention,
            'setupStatus' => $setupStatus,
            'currentSession' => $currentSession,
        ]);
    }

    private function classTeacherDashboard($user, ?AcademicSession $currentSession): Response
    {
        $needsAttention = $this->needsAttentionFor($user, $currentSession);
        try {
            $sections = Section::where('class_teacher_id', $user->id)
                ->with(['schoolClass', 'students' => fn ($q) => $q->active()])
                ->active()
                ->get();

            $sectionIds = $sections->pluck('id');

            $totalStudents = Student::whereIn('section_id', $sectionIds)
                ->active()
                ->when($currentSession, fn ($q) => $q->where('academic_session_id', $currentSession->id))
                ->count();

            $pendingMarksEntry = Exam::published()
                ->where('status', 'marks_entry')
                ->where('is_locked', false)
                ->when($currentSession, fn ($q) => $q->where('academic_session_id', $currentSession->id))
                ->count();

            $recentExams = Exam::published()
                ->when($currentSession, fn ($q) => $q->where('academic_session_id', $currentSession->id))
                ->with('examType')
                ->latest()
                ->take(5)
                ->get()
                ->map(fn ($exam) => [
                    'id' => $exam->id,
                    'name' => $exam->name,
                    'type' => $exam->examType?->name,
                    'status' => $exam->status,
                    'start_date' => $exam->start_date?->format('M d, Y'),
                    'end_date' => $exam->end_date?->format('M d, Y'),
                ]);

            $sectionsData = $sections->map(fn ($section) => [
                'id' => $section->id,
                'name' => $section->name,
                'class_name' => $section->schoolClass?->name,
                'students_count' => $section->students->count(),
            ]);
        } catch (\Exception $e) {
            report($e);
            $totalStudents = $pendingMarksEntry = 0;
            $sectionsData = collect();
            $sections = collect();
            $recentExams = collect();
        }

        return Inertia::render('Dashboard', [
            'role' => 'class-teacher',
            'stats' => [
                'totalStudents' => $totalStudents,
                'totalSections' => $sections->count(),
                'pendingMarksEntry' => $pendingMarksEntry,
            ],
            'sections' => $sectionsData,
            'recentExams' => $recentExams,
            'needsAttention' => $needsAttention,
            'currentSession' => $currentSession,
        ]);
    }

    private function subjectTeacherDashboard($user, ?AcademicSession $currentSession): Response
    {
        $needsAttention = $this->needsAttentionFor($user, $currentSession);
        try {
            $assignments = SubjectTeacher::where('user_id', $user->id)
                ->where('is_active', true)
                ->when($currentSession, fn ($q) => $q->where('academic_session_id', $currentSession->id))
                ->with(['subject', 'schoolClass', 'section'])
                ->get();

            $assignedSubjectIds = $assignments->pluck('subject_id')->unique();
            $assignedSectionIds = $assignments->pluck('section_id')->unique();

            $pendingMarks = Exam::published()
                ->where('status', 'marks_entry')
                ->where('is_locked', false)
                ->when($currentSession, fn ($q) => $q->where('academic_session_id', $currentSession->id))
                ->whereHas('examSubjects', fn ($q) => $q->whereIn('subject_id', $assignedSubjectIds))
                ->get();

            // ─── N+1 fix: aggregate counts in 2 queries instead of 2 per exam ───
            $examIds = $pendingMarks->pluck('id');

            $totalEntriesByExam = DB::table('exam_subjects')
                ->whereIn('exam_id', $examIds)
                ->whereIn('subject_id', $assignedSubjectIds)
                ->groupBy('exam_id')
                ->selectRaw('exam_id, COUNT(*) as cnt')
                ->pluck('cnt', 'exam_id');

            $submittedByExam = MarksSubmission::query()
                ->whereIn('exam_id', $examIds)
                ->whereIn('subject_id', $assignedSubjectIds)
                ->whereIn('section_id', $assignedSectionIds)
                ->where('status', 'submitted')
                ->groupBy('exam_id')
                ->selectRaw('exam_id, COUNT(*) as cnt')
                ->pluck('cnt', 'exam_id');

            $pendingMarks->each(function ($exam) use ($totalEntriesByExam, $submittedByExam) {
                $total = (int) ($totalEntriesByExam[$exam->id] ?? 0);
                $submitted = (int) ($submittedByExam[$exam->id] ?? 0);
                $exam->pending_count = max(0, $total - $submitted);
            });

            $pendingMarksEntry = $pendingMarks->sum('pending_count');

            $recentExams = Exam::published()
                ->when($currentSession, fn ($q) => $q->where('academic_session_id', $currentSession->id))
                ->with('examType')
                ->latest()
                ->take(5)
                ->get()
                ->map(fn ($exam) => [
                    'id' => $exam->id,
                    'name' => $exam->name,
                    'type' => $exam->examType?->name,
                    'status' => $exam->status,
                    'start_date' => $exam->start_date?->format('M d, Y'),
                    'end_date' => $exam->end_date?->format('M d, Y'),
                ]);

            $assignmentsData = $assignments->map(fn ($a) => [
                'id' => $a->id,
                'subject_name' => $a->subject?->name,
                'class_name' => $a->schoolClass?->name,
                'section_name' => $a->section?->name,
            ]);
        } catch (\Exception $e) {
            report($e);
            $assignedSubjectIds = collect();
            $assignedSectionIds = collect();
            $pendingMarksEntry = 0;
            $recentExams = collect();
            $assignmentsData = collect();
            $pendingMarks = collect();
        }

        return Inertia::render('Dashboard', [
            'role' => 'subject-teacher',
            'stats' => [
                'assignedSubjects' => $assignedSubjectIds->count(),
                'assignedSections' => $assignedSectionIds->count(),
                'pendingMarksEntry' => $pendingMarksEntry,
            ],
            'assignments' => $assignmentsData,
            'pendingExams' => $pendingMarks,
            'recentExams' => $recentExams,
            'needsAttention' => $needsAttention,
            'currentSession' => $currentSession,
        ]);
    }

    /**
     * Setup status for first-time / empty-DB users. Returns the dependency
     * chain (Schools → Subjects → Classes → Sections → Students → Users) with
     * a "done" flag for each step. The dashboard renders this as a checklist
     * so a fresh DDO user knows exactly what to do, in what order, before they
     * can run a real exam cycle.
     *
     * Only returned for super-admin / school-admin. Teachers don't set up data.
     */
    public function setupStatus($user): ?array
    {
        if (!$user->isSuperAdmin() && !$user->isSchoolAdmin()) return null;

        $isAdmin = $user->isSchoolAdmin();
        $schoolId = $user->school_id;

        // Laravel 12 footgun: ->when($closure) with a SINGLE argument returns
        // a HigherOrderWhenProxy, not the builder. Always pass the condition
        // as the first arg and the callback as the second.
        $schoolsCount = $isAdmin ? 1 : School::active()->count();
        $subjectsCount = \App\Models\Subject::active()->count();
        $classesCount = SchoolClass::query()
            ->when($isAdmin, fn ($q) => $q->where('school_id', $schoolId))
            ->count();
        $sectionsCount = Section::query()
            ->when($isAdmin, fn ($q) => $q->whereHas('schoolClass', fn ($q2) => $q2->where('school_id', $schoolId)))
            ->count();
        $studentsCount = Student::query()
            ->when($isAdmin, fn ($q) => $q->where('school_id', $schoolId))
            ->count();
        $teachersCount = User::query()
            ->when($isAdmin, fn ($q) => $q->where('school_id', $schoolId))
            ->whereHas('roles', fn ($q) => $q->whereIn('name', ['class-teacher', 'subject-teacher']))
            ->count();
        $examsCount = Exam::query()
            ->when($isAdmin, fn ($q) => $q->whereHas('schools', fn ($q2) => $q2->where('schools.id', $schoolId)))
            ->count();

        $steps = [];

        if (!$isAdmin) {
            $steps[] = [
                'key' => 'schools',
                'label' => 'Add at least one school',
                'count' => $schoolsCount,
                'done' => $schoolsCount > 0,
                'action_url' => route('schools.create'),
                'action_label' => 'Add school',
            ];
        }
        $steps[] = [
            'key' => 'subjects',
            'label' => 'Add subjects taught',
            'count' => $subjectsCount,
            'done' => $subjectsCount > 0,
            'action_url' => route('subjects.create'),
            'action_label' => 'Add subject',
        ];
        $steps[] = [
            'key' => 'classes',
            'label' => 'Add classes' . ($isAdmin ? '' : ' under your school(s)'),
            'count' => $classesCount,
            'done' => $classesCount > 0,
            'action_url' => route('classes.create'),
            'action_label' => 'Add class',
        ];
        $steps[] = [
            'key' => 'sections',
            'label' => 'Add sections under each class',
            'count' => $sectionsCount,
            'done' => $sectionsCount > 0,
            'action_url' => route('sections.create'),
            'action_label' => 'Add section',
        ];
        $steps[] = [
            'key' => 'users',
            'label' => 'Add ' . ($isAdmin ? 'teachers' : 'principals + teachers'),
            'count' => $teachersCount,
            'done' => $teachersCount > 0,
            'action_url' => route('users.create'),
            'action_label' => 'Add user',
        ];
        $steps[] = [
            'key' => 'students',
            'label' => 'Enroll students into sections',
            'count' => $studentsCount,
            'done' => $studentsCount > 0,
            'action_url' => route('students.create'),
            'action_label' => 'Add student',
        ];
        $steps[] = [
            'key' => 'exams',
            'label' => 'Create your first exam',
            'count' => $examsCount,
            'done' => $examsCount > 0,
            'action_url' => route('exams.create'),
            'action_label' => 'Create exam',
        ];

        $totalSteps = count($steps);
        $doneSteps = count(array_filter($steps, fn ($s) => $s['done']));

        return [
            'steps' => $steps,
            'done_count' => $doneSteps,
            'total_count' => $totalSteps,
            // "Complete" only when every prereq is in place — hides the panel.
            'is_complete' => $doneSteps === $totalSteps,
        ];
    }

    /**
     * Build a role-aware "needs attention" list for the dashboard.
     * Each item: {key, severity, title, description, action_label, action_url, count}.
     * The dashboard shows the top items as actionable cards so the user lands
     * on something they can DO, not just numbers.
     */
    private function needsAttentionFor($user, ?AcademicSession $currentSession): array
    {
        $items = [];

        // ─── DDO (super-admin): result submissions awaiting review ───
        if ($user->isSuperAdmin()) {
            $awaitingReview = \App\Models\ResultSubmission::where('status', 'submitted')->count();
            if ($awaitingReview > 0) {
                $items[] = [
                    'key' => 'pending-review',
                    'severity' => 'warning',
                    'title' => "{$awaitingReview} result submission" . ($awaitingReview === 1 ? '' : 's') . ' awaiting your review',
                    'description' => 'Approve or return for correction.',
                    'action_label' => 'Open Results',
                    'action_url' => route('results.index'),
                    'count' => $awaitingReview,
                ];
            }

            $draftExamsNoSubjects = Exam::where('status', 'draft')
                ->whereDoesntHave('examSubjects')
                ->when($currentSession, fn ($q) => $q->where('academic_session_id', $currentSession->id))
                ->count();
            if ($draftExamsNoSubjects > 0) {
                $items[] = [
                    'key' => 'draft-incomplete',
                    'severity' => 'info',
                    'title' => "{$draftExamsNoSubjects} draft exam" . ($draftExamsNoSubjects === 1 ? '' : 's') . ' with no subjects yet',
                    'description' => 'Finish the setup wizard to publish them.',
                    'action_label' => 'Open Exams',
                    'action_url' => route('exams.index'),
                    'count' => $draftExamsNoSubjects,
                ];
            }
        }

        // ─── School admin: marks pending across the school for active exams ───
        if ($user->isSchoolAdmin()) {
            $schoolId = $user->school_id;
            $activeExams = Exam::where('status', 'marks_entry')
                ->whereHas('schools', fn ($q) => $q->where('schools.id', $schoolId))
                ->when($currentSession, fn ($q) => $q->where('academic_session_id', $currentSession->id))
                ->pluck('id');

            if ($activeExams->isNotEmpty()) {
                // Expected = (exam_subjects ⨯ sections of that class) for our school's classes.
                // Submitted = MarksSubmission rows with status='submitted'.
                // Pending = expected - submitted.
                $expected = DB::table('exam_subjects')
                    ->join('sections', 'sections.school_class_id', '=', 'exam_subjects.school_class_id')
                    ->join('school_classes', 'school_classes.id', '=', 'exam_subjects.school_class_id')
                    ->where('school_classes.school_id', $schoolId)
                    ->whereIn('exam_subjects.exam_id', $activeExams)
                    ->count();

                $submitted = MarksSubmission::whereIn('exam_id', $activeExams)
                    ->where('school_id', $schoolId)
                    ->where('status', 'submitted')
                    ->count();

                $pending = max(0, $expected - $submitted);
                if ($pending > 0) {
                    $items[] = [
                        'key' => 'marks-pending',
                        'severity' => 'warning',
                        'title' => "{$pending} subject" . ($pending === 1 ? '' : 's') . ' with marks not yet submitted',
                        'description' => 'Follow up with the teachers.',
                        'action_label' => 'View Marks',
                        'action_url' => route('marks.index'),
                        'count' => $pending,
                    ];
                }
            }

            // Results awaiting submission to DDO (status = generated, not yet submitted)
            $resultsReady = Result::where('school_id', $schoolId)
                ->where('status', 'generated')
                ->when($currentSession, fn ($q) => $q->where('academic_session_id', $currentSession->id))
                ->distinct('exam_id')->count('exam_id');
            if ($resultsReady > 0) {
                $items[] = [
                    'key' => 'results-ready',
                    'severity' => 'info',
                    'title' => "{$resultsReady} exam" . ($resultsReady === 1 ? '' : 's') . ' have results ready to submit to DDO',
                    'description' => 'Review and forward for finalization.',
                    'action_label' => 'Open Results',
                    'action_url' => route('results.index'),
                    'count' => $resultsReady,
                ];
            }

            // Returned for correction
            $returned = \App\Models\ResultSubmission::where('school_id', $schoolId)
                ->where('status', 'rejected')
                ->count();
            if ($returned > 0) {
                $items[] = [
                    'key' => 'results-returned',
                    'severity' => 'error',
                    'title' => "{$returned} result submission" . ($returned === 1 ? '' : 's') . ' returned for correction by DDO',
                    'description' => 'Review the remarks and re-submit.',
                    'action_label' => 'Open Results',
                    'action_url' => route('results.index'),
                    'count' => $returned,
                ];
            }
        }

        // ─── Class / Subject teachers: pending marks for their assignments ───
        if ($user->isClassTeacher() || $user->hasRole('subject-teacher')) {
            $myAssignments = SubjectTeacher::where('user_id', $user->id)
                ->where('is_active', true)
                ->when($currentSession, fn ($q) => $q->where('academic_session_id', $currentSession->id))
                ->get(['subject_id', 'section_id']);

            if ($myAssignments->isNotEmpty()) {
                $activeExams = Exam::where('status', 'marks_entry')
                    ->where('is_locked', false)
                    ->when($currentSession, fn ($q) => $q->where('academic_session_id', $currentSession->id))
                    ->pluck('id');

                if ($activeExams->isNotEmpty()) {
                    // Count (exam, subject, section) tuples we own that aren't submitted yet
                    $pending = 0;
                    foreach ($myAssignments as $a) {
                        $pending += $activeExams->count() - MarksSubmission::whereIn('exam_id', $activeExams)
                            ->where('subject_id', $a->subject_id)
                            ->where('section_id', $a->section_id)
                            ->where('status', 'submitted')
                            ->count();
                    }
                    if ($pending > 0) {
                        $items[] = [
                            'key' => 'my-marks-pending',
                            'severity' => 'warning',
                            'title' => "{$pending} subject-section" . ($pending === 1 ? '' : 's') . ' need your marks',
                            'description' => 'Enter and submit before the deadline.',
                            'action_label' => 'Enter Marks',
                            'action_url' => route('marks.index'),
                            'count' => $pending,
                        ];
                    }
                }
            }
        }

        return $items;
    }
}
