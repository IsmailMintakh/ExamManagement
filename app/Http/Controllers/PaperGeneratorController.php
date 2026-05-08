<?php

namespace App\Http\Controllers;

use App\Models\Paper;
use App\Models\Question;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Subject;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Inertia\Inertia;
use Inertia\Response;

class PaperGeneratorController extends Controller
{
    /**
     * Role-based visibility scoping for Paper queries.
     */
    protected function scopePapersForUser($query, $user)
    {
        if ($user->isSuperAdmin()) {
            return $query;
        }

        if ($user->isSchoolAdmin()) {
            return $query->where(function ($q) use ($user) {
                $q->where('school_id', $user->school_id)
                  ->orWhereNull('school_id');
            });
        }

        return $query->where('generated_by', $user->id);
    }

    /**
     * Apply role-based scoping + UI "source" filter to the question pool used
     * during paper generation. See QuestionController::scopeForUser for the
     * canonical doc — same semantics here. Default for paper generation is
     * 'all' so teachers have the widest possible pool to draw from.
     */
    protected function scopeQuestionsForUser($query, $user, string $source = 'all')
    {
        if ($user->isSuperAdmin()) {
            return $query;
        }

        $ownClause = $user->isSchoolAdmin()
            ? fn ($q) => $q->where('school_id', $user->school_id)
            : fn ($q) => $q->where('created_by', $user->id);

        return match ($source) {
            'library' => $query->whereNull('school_id'),
            'mine' => $query->where($ownClause),
            default /* 'all' */ => $query->where(function ($q) use ($ownClause) {
                $ownClause($q);
                $q->orWhereNull('school_id');
            }),
        };
    }

    /**
     * Whitelist the source param. Anything off-list collapses to 'all'
     * (paper-gen default — wider pool is the right safe fallback here).
     */
    protected function resolveSource(Request $request, string $default = 'all'): string
    {
        $s = $request->input('source', $default);
        return in_array($s, ['mine', 'library', 'all'], true) ? $s : $default;
    }

    public function index(Request $request): Response
    {
        $user = $request->user();

        $papers = Paper::query()
            ->with(['subject:id,name,code', 'schoolClass:id,name', 'generator:id,name'])
            ->when(true, fn ($q) => $this->scopePapersForUser($q, $user))
            ->when($request->filled('subject_id'), fn ($q) => $q->where('subject_id', $request->input('subject_id')))
            ->when($request->filled('school_class_id'), fn ($q) => $q->where('school_class_id', $request->input('school_class_id')))
            ->when($request->filled('search'), function ($q) use ($request) {
                $s = $request->input('search');
                $q->where(function ($qq) use ($s) {
                    $qq->where('title', 'like', "%{$s}%")
                       ->orWhere('exam_name', 'like', "%{$s}%");
                });
            })
            ->latest()
            ->paginate(15)
            ->withQueryString();

        // Subject + class dropdowns reflect what the user can actually pick.
        // Subject-teachers (who aren't also school-admin) get narrowed to
        // only the subjects/classes they're assigned to teach.
        [$subjects, $classes] = $this->narrowedSubjectsAndClasses($user);

        return Inertia::render('Papers/Index', [
            'papers' => $papers,
            'subjects' => $subjects,
            'classes' => $classes,
            'filters' => $request->only(['search', 'subject_id', 'school_class_id']),
        ]);
    }

    /**
     * Resolve the subject + class dropdowns for paper generation, narrowed
     * by role. Returns [Collection $subjects, Collection $classes].
     *
     * - super-admin: all active subjects + (no school) all classes
     * - school-admin: all active subjects + classes in their school
     * - subject-teacher (not also admin): only subjects they teach AND
     *   classes they teach in those subjects (per subject_teachers pivot)
     * - class-teacher (not also admin/subject-teacher): subjects of their
     *   classes' assigned subject pool + their classes only
     */
    protected function narrowedSubjectsAndClasses($user): array
    {
        if ($user->isSuperAdmin() || $user->isSchoolAdmin()) {
            $subjects = Subject::active()->orderBy('name')->get(['id', 'name', 'code']);
            $classes = SchoolClass::query()
                ->when(!$user->isSuperAdmin(), fn ($q) => $q->where('school_id', $user->school_id))
                ->active()->ordered()->get(['id', 'name', 'school_id']);
            return [$subjects, $classes];
        }

        if ($user->isSubjectTeacher()) {
            $assignments = $user->subjectTeachings ?? collect();
            $subjectIds = $assignments->pluck('subject_id')->unique()->all();
            $classIds = $assignments->pluck('school_class_id')->unique()->all();
            $subjects = Subject::active()->whereIn('id', $subjectIds)->orderBy('name')->get(['id', 'name', 'code']);
            $classes = SchoolClass::active()->whereIn('id', $classIds)->ordered()->get(['id', 'name', 'school_id']);
            return [$subjects, $classes];
        }

        if ($user->isClassTeacher()) {
            $classIds = $user->classSections->pluck('school_class_id')->unique()->all();
            $classes = SchoolClass::active()->whereIn('id', $classIds)->ordered()->get(['id', 'name', 'school_id']);
            // Subjects: union of subjects assigned to the class-teacher's
            // classes via class_subjects pivot. Falls back to all subjects
            // if the class-subject pivot isn't seeded.
            $subjects = Subject::active()
                ->whereHas('classes', fn ($q) => $q->whereIn('school_classes.id', $classIds))
                ->orderBy('name')
                ->get(['id', 'name', 'code']);
            if ($subjects->isEmpty()) {
                $subjects = Subject::active()->orderBy('name')->get(['id', 'name', 'code']);
            }
            return [$subjects, $classes];
        }

        // Fallback: empty (no role-relevant scope).
        return [collect(), collect()];
    }

    public function create(Request $request): Response
    {
        $user = $request->user();

        [$subjects, $classes] = $this->narrowedSubjectsAndClasses($user);

        $topics = Question::query()
            ->when(true, fn ($q) => $this->scopeQuestionsForUser($q, $user, 'all'))
            ->whereNotNull('topic')
            ->distinct()
            ->pluck('topic')
            ->filter()
            ->values();

        // Pre-compute pool sizes for the source toggle so the UI can show
        // "Mine (12) · Library (340) · All (352)" without extra requests.
        $sourceCounts = $user->isSuperAdmin()
            ? null /* DDO sees everything; no toggle needed */
            : [
                'mine' => $this->scopeQuestionsForUser(Question::query()->where('is_active', true), $user, 'mine')->count(),
                'library' => $this->scopeQuestionsForUser(Question::query()->where('is_active', true), $user, 'library')->count(),
                'all' => $this->scopeQuestionsForUser(Question::query()->where('is_active', true), $user, 'all')->count(),
            ];

        return Inertia::render('Papers/Generate', [
            'subjects' => $subjects,
            'classes' => $classes,
            'topics' => $topics,
            'sourceCounts' => $sourceCounts,
            'defaultSource' => 'all',
        ]);
    }

    /**
     * AJAX: return question counts for current filter selection.
     */
    public function preview(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'subject_id' => ['required', 'exists:subjects,id'],
            'school_class_id' => ['nullable', 'exists:school_classes,id'],
            'topics' => ['nullable', 'array'],
            'topics.*' => ['string'],
            'source' => ['nullable', 'in:mine,library,all'],
        ]);

        $source = $this->resolveSource($request, 'all');

        $query = Question::query()
            ->where('is_active', true)
            ->where('subject_id', $validated['subject_id']);

        $this->scopeQuestionsForUser($query, $user, $source);

        if (!empty($validated['school_class_id'])) {
            $query->where(function ($q) use ($validated) {
                $q->where('school_class_id', $validated['school_class_id'])
                  ->orWhereNull('school_class_id');
            });
        }

        if (!empty($validated['topics'])) {
            $query->whereIn('topic', $validated['topics']);
        }

        $counts = $query->selectRaw('type, difficulty, COUNT(*) as total, AVG(marks) as avg_marks')
            ->groupBy('type', 'difficulty')
            ->get();

        $byType = [];
        foreach (['mcq', 'short_answer', 'long_answer', 'true_false', 'fill_blank'] as $t) {
            $byType[$t] = ['total' => 0, 'easy' => 0, 'medium' => 0, 'hard' => 0,
                            'avg_marks' => 0, '_marks_sum' => 0, '_marks_count' => 0];
        }

        foreach ($counts as $row) {
            $byType[$row->type]['total'] += (int) $row->total;
            $byType[$row->type][$row->difficulty] = (int) $row->total;
            // Weighted average across difficulties (sum-of-products / total)
            $byType[$row->type]['_marks_sum'] += (float) $row->avg_marks * (int) $row->total;
            $byType[$row->type]['_marks_count'] += (int) $row->total;
        }
        // Finalise the per-type average marks; drop the working columns.
        foreach ($byType as $t => &$data) {
            $data['avg_marks'] = $data['_marks_count'] > 0
                ? round($data['_marks_sum'] / $data['_marks_count'], 2)
                : 0;
            unset($data['_marks_sum'], $data['_marks_count']);
        }
        unset($data);

        return response()->json([
            'counts' => $byType,
            'total' => array_sum(array_column($byType, 'total')),
        ]);
    }

    public function generate(Request $request): RedirectResponse
    {
        $user = $request->user();

        // Normalize empty-string form inputs to null before validation.
        foreach (['school_class_id', 'exam_name', 'instructions', 'set_code'] as $field) {
            if ($request->input($field) === '') $request->merge([$field => null]);
        }

        $validated = $request->validate([
            'subject_id' => ['required', 'exists:subjects,id'],
            'school_class_id' => ['nullable', 'exists:school_classes,id'],
            'title' => ['required', 'string', 'max:255'],
            'exam_name' => ['nullable', 'string', 'max:255'],
            'duration_minutes' => ['required', 'integer', 'min:15', 'max:600'],
            'instructions' => ['nullable', 'string'],
            'set_code' => ['nullable', 'string', 'max:8'],
            'shuffle' => ['nullable', 'boolean'],
            'show_sections' => ['nullable', 'boolean'],
            'topics' => ['nullable', 'array'],
            'topics.*' => ['string'],
            'source' => ['nullable', 'in:mine,library,all'],
            'sections' => ['required', 'array', 'min:1'],
            'sections.*.label' => ['required', 'string'],
            'sections.*.type' => ['required', 'in:mcq,short_answer,long_answer,true_false,fill_blank'],
            'sections.*.difficulty' => ['nullable', 'in:easy,medium,hard,mixed'],
            'sections.*.count' => ['required', 'integer', 'min:1', 'max:200'],
            // marks_each is no longer required — each question carries its own
            // marks from the question bank. Kept nullable so existing UIs that
            // still send the field don't error.
            'sections.*.marks_each' => ['nullable', 'numeric', 'min:0', 'max:100'],
            // 0 = no writing space (just the question, like an MCQ); 1–20 = blank
            // ruled lines under each question — useful for primary classes
            // (Nursery / Prep) where students need lots of room to write.
            'sections.*.answer_lines' => ['nullable', 'integer', 'min:0', 'max:20'],
            // Per-section numbering: arabic (1, 2, 3) | roman (I, II, III) |
            // alpha_upper (A, B, C) | alpha_lower (a, b, c).
            'sections.*.numbering_style' => ['nullable', 'in:arabic,roman,alpha_upper,alpha_lower'],
            // When true, the question counter resets to 1 at the start of this section.
            'sections.*.restart_numbering' => ['nullable', 'boolean'],
        ]);

        $shuffle = (bool) ($validated['shuffle'] ?? true);
        $topics = $validated['topics'] ?? [];
        $source = $this->resolveSource($request, 'all');

        $allQuestionIds = [];
        $outSections = [];
        $totalMarks = 0;

        foreach ($validated['sections'] as $section) {
            $query = Question::query()
                ->where('is_active', true)
                ->where('subject_id', $validated['subject_id'])
                ->where('type', $section['type']);

            $this->scopeQuestionsForUser($query, $user, $source);

            if (!empty($validated['school_class_id'])) {
                $query->where(function ($q) use ($validated) {
                    $q->where('school_class_id', $validated['school_class_id'])
                      ->orWhereNull('school_class_id');
                });
            }

            if (!empty($section['difficulty']) && $section['difficulty'] !== 'mixed') {
                $query->where('difficulty', $section['difficulty']);
            }

            if (!empty($topics)) {
                $query->whereIn('topic', $topics);
            }

            // Avoid reusing questions across sections of the same paper
            if (!empty($allQuestionIds)) {
                $query->whereNotIn('id', $allQuestionIds);
            }

            $available = (clone $query)->count();
            if ($available < $section['count']) {
                return redirect()->back()
                    ->withInput()
                    ->with('error', sprintf(
                        'Section "%s" needs %d questions but only %d available.',
                        $section['label'],
                        $section['count'],
                        $available
                    ));
            }

            $picked = $query->inRandomOrder()->limit($section['count'])->get();
            $pickedIds = $picked->pluck('id')->all();
            $allQuestionIds = array_merge($allQuestionIds, $pickedIds);

            $sectionQuestions = [];
            $sectionTotalMarks = 0.0;
            foreach ($picked as $q) {
                // Use the QUESTION's own marks (set when the question was created),
                // not the section's marks_each default. This is the source of
                // truth — overriding it would silently change every printed and
                // result-affecting paper into a different total than the bank says.
                $qMarks = (float) ($q->marks ?? $section['marks_each'] ?? 1);

                $entry = [
                    'question_id' => $q->id,
                    'marks' => $qMarks,
                ];

                // Store an option ordering for MCQ (enables shuffling w/o losing correctness tracking)
                if ($q->type === 'mcq' && is_array($q->options)) {
                    $indices = array_keys($q->options);
                    if ($shuffle) {
                        shuffle($indices);
                    }
                    $entry['option_order'] = $indices;
                }

                $sectionQuestions[] = $entry;
                $sectionTotalMarks += $qMarks;
            }

            $outSections[] = [
                'label' => $section['label'],
                'type' => $section['type'],
                'difficulty' => $section['difficulty'] ?? 'mixed',
                'count' => (int) $section['count'],
                'section_total_marks' => round($sectionTotalMarks, 2),
                'answer_lines' => isset($section['answer_lines']) ? (int) $section['answer_lines'] : null,
                'numbering_style' => $section['numbering_style'] ?? 'arabic',
                'restart_numbering' => (bool) ($section['restart_numbering'] ?? false),
                'questions' => $sectionQuestions,
            ];

            $totalMarks += $sectionTotalMarks;
        }

        $paper = Paper::create([
            'school_id' => $user->isSuperAdmin() ? null : $user->school_id,
            'subject_id' => $validated['subject_id'],
            'school_class_id' => $validated['school_class_id'] ?? null,
            'generated_by' => $user->id,
            'title' => $validated['title'],
            'exam_name' => $validated['exam_name'] ?? null,
            'duration_minutes' => $validated['duration_minutes'],
            'total_marks' => round($totalMarks, 2),
            'instructions' => $validated['instructions'] ?? null,
            'sections' => $outSections,
            'question_ids' => $allQuestionIds,
            'shuffle_enabled' => $shuffle,
            'show_sections' => (bool) ($validated['show_sections'] ?? true),
            'set_code' => $validated['set_code'] ?? 'A',
        ]);

        // Increment usage counter
        if (!empty($allQuestionIds)) {
            Question::whereIn('id', $allQuestionIds)->increment('times_used');
        }

        return redirect()->route('papers.show', $paper->id)
            ->with('success', 'Paper generated successfully.');
    }

    public function show(Paper $paper): Response
    {
        $this->authorizePaper($paper, 'view');
        $paper->load(['subject', 'schoolClass', 'generator', 'school']);

        $questionIds = $paper->question_ids ?? [];
        $questions = Question::whereIn('id', $questionIds)->get()->keyBy('id');

        return Inertia::render('Papers/Show', [
            'paper' => $paper,
            'questions' => $questions,
        ]);
    }

    public function downloadPaper(Paper $paper): HttpResponse
    {
        $this->authorizePaper($paper, 'view');
        $paper->load(['subject', 'schoolClass', 'school']);

        $questions = Question::whereIn('id', $paper->question_ids ?? [])->get()->keyBy('id');

        // Slips per page: 1 (default A4 portrait) or 2 (A5 side-by-side on landscape).
        // 2-up cuts paper use in half — print N/2 sheets and snip down the middle
        // dashed line. 3-up was tried but produces unreadable 6.5pt text and
        // routinely overflows; it's been removed.
        $slips = (int) request()->query('slips', 1);
        if (!in_array($slips, [1, 2], true)) $slips = 1;

        $orientation = $slips === 2 ? 'landscape' : 'portrait';

        $pdf = Pdf::loadView('reports.question-paper', [
            'paper' => $paper,
            'questions' => $questions,
            'school' => $this->resolveSchool($paper),
            'slipsPerPage' => $slips,
        ])->setPaper('a4', $orientation);

        return $pdf->stream("paper-{$paper->id}-set-{$paper->set_code}.pdf");
    }

    public function downloadAnswerKey(Paper $paper): HttpResponse
    {
        $this->authorizePaper($paper, 'view');
        $paper->load(['subject', 'schoolClass', 'school']);

        $questions = Question::whereIn('id', $paper->question_ids ?? [])->get()->keyBy('id');

        $pdf = Pdf::loadView('reports.answer-key', [
            'paper' => $paper,
            'questions' => $questions,
            'school' => $this->resolveSchool($paper),
        ])->setPaper('a4', 'portrait');

        return $pdf->stream("answer-key-{$paper->id}-set-{$paper->set_code}.pdf");
    }

    /**
     * Resolve which school's name + logo to print on the paper header.
     *
     * Priority: paper's own school → current user's school → schoolClass's
     * school → generic placeholder. We never want "All Schools" on a printed
     * paper that's actually being given to one specific school's students.
     */
    protected function resolveSchool(Paper $paper)
    {
        if ($paper->school) {
            return $paper->school;
        }
        $user = request()->user();
        if ($user && $user->school) {
            return $user->school;
        }
        if ($paper->schoolClass && $paper->schoolClass->school) {
            return $paper->schoolClass->school;
        }
        return (object) [
            'name' => config('app.name', 'School'),
            'address' => '',
            'phone' => '',
            'logo' => null,
        ];
    }

    public function regenerate(Request $request, Paper $paper): RedirectResponse
    {
        $this->authorizePaper($paper, 'edit');
        $user = $request->user();

        $sections = $paper->sections ?? [];
        $existingIds = $paper->question_ids ?? [];

        $nextSet = $this->nextSetCode($paper->set_code);

        $allQuestionIds = [];
        $outSections = [];
        $totalMarks = 0;

        foreach ($sections as $section) {
            $query = Question::query()
                ->where('is_active', true)
                ->where('subject_id', $paper->subject_id)
                ->where('type', $section['type'])
                ->whereNotIn('id', array_merge($existingIds, $allQuestionIds));

            $this->scopeQuestionsForUser($query, $user);

            if (!empty($paper->school_class_id)) {
                $query->where(function ($q) use ($paper) {
                    $q->where('school_class_id', $paper->school_class_id)
                      ->orWhereNull('school_class_id');
                });
            }

            if (!empty($section['difficulty']) && $section['difficulty'] !== 'mixed') {
                $query->where('difficulty', $section['difficulty']);
            }

            $available = (clone $query)->count();
            if ($available < ($section['count'] ?? 0)) {
                return redirect()->back()->with('error', sprintf(
                    'Not enough fresh questions for section "%s" (need %d, have %d). Add more questions to the bank.',
                    $section['label'] ?? 'Unknown',
                    $section['count'] ?? 0,
                    $available
                ));
            }

            $picked = $query->inRandomOrder()->limit($section['count'])->get();
            $pickedIds = $picked->pluck('id')->all();
            $allQuestionIds = array_merge($allQuestionIds, $pickedIds);

            $sectionQuestions = [];
            $sectionTotalMarks = 0.0;
            foreach ($picked as $q) {
                // Use the question's own marks (set when created) — never the
                // section default. Same fix as the original generate() path.
                $qMarks = (float) ($q->marks ?? $section['marks_each'] ?? 1);
                $entry = [
                    'question_id' => $q->id,
                    'marks' => $qMarks,
                ];
                if ($q->type === 'mcq' && is_array($q->options)) {
                    $indices = array_keys($q->options);
                    if ($paper->shuffle_enabled) {
                        shuffle($indices);
                    }
                    $entry['option_order'] = $indices;
                }
                $sectionQuestions[] = $entry;
                $sectionTotalMarks += $qMarks;
            }

            $outSections[] = [
                'label' => $section['label'] ?? 'Section',
                'type' => $section['type'],
                'difficulty' => $section['difficulty'] ?? 'mixed',
                'count' => (int) ($section['count'] ?? 0),
                'section_total_marks' => round($sectionTotalMarks, 2),
                'answer_lines' => $section['answer_lines'] ?? null,
                'numbering_style' => $section['numbering_style'] ?? 'arabic',
                'restart_numbering' => (bool) ($section['restart_numbering'] ?? false),
                'questions' => $sectionQuestions,
            ];
            $totalMarks += $sectionTotalMarks;
        }

        $newPaper = Paper::create([
            'school_id' => $paper->school_id,
            'subject_id' => $paper->subject_id,
            'school_class_id' => $paper->school_class_id,
            'generated_by' => $user->id,
            'title' => $paper->title,
            'exam_name' => $paper->exam_name,
            'duration_minutes' => $paper->duration_minutes,
            'total_marks' => round($totalMarks, 2),
            'instructions' => $paper->instructions,
            'sections' => $outSections,
            'question_ids' => $allQuestionIds,
            'shuffle_enabled' => $paper->shuffle_enabled,
            'show_sections' => $paper->show_sections,
            'set_code' => $nextSet,
        ]);

        if (!empty($allQuestionIds)) {
            Question::whereIn('id', $allQuestionIds)->increment('times_used');
        }

        return redirect()->route('papers.show', $newPaper->id)
            ->with('success', "New set '{$nextSet}' generated.");
    }

    public function destroy(Paper $paper): RedirectResponse
    {
        $this->authorizePaper($paper, 'delete');
        $paper->delete();

        return redirect()->route('papers.index')->with('success', 'Paper removed.');
    }

    // ---------------- Helpers ----------------

    protected function nextSetCode(string $current): string
    {
        $current = strtoupper(trim($current));
        if ($current === '' || !ctype_alpha($current)) {
            return 'B';
        }

        $last = $current[strlen($current) - 1];
        if ($last === 'Z') {
            return $current . 'A';
        }

        return substr($current, 0, -1) . chr(ord($last) + 1);
    }

    protected function authorizePaper(Paper $paper, string $action): void
    {
        $user = request()->user();
        if (!$user) {
            abort(403);
        }

        if ($user->isSuperAdmin()) {
            return;
        }

        if ($user->isSchoolAdmin()) {
            if ($paper->school_id === null || $paper->school_id === $user->school_id) {
                return;
            }
            abort(403);
        }

        if ($paper->generated_by === $user->id) {
            return;
        }

        abort(403);
    }
}
