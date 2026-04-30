<?php

namespace App\Http\Controllers;

use App\Models\AcademicSession;
use App\Models\Exam;
use App\Models\Mark;
use App\Models\Result;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class AnalyticsController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        if (!$user->isSuperAdmin() && !$user->isSchoolAdmin()) {
            abort(403, 'Only DDO super-admin and school-admin can view analytics.');
        }

        $currentSession = AcademicSession::currentSession();
        $isSuper = $user->isSuperAdmin();

        // Overview
        $totalSchools = $isSuper ? School::active()->count() : 1;
        $totalStudents = Student::active()
            ->when(!$isSuper, fn ($q) => $q->where('school_id', $user->school_id))
            ->when($currentSession, fn ($q) => $q->where('academic_session_id', $currentSession->id))
            ->count();
        $totalExams = Exam::when($currentSession, fn ($q) => $q->where('academic_session_id', $currentSession->id))->count();

        $resultsQuery = Result::query()
            ->when(!$isSuper, fn ($q) => $q->where('school_id', $user->school_id))
            ->when($currentSession, fn ($q) => $q->where('academic_session_id', $currentSession->id));
        $totalResults = $resultsQuery->count();
        $passed = (clone $resultsQuery)->where('is_passed', true)->count();
        $passRate = $totalResults > 0 ? round($passed / $totalResults * 100, 2) : 0;
        $avgPct = round((clone $resultsQuery)->avg('percentage') ?? 0, 2);

        // School leaderboard (super admin only) - top by pass rate
        $schoolLeaderboard = collect();
        if ($isSuper) {
            $schoolLeaderboard = School::active()->get()->map(function ($school) use ($currentSession) {
                $r = Result::where('school_id', $school->id)
                    ->when($currentSession, fn ($q) => $q->where('academic_session_id', $currentSession->id));
                $total = $r->count();
                $passed = (clone $r)->where('is_passed', true)->count();
                $avg = round((clone $r)->avg('percentage') ?? 0, 2);
                return [
                    'id' => $school->id,
                    'name' => $school->name,
                    'code' => $school->code,
                    'total_students' => Student::where('school_id', $school->id)->active()->count(),
                    'total_results' => $total,
                    'pass_rate' => $total > 0 ? round($passed / $total * 100, 2) : 0,
                    'avg_percentage' => $avg,
                ];
            })->sortByDesc('pass_rate')->values();
        }

        // Subject performance - avg marks per subject across all marks
        $subjectPerformance = DB::table('marks')
            ->join('subjects', 'marks.subject_id', '=', 'subjects.id')
            ->when(!$isSuper, fn ($q) => $q->where('marks.school_id', $user->school_id))
            ->when($currentSession, fn ($q) => $q->where('marks.academic_session_id', $currentSession->id))
            ->where('marks.status', 'submitted')
            ->groupBy('subjects.id', 'subjects.name', 'subjects.code')
            ->selectRaw('subjects.id, subjects.name, subjects.code, AVG(marks_obtained) as avg_marks, AVG(marks_obtained / NULLIF(total_marks, 0) * 100) as avg_percentage, COUNT(*) as total_entries')
            ->orderByDesc('avg_percentage')
            ->limit(15)
            ->get();

        // Class performance
        $classPerformance = SchoolClass::active()
            ->when(!$isSuper, fn ($q) => $q->where('school_id', $user->school_id))
            ->with('school')
            ->get()
            ->map(function ($class) use ($currentSession) {
                $r = Result::where('school_class_id', $class->id)
                    ->when($currentSession, fn ($q) => $q->where('academic_session_id', $currentSession->id));
                $total = $r->count();
                $passed = (clone $r)->where('is_passed', true)->count();
                return [
                    'id' => $class->id,
                    'name' => $class->name,
                    'school_name' => $class->school?->name,
                    'total_students' => $total,
                    'pass_rate' => $total > 0 ? round($passed / $total * 100, 2) : 0,
                    'avg_percentage' => round((clone $r)->avg('percentage') ?? 0, 2),
                ];
            })
            ->filter(fn ($c) => $c['total_students'] > 0)
            ->sortByDesc('pass_rate')
            ->values();

        // Recent trend - last 6 sessions
        $recentTrend = AcademicSession::orderByDesc('start_date')->take(6)->get()->reverse()->values()->map(function ($session) use ($isSuper, $user) {
            $r = Result::where('academic_session_id', $session->id)
                ->when(!$isSuper, fn ($q) => $q->where('school_id', $user->school_id));
            $total = $r->count();
            $passed = (clone $r)->where('is_passed', true)->count();
            return [
                'session_name' => $session->name,
                'pass_rate' => $total > 0 ? round($passed / $total * 100, 2) : 0,
                'total_results' => $total,
            ];
        });

        // Top performers - top 10 students by percentage
        $topPerformers = Result::query()
            ->when(!$isSuper, fn ($q) => $q->where('school_id', $user->school_id))
            ->when($currentSession, fn ($q) => $q->where('academic_session_id', $currentSession->id))
            ->where('is_passed', true)
            ->with(['student', 'school', 'schoolClass', 'section', 'exam'])
            ->orderByDesc('percentage')
            ->limit(10)
            ->get()
            ->map(fn ($r) => [
                'id' => $r->id,
                'student_name' => $r->student?->name,
                'admission_no' => $r->student?->admission_no,
                'school_name' => $r->school?->name,
                'class_name' => $r->schoolClass?->name,
                'section_name' => $r->section?->name,
                'exam_name' => $r->exam?->name,
                'percentage' => $r->percentage,
                'grade' => $r->grade,
                'position' => $r->position,
            ]);

        return Inertia::render('Analytics/Index', [
            'overview' => [
                'totalSchools' => $totalSchools,
                'totalStudents' => $totalStudents,
                'totalExams' => $totalExams,
                'totalResults' => $totalResults,
                'overallPassRate' => $passRate,
                'avgPercentage' => $avgPct,
            ],
            'schoolLeaderboard' => $schoolLeaderboard,
            'subjectPerformance' => $subjectPerformance,
            'classPerformance' => $classPerformance,
            'recentTrend' => $recentTrend,
            'topPerformers' => $topPerformers,
            'isSuperAdmin' => $isSuper,
            'currentSession' => $currentSession,
        ]);
    }
}
