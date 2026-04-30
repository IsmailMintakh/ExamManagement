<?php

namespace App\Http\Controllers;

use App\Models\Question;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Subject;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class QuestionController extends Controller
{
    /**
     * Apply role-based scoping to a Question query.
     */
    protected function scopeForUser($query, $user)
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

        // Subject teacher / class teacher: own creations OR unscoped questions.
        return $query->where(function ($q) use ($user) {
            $q->where('created_by', $user->id)
              ->orWhereNull('school_id');
        });
    }

    public function index(Request $request): Response
    {
        $user = $request->user();

        $questions = Question::query()
            ->with(['subject:id,name,code', 'schoolClass:id,name', 'creator:id,name'])
            ->when(true, fn ($q) => $this->scopeForUser($q, $user))
            ->when($request->filled('subject_id'), fn ($q) => $q->where('subject_id', $request->input('subject_id')))
            ->when($request->filled('school_class_id'), fn ($q) => $q->where('school_class_id', $request->input('school_class_id')))
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->input('type')))
            ->when($request->filled('difficulty'), fn ($q) => $q->where('difficulty', $request->input('difficulty')))
            ->when($request->filled('topic'), fn ($q) => $q->where('topic', 'like', '%' . $request->input('topic') . '%'))
            ->when($request->filled('search'), function ($q) use ($request) {
                $search = $request->input('search');
                $q->where('question_text', 'like', "%{$search}%");
            })
            ->latest()
            ->paginate(20)
            ->withQueryString();

        // Stats snapshot (for current filter scope)
        $statsQuery = Question::query();
        $this->scopeForUser($statsQuery, $user);
        $statsSnapshot = $statsQuery->selectRaw('type, COUNT(*) as total')
            ->groupBy('type')
            ->pluck('total', 'type')
            ->toArray();

        $totalCount = (clone $statsQuery)->count();

        $subjects = Subject::active()->orderBy('name')->get(['id', 'name', 'code']);
        $classes = SchoolClass::query()
            ->when(!$user->isSuperAdmin(), fn ($q) => $q->where('school_id', $user->school_id))
            ->active()->ordered()->get(['id', 'name', 'school_id']);

        return Inertia::render('Questions/Index', [
            'questions' => $questions,
            'subjects' => $subjects,
            'classes' => $classes,
            'filters' => $request->only(['search', 'subject_id', 'school_class_id', 'type', 'difficulty', 'topic']),
            'stats' => [
                'total' => $totalCount,
                'by_type' => $statsSnapshot,
            ],
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
            ->when(true, fn ($q) => $this->scopeForUser($q, $user))
            ->whereNotNull('topic')
            ->distinct()
            ->pluck('topic')
            ->filter()
            ->values();

        return Inertia::render('Questions/Create', [
            'subjects' => $subjects,
            'classes' => $classes,
            'topics' => $topics,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();

        // Normalize empty strings -> null before validation
        if ($request->input('school_class_id') === '') $request->merge(['school_class_id' => null]);
        if ($request->input('topic') === '') $request->merge(['topic' => null]);
        if ($request->input('explanation') === '') $request->merge(['explanation' => null]);

        $validated = $this->validateQuestion($request);

        // Normalise options for true_false
        if ($validated['type'] === 'true_false') {
            $correct = strtolower((string) ($validated['correct_answer'] ?? ''));
            $trueCorrect = in_array($correct, ['true', '1', 'yes', 't'], true);
            $validated['options'] = [
                ['text' => 'True', 'is_correct' => $trueCorrect],
                ['text' => 'False', 'is_correct' => !$trueCorrect],
            ];
            $validated['correct_answer'] = $trueCorrect ? 'True' : 'False';
        }

        // MCQ: validate exactly one correct option
        if ($validated['type'] === 'mcq') {
            $options = $validated['options'] ?? [];
            $correctCount = collect($options)->filter(fn ($o) => !empty($o['is_correct']))->count();
            if (count($options) < 2 || $correctCount !== 1) {
                return redirect()->back()
                    ->withInput()
                    ->withErrors(['options' => 'MCQ must have at least 2 options with exactly one marked correct.']);
            }
        }

        $validated['created_by'] = $user->id;
        $validated['school_id'] = $user->isSuperAdmin() ? ($validated['school_id'] ?? null) : $user->school_id;

        Question::create($validated);

        return redirect()->route('questions.index')->with('success', 'Question added to the bank.');
    }

    public function show(Question $question): Response
    {
        $this->authorizeAccess($question, 'view');
        $question->load(['subject', 'schoolClass', 'creator']);

        return Inertia::render('Questions/Show', [
            'question' => $question,
        ]);
    }

    public function edit(Request $request, Question $question): Response
    {
        $this->authorizeAccess($question, 'edit');
        $user = $request->user();

        $subjects = Subject::active()->orderBy('name')->get(['id', 'name', 'code']);
        $classes = SchoolClass::query()
            ->when(!$user->isSuperAdmin(), fn ($q) => $q->where('school_id', $user->school_id))
            ->active()->ordered()->get(['id', 'name', 'school_id']);

        $topics = Question::query()
            ->when(true, fn ($q) => $this->scopeForUser($q, $user))
            ->whereNotNull('topic')
            ->distinct()
            ->pluck('topic')
            ->filter()
            ->values();

        return Inertia::render('Questions/Create', [
            'question' => $question,
            'subjects' => $subjects,
            'classes' => $classes,
            'topics' => $topics,
        ]);
    }

    public function update(Request $request, Question $question): RedirectResponse
    {
        $this->authorizeAccess($question, 'edit');

        // Normalize empty strings -> null before validation
        if ($request->input('school_class_id') === '') $request->merge(['school_class_id' => null]);
        if ($request->input('topic') === '') $request->merge(['topic' => null]);
        if ($request->input('explanation') === '') $request->merge(['explanation' => null]);

        $validated = $this->validateQuestion($request, $question);

        if ($validated['type'] === 'true_false') {
            $correct = strtolower((string) ($validated['correct_answer'] ?? ''));
            $trueCorrect = in_array($correct, ['true', '1', 'yes', 't'], true);
            $validated['options'] = [
                ['text' => 'True', 'is_correct' => $trueCorrect],
                ['text' => 'False', 'is_correct' => !$trueCorrect],
            ];
            $validated['correct_answer'] = $trueCorrect ? 'True' : 'False';
        }

        if ($validated['type'] === 'mcq') {
            $options = $validated['options'] ?? [];
            $correctCount = collect($options)->filter(fn ($o) => !empty($o['is_correct']))->count();
            if (count($options) < 2 || $correctCount !== 1) {
                return redirect()->back()
                    ->withInput()
                    ->withErrors(['options' => 'MCQ must have at least 2 options with exactly one marked correct.']);
            }
        }

        $question->update($validated);

        return redirect()->route('questions.index')->with('success', 'Question updated.');
    }

    public function destroy(Question $question): RedirectResponse
    {
        $this->authorizeAccess($question, 'delete');
        $question->delete();

        return redirect()->route('questions.index')->with('success', 'Question removed.');
    }

    // ---------------- Bulk Import ----------------

    public function bulkImport(Request $request): Response
    {
        $user = $request->user();

        $subjects = Subject::active()->orderBy('name')->get(['id', 'name', 'code']);
        $classes = SchoolClass::query()
            ->when(!$user->isSuperAdmin(), fn ($q) => $q->where('school_id', $user->school_id))
            ->active()->ordered()->get(['id', 'name', 'school_id']);

        return Inertia::render('Questions/Import', [
            'subjects' => $subjects,
            'classes' => $classes,
        ]);
    }

    public function processImport(Request $request): RedirectResponse
    {
        $user = $request->user();

        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:5120'],
            'school_class_id' => ['nullable', 'exists:school_classes,id'],
        ]);

        $file = $request->file('file');
        $handle = fopen($file->getRealPath(), 'r');
        if (!$handle) {
            return redirect()->back()->with('error', 'Unable to open CSV file.');
        }

        $header = fgetcsv($handle);
        if (!$header) {
            fclose($handle);
            return redirect()->back()->with('error', 'CSV is empty.');
        }

        $header = array_map(fn ($h) => strtolower(trim((string) $h)), $header);

        $subjectsByCode = Subject::pluck('id', 'code')->map(fn ($id) => (int) $id);
        $subjectsByName = Subject::pluck('id', 'name')->map(fn ($id) => (int) $id);

        $created = 0;
        $errors = [];
        $row = 1;

        while (($data = fgetcsv($handle)) !== false) {
            $row++;
            if (count(array_filter($data)) === 0) {
                continue;
            }

            $record = array_combine($header, array_pad($data, count($header), null));

            try {
                $type = strtolower(trim((string) ($record['type'] ?? 'mcq')));
                if (!in_array($type, ['mcq', 'short_answer', 'long_answer', 'true_false', 'fill_blank'], true)) {
                    $errors[] = "Row {$row}: invalid type '{$type}'";
                    continue;
                }

                $code = trim((string) ($record['subject_code'] ?? ''));
                $subjectId = $subjectsByCode->get($code) ?? $subjectsByName->get($code);
                if (!$subjectId) {
                    $errors[] = "Row {$row}: subject '{$code}' not found";
                    continue;
                }

                $difficulty = strtolower(trim((string) ($record['difficulty'] ?? 'medium')));
                if (!in_array($difficulty, ['easy', 'medium', 'hard'], true)) {
                    $difficulty = 'medium';
                }

                $questionText = trim((string) ($record['question_text'] ?? ''));
                if ($questionText === '') {
                    $errors[] = "Row {$row}: empty question_text";
                    continue;
                }

                $marks = is_numeric($record['marks'] ?? null) ? (float) $record['marks'] : 1;
                $topic = trim((string) ($record['topic'] ?? '')) ?: null;

                $options = null;
                $correctAnswer = trim((string) ($record['correct_answer'] ?? '')) ?: null;

                if ($type === 'mcq') {
                    $rawOpts = [];
                    for ($i = 1; $i <= 6; $i++) {
                        $key = "option_{$i}";
                        if (!empty($record[$key])) {
                            $rawOpts[] = trim((string) $record[$key]);
                        }
                    }
                    if (count($rawOpts) < 2) {
                        $errors[] = "Row {$row}: MCQ needs at least 2 options";
                        continue;
                    }
                    $correctIndex = (int) ($record['correct_option_index'] ?? 1) - 1;
                    if ($correctIndex < 0 || $correctIndex >= count($rawOpts)) {
                        $errors[] = "Row {$row}: invalid correct_option_index";
                        continue;
                    }
                    $options = [];
                    foreach ($rawOpts as $idx => $text) {
                        $options[] = ['text' => $text, 'is_correct' => $idx === $correctIndex];
                    }
                    $correctAnswer = $rawOpts[$correctIndex];
                } elseif ($type === 'true_false') {
                    $tc = strtolower((string) $correctAnswer);
                    $isTrue = in_array($tc, ['true', '1', 'yes', 't'], true);
                    $options = [
                        ['text' => 'True', 'is_correct' => $isTrue],
                        ['text' => 'False', 'is_correct' => !$isTrue],
                    ];
                    $correctAnswer = $isTrue ? 'True' : 'False';
                } else {
                    if (!$correctAnswer) {
                        $errors[] = "Row {$row}: correct_answer required for type {$type}";
                        continue;
                    }
                }

                Question::create([
                    'school_id' => $user->isSuperAdmin() ? null : $user->school_id,
                    'subject_id' => $subjectId,
                    'school_class_id' => $request->input('school_class_id'),
                    'created_by' => $user->id,
                    'type' => $type,
                    'difficulty' => $difficulty,
                    'question_text' => $questionText,
                    'options' => $options,
                    'correct_answer' => $correctAnswer,
                    'marks' => $marks,
                    'topic' => $topic,
                    'is_active' => true,
                ]);
                $created++;
            } catch (\Throwable $e) {
                $errors[] = "Row {$row}: " . $e->getMessage();
            }
        }

        fclose($handle);

        $msg = "{$created} questions imported.";
        if (!empty($errors)) {
            $msg .= ' Errors: ' . implode(' | ', array_slice($errors, 0, 10));
        }

        return redirect()->route('questions.index')->with($created > 0 ? 'success' : 'warning', $msg);
    }

    // ---------------- Helpers ----------------

    protected function validateQuestion(Request $request, ?Question $question = null): array
    {
        return $request->validate([
            'type' => ['required', 'in:mcq,short_answer,long_answer,true_false,fill_blank'],
            'subject_id' => ['required', 'exists:subjects,id'],
            'school_class_id' => ['nullable', 'exists:school_classes,id'],
            'difficulty' => ['required', 'in:easy,medium,hard'],
            'question_text' => ['required', 'string', 'min:3'],
            'options' => ['nullable', 'array'],
            'options.*.text' => ['required_with:options', 'string'],
            'options.*.is_correct' => ['nullable', 'boolean'],
            'correct_answer' => ['nullable', 'string'],
            'explanation' => ['nullable', 'string'],
            'marks' => ['required', 'numeric', 'min:0.25', 'max:100'],
            'topic' => ['nullable', 'string', 'max:255'],
            'is_active' => ['nullable', 'boolean'],
            'school_id' => ['nullable', 'exists:schools,id'],
        ]);
    }

    /**
     * Authorize view/edit/delete on a Question.
     */
    protected function authorizeAccess(Question $question, string $action): void
    {
        $user = Auth::user();
        if (!$user) {
            abort(403);
        }

        if ($user->isSuperAdmin()) {
            return;
        }

        if ($action === 'view') {
            if ($question->school_id === null
                || $question->school_id === $user->school_id
                || $question->created_by === $user->id) {
                return;
            }
            abort(403);
        }

        // edit/delete
        if ($question->created_by === $user->id) {
            return;
        }
        if ($user->isSchoolAdmin() && $question->school_id === $user->school_id) {
            return;
        }

        abort(403);
    }
}
