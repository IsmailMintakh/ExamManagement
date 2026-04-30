<?php

namespace App\Http\Controllers;

use App\Models\AcademicSession;
use App\Models\Exam;
use App\Models\Result;
use App\Models\Student;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ArchiveController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()->can('archive.view'), 403);
        $sessions = AcademicSession::orderByDesc('start_date')
            ->withCount(['exams', 'students'])
            ->get();

        return Inertia::render('Archive/Index', [
            'sessions' => $sessions,
        ]);
    }

    public function show(Request $request, AcademicSession $session): Response
    {
        $user = $request->user();
        abort_unless($user->can('archive.view'), 403);

        $exams = Exam::where('academic_session_id', $session->id)
            ->when(!$user->isSuperAdmin(), fn ($q) => $q->whereHas('schools', fn ($q2) => $q2->where('schools.id', $user->school_id)))
            ->with(['examType'])
            ->withCount('results')
            ->get();

        $totalStudents = Student::where('academic_session_id', $session->id)
            ->when(!$user->isSuperAdmin(), fn ($q) => $q->where('school_id', $user->school_id))
            ->count();

        $totalResults = Result::where('academic_session_id', $session->id)
            ->when(!$user->isSuperAdmin(), fn ($q) => $q->where('school_id', $user->school_id))
            ->count();

        return Inertia::render('Archive/Show', [
            'session' => $session,
            'exams' => $exams,
            'stats' => [
                'totalStudents' => $totalStudents,
                'totalExams' => $exams->count(),
                'totalResults' => $totalResults,
            ],
        ]);
    }
}
