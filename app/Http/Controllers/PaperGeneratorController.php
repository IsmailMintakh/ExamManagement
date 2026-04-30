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

    protected function scopeQuestionsForUser($query, $user)
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

        return $query->where(function ($q) use ($user) {
            $q->where('created_by', $user->id)
              ->orWhereNull('school_id');
        });
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

        $subjects = Subject::active()->orderBy('name')->get(['id', 'name', 'code']);
        $classes = SchoolClass::query()
            ->when(!$user->isSuperAdmin(), fn ($q) => $q->where('school_id', $user->school_id))
            ->active()->ordered()->get(['id', 'name', 'school_id']);

        return Inertia::render('Papers/Index', [
            'papers' => $papers,
            'subjects' => $subjects,
            'classes' => $classes,
            'filters' => $request->only(['search', 'subject_id', 'school_class_id']),
        ]);
    }

    public function create(Request $request): Response
    {
        $user = $request->user();

        $subjects = Subject::active()->orderBy('name')->get(['id', 'name', 'code']);
        $classes = SchoolClass::query()
            ->when(!$user->isSuperAdmin(), fn ($q) => $q->where('school_id', $user->school_id))
            ->active()->ordered()->get(['id', 'name', 'school_id']);

        $topics = Question::query()
            ->when(true, fn ($q) => $this->scopeQuestionsForUser($q, $user))
            ->whereNotNull('topic')
            ->distinct()
            ->pluck('topic')
            ->filter()
            ->values();

        return Inertia::render('Papers/Generate', [
            'subjects' => $subjects,
            'classes' => $classes,
            'topics' => $topics,
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
        ]);

        $query = Question::query()
            ->where('is_active', true)
            ->where('subject_id', $validated['subject_id']);

        $this->scopeQuestionsForUser($query, $user);

        if (!empty($validated['school_class_id'])) {
            $query->where(function ($q) use ($validated) {
                $q->where('school_class_id', $validated['school_class_id'])
                  ->orWhereNull('school_class_id');
            });
        }

        if (!empty($validated['topics'])) {
            $query->whereIn('topic', $validated['topics']);
        }

        $counts = $query->selectRaw('type, difficulty, COUNT(*) as total')
            ->groupBy('type', 'difficulty')
            ->get();

        $byType = [];
        foreach (['mcq', 'short_answer', 'long_answer', 'true_false', 'fill_blank'] as $t) {
            $byType[$t] = ['total' => 0, 'easy' => 0, 'medium' => 0, 'hard' => 0];
        }

        foreach ($counts as $row) {
            $byType[$row->type]['total'] += (int) $row->total;
            $byType[$row->type][$row->difficulty] = (int) $row->total;
        }

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
            'topics' => ['nullable', 'array'],
            'topics.*' => ['string'],
            'sections' => ['required', 'array', 'min:1'],
            'sections.*.label' => ['required', 'string'],
            'sections.*.type' => ['required', 'in:mcq,short_answer,long_answer,true_false,fill_blank'],
            'sections.*.difficulty' => ['nullable', 'in:easy,medium,hard,mixed'],
            'sections.*.count' => ['required', 'integer', 'min:1', 'max:200'],
            'sections.*.marks_each' => ['required', 'numeric', 'min:0.25', 'max:100'],
        ]);

        $shuffle = (bool) ($validated['shuffle'] ?? true);
        $topics = $validated['topics'] ?? [];

        $allQuestionIds = [];
        $outSections = [];
        $totalMarks = 0;

        foreach ($validated['sections'] as $section) {
            $query = Question::query()
                ->where('is_active', true)
                ->where('subject_id', $validated['subject_id'])
                ->where('type', $section['type']);

            $this->scopeQuestionsForUser($query, $user);

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
            foreach ($picked as $q) {
                $entry = [
                    'question_id' => $q->id,
                    'marks' => (float) $section['marks_each'],
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
            }

            $outSections[] = [
                'label' => $section['label'],
                'type' => $section['type'],
                'difficulty' => $section['difficulty'] ?? 'mixed',
                'count' => (int) $section['count'],
                'marks_each' => (float) $section['marks_each'],
                'questions' => $sectionQuestions,
            ];

            $totalMarks += $section['count'] * $section['marks_each'];
        }

        $paper = Paper::create([
            'school_id' => $user->isSuperAdmin() ? null : $user->school_id,
            'subject_id' => $validated['subject_id'],
            'school_class_id' => $validated['school_class_id'] ?? null,
            'generated_by' => $user->id,
            'title' => $validated['title'],
            'exam_name' => $validated['exam_name'] ?? null,
            'duration_minutes' => $validated['duration_minutes'],
            'total_marks' => $totalMarks,
            'instructions' => $validated['instructions'] ?? null,
            'sections' => $outSections,
            'question_ids' => $allQuestionIds,
            'shuffle_enabled' => $shuffle,
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

        $school = $paper->school ?: (object) [
            'name' => 'All Schools',
            'address' => '',
            'phone' => '',
            'logo' => null,
        ];

        $pdf = Pdf::loadView('reports.question-paper', [
            'paper' => $paper,
            'questions' => $questions,
            'school' => $school,
        ])->setPaper('a4', 'portrait');

        return $pdf->stream("paper-{$paper->id}-set-{$paper->set_code}.pdf");
    }

    public function downloadAnswerKey(Paper $paper): HttpResponse
    {
        $this->authorizePaper($paper, 'view');
        $paper->load(['subject', 'schoolClass', 'school']);

        $questions = Question::whereIn('id', $paper->question_ids ?? [])->get()->keyBy('id');

        $school = $paper->school ?: (object) [
            'name' => 'All Schools',
            'address' => '',
            'phone' => '',
            'logo' => null,
        ];

        $pdf = Pdf::loadView('reports.answer-key', [
            'paper' => $paper,
            'questions' => $questions,
            'school' => $school,
        ])->setPaper('a4', 'portrait');

        return $pdf->stream("answer-key-{$paper->id}-set-{$paper->set_code}.pdf");
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
            foreach ($picked as $q) {
                $entry = [
                    'question_id' => $q->id,
                    'marks' => (float) ($section['marks_each'] ?? 1),
                ];
                if ($q->type === 'mcq' && is_array($q->options)) {
                    $indices = array_keys($q->options);
                    if ($paper->shuffle_enabled) {
                        shuffle($indices);
                    }
                    $entry['option_order'] = $indices;
                }
                $sectionQuestions[] = $entry;
            }

            $outSections[] = [
                'label' => $section['label'] ?? 'Section',
                'type' => $section['type'],
                'difficulty' => $section['difficulty'] ?? 'mixed',
                'count' => (int) ($section['count'] ?? 0),
                'marks_each' => (float) ($section['marks_each'] ?? 1),
                'questions' => $sectionQuestions,
            ];
            $totalMarks += ($section['count'] ?? 0) * ($section['marks_each'] ?? 1);
        }

        $newPaper = Paper::create([
            'school_id' => $paper->school_id,
            'subject_id' => $paper->subject_id,
            'school_class_id' => $paper->school_class_id,
            'generated_by' => $user->id,
            'title' => $paper->title,
            'exam_name' => $paper->exam_name,
            'duration_minutes' => $paper->duration_minutes,
            'total_marks' => $totalMarks,
            'instructions' => $paper->instructions,
            'sections' => $outSections,
            'question_ids' => $allQuestionIds,
            'shuffle_enabled' => $paper->shuffle_enabled,
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
