<?php

namespace App\Http\Controllers;

use App\Models\AcademicSession;
use App\Models\Exam;
use App\Models\ExamSubject;
use App\Models\Mark;
use App\Models\MarksSubmission;
use App\Models\Result;
use App\Models\ResultSubmission;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Subject;
use App\Models\School;
use App\Models\User;
use App\Notifications\ResultApprovedNotification;
use App\Notifications\ResultGeneratedNotification;
use App\Notifications\ResultReturnedForCorrectionNotification;
use App\Notifications\ResultSubmittedToDdoNotification;
use App\Services\ResultProcessingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Inertia\Inertia;
use Inertia\Response;

class ResultController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user->can('results.view'), 403);
        $currentSession = AcademicSession::currentSession();

        $exams = Exam::query()
            ->whereIn('status', ['marks_entry', 'processing', 'completed', 'published'])
            ->when($currentSession, fn ($q) => $q->where('academic_session_id', $currentSession->id))
            ->when(!$user->isSuperAdmin(), function ($query) use ($user) {
                $query->whereHas('schools', fn ($q) => $q->where('schools.id', $user->school_id));
            })
            ->with(['examType', 'academicSession', 'schools'])
            ->withCount('results')
            ->latest()
            ->get()
            ->map(function ($exam) use ($user) {
                $data = [
                    'id' => $exam->id,
                    'name' => $exam->name,
                    'exam_type' => $exam->examType?->name,
                    'session' => $exam->academicSession?->name,
                    'status' => $exam->status,
                    'results_count' => $exam->results_count,
                ];

                // For super admin, include per-school submission status
                if ($user->isSuperAdmin()) {
                    $data['school_submissions'] = $exam->schools->map(function ($school) use ($exam) {
                        $submissions = ResultSubmission::where('exam_id', $exam->id)
                            ->where('school_id', $school->id)
                            ->get();
                        $totalSections = $submissions->count();
                        $submitted = $submissions->where('status', 'submitted')->count();
                        $approved = $submissions->where('status', 'approved')->count();

                        return [
                            'school_id' => $school->id,
                            'school_name' => $school->name,
                            'total_sections' => $totalSections,
                            'submitted' => $submitted,
                            'approved' => $approved,
                            'status' => $approved === $totalSections && $totalSections > 0
                                ? 'finalized'
                                : ($submitted > 0 ? 'submitted' : ($totalSections > 0 ? 'generated' : 'pending')),
                        ];
                    });
                }

                return $data;
            });

        return Inertia::render('Results/Index', [
            'exams' => $exams,
        ]);
    }

    public function generate(Exam $exam): Response
    {
        $this->authorize('view', $exam);

        $user = request()->user();
        abort_unless($user->can('results.generate') || $user->can('results.view'), 403);

        $exam->load(['examSubjects.subject', 'examSubjects.schoolClass', 'schools']);

        $classIds = $exam->examSubjects->pluck('school_class_id')->unique();

        $classes = SchoolClass::query()
            ->when(!$user->isSuperAdmin(), fn ($q) => $q->where('school_id', $user->school_id))
            ->whereIn('id', $classIds)
            ->active()
            ->ordered()
            ->get(['id', 'name']);

        $sections = Section::query()
            ->whereIn('school_class_id', $classIds)
            ->when(!$user->isSuperAdmin(), function ($q) use ($user) {
                $q->whereHas('schoolClass', fn ($q2) => $q2->where('school_id', $user->school_id));
            })
            ->active()
            ->get(['id', 'name', 'school_class_id']);

        // Marks submission status — include class/section ids so the
        // frontend can group by class and check "all subjects submitted".
        $marksStatus = [];
        foreach ($exam->examSubjects as $es) {
            $secs = Section::where('school_class_id', $es->school_class_id)->active()->get();
            foreach ($secs as $sec) {
                $submission = MarksSubmission::where('exam_id', $exam->id)
                    ->where('subject_id', $es->subject_id)
                    ->where('section_id', $sec->id)
                    ->first();
                $marksStatus[] = [
                    'id' => $es->id . '-' . $sec->id,
                    'class_id' => $es->school_class_id,
                    'section_id' => $sec->id,
                    'subject' => $es->subject?->name,
                    'class_name' => $es->schoolClass?->name,
                    'section_name' => $sec->name,
                    'submitted' => $submission !== null,
                    'teacher' => $submission?->submittedBy?->name,
                ];
            }
        }

        // Existing results grouped by section
        $rawResults = Result::where('exam_id', $exam->id)
            ->when(!$user->isSuperAdmin(), function ($q) use ($user) {
                $q->where('school_id', $user->school_id);
            })
            ->selectRaw('section_id, school_class_id, COUNT(*) as total, SUM(is_passed) as passed, COUNT(*) - SUM(is_passed) as failed, ROUND(AVG(percentage), 2) as pass_percentage')
            ->groupBy('section_id', 'school_class_id')
            ->get();

        // Batch load sections to avoid N+1
        $sectionMap = Section::with('schoolClass')
            ->whereIn('id', $rawResults->pluck('section_id'))
            ->get()
            ->keyBy('id');

        $existingResults = $rawResults->map(function ($r) use ($sectionMap) {
            $section = $sectionMap->get($r->section_id);
            return [
                'section_id' => $r->section_id,
                'class_name' => $section?->schoolClass?->name,
                'section_name' => $section?->name,
                'total' => $r->total,
                'passed' => (int) $r->passed,
                'failed' => (int) $r->failed,
                'pass_percentage' => $r->pass_percentage,
                'status' => 'generated',
            ];
        });

        return Inertia::render('Results/Generate', [
            'exam' => $exam,
            'classes' => $classes,
            'sections' => $sections,
            'marksStatus' => $marksStatus,
            'existingResults' => $existingResults,
        ]);
    }

    public function processGenerate(Request $request, Exam $exam, ResultProcessingService $resultService): RedirectResponse
    {
        $this->authorize('update', $exam);

        $validated = $request->validate([
            'school_class_id' => ['required', 'exists:school_classes,id'],
            'section_id' => ['required', 'exists:sections,id'],
        ]);

        try {
            $resultService->generateResults($exam, $validated['school_class_id'], $validated['section_id']);

            // Notify the school admin(s) that results are ready to review/submit
            try {
                $section = Section::with('schoolClass')->find($validated['section_id']);
                if ($section?->schoolClass) {
                    $admins = User::where('school_id', $section->schoolClass->school_id)
                        ->whereHas('roles', fn ($q) => $q->where('name', 'school-admin'))
                        ->where('id', '!=', $request->user()->id)
                        ->get();
                    if ($admins->isNotEmpty()) {
                        Notification::send($admins, new ResultGeneratedNotification(
                            $exam,
                            $section->schoolClass->name,
                            $section->name
                        ));
                    }
                }
            } catch (\Throwable $e) {
                \Log::warning('ResultGeneratedNotification failed: ' . $e->getMessage());
            }

            return redirect()->back()->with('success', 'Results generated successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Failed to generate results: ' . $e->getMessage());
        }
    }

    public function show(Exam $exam, Section $section): Response
    {
        $this->authorize('view', $exam);

        $section->load('schoolClass');

        // Cross-tenant guard: previously a school-admin from school A could
        // open a result page for school B's section by guessing IDs. The
        // authorize() above only checks the exam, not that the section
        // belongs to the same school the user is part of.
        $user = auth()->user();
        if (!$user->isSuperAdmin() && $section->schoolClass?->school_id !== $user->school_id) {
            abort(403, 'This section does not belong to your school.');
        }

        // Filter results to ONLY this section + same-school scope. The
        // whereHas hop ensures any future reuse of the query (or a leaked
        // section_id with mismatched school) won't surface foreign rows.
        $resultsBase = Result::where('exam_id', $exam->id)
            ->where('section_id', $section->id)
            ->whereHas('section.schoolClass', fn ($q) => $q->where('school_id', $section->schoolClass?->school_id));

        $results = (clone $resultsBase)
            ->with('student')
            ->orderBy('position')
            ->paginate(50);

        $total = (clone $resultsBase)->count();
        $passed = (clone $resultsBase)->where('is_passed', true)->count();

        $summary = [
            'total' => $total,
            'passed' => $passed,
            'failed' => $total - $passed,
            'passPercentage' => $total > 0 ? round($passed / $total * 100, 2) : 0,
            'avgPercentage' => round((clone $resultsBase)->avg('percentage') ?? 0, 2),
        ];

        // Get subjects for this exam/class
        $subjects = ExamSubject::where('exam_id', $exam->id)
            ->where('school_class_id', $section->school_class_id)
            ->with('subject')
            ->get()
            ->map(fn ($es) => [
                'id' => $es->subject_id,
                'code' => $es->subject?->code,
                'name' => $es->subject?->name,
                'total' => $es->total_marks,
            ]);

        // Re-index subject_results by subject_id for the Vue table
        $results->getCollection()->transform(function ($result) {
            $stored = $result->subject_results ?? [];
            $indexed = [];
            foreach ($stored as $sr) {
                $indexed[$sr['subject_id']] = [
                    'obtained' => $sr['effective_marks'] ?? $sr['marks_obtained'] ?? 0,
                    'total' => $sr['total_marks'] ?? 0,
                    'is_absent' => $sr['is_absent'] ?? false,
                    'failed' => !($sr['is_passed'] ?? true),
                ];
            }
            $result->subject_results = $indexed;
            return $result;
        });

        return Inertia::render('Results/Show', [
            'exam' => $exam,
            'section' => $section,
            'schoolClass' => $section->schoolClass,
            'results' => $results,
            'summary' => $summary,
            'subjects' => $subjects,
        ]);
    }

    public function submitToDdo(Exam $exam): RedirectResponse
    {
        $user = request()->user();

        if (!$user->isSchoolAdmin() && !$user->isSuperAdmin()) {
            abort(403, 'Only school admins can submit results to DDO.');
        }

        $schoolId = $user->school_id;

        $sectionIds = Result::where('exam_id', $exam->id)
            ->where('school_id', $schoolId)
            ->distinct()
            ->pluck('section_id');

        foreach ($sectionIds as $sectionId) {
            ResultSubmission::updateOrCreate(
                ['exam_id' => $exam->id, 'school_id' => $schoolId, 'section_id' => $sectionId],
                [
                    'school_class_id' => Section::find($sectionId)?->school_class_id,
                    'submitted_by' => $user->id,
                    'status' => 'submitted',
                    'submitted_at' => now(),
                ]
            );
        }

        Result::where('exam_id', $exam->id)
            ->where('school_id', $schoolId)
            ->update(['status' => 'submitted_to_ddo', 'submitted_by' => $user->id, 'submitted_at' => now()]);

        // Notify all super-admins (DDO) that a school's results need review
        try {
            $school = School::find($schoolId);
            if ($school) {
                $ddoUsers = User::whereHas('roles', fn ($q) => $q->where('name', 'super-admin'))->get();
                if ($ddoUsers->isNotEmpty()) {
                    Notification::send($ddoUsers, new ResultSubmittedToDdoNotification($exam, $school));
                }
            }
        } catch (\Throwable $e) {
            \Log::warning('ResultSubmittedToDdoNotification failed: ' . $e->getMessage());
        }

        return redirect()->back()->with('success', 'Results submitted to DDO successfully.');
    }

    public function finalize(Exam $exam, int $school): RedirectResponse
    {
        $user = request()->user();

        if (!$user->isSuperAdmin()) {
            abort(403, 'Only DDO can finalize results.');
        }

        ResultSubmission::where('exam_id', $exam->id)
            ->where('school_id', $school)
            ->where('status', 'submitted')
            ->update([
                'status' => 'approved',
                'reviewed_by' => $user->id,
                'reviewed_at' => now(),
            ]);

        Result::where('exam_id', $exam->id)
            ->where('school_id', $school)
            ->update([
                'status' => 'finalized',
                'finalized_by' => $user->id,
                'finalized_at' => now(),
            ]);

        return redirect()->back()->with('success', 'Results finalized successfully.');
    }

    /**
     * DDO Review Queue - lists all submitted result submissions awaiting review.
     */
    public function reviewQueue(Request $request): Response
    {
        $user = $request->user();

        if (!$user->isSuperAdmin()) {
            abort(403, 'Only DDO can access the review queue.');
        }

        $rawSubmissions = ResultSubmission::query()
            ->where('status', 'submitted')
            ->with([
                'exam:id,name',
                'school:id,name',
                'submittedBy:id,name',
            ])
            ->orderBy('submitted_at', 'desc')
            ->get();

        // Batch load sections to avoid N+1
        $sectionMap = Section::with('schoolClass')
            ->whereIn('id', $rawSubmissions->pluck('section_id'))
            ->get()
            ->keyBy('id');

        // Batch load result counts per (exam, school, section) triple
        $countsMap = Result::query()
            ->whereIn('exam_id', $rawSubmissions->pluck('exam_id'))
            ->whereIn('section_id', $rawSubmissions->pluck('section_id'))
            ->selectRaw('exam_id, school_id, section_id, COUNT(*) as total, SUM(is_passed) as passed')
            ->groupBy('exam_id', 'school_id', 'section_id')
            ->get()
            ->keyBy(fn ($r) => "{$r->exam_id}-{$r->school_id}-{$r->section_id}");

        $submissions = $rawSubmissions->map(function ($submission) use ($sectionMap, $countsMap) {
            $section = $sectionMap->get($submission->section_id);
            $key = "{$submission->exam_id}-{$submission->school_id}-{$submission->section_id}";
            $counts = $countsMap->get($key);
            $totalStudents = (int) ($counts->total ?? 0);
            $passCount = (int) ($counts->passed ?? 0);

            return [
                'id' => $submission->id,
                'exam_id' => $submission->exam_id,
                'exam_name' => $submission->exam?->name,
                'school_id' => $submission->school_id,
                'school_name' => $submission->school?->name,
                'school_class_id' => $submission->school_class_id,
                'section_id' => $submission->section_id,
                'class_name' => $section?->schoolClass?->name,
                'section_name' => $section?->name,
                'submitted_by' => $submission->submittedBy?->name,
                'submitted_at' => $submission->submitted_at?->toIso8601String(),
                'submitted_at_human' => $submission->submitted_at?->diffForHumans(),
                'total_students' => $totalStudents,
                'pass_count' => $passCount,
                'fail_count' => $totalStudents - $passCount,
                'view_url' => route('results.show', [$submission->exam_id, $submission->section_id]),
            ];
        });

        // Group by school name
        $grouped = $submissions->groupBy('school_name')->map(function ($items, $schoolName) {
            return [
                'school_name' => $schoolName,
                'school_id' => $items->first()['school_id'] ?? null,
                'submissions' => $items->values(),
            ];
        })->values();

        $today = now()->startOfDay();

        $stats = [
            'pending' => $submissions->count(),
            'approved_today' => ResultSubmission::where('status', 'approved')
                ->whereDate('reviewed_at', $today)
                ->count(),
            'returned' => ResultSubmission::where('status', 'rejected')->count(),
        ];

        return Inertia::render('Results/ReviewQueue', [
            'groups' => $grouped,
            'stats' => $stats,
        ]);
    }

    /**
     * DDO approves a submitted ResultSubmission.
     */
    public function approve(ResultSubmission $submission): RedirectResponse
    {
        $user = request()->user();

        if (!$user->isSuperAdmin()) {
            abort(403, 'Only DDO can approve results.');
        }

        $submission->update([
            'status' => 'approved',
            'reviewed_by' => $user->id,
            'reviewed_at' => now(),
        ]);

        Result::where('exam_id', $submission->exam_id)
            ->where('school_id', $submission->school_id)
            ->where('section_id', $submission->section_id)
            ->update([
                'status' => 'finalized',
                'finalized_by' => $user->id,
                'finalized_at' => now(),
            ]);

        try {
            $schoolAdmins = User::where('school_id', $submission->school_id)
                ->whereHas('roles', fn ($q) => $q->where('name', 'school-admin'))
                ->get();
            if ($schoolAdmins->isNotEmpty()) {
                Notification::send($schoolAdmins, new ResultApprovedNotification($submission->exam, $submission));
            }
        } catch (\Throwable $e) {
            \Log::warning('ResultApprovedNotification failed: ' . $e->getMessage());
        }

        return redirect()->back()->with('success', 'Result submission approved and finalized.');
    }

    /**
     * DDO returns a submitted ResultSubmission for correction.
     */
    public function returnForCorrection(Request $request, ResultSubmission $submission): RedirectResponse
    {
        $user = $request->user();

        if (!$user->isSuperAdmin()) {
            abort(403, 'Only DDO can return results for correction.');
        }

        $validated = $request->validate([
            'remarks' => ['required', 'string', 'min:3', 'max:2000'],
        ]);

        $submission->update([
            'status' => 'rejected',
            'remarks' => $validated['remarks'],
            'reviewed_by' => $user->id,
            'reviewed_at' => now(),
        ]);

        Result::where('exam_id', $submission->exam_id)
            ->where('school_id', $submission->school_id)
            ->where('section_id', $submission->section_id)
            ->update([
                'status' => 'generated',
            ]);

        try {
            $schoolAdmins = User::where('school_id', $submission->school_id)
                ->whereHas('roles', fn ($q) => $q->where('name', 'school-admin'))
                ->get();
            if ($schoolAdmins->isNotEmpty()) {
                Notification::send(
                    $schoolAdmins,
                    new ResultReturnedForCorrectionNotification($submission->exam, $submission, $validated['remarks'])
                );
            }
        } catch (\Throwable $e) {
            \Log::warning('ResultReturnedForCorrectionNotification failed: ' . $e->getMessage());
        }

        return redirect()->back()->with('warning', 'Result submission returned to school for correction.');
    }
}
