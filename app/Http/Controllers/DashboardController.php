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

            $schoolWiseComparison = School::active()
                ->withCount([
                    'students' => fn ($q) => $q->active()
                        ->when($currentSession, fn ($q2) => $q2->where('academic_session_id', $currentSession->id)),
                ])
                ->get()
                ->map(function ($school) use ($currentSession) {
                    $results = Result::where('school_id', $school->id)
                        ->when($currentSession, fn ($q) => $q->where('academic_session_id', $currentSession->id));
                    $total = $results->count();
                    $school->pass_percentage = $total > 0
                        ? round($results->clone()->where('is_passed', true)->count() / $total * 100, 1)
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
            'currentSession' => $currentSession,
        ]);
    }

    private function schoolAdminDashboard($user, ?AcademicSession $currentSession): Response
    {
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

            $classWisePerformance = SchoolClass::where('school_id', $schoolId)
                ->active()
                ->with('sections')
                ->get()
                ->map(function ($class) use ($currentSession) {
                    $results = Result::where('school_class_id', $class->id)
                        ->when($currentSession, fn ($q) => $q->where('academic_session_id', $currentSession->id));
                    $total = $results->count();
                    $class->pass_percentage = $total > 0
                        ? round($results->clone()->where('is_passed', true)->count() / $total * 100, 1)
                        : 0;
                    $class->total_students = Student::where('school_class_id', $class->id)
                        ->active()
                        ->when($currentSession, fn ($q) => $q->where('academic_session_id', $currentSession->id))
                        ->count();

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
            'currentSession' => $currentSession,
        ]);
    }

    private function classTeacherDashboard($user, ?AcademicSession $currentSession): Response
    {
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
            'currentSession' => $currentSession,
        ]);
    }

    private function subjectTeacherDashboard($user, ?AcademicSession $currentSession): Response
    {
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
                ->get()
                ->map(function ($exam) use ($assignedSubjectIds, $assignedSectionIds) {
                    $totalEntries = $exam->examSubjects()
                        ->whereIn('subject_id', $assignedSubjectIds)
                        ->count();
                    $submittedEntries = MarksSubmission::where('exam_id', $exam->id)
                        ->whereIn('subject_id', $assignedSubjectIds)
                        ->whereIn('section_id', $assignedSectionIds)
                        ->where('status', 'submitted')
                        ->count();
                    $exam->pending_count = max(0, $totalEntries - $submittedEntries);

                    return $exam;
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
            'currentSession' => $currentSession,
        ]);
    }
}
