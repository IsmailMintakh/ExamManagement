<?php

namespace App\Http\Controllers;

use App\Models\AcademicSession;
use App\Models\ExamSchedule;
use App\Models\Result;
use App\Models\Student;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Unified portal serving BOTH student and parent roles from one set of routes
 * and Vue pages. The role distinction is invisible to the URL — a student
 * sees their own data, a parent sees the same pages but with a child picker
 * because they can have multiple children. Replaces the old split between
 * StudentPortalController + ParentPortalController.
 */
class FamilyPortalController extends Controller
{
    /**
     * Build the list of students the logged-in user can view.
     *  - student: their own profile (one entry)
     *  - parent: their linked children (zero or more)
     *  - else: empty
     */
    protected function viewableStudents(User $user)
    {
        if ($user->isStudent()) {
            $own = $user->studentProfile()
                ->with(['school', 'schoolClass', 'section', 'academicSession'])
                ->first();
            return $own ? collect([$own]) : collect();
        }

        if ($user->isParent()) {
            return $user->children()
                ->with(['school', 'schoolClass', 'section', 'academicSession'])
                ->get();
        }

        return collect();
    }

    /**
     * Pick the "active" student given the request — explicit ?student_id wins,
     * otherwise the first one in the viewable list. Returns null if none.
     */
    protected function activeStudent(Request $request, $students): ?Student
    {
        if ($students->isEmpty()) return null;

        $requestedId = $request->integer('student_id');
        if ($requestedId) {
            $match = $students->firstWhere('id', $requestedId);
            if ($match) return $match;
            // Parent passed a student_id that's not theirs — refuse rather
            // than silently fall back, so they don't get a misleading view.
            abort(403, 'This child is not linked to your account.');
        }

        return $students->first();
    }

    public function dashboard(Request $request): Response|RedirectResponse
    {
        $user = $request->user();
        $students = $this->viewableStudents($user);

        if ($students->isEmpty()) {
            return redirect()->route('profile.edit')
                ->with('error', 'Your account is not linked to any student record. Please contact your school administrator.');
        }

        $student = $this->activeStudent($request, $students);

        // Quick stats for the active student.
        // Family Portal hides unpublished results — they only become visible
        // after the Principal/DDO clicks "Publish Results".
        $resultsQuery = Result::where('student_id', $student->id)->published();
        $totalExams = (clone $resultsQuery)->count();
        $latestResult = (clone $resultsQuery)
            ->with(['exam.examType'])
            ->latest('id')
            ->first();

        $recentResults = (clone $resultsQuery)
            ->with(['exam.examType', 'academicSession'])
            ->latest('id')
            ->take(5)
            ->get()
            ->map(fn ($r) => $this->serializeResultSummary($r));

        // Upcoming papers (next 14 days) so the student sees what's around the corner.
        $upcoming = ExamSchedule::query()
            ->where('school_class_id', $student->school_class_id)
            ->whereDate('exam_date', '>=', now()->toDateString())
            ->whereDate('exam_date', '<=', now()->addDays(14)->toDateString())
            ->with(['subject:id,name,code', 'exam:id,name'])
            ->orderBy('exam_date')
            ->orderBy('start_time')
            ->limit(8)
            ->get()
            ->map(fn ($s) => [
                'subject' => $s->subject?->name,
                'exam_name' => $s->exam?->name,
                'date' => $s->exam_date?->format('d M Y'),
                'day' => $s->exam_date?->format('l'),
                'time' => substr($s->start_time ?? '', 0, 5) . ' – ' . substr($s->end_time ?? '', 0, 5),
            ]);

        return Inertia::render('Family/Dashboard', [
            'role' => $user->isParent() ? 'parent' : 'student',
            'students' => $students->map(fn ($s) => $this->serializeStudentCard($s))->values(),
            'activeStudentId' => $student->id,
            'student' => $this->serializeStudent($student),
            'currentSession' => AcademicSession::currentSession(),
            'stats' => [
                'totalExams' => $totalExams,
                'latestGrade' => $latestResult?->grade,
                'latestPosition' => $latestResult?->position,
                'latestPercentage' => $latestResult?->percentage,
                'latestExamName' => $latestResult?->exam?->name,
            ],
            'recentResults' => $recentResults,
            'upcoming' => $upcoming,
        ]);
    }

    public function results(Request $request): Response|RedirectResponse
    {
        $user = $request->user();
        $students = $this->viewableStudents($user);

        if ($students->isEmpty()) {
            return redirect()->route('profile.edit')
                ->with('error', 'Your account is not linked to any student record.');
        }

        $student = $this->activeStudent($request, $students);

        $results = Result::where('student_id', $student->id)
            ->published()
            ->with(['exam.examType', 'academicSession'])
            ->latest('id')
            ->get()
            ->map(fn ($r) => $this->serializeResultSummary($r));

        $sessions = AcademicSession::whereIn('id', $results->pluck('session_id')->unique()->filter())
            ->orderByDesc('start_date')
            ->get(['id', 'name']);

        return Inertia::render('Family/Results', [
            'role' => $user->isParent() ? 'parent' : 'student',
            'students' => $students->map(fn ($s) => $this->serializeStudentCard($s))->values(),
            'activeStudentId' => $student->id,
            'student' => $this->serializeStudent($student),
            'results' => $results,
            'sessions' => $sessions,
        ]);
    }

    public function resultDetail(Request $request, Result $result): Response|RedirectResponse
    {
        $user = $request->user();
        $students = $this->viewableStudents($user);

        // Authorize: this result must belong to one of the user's viewable students.
        if (!$students->pluck('id')->contains($result->student_id)) {
            abort(403, 'You do not have access to this result.');
        }

        // Hide unpublished results — even if a parent has the URL, they can't
        // see the breakdown until publication.
        $result->loadMissing('exam');
        if (!$result->published_at && !$result->exam?->results_published_at) {
            abort(404, 'This result has not been published yet.');
        }

        $student = $students->firstWhere('id', $result->student_id);
        $result->load([
            'exam.examType', 'exam.gradingScale', 'school', 'schoolClass',
            'section', 'academicSession',
            // Amendment audit trail — most-recent first.
            'amendments.amendedBy:id,name',
        ]);

        $subjects = collect($result->subject_results ?? [])->map(function ($s) {
            return [
                'subject_id' => $s['subject_id'] ?? null,
                'subject_name' => $s['subject_name'] ?? ($s['name'] ?? 'Subject'),
                'total_marks' => $s['total_marks'] ?? null,
                'obtained_marks' => $s['obtained_marks'] ?? $s['marks_obtained'] ?? null,
                'percentage' => $s['percentage'] ?? null,
                'grade' => $s['grade'] ?? null,
                'is_passed' => $s['is_passed'] ?? null,
                'status' => isset($s['is_passed']) ? ($s['is_passed'] ? 'Pass' : 'Fail') : null,
            ];
        })->values();

        $amendments = $result->amendments->map(fn ($a) => [
            'id' => $a->id,
            'reason' => $a->reason,
            'amended_at' => $a->created_at->format('d M Y, h:i A'),
            'amended_by' => $a->amendedBy?->name,
        ]);

        return Inertia::render('Family/ResultDetail', [
            'role' => $user->isParent() ? 'parent' : 'student',
            'student' => $this->serializeStudent($student),
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
                'last_amended_at' => $result->last_amended_at?->format('d M Y, h:i A'),
                'session' => $result->academicSession ? ['id' => $result->academicSession->id, 'name' => $result->academicSession->name] : null,
            ],
            'amendments' => $amendments,
            'exam' => $result->exam ? [
                'id' => $result->exam->id,
                'name' => $result->exam->name,
                'type' => $result->exam->examType?->name,
                'start_date' => $result->exam->start_date?->format('M d, Y'),
                'end_date' => $result->exam->end_date?->format('M d, Y'),
            ] : null,
            'subjects' => $subjects,
        ]);
    }

    /** Hand off to the existing report-card PDF route, preserving auth. */
    public function downloadReportCard(Request $request, Result $result): RedirectResponse
    {
        $user = $request->user();
        $students = $this->viewableStudents($user);

        if (!$students->pluck('id')->contains($result->student_id)) {
            abort(403, 'You do not have access to this report card.');
        }

        return redirect()->route('reports.report-card', [$result->exam_id, $result->student_id]);
    }

    // ─── Serializers ─────────────────────────────────────────────────

    /** Compact student summary used for the picker bar (avatar + name). */
    protected function serializeStudentCard(Student $s): array
    {
        return [
            'id' => $s->id,
            'name' => $s->name,
            'admission_no' => $s->admission_no,
            'photo_url' => $s->photo_url,
            'class_name' => $s->schoolClass?->name,
            'section_name' => $s->section?->name,
        ];
    }

    /** Full student profile shown on the dashboard. */
    protected function serializeStudent(Student $s): array
    {
        return [
            'id' => $s->id,
            'name' => $s->name,
            'admission_no' => $s->admission_no,
            'roll_no' => $s->roll_no,
            'father_name' => $s->father_name,
            'mother_name' => $s->mother_name,
            'date_of_birth' => $s->date_of_birth?->format('Y-m-d'),
            'gender' => $s->gender,
            'photo_url' => $s->photo_url,
            'school' => $s->school ? ['id' => $s->school->id, 'name' => $s->school->name] : null,
            'class' => $s->schoolClass ? ['id' => $s->schoolClass->id, 'name' => $s->schoolClass->name] : null,
            'section' => $s->section ? ['id' => $s->section->id, 'name' => $s->section->name] : null,
            'session' => $s->academicSession ? ['id' => $s->academicSession->id, 'name' => $s->academicSession->name] : null,
        ];
    }

    /** Compact summary line used by both the dashboard and results pages. */
    protected function serializeResultSummary(Result $r): array
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
