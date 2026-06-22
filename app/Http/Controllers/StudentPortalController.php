<?php

namespace App\Http\Controllers;

use App\Models\AcademicSession;
use App\Models\Result;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class StudentPortalController extends Controller
{
    /**
     * Student dashboard - shows the student's profile, current class/section,
     * academic history, and a quick summary of recent results.
     */
    public function dashboard(Request $request): Response|RedirectResponse
    {
        $user = $request->user();
        $student = $user->studentProfile()
            ->with(['school', 'schoolClass', 'section', 'academicSession'])
            ->first();

        if (!$student) {
            return redirect()->route('profile.edit')
                ->with('error', 'Your student account is not linked to a student record. Please contact your school administrator.');
        }

        $currentSession = AcademicSession::currentSession();

        // Sessions the student has been part of (via results history)
        $sessionIds = Result::where('student_id', $student->id)
            ->pluck('academic_session_id')
            ->unique()
            ->filter()
            ->values();

        $academicHistory = AcademicSession::whereIn('id', $sessionIds)
            ->orderByDesc('start_date')
            ->get();

        // Quick stats
        $resultsQuery = Result::where('student_id', $student->id);
        $totalExams = (clone $resultsQuery)->count();

        $latestResult = (clone $resultsQuery)
            ->with(['exam.examType'])
            ->latest('id')
            ->first();

        $recentResults = (clone $resultsQuery)
            ->with(['exam.examType', 'academicSession'])
            ->latest('id')
            ->take(3)
            ->get()
            ->map(fn ($r) => $this->serializeResultSummary($r));

        return Inertia::render('StudentPortal/Dashboard', [
            'student' => [
                'id' => $student->id,
                'name' => $student->name,
                'admission_no' => $student->admission_no,
                'roll_no' => $student->roll_no,
                'father_name' => $student->father_name,
                'mother_name' => $student->mother_name,
                'date_of_birth' => $student->date_of_birth?->format('Y-m-d'),
                'gender' => $student->gender,
                'photo_url' => $student->photo_url,
                'school' => $student->school ? ['id' => $student->school->id, 'name' => $student->school->name] : null,
                'class' => $student->schoolClass ? ['id' => $student->schoolClass->id, 'name' => $student->schoolClass->name] : null,
                'section' => $student->section ? ['id' => $student->section->id, 'name' => $student->section->name] : null,
                'session' => $student->academicSession ? ['id' => $student->academicSession->id, 'name' => $student->academicSession->name] : null,
            ],
            'currentSession' => $currentSession,
            'academicHistory' => $academicHistory,
            'stats' => [
                'totalExams' => $totalExams,
                'latestGrade' => $latestResult?->grade,
                'latestPosition' => $latestResult?->position,
                'latestPercentage' => $latestResult?->percentage,
                'latestExamName' => $latestResult?->exam?->name,
            ],
            'recentResults' => $recentResults,
        ]);
    }

    /**
     * All exam results for the student grouped by academic session.
     */
    public function results(Request $request): Response|RedirectResponse
    {
        $user = $request->user();
        $student = $user->studentProfile;

        if (!$student) {
            return redirect()->route('profile.edit')
                ->with('error', 'Your student account is not linked to a student record.');
        }

        $results = Result::where('student_id', $student->id)
            ->with(['exam.examType', 'academicSession'])
            ->latest('id')
            ->get()
            ->map(fn ($r) => $this->serializeResultSummary($r));

        $grouped = $results->groupBy('session_id');

        $sessions = AcademicSession::whereIn('id', $results->pluck('session_id')->unique()->filter())
            ->orderByDesc('start_date')
            ->get(['id', 'name']);

        return Inertia::render('StudentPortal/Results', [
            'results' => $results,
            'grouped' => $grouped,
            'sessions' => $sessions,
        ]);
    }

    /**
     * Single result detail.
     */
    public function resultDetail(Request $request, Result $result): Response|RedirectResponse
    {
        $user = $request->user();
        $student = $user->studentProfile;

        if (!$student || $result->student_id !== $student->id) {
            abort(403, 'You do not have access to this result.');
        }

        $result->load(['exam.examType', 'exam.gradingScale', 'school', 'schoolClass', 'section', 'academicSession']);

        $subjects = collect($result->subject_results ?? [])->map(function ($s) {
            return [
                'subject_id' => $s['subject_id'] ?? null,
                'subject_name' => $s['subject_name'] ?? ($s['name'] ?? 'Subject'),
                'total_marks' => $s['total_marks'] ?? null,
                'obtained_marks' => $s['obtained_marks'] ?? null,
                'percentage' => $s['percentage'] ?? null,
                'grade' => $s['grade'] ?? null,
                'is_passed' => $s['is_passed'] ?? null,
                'status' => isset($s['is_passed']) ? ($s['is_passed'] ? 'Pass' : 'Fail') : null,
                'primary_breakdown' => $s['primary_breakdown'] ?? null,
            ];
        })->values();

        // Primary section: pull the overall Assessment row for the same
        // session so the student sees the conduct/participation score next
        // to their academic numbers.
        $isPrimary = $student->schoolClass?->isPrimaryStage() ?? false;
        $assessment = null;
        if ($isPrimary && $result->academic_session_id) {
            $am = \App\Models\AssessmentMark::where('student_id', $student->id)
                ->where('academic_session_id', $result->academic_session_id)
                ->first();
            if ($am) {
                $assessment = [
                    'obtained' => (float) $am->marks_obtained,
                    'total' => (float) $am->marks_total,
                    'passing' => (float) $am->passing_marks,
                    'passed' => $am->isPassed(),
                    'remarks' => $am->remarks,
                ];
            }
        }

        return Inertia::render('StudentPortal/ResultDetail', [
            'result' => [
                'id' => $result->id,
                'total_marks' => $result->total_marks,
                'obtained_marks' => $result->obtained_marks,
                'percentage' => $result->percentage,
                'grade' => $result->grade,
                'grade_point' => $result->grade_point,
                'position' => $result->position,
                'total_students' => $result->total_students,
                'subjects_passed' => $result->subjects_passed,
                'subjects_failed' => $result->subjects_failed,
                'is_passed' => (bool) $result->is_passed,
                'status' => $result->status,
                'remarks' => $result->remarks,
                'session' => $result->academicSession ? ['id' => $result->academicSession->id, 'name' => $result->academicSession->name] : null,
            ],
            'exam' => $result->exam ? [
                'id' => $result->exam->id,
                'name' => $result->exam->name,
                'type' => $result->exam->examType?->name,
                'start_date' => $result->exam->start_date?->format('M d, Y'),
                'end_date' => $result->exam->end_date?->format('M d, Y'),
            ] : null,
            'subjects' => $subjects,
            'isPrimary' => $isPrimary,
            'assessment' => $assessment,
            'student' => [
                'id' => $student->id,
                'name' => $student->name,
                'admission_no' => $student->admission_no,
                'roll_no' => $student->roll_no,
            ],
        ]);
    }

    /**
     * Download report card PDF for one of the student's results.
     */
    public function downloadReportCard(Request $request, Result $result)
    {
        $user = $request->user();
        $student = $user->studentProfile;

        if (!$student || $result->student_id !== $student->id) {
            abort(403, 'You do not have access to this report card.');
        }

        return redirect()->route('reports.report-card', [$result->exam_id, $student->id]);
    }

    private function serializeResultSummary(Result $r): array
    {
        return [
            'id' => $r->id,
            'exam_id' => $r->exam_id,
            'exam_name' => $r->exam?->name,
            'exam_type' => $r->exam?->examType?->name,
            'session_id' => $r->academic_session_id,
            'session_name' => $r->academicSession?->name,
            'total_marks' => $r->total_marks,
            'obtained_marks' => $r->obtained_marks,
            'percentage' => $r->percentage,
            'grade' => $r->grade,
            'position' => $r->position,
            'is_passed' => (bool) $r->is_passed,
            'status' => $r->status,
        ];
    }
}
