<?php

namespace App\Http\Controllers;

use App\Models\AcademicSession;
use App\Models\Result;
use App\Models\Student;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ParentPortalController extends Controller
{
    /**
     * Parent dashboard - lists all linked children with a quick summary
     * (school, class, section, latest result).
     */
    public function dashboard(Request $request): Response
    {
        $user = $request->user();

        $children = $user->children()
            ->with(['school', 'schoolClass', 'section', 'academicSession'])
            ->get()
            ->map(function (Student $child) {
                $latest = Result::where('student_id', $child->id)
                    ->with(['exam.examType', 'academicSession'])
                    ->latest('id')
                    ->first();

                return [
                    'id' => $child->id,
                    'name' => $child->name,
                    'admission_no' => $child->admission_no,
                    'roll_no' => $child->roll_no,
                    'photo_url' => $child->photo_url,
                    'school' => $child->school ? ['id' => $child->school->id, 'name' => $child->school->name] : null,
                    'class' => $child->schoolClass ? ['id' => $child->schoolClass->id, 'name' => $child->schoolClass->name] : null,
                    'section' => $child->section ? ['id' => $child->section->id, 'name' => $child->section->name] : null,
                    'session' => $child->academicSession ? ['id' => $child->academicSession->id, 'name' => $child->academicSession->name] : null,
                    'latest_result' => $latest ? [
                        'id' => $latest->id,
                        'exam_name' => $latest->exam?->name,
                        'exam_type' => $latest->exam?->examType?->name,
                        'percentage' => $latest->percentage,
                        'grade' => $latest->grade,
                        'position' => $latest->position,
                        'is_passed' => (bool) $latest->is_passed,
                        'session_name' => $latest->academicSession?->name,
                    ] : null,
                ];
            });

        return Inertia::render('ParentPortal/Dashboard', [
            'children' => $children,
            'currentSession' => AcademicSession::currentSession(),
        ]);
    }

    /**
     * Results for a single child grouped by academic session.
     */
    public function childResults(Request $request, Student $student): Response
    {
        $user = $request->user();

        if ((int) $student->parent_user_id !== (int) $user->id) {
            abort(403, 'This child is not linked to your account.');
        }

        $student->load(['school', 'schoolClass', 'section', 'academicSession']);

        $results = Result::where('student_id', $student->id)
            ->with(['exam.examType', 'academicSession'])
            ->latest('id')
            ->get()
            ->map(fn ($r) => [
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
            ]);

        $sessions = AcademicSession::whereIn('id', $results->pluck('session_id')->unique()->filter())
            ->orderByDesc('start_date')
            ->get(['id', 'name']);

        return Inertia::render('ParentPortal/ChildResults', [
            'student' => [
                'id' => $student->id,
                'name' => $student->name,
                'admission_no' => $student->admission_no,
                'roll_no' => $student->roll_no,
                'photo_url' => $student->photo_url,
                'school' => $student->school ? ['id' => $student->school->id, 'name' => $student->school->name] : null,
                'class' => $student->schoolClass ? ['id' => $student->schoolClass->id, 'name' => $student->schoolClass->name] : null,
                'section' => $student->section ? ['id' => $student->section->id, 'name' => $student->section->name] : null,
            ],
            'results' => $results,
            'sessions' => $sessions,
        ]);
    }
}
