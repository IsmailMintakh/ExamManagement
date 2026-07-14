<?php

namespace App\Http\Controllers;

use App\Models\AcademicSession;
use App\Models\Exam;
use App\Models\ExamSubject;
use App\Models\Mark;
use App\Models\Result;
use App\Models\Section;
use App\Models\Subject;
use App\Models\SubjectTeacher;
use App\Services\ResultProcessingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class SupplementaryController extends Controller
{
    /**
     * Default threshold: students who failed up to this many subjects are eligible
     * for supplementary auto-detection.
     */
    protected const ELIGIBILITY_THRESHOLD = 2;

    /**
     * List exams that have any supplementary-eligible students.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user->can('supplementary.view'), 403);
        $currentSession = AcademicSession::currentSession();

        $exams = Exam::query()
            ->when($currentSession, fn ($q) => $q->where('academic_session_id', $currentSession->id))
            ->when(!$user->isSuperAdmin(), fn ($q) => $q->visibleToSchool($user->school_id))
            ->whereHas('results', function ($q) use ($user) {
                $q->where('is_supplementary_eligible', true)
                    ->when(!$user->isSuperAdmin(), fn ($q2) => $q2->where('school_id', $user->school_id));
            })
            ->with(['examType', 'academicSession'])
            ->latest()
            ->get()
            ->map(function ($exam) use ($user) {
                $base = Result::where('exam_id', $exam->id)
                    ->where('is_supplementary_eligible', true)
                    ->when(!$user->isSuperAdmin(), fn ($q) => $q->where('school_id', $user->school_id));

                $eligible = (clone $base)->count();
                $appeared = (clone $base)->whereIn('supplementary_status', ['appeared', 'passed', 'failed'])->count();
                $passed = (clone $base)->where('supplementary_status', 'passed')->count();
                $failed = (clone $base)->where('supplementary_status', 'failed')->count();

                return [
                    'id' => $exam->id,
                    'name' => $exam->name,
                    'exam_type' => $exam->examType?->name,
                    'session' => $exam->academicSession?->name,
                    'status' => $exam->status,
                    'eligible_count' => $eligible,
                    'appeared_count' => $appeared,
                    'passed_count' => $passed,
                    'failed_count' => $failed,
                    'pending_count' => max(0, $eligible - $appeared),
                ];
            });

        $totals = [
            'pending_exams' => $exams->where('pending_count', '>', 0)->count(),
            'eligible_students' => $exams->sum('eligible_count'),
            'appeared' => $exams->sum('appeared_count'),
            'passed' => $exams->sum('passed_count'),
        ];

        return Inertia::render('Supplementary/Index', [
            'exams' => $exams->values(),
            'totals' => $totals,
            'currentSession' => $currentSession,
        ]);
    }

    /**
     * Show eligible students for an exam.
     */
    public function show(Exam $exam): Response
    {
        $user = request()->user();
        abort_unless($user->can('supplementary.view'), 403);

        if (!$user->isSuperAdmin()) {
            $exam->load('schools');
            $hasAccess = $exam->schools->contains('id', $user->school_id);
            if (!$hasAccess) {
                abort(403);
            }
        }

        $exam->load(['examType', 'academicSession']);

        // All failed results in this exam (used to surface auto-detect potential)
        $failedQuery = Result::where('exam_id', $exam->id)
            ->where('is_passed', false)
            ->when(!$user->isSuperAdmin(), fn ($q) => $q->where('school_id', $user->school_id));

        $totalFailed = (clone $failedQuery)->count();

        // Students currently flagged as eligible
        $eligibleResults = Result::where('exam_id', $exam->id)
            ->where('is_supplementary_eligible', true)
            ->when(!$user->isSuperAdmin(), fn ($q) => $q->where('school_id', $user->school_id))
            ->with(['student', 'schoolClass', 'section'])
            ->orderBy('school_class_id')
            ->orderBy('section_id')
            ->get();

        // Build a map of subject ids -> subject info we'll need
        $subjectIds = collect();
        foreach ($eligibleResults as $r) {
            foreach (($r->supplementary_subjects ?? []) as $sid) {
                $subjectIds->push((int) $sid);
            }
        }
        $subjects = Subject::whereIn('id', $subjectIds->unique()->values()->all())
            ->get(['id', 'name', 'code'])
            ->keyBy('id');

        $rows = $eligibleResults->map(function ($r) use ($subjects) {
            $failedSubjects = collect($r->supplementary_subjects ?? [])->map(function ($sid) use ($subjects) {
                $s = $subjects->get((int) $sid);
                return [
                    'id' => (int) $sid,
                    'name' => $s?->name ?? ('Subject #' . $sid),
                    'code' => $s?->code,
                ];
            });

            return [
                'id' => $r->id,
                'student_id' => $r->student_id,
                'student_name' => $r->student?->name,
                'roll_no' => $r->student?->roll_no,
                'class_name' => $r->schoolClass?->name,
                'section_name' => $r->section?->name,
                'failed_subjects' => $failedSubjects,
                'supplementary_status' => $r->supplementary_status,
                'is_passed' => (bool) $r->is_passed,
            ];
        });

        $stats = [
            'total_failed' => $totalFailed,
            'eligible' => $eligibleResults->count(),
            'appeared' => $eligibleResults->whereIn('supplementary_status', ['appeared', 'passed', 'failed'])->count(),
            'passed' => $eligibleResults->where('supplementary_status', 'passed')->count(),
            'failed' => $eligibleResults->where('supplementary_status', 'failed')->count(),
            'threshold' => self::ELIGIBILITY_THRESHOLD,
        ];

        return Inertia::render('Supplementary/Show', [
            'exam' => [
                'id' => $exam->id,
                'name' => $exam->name,
                'exam_type' => $exam->examType?->name,
                'session' => $exam->academicSession?->name,
                'status' => $exam->status,
            ],
            'rows' => $rows,
            'stats' => $stats,
        ]);
    }

    /**
     * Bulk auto-detect eligible students based on the threshold.
     * Principal-only action.
     */
    public function markEligible(Request $request, Exam $exam): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->can('supplementary.manage'), 403);

        if (!$user->isSchoolAdmin() && !$user->isSuperAdmin()) {
            abort(403, 'Only the principal can mark students eligible for supplementary.');
        }

        $threshold = (int) $request->input('threshold', self::ELIGIBILITY_THRESHOLD);
        if ($threshold < 1) {
            $threshold = self::ELIGIBILITY_THRESHOLD;
        }

        $failedResults = Result::where('exam_id', $exam->id)
            ->where('is_passed', false)
            ->when(!$user->isSuperAdmin(), fn ($q) => $q->where('school_id', $user->school_id))
            ->get();

        $count = 0;

        DB::transaction(function () use ($failedResults, $threshold, &$count) {
            foreach ($failedResults as $result) {
                $subjectResults = $result->subject_results ?? [];

                // Failed, non-absent subjects only
                $failedSubjectIds = collect($subjectResults)
                    ->filter(fn ($sr) => !($sr['is_passed'] ?? true) && !($sr['is_absent'] ?? false))
                    ->pluck('subject_id')
                    ->map(fn ($id) => (int) $id)
                    ->values()
                    ->all();

                $failedCount = count($failedSubjectIds);

                if ($failedCount === 0 || $failedCount > $threshold) {
                    continue;
                }

                $result->is_supplementary_eligible = true;
                $result->supplementary_subjects = $failedSubjectIds;
                $result->supplementary_status = 'eligible';
                $result->save();

                $count++;
            }
        });

        return redirect()
            ->route('supplementary.show', $exam->id)
            ->with('success', sprintf('%d student(s) marked as eligible for supplementary.', $count));
    }

    /**
     * Marks-entry view for a single eligible student.
     * Subject-teacher access (for their failed subjects only).
     */
    public function enterMarks(Result $result): Response
    {
        $user = request()->user();
        abort_unless($user->can('supplementary.manage'), 403);

        if (!$result->is_supplementary_eligible) {
            abort(404, 'This student is not eligible for supplementary.');
        }

        $exam = $result->exam;

        // Access check
        $allowed = $user->isSuperAdmin() || $user->isSchoolAdmin();
        if (!$allowed && $user->isSubjectTeacher()) {
            // Subject teacher must teach at least one of the failed subjects in this section
            $allowed = SubjectTeacher::where('user_id', $user->id)
                ->where('section_id', $result->section_id)
                ->whereIn('subject_id', $result->supplementary_subjects ?? [])
                ->where('is_active', true)
                ->exists();
        }
        if (!$allowed) {
            abort(403, 'You do not have permission to enter supplementary marks for this student.');
        }

        $subjectIds = collect($result->supplementary_subjects ?? [])->map(fn ($id) => (int) $id);

        $examSubjects = ExamSubject::where('exam_id', $result->exam_id)
            ->where('school_class_id', $result->school_class_id)
            ->whereIn('subject_id', $subjectIds)
            ->with('subject')
            ->get()
            // Skip papers this section doesn't take — supplementary
            // should never surface a subject that was excluded at exam
            // setup time.
            ->filter(fn ($es) => $es->appliesToSection((int) $result->section_id))
            ->values();

        $existingMarks = Mark::where('exam_id', $result->exam_id)
            ->where('student_id', $result->student_id)
            ->whereIn('subject_id', $subjectIds)
            ->get()
            ->keyBy('subject_id');

        $subjects = $examSubjects->map(function ($es) use ($existingMarks) {
            $mark = $existingMarks->get($es->subject_id);
            return [
                'subject_id' => $es->subject_id,
                'name' => $es->subject?->name,
                'code' => $es->subject?->code,
                'total_marks' => (float) $es->total_marks,
                'passing_marks' => (float) $es->passing_marks,
                'previous_marks' => $mark ? (float) $mark->marks_obtained : null,
                'supplementary_marks' => $mark?->supplementary_marks !== null ? (float) $mark->supplementary_marks : null,
            ];
        });

        $result->load(['student', 'schoolClass', 'section', 'exam']);

        return Inertia::render('Supplementary/Entry', [
            'result' => [
                'id' => $result->id,
                'student_name' => $result->student?->name,
                'roll_no' => $result->student?->roll_no,
                'class_name' => $result->schoolClass?->name,
                'section_name' => $result->section?->name,
                'supplementary_status' => $result->supplementary_status,
            ],
            'exam' => [
                'id' => $result->exam?->id,
                'name' => $result->exam?->name,
            ],
            'subjects' => $subjects,
        ]);
    }

    /**
     * Save supplementary marks for the student's failed subjects.
     */
    public function storeMarks(Request $request, Result $result): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->can('supplementary.manage'), 403);

        if (!$result->is_supplementary_eligible) {
            abort(404);
        }

        $supplementarySubjectIds = collect($result->supplementary_subjects ?? [])
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();

        // Access check
        $allowed = $user->isSuperAdmin() || $user->isSchoolAdmin();
        if (!$allowed && $user->isSubjectTeacher()) {
            $allowed = SubjectTeacher::where('user_id', $user->id)
                ->where('section_id', $result->section_id)
                ->whereIn('subject_id', $supplementarySubjectIds)
                ->where('is_active', true)
                ->exists();
        }
        if (!$allowed) {
            abort(403);
        }

        $validated = $request->validate([
            'marks' => ['required', 'array', 'min:1'],
            'marks.*' => ['nullable', 'numeric', 'min:0'],
        ]);

        $currentSession = AcademicSession::currentSession();

        DB::transaction(function () use ($validated, $result, $supplementarySubjectIds, $user, $currentSession) {
            foreach ($validated['marks'] as $subjectId => $marks) {
                $subjectId = (int) $subjectId;

                if (!in_array($subjectId, $supplementarySubjectIds, true)) {
                    continue; // ignore subjects not flagged as supplementary
                }

                if ($marks === null || $marks === '') {
                    continue;
                }

                $examSubject = ExamSubject::where('exam_id', $result->exam_id)
                    ->where('school_class_id', $result->school_class_id)
                    ->where('subject_id', $subjectId)
                    ->first();
                // Refuse supplementary marks for a subject not applicable
                // to this student's section — defense in depth even though
                // the form-side list should already have filtered it out.
                if ($examSubject && !$examSubject->appliesToSection((int) $result->section_id)) {
                    continue;
                }

                if (!$examSubject) {
                    continue;
                }

                Mark::updateOrCreate(
                    [
                        'exam_id' => $result->exam_id,
                        'subject_id' => $subjectId,
                        'student_id' => $result->student_id,
                        'section_id' => $result->section_id,
                    ],
                    [
                        'exam_subject_id' => $examSubject->id,
                        'school_id' => $result->school_id,
                        'school_class_id' => $result->school_class_id,
                        'academic_session_id' => $result->academic_session_id ?? $currentSession?->id,
                        'total_marks' => $examSubject->total_marks,
                        'is_supplementary' => true,
                        'supplementary_marks' => (float) $marks,
                        'status' => 'submitted',
                        'entered_by' => $user->id,
                        'submitted_at' => now(),
                    ]
                );
            }

            $result->supplementary_status = 'appeared';
            $result->supplementary_at = now();
            $result->save();
        });

        return redirect()
            ->route('supplementary.show', $result->exam_id)
            ->with('success', 'Supplementary marks saved.');
    }

    /**
     * Recalculate the student's result after supplementary marks are in.
     * Uses the better of (original, supplementary) for each affected subject.
     */
    public function finalize(Result $result, ResultProcessingService $service): RedirectResponse
    {
        $user = request()->user();
        abort_unless($user->can('supplementary.manage'), 403);

        if (!$user->isSuperAdmin() && !$user->isSchoolAdmin()) {
            abort(403, 'Only the principal can finalize supplementary results.');
        }

        if (!$result->is_supplementary_eligible) {
            abort(404);
        }

        $supplementarySubjectIds = collect($result->supplementary_subjects ?? [])
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();

        DB::transaction(function () use ($result, $supplementarySubjectIds, $service) {
            // Promote supplementary marks into marks_obtained when better
            foreach ($supplementarySubjectIds as $subjectId) {
                $mark = Mark::where('exam_id', $result->exam_id)
                    ->where('student_id', $result->student_id)
                    ->where('subject_id', $subjectId)
                    ->first();

                if (!$mark || $mark->supplementary_marks === null) {
                    continue;
                }

                $original = (float) $mark->marks_obtained;
                $supp = (float) $mark->supplementary_marks;
                $best = max($original, $supp);

                $mark->marks_obtained = $best;
                $mark->is_absent = false;
                $mark->status = 'submitted';
                if ($mark->submitted_at === null) {
                    $mark->submitted_at = now();
                }
                $mark->save();
            }

            // Regenerate this student's result by reprocessing the entire section,
            // then re-stamp the supplementary fields on the fresh row.
            $service->generateResults($result->exam, $result->school_class_id, $result->section_id);

            $fresh = Result::where('exam_id', $result->exam_id)
                ->where('student_id', $result->student_id)
                ->where('section_id', $result->section_id)
                ->first();

            if ($fresh) {
                $fresh->is_supplementary_eligible = true;
                $fresh->supplementary_subjects = $supplementarySubjectIds;
                $fresh->supplementary_status = $fresh->is_passed ? 'passed' : 'failed';
                $fresh->supplementary_at = now();
                $fresh->save();
            }
        });

        return redirect()
            ->route('supplementary.show', $result->exam_id)
            ->with('success', 'Supplementary result finalized.');
    }
}
