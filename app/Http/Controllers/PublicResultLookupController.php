<?php

namespace App\Http\Controllers;

use App\Models\Exam;
use App\Models\Result;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Public (no login) result lookup.
 *
 * Flow:
 *   1. Page loads with a dropdown of currently-published exams. If none
 *      are published, the form shows an empty-state instead of a search.
 *   2. Student/parent picks the exam, enters admission number + DOB.
 *   3. We rate-limit by IP (10/minute), match the student, and return
 *      ONLY that exam's result for that student.
 *
 * Why select-an-exam-first: schools usually announce results one exam at
 * a time. The previous "show every published result for this student" UX
 * was confusing — the user wants to look up "my Mid-Term result", not
 * scroll a list of past results.
 */
class PublicResultLookupController extends Controller
{
    /** GET /check-result — render the form. */
    public function index(): Response
    {
        return Inertia::render('Public/ResultLookup', [
            'publishedExams' => $this->publishedExamsForDropdown(),
            'result' => null,
            'student' => null,
            'searched' => false,
        ]);
    }

    /** POST /check-result — validate, rate-limit, look up. */
    public function lookup(Request $request): Response
    {
        $validated = $request->validate([
            'exam_id' => ['required', 'integer', 'exists:exams,id'],
            'admission_no' => ['required', 'string', 'max:50'],
        ], [
            'exam_id.required' => 'Pick an exam.',
            'admission_no.required' => 'Enter your admission number.',
        ]);

        // Trim + case-fold the admission number on both sides so leading
        // spaces, trailing tabs, or "100 " vs "100" don't fail to match.
        $admission = trim($validated['admission_no']);

        // Rate limit per IP. Without the DOB second-factor, admission-number
        // enumeration is cheaper, so we tighten the limit (was 10, now 6 per
        // minute) and lengthen the decay window. Counter is checked BEFORE
        // any DB hit so a denial costs nothing.
        $key = 'public-result-lookup:' . $request->ip();
        if (RateLimiter::tooManyAttempts($key, 6)) {
            $seconds = RateLimiter::availableIn($key);
            return Inertia::render('Public/ResultLookup', [
                'publishedExams' => $this->publishedExamsForDropdown(),
                'result' => null,
                'student' => null,
                'searched' => true,
                'error' => "Too many lookup attempts. Try again in {$seconds} seconds.",
                'admission_no' => $admission,
                'exam_id' => (int) $validated['exam_id'],
            ]);
        }
        RateLimiter::hit($key, 90);

        // Ensure the picked exam is actually published — even if a stale form
        // submits an exam that was unpublished after the page loaded.
        $exam = Exam::whereNotNull('results_published_at')
            ->where('id', $validated['exam_id'])
            ->with('examType', 'academicSession')
            ->first();
        if (!$exam) {
            return Inertia::render('Public/ResultLookup', [
                'publishedExams' => $this->publishedExamsForDropdown(),
                'result' => null,
                'student' => null,
                'searched' => true,
                'error' => 'Results for that exam are not yet published.',
                'admission_no' => $admission,
                'exam_id' => (int) $validated['exam_id'],
            ]);
        }

        // Whitespace-tolerant admission match. We narrow to the schools this
        // exam applies to — admission numbers can collide across schools, but
        // within the schools assigned to one exam they should be unique.
        $matches = Student::query()
            ->whereRaw('LOWER(TRIM(admission_no)) = ?', [strtolower($admission)])
            ->whereIn('school_id', $exam->schools()->pluck('schools.id'))
            ->with(['school:id,name', 'schoolClass:id,name', 'section:id,name'])
            ->get();

        if ($matches->isEmpty()) {
            return Inertia::render('Public/ResultLookup', [
                'publishedExams' => $this->publishedExamsForDropdown(),
                'result' => null,
                'student' => null,
                'searched' => true,
                'error' => 'No student matches that admission number for the selected exam. Check the admission number and the exam are correct.',
                'admission_no' => $admission,
                'exam_id' => (int) $validated['exam_id'],
            ]);
        }

        // If the same admission number exists in multiple schools that share
        // this exam, we can't pick the right one safely without more info.
        // Fall back to the one whose result is actually published — usually
        // there's only one, since most exams target one school in practice.
        if ($matches->count() > 1) {
            $withResult = $matches->first(fn ($s) => Result::where('student_id', $s->id)
                ->where('exam_id', $exam->id)
                ->published()
                ->exists());
            $student = $withResult ?? $matches->first();
        } else {
            $student = $matches->first();
        }

        // Pull the ONE result for this exam, only if it's published.
        $result = Result::where('student_id', $student->id)
            ->where('exam_id', $exam->id)
            ->published()
            ->first();

        return Inertia::render('Public/ResultLookup', [
            'publishedExams' => $this->publishedExamsForDropdown(),
            'searched' => true,
            'student' => [
                'name' => $student->name,
                'admission_no' => $student->admission_no,
                'roll_no' => $student->roll_no,
                'father_name' => $student->father_name,
                'school_name' => $student->school?->name,
                'class_name' => $student->schoolClass?->name,
                'section_name' => $student->section?->name,
            ],
            'exam' => [
                'id' => $exam->id,
                'name' => $exam->name,
                'type' => $exam->examType?->name,
                'session_name' => $exam->academicSession?->name,
            ],
            'result' => $result ? [
                'id' => $result->id,
                'total_marks' => $result->total_marks,
                'obtained_marks' => $result->obtained_marks,
                'percentage' => $result->percentage,
                'grade' => $result->grade,
                'position' => $result->position,
                'is_passed' => (bool) $result->is_passed,
                'published_on' => ($result->published_at ?? $exam->results_published_at)?->format('d M Y'),
            ] : null,
            'admission_no' => $admission,
            'exam_id' => (int) $exam->id,
            'noResult' => $result === null,
        ]);
    }

    /**
     * Compact dropdown source: every exam whose results are currently
     * published, ordered most-recent first. We expose only the bare
     * minimum (id, name, type, session) — no school list — to keep the
     * public surface small.
     */
    protected function publishedExamsForDropdown(): array
    {
        return Exam::whereNotNull('results_published_at')
            ->with('examType:id,name', 'academicSession:id,name')
            ->orderByDesc('results_published_at')
            ->get(['id', 'name', 'exam_type_id', 'academic_session_id', 'results_published_at'])
            ->map(fn ($e) => [
                'id' => $e->id,
                'name' => $e->name,
                'type' => $e->examType?->name,
                'session' => $e->academicSession?->name,
                'label' => trim(($e->examType?->name ? $e->examType->name . ' · ' : '') . $e->name . ($e->academicSession?->name ? ' (' . $e->academicSession->name . ')' : '')),
            ])
            ->values()
            ->all();
    }
}
