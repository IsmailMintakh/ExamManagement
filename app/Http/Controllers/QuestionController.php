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
     * Apply role-based scoping + UI "source" filter to a Question query.
     *
     * $source values (view filter — never widens authorization):
     *   - 'mine'    → only the user's own creations / school's questions
     *   - 'library' → only the DDO global pool (school_id IS NULL)
     *   - 'all'     → mine OR library
     *
     * Super-admin always sees everything regardless of $source — there is
     * no "library" for the DDO (everything is theirs).
     */
    protected function scopeForUser($query, $user, string $source = 'mine')
    {
        if ($user->isSuperAdmin()) {
            return $query;
        }

        // Identify what counts as "mine" vs "library" for this role.
        $ownClause = $user->isSchoolAdmin()
            ? fn ($q) => $q->where('school_id', $user->school_id)
            : fn ($q) => $q->where('created_by', $user->id);

        return match ($source) {
            'library' => $query->whereNull('school_id'),
            'all' => $query->where(function ($q) use ($ownClause) {
                $ownClause($q);
                $q->orWhereNull('school_id');
            }),
            default /* 'mine' */ => $query->where($ownClause),
        };
    }

    /**
     * Subjects + classes available to this user as filter / picker options.
     * Admins (super / school) see everything in scope. Teachers see ONLY
     * what they're assigned to teach via SubjectTeacher — otherwise the
     * Question Bank dropdowns leak every subject in the school, which the
     * user can't action anyway.
     */
    protected function pickerData($user): array
    {
        $isAdmin = $user->isSuperAdmin() || $user->isSchoolAdmin();
        if ($isAdmin) {
            return [
                'subjects' => Subject::active()->orderBy('name')->get(['id', 'name', 'code']),
                'classes'  => SchoolClass::query()
                    ->when(!$user->isSuperAdmin(), fn ($q) => $q->where('school_id', $user->school_id))
                    ->active()->ordered()->get(['id', 'name', 'school_id']),
            ];
        }
        $assignments = \App\Models\SubjectTeacher::where('user_id', $user->id)
            ->where('is_active', true)
            ->get(['subject_id', 'school_class_id']);
        return [
            'subjects' => Subject::active()
                ->whereIn('id', $assignments->pluck('subject_id')->unique())
                ->orderBy('name')
                ->get(['id', 'name', 'code']),
            'classes'  => SchoolClass::query()
                ->whereIn('id', $assignments->pluck('school_class_id')->unique())
                ->active()->ordered()
                ->get(['id', 'name', 'school_id']),
        ];
    }

    /**
     * Resolve the source param. Whitelisted values only — anything else
     * collapses to the default so users can't break the query with garbage.
     */
    protected function resolveSource(Request $request, string $default = 'mine'): string
    {
        $s = $request->input('source', $default);
        return in_array($s, ['mine', 'library', 'all'], true) ? $s : $default;
    }

    public function index(Request $request): Response
    {
        $user = $request->user();
        $source = $this->resolveSource($request, 'mine');

        $questions = Question::query()
            ->with(['subject:id,name,code', 'schoolClass:id,name', 'creator:id,name'])
            ->when(true, fn ($q) => $this->scopeForUser($q, $user, $source))
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

        // Stats reflect the active source so the count matches the visible list.
        $statsQuery = Question::query();
        $this->scopeForUser($statsQuery, $user, $source);
        $statsSnapshot = (clone $statsQuery)->selectRaw('type, COUNT(*) as total')
            ->groupBy('type')
            ->pluck('total', 'type')
            ->toArray();

        $totalCount = (clone $statsQuery)->count();

        // Pre-compute counts for the source toggle so the UI can show
        // "Mine (12) · Library (340) · All (352)" without extra requests.
        $sourceCounts = $user->isSuperAdmin()
            ? ['mine' => $totalCount, 'library' => 0, 'all' => $totalCount]
            : [
                'mine' => $this->scopeForUser(Question::query(), $user, 'mine')->count(),
                'library' => $this->scopeForUser(Question::query(), $user, 'library')->count(),
                'all' => $this->scopeForUser(Question::query(), $user, 'all')->count(),
            ];

        ['subjects' => $subjects, 'classes' => $classes] = $this->pickerData($user);

        return Inertia::render('Questions/Index', [
            'questions' => $questions,
            'subjects' => $subjects,
            'classes' => $classes,
            'filters' => array_merge(
                $request->only(['search', 'subject_id', 'school_class_id', 'type', 'difficulty', 'topic']),
                ['source' => $source]
            ),
            'sourceCounts' => $sourceCounts,
            'stats' => [
                'total' => $totalCount,
                'by_type' => $statsSnapshot,
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        $user = $request->user();

        ['subjects' => $subjects, 'classes' => $classes] = $this->pickerData($user);

        // Topic suggestions span the union of own + library so the autocomplete
        // is useful even when a teacher hasn't authored anything yet.
        $topics = Question::query()
            ->when(true, fn ($q) => $this->scopeForUser($q, $user, 'all'))
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

        $this->normalizeForType($request);
        $validated = $this->validateQuestion($request);
        $validated = $this->finalizeForType($validated);

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

        ['subjects' => $subjects, 'classes' => $classes] = $this->pickerData($user);

        // Topic suggestions span the union of own + library so the autocomplete
        // is useful even when a teacher hasn't authored anything yet.
        $topics = Question::query()
            ->when(true, fn ($q) => $this->scopeForUser($q, $user, 'all'))
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

        $this->normalizeForType($request);
        $validated = $this->validateQuestion($request, $question);
        $validated = $this->finalizeForType($validated);

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

        ['subjects' => $subjects, 'classes' => $classes] = $this->pickerData($user);

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

    /**
     * Strip / normalize fields based on the chosen question type BEFORE
     * validation runs — non-MCQ types should not carry the empty MCQ
     * option scaffolding the form sends by default.
     */
    protected function normalizeForType(Request $request): void
    {
        if ($request->input('school_class_id') === '') $request->merge(['school_class_id' => null]);
        if ($request->input('topic') === '') $request->merge(['topic' => null]);
        if ($request->input('explanation') === '') $request->merge(['explanation' => null]);

        $type = $request->input('type');

        // Only MCQ keeps the options array. Everything else clears it so the
        // `options.*.text required_with:options` rule never fires falsely.
        if ($type !== 'mcq') {
            $request->merge(['options' => null]);
        } else {
            // Drop completely-empty option rows the user may have left in.
            $opts = collect($request->input('options', []))
                ->filter(fn ($o) => trim((string) ($o['text'] ?? '')) !== '')
                ->values()
                ->all();
            $request->merge(['options' => $opts]);
        }
    }

    protected function validateQuestion(Request $request, ?Question $question = null): array
    {
        $type = $request->input('type');

        $rules = [
            'type' => ['required', 'in:mcq,short_answer,long_answer,true_false,fill_blank'],
            'subject_id' => ['required', 'exists:subjects,id'],
            'school_class_id' => ['nullable', 'exists:school_classes,id'],
            'difficulty' => ['required', 'in:easy,medium,hard'],
            'question_text' => ['required', 'string', 'min:3'],
            'explanation' => ['nullable', 'string'],
            'marks' => ['required', 'numeric', 'min:0.25', 'max:100'],
            'topic' => ['nullable', 'string', 'max:255'],
            'is_active' => ['nullable', 'boolean'],
            'school_id' => ['nullable', 'exists:schools,id'],
        ];

        if ($type === 'mcq') {
            // Options stay required (an MCQ without options doesn't make sense),
            // but marking a correct one is OPTIONAL — teachers can come back and
            // set the answer key later, or use the question for practice only.
            $rules['options'] = ['required', 'array', 'min:2', 'max:6'];
            $rules['options.*.text'] = ['required', 'string', 'max:500'];
            $rules['options.*.is_correct'] = ['nullable', 'boolean'];
            $rules['correct_answer'] = ['nullable', 'string'];
        } elseif ($type === 'true_false') {
            // Correct answer is OPTIONAL — when blank, the question is saved
            // without an answer key (useful for review-only or practice items).
            $rules['correct_answer'] = ['nullable', 'string', 'in:True,False,true,false,1,0,yes,no'];
            $rules['options'] = ['nullable'];
        } else { // short_answer, long_answer, fill_blank
            $rules['correct_answer'] = ['nullable', 'string', 'max:5000'];
            $rules['options'] = ['nullable'];
        }

        $messages = [
            'options.required' => 'Add at least 2 answer options for an MCQ.',
            'options.min' => 'MCQ must have at least 2 options.',
            'options.*.text.required' => 'Option text cannot be empty.',
        ];

        return $request->validate($rules, $messages);
    }

    /**
     * After validation, normalize the persisted shape — true_false stores
     * its options/correct_answer in a canonical way.
     */
    protected function finalizeForType(array $validated): array
    {
        if ($validated['type'] === 'true_false') {
            $raw = trim((string) ($validated['correct_answer'] ?? ''));
            if ($raw === '') {
                // No answer set — store options shell with neither marked correct.
                $validated['options'] = [
                    ['text' => 'True', 'is_correct' => false],
                    ['text' => 'False', 'is_correct' => false],
                ];
                $validated['correct_answer'] = null;
            } else {
                $isTrue = in_array(strtolower($raw), ['true', '1', 'yes', 't'], true);
                $validated['options'] = [
                    ['text' => 'True', 'is_correct' => $isTrue],
                    ['text' => 'False', 'is_correct' => !$isTrue],
                ];
                $validated['correct_answer'] = $isTrue ? 'True' : 'False';
            }
        }
        return $validated;
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
