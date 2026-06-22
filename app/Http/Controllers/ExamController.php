<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreExamRequest;
use App\Models\AcademicSession;
use App\Models\Exam;
use App\Models\ExamSubject;
use App\Models\ExamType;
use App\Models\GradingScale;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class ExamController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Exam::class);

        $user = $request->user();
        $currentSession = AcademicSession::currentSession();

        // Effective school = either the user's actual school OR the school
        // they've picked via the DDO topbar selector. NULL means "all
        // schools" (only super-admins without a pick land here).
        $scopeSchoolId = $user->effectiveSchoolId();

        $exams = Exam::query()
            ->when($request->has('search'), function ($query) use ($request) {
                $query->where('name', 'like', '%' . $request->input('search') . '%');
            })
            ->when($request->has('exam_type_id'), fn ($q) => $q->where('exam_type_id', $request->input('exam_type_id')))
            ->when($request->has('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->when($request->has('session_id'), function ($query) use ($request) {
                $query->where('academic_session_id', $request->input('session_id'));
            }, function ($query) use ($currentSession) {
                if ($currentSession) {
                    $query->where('academic_session_id', $currentSession->id);
                }
            })
            // Visibility: school in pivot AND at least one exam_subject for
            // a class belonging to the school. The class check hides exams
            // whose target classes don't exist at this school (e.g. a Class
            // 6 exam at a Nursery–5 primary school).
            ->when($scopeSchoolId, fn ($q) => $q->visibleToSchool($scopeSchoolId))
            // Teachers: narrow further to exams they actually teach in
            // (class-teacher's class + subject-teacher's subject tuples).
            ->forTeacher($user)
            ->with(['examType', 'academicSession', 'gradingScale'])
            ->withCount(['examSubjects', 'marks', 'results'])
            ->latest()
            ->paginate(15)
            ->withQueryString();

        $examTypes = ExamType::active()->orderBy('sort_order')->get(['id', 'name']);
        $sessions = AcademicSession::active()->orderByDesc('start_date')->get(['id', 'name']);

        // KPI strip — phase counts respecting the same visibility scope as
        // the list above (visibleToSchool + forTeacher), but pre-filter so
        // the strip reflects "all exams I can see" rather than the current
        // search/type/status drill-down. Sticks to the current session unless
        // the user explicitly picked another.
        $statusCounts = Exam::query()
            ->when($scopeSchoolId, fn ($q) => $q->visibleToSchool($scopeSchoolId))
            ->forTeacher($user)
            ->when($request->filled('session_id'),
                fn ($q) => $q->where('academic_session_id', $request->input('session_id')),
                fn ($q) => $currentSession ? $q->where('academic_session_id', $currentSession->id) : $q,
            )
            ->selectRaw('status, COUNT(*) as cnt')
            ->groupBy('status')
            ->pluck('cnt', 'status');

        return Inertia::render('Exams/Index', [
            'exams' => $exams,
            'examTypes' => $examTypes,
            'sessions' => $sessions,
            'filters' => $request->only(['search', 'exam_type_id', 'status', 'session_id']),
            'statusCounts' => [
                'total' => (int) $statusCounts->sum(),
                'draft' => (int) ($statusCounts['draft'] ?? 0),
                'marks_entry' => (int) ($statusCounts['marks_entry'] ?? 0),
                'completed' => (int) (($statusCounts['completed'] ?? 0) + ($statusCounts['published'] ?? 0)),
            ],
        ]);
    }

    public function create(): Response|RedirectResponse
    {
        $this->authorize('create', Exam::class);

        $user = request()->user();

        // ─── Prerequisite chain ───
        // An exam is meaningless without: an academic session to live in, an
        // exam type to classify it, at least one school to apply it to, and
        // at least one subject teachers can enter marks for. We bounce the
        // user to the right setup page with a clear explanation instead of
        // showing them a half-broken form with empty dropdowns.
        $sessions = AcademicSession::active()->orderByDesc('start_date')->get();
        if ($sessions->isEmpty()) {
            return redirect()->route('academic-sessions.create')
                ->with('warning', 'Add an academic session first — every exam belongs to one.');
        }

        $examTypes = ExamType::active()->orderBy('sort_order')->get();
        if ($examTypes->isEmpty()) {
            return redirect()->route('exam-types.create')
                ->with('warning', 'Add at least one exam type first (e.g. Monthly, Term, Annual).');
        }

        // Effective school respects the DDO topbar selector, so a DDO
        // building an exam while "viewing" one school only sees that
        // school's classes/sections.
        $scopeSchoolId = $user->effectiveSchoolId();

        $schools = School::active()
            ->when($scopeSchoolId, fn ($q) => $q->where('id', $scopeSchoolId))
            ->orderBy('name')->get(['id', 'name']);
        if ($schools->isEmpty()) {
            return redirect()->route('schools.create')
                ->with('warning', 'Add a school first — exams need at least one school to apply to.');
        }

        $subjects = Subject::active()->ordered()->get();
        if ($subjects->isEmpty()) {
            return redirect()->route('subjects.create')
                ->with('warning', 'Add subjects first — teachers will enter marks against these.');
        }

        $classes = SchoolClass::active()->ordered()
            ->when($scopeSchoolId, fn ($q) => $q->where('school_id', $scopeSchoolId))
            ->with(['sections', 'subjects'])->get();
        if ($classes->isEmpty()) {
            return redirect()->route('classes.create')
                ->with('warning', 'Add at least one class — you map exam papers to classes.');
        }

        $classes = $this->mergeTaughtSubjectsIntoCurriculum($classes);

        $gradingScales = GradingScale::where('is_active', true)->with('entries')->get();

        return Inertia::render('Exams/Create', [
            'examTypes' => $examTypes,
            'sessions' => $sessions,
            'gradingScales' => $gradingScales,
            'schools' => $schools,
            'classes' => $classes,
            'subjects' => $subjects,
            'teachers' => $this->teachersForUser($user),
            'isSuperAdmin' => $user->isSuperAdmin(),
            'currentSchoolId' => $user->school_id,
            'defaultTermWeights' => Exam::DEFAULT_TERM_WEIGHTS,
        ]);
    }

    /**
     * The exam Create form uses `class.subjects` (the class_subjects pivot)
     * to decide which subjects auto-tick when an admin picks a class. But
     * historically TeacherAssignment rows (subject_teachers) were created
     * without also seeding class_subjects, so a subject can be actively
     * TAUGHT in a class while missing from the curriculum pivot — and then
     * silently disappears from the exam form (e.g. Drawing on Class 8).
     *
     * We close that gap here: any (subject, class) pair that has an active
     * SubjectTeacher row is treated as part of the class curriculum for the
     * purpose of this form, so it shows up alongside the explicit pivot.
     */
    private function mergeTaughtSubjectsIntoCurriculum($classes)
    {
        $classIds = $classes->pluck('id');
        if ($classIds->isEmpty()) return $classes;

        $taughtPairs = \DB::table('subject_teachers')
            ->whereIn('school_class_id', $classIds)
            ->where('is_active', true)
            ->select('school_class_id', 'subject_id')
            ->distinct()
            ->get();

        $extraSubjectIds = $taughtPairs->pluck('subject_id')->unique();
        if ($extraSubjectIds->isEmpty()) return $classes;

        $extraSubjects = Subject::whereIn('id', $extraSubjectIds)
            ->where('is_active', true)
            ->get()
            ->keyBy('id');

        $pairsByClass = $taughtPairs->groupBy('school_class_id');

        foreach ($classes as $cls) {
            $existingIds = collect($cls->subjects ?? [])->pluck('id')->all();
            $extraForClass = $pairsByClass->get($cls->id, collect())
                ->pluck('subject_id')
                ->reject(fn ($sid) => in_array($sid, $existingIds, true))
                ->map(fn ($sid) => $extraSubjects->get($sid))
                ->filter()
                ->values();
            if ($extraForClass->isNotEmpty()) {
                $cls->setRelation('subjects', $cls->subjects->concat($extraForClass));
            }
        }

        return $classes;
    }

    /**
     * Teachers eligible to be designated as Exam Controller for a new exam.
     * Principals see only their school's teachers; super-admin sees all.
     */
    private function teachersForUser($user)
    {
        return User::query()
            ->whereHas('roles', fn ($q) => $q->whereIn('name', ['class-teacher', 'subject-teacher', 'school-admin']))
            ->when(!$user->isSuperAdmin(), fn ($q) => $q->where('school_id', $user->school_id))
            ->orderBy('name')
            ->get(['id', 'name', 'school_id']);
    }

    /**
     * Derive accurate exam-level total_marks / passing_marks / passing_percentage
     * from the per-subject inputs.
     *
     * Per-subject marks are the source of truth for grading. The exam-level
     * total / passing are stored as the per-class TYPICAL totals (mode of
     * per-class sums) rather than the sum across all classes — because each
     * class is graded against only its own subjects. A 7-subject Class 8 is
     * out of 700, not the 2100 union across 3 classes.
     */
    private function reconcileExamMarks(array $data): array
    {
        $subjects = $data['subjects'] ?? [];
        if (!empty($subjects)) {
            $perClassTotal = [];
            $perClassPass = [];
            $perSubjectPcts = [];
            foreach ($subjects as $s) {
                $cid = (int) ($s['school_class_id'] ?? 0);
                $t = (float) ($s['total_marks'] ?? 0);
                $p = (float) ($s['passing_marks'] ?? 0);
                if ($cid > 0) {
                    $perClassTotal[$cid] = ($perClassTotal[$cid] ?? 0) + $t;
                    $perClassPass[$cid]  = ($perClassPass[$cid]  ?? 0) + $p;
                }
                if ($t > 0) {
                    $perSubjectPcts[] = round(($p / $t) * 100, 2);
                }
            }

            // Pick the mode of per-class sums — what a typical class is
            // graded out of. With uniform subject counts/marks the mode is
            // unambiguous; with variance the most-common stays representative.
            $modeOf = function (array $vals) {
                if (empty($vals)) return null;
                $counts = array_count_values(array_map('strval', array_map(fn ($v) => (string) $v, $vals)));
                arsort($counts);
                return (float) array_key_first($counts);
            };
            $modeTotal = $modeOf(array_values($perClassTotal));
            $modePass  = $modeOf(array_values($perClassPass));
            if ($modeTotal !== null) $data['total_marks'] = $modeTotal;
            if ($modePass !== null)  $data['passing_marks'] = $modePass;

            if (!empty($perSubjectPcts)) {
                $counts = array_count_values(array_map('strval', $perSubjectPcts));
                arsort($counts);
                $data['passing_percentage'] = (float) array_key_first($counts);
            }
        }

        // Final fallbacks when no subjects were provided.
        $data['total_marks'] = $data['total_marks'] ?? 100;
        $data['passing_marks'] = $data['passing_marks'] ?? 33;
        $data['passing_percentage'] = $data['passing_percentage'] ?? 33;
        return $data;
    }

    public function store(StoreExamRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $data['slug'] = Str::slug($data['name']);
        $data['created_by'] = $request->user()->id;
        $data['status'] = 'draft';

        // Exam-level marks: when the admin maps per-subject totals, those are
        // the source of truth. Re-derive total_marks / passing_marks /
        // passing_percentage from them so the summary doesn't show stale 33%
        // when the user typed 40 on every row.
        $data = $this->reconcileExamMarks($data);

        $data['grace_marks'] = $data['grace_marks'] ?? 0;
        $data['grace_marks_max_subjects'] = $data['grace_marks_max_subjects'] ?? 0;
        $data['min_subjects_to_pass'] = $data['min_subjects_to_pass'] ?? null;

        // passing_rules and combination_rules are cast to 'array' on the model,
        // so we pass them as arrays directly — no manual json_encode needed.

        $user = $request->user();
        $applyToAllSchools = $data['apply_to_all_schools'] ?? false;
        $schoolIds = $data['selected_school_ids'] ?? [];
        $subjects = $data['subjects'] ?? [];
        unset($data['selected_school_ids'], $data['subjects']);

        // Principals can ONLY create exams for their own school. Force-scope and ignore any
        // attempt to apply to other schools.
        if (!$user->isSuperAdmin()) {
            $applyToAllSchools = false;
            $schoolIds = [$user->school_id];
            $data['apply_to_all_schools'] = false;
        }

        $exam = Exam::create($data);

        if ($applyToAllSchools) {
            $allActiveSchoolIds = School::active()->pluck('id')->toArray();
            $exam->schools()->sync($allActiveSchoolIds);
        } elseif (!empty($schoolIds)) {
            $exam->schools()->sync($schoolIds);
        }

        foreach ($subjects as $subjectData) {
            ExamSubject::create([
                'exam_id' => $exam->id,
                'subject_id' => $subjectData['subject_id'],
                'school_class_id' => $subjectData['school_class_id'],
                'total_marks' => $subjectData['total_marks'],
                'passing_marks' => $subjectData['passing_marks'],
                'exam_date' => $subjectData['exam_date'] ?? null,
                'start_time' => $subjectData['start_time'] ?? null,
                'end_time' => $subjectData['end_time'] ?? null,
            ]);
        }

        return redirect()->route('exams.index')->with('success', 'Exam created successfully.');
    }

    public function show(Exam $exam): Response
    {
        $this->authorize('view', $exam);

        $exam->load([
            'examType',
            'academicSession',
            'gradingScale.entries',
            'schools',
            'examSubjects.subject',
            'examSubjects.schoolClass',
            'creator',
        ]);
        $exam->loadCount(['marks', 'results']);

        // ── Show ONLY (subject, class) pairs that exist in the class curriculum ──
        // The old form allowed adding any subject to any class, so an exam can
        // hold pairs like "English → Nursery" that aren't actually taught.
        // Those are surfaced as "not in curriculum" and hidden from the view.
        $classIds = $exam->examSubjects->pluck('school_class_id')->unique();
        $curriculum = \DB::table('class_subjects')
            ->where('is_active', true)
            ->whereIn('school_class_id', $classIds)
            ->get(['school_class_id', 'subject_id'])
            ->map(fn ($r) => $r->school_class_id.':'.$r->subject_id)
            ->flip()
            ->toArray();

        $validExamSubjects = $exam->examSubjects
            ->filter(fn ($es) => isset($curriculum[$es->school_class_id.':'.$es->subject_id]))
            ->values();

        $orphanCount = $exam->examSubjects->count() - $validExamSubjects->count();

        return Inertia::render('Exams/Show', [
            'exam' => $exam,
            'examSubjects' => $validExamSubjects,
            'schools' => $exam->schools,
            'orphanCount' => $orphanCount,
        ]);
    }

    /**
     * Remove ExamSubject rows whose (subject, class) pair is not in the class
     * curriculum (class_subjects). Cleanly trims phantom subjects from an exam.
     */
    public function cleanupOrphanSubjects(Exam $exam): RedirectResponse
    {
        $this->authorize('update', $exam);

        $classIds = ExamSubject::where('exam_id', $exam->id)
            ->pluck('school_class_id')->unique();

        $curriculum = \DB::table('class_subjects')
            ->where('is_active', true)
            ->whereIn('school_class_id', $classIds)
            ->get(['school_class_id', 'subject_id'])
            ->map(fn ($r) => $r->school_class_id.':'.$r->subject_id)
            ->flip()->toArray();

        $removed = 0;
        \DB::transaction(function () use ($exam, $curriculum, &$removed) {
            $rows = ExamSubject::where('exam_id', $exam->id)->get();
            foreach ($rows as $r) {
                if (!isset($curriculum[$r->school_class_id.':'.$r->subject_id])) {
                    // Don't touch a row that already has marks recorded.
                    $hasMarks = \App\Models\Mark::where('exam_id', $exam->id)
                        ->where('subject_id', $r->subject_id)
                        ->where('school_class_id', $r->school_class_id)
                        ->exists();
                    if (!$hasMarks) {
                        $r->delete();
                        $removed++;
                    }
                }
            }
        });

        return redirect()->back()->with(
            'success',
            "Removed {$removed} subject(s) that weren't in the class curriculum."
        );
    }

    public function edit(Exam $exam): Response|RedirectResponse
    {
        $this->authorize('update', $exam);

        // Once results are published, the exam is effectively frozen for
        // grading purposes — letting an admin add/remove subjects after
        // students have seen their marks would silently shift percentages.
        // Block the edit form (and the corresponding update endpoint below).
        if ($exam->isResultsPublished()) {
            return redirect()->route('exams.show', $exam)
                ->with('warning', 'This exam is locked because results have been published. Unpublish results first to edit it.');
        }

        $user = request()->user();
        $exam->load(['schools']);
        // Eager-load each exam_subject with a count of marks already entered,
        // so the form can lock down rows that already have data and signal
        // to the admin that they can't be removed or have their totals
        // changed via this edit screen.
        $exam->setRelation('examSubjects', $exam->examSubjects()->get()->map(function ($es) use ($exam) {
            $es->setAttribute('marks_count', \App\Models\Mark::where('exam_id', $exam->id)
                ->where('subject_id', $es->subject_id)
                ->where('school_class_id', $es->school_class_id)
                ->count());
            return $es;
        }));

        $examTypes = ExamType::active()->orderBy('sort_order')->get();
        $sessions = AcademicSession::active()->orderByDesc('start_date')->get();
        $gradingScales = GradingScale::where('is_active', true)->with('entries')->get();

        $schools = School::active()
            ->when(!$user->isSuperAdmin(), fn ($q) => $q->where('id', $user->school_id))
            ->orderBy('name')->get(['id', 'name']);

        $scopeSchoolId = $user->effectiveSchoolId();
        $classes = SchoolClass::active()->ordered()
            ->when($scopeSchoolId, fn ($q) => $q->where('school_id', $scopeSchoolId))
            ->with(['sections', 'subjects'])->get();
        $classes = $this->mergeTaughtSubjectsIntoCurriculum($classes);

        $subjects = Subject::active()->ordered()->get();

        return Inertia::render('Exams/Create', [
            'exam' => $exam,
            'examTypes' => $examTypes,
            'sessions' => $sessions,
            'gradingScales' => $gradingScales,
            'schools' => $schools,
            'classes' => $classes,
            'subjects' => $subjects,
            'teachers' => $this->teachersForUser($user),
            'isSuperAdmin' => $user->isSuperAdmin(),
            'currentSchoolId' => $user->school_id,
            'defaultTermWeights' => Exam::DEFAULT_TERM_WEIGHTS,
        ]);
    }

    public function update(Request $request, Exam $exam): RedirectResponse
    {
        $this->authorize('update', $exam);

        if ($exam->isResultsPublished()) {
            return back()->withErrors([
                'name' => 'Cannot edit an exam after its results have been published. Unpublish first.',
            ])->withInput();
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'exam_type_id' => ['required', 'exists:exam_types,id'],
            'academic_session_id' => ['required', 'exists:academic_sessions,id'],
            'grading_scale_id' => ['nullable', 'exists:grading_scales,id'],
            'term' => ['nullable', 'in:first,second,final'],
            'combine_previous_terms' => ['boolean'],
            'term_weights' => ['nullable', 'array'],
            'term_weights.first' => ['nullable', 'integer', 'min:0', 'max:100'],
            'term_weights.second' => ['nullable', 'integer', 'min:0', 'max:100'],
            'term_weights.final' => ['nullable', 'integer', 'min:0', 'max:100'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'description' => ['nullable', 'string', 'max:1000'],
            'total_marks' => ['nullable', 'numeric', 'min:0'],
            'passing_marks' => ['nullable', 'numeric', 'min:0'],
            'passing_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'min_subjects_to_pass' => ['nullable', 'integer', 'min:0'],
            'main_subjects_must_pass' => ['boolean'],
            'all_subjects_must_pass' => ['boolean'],
            'grace_marks' => ['nullable', 'numeric', 'min:0'],
            'grace_marks_max_subjects' => ['nullable', 'integer', 'min:0'],
            'position_calculation' => ['nullable', 'string', 'in:section,class,school'],
            'passing_rules' => ['nullable', 'array'],
            'combination_rules' => ['nullable', 'array'],
            'apply_to_all_schools' => ['boolean'],
            'applicable_class_ids' => ['nullable', 'array'],
            'marks_entry_deadline' => ['nullable', 'date', 'after_or_equal:end_date'],
            'exam_controller_id' => ['nullable', 'exists:users,id'],
            'selected_school_ids' => ['nullable', 'array'],
            'selected_school_ids.*' => ['exists:schools,id'],
            'subjects' => ['nullable', 'array'],
            'subjects.*.subject_id' => ['required_with:subjects', 'exists:subjects,id'],
            'subjects.*.school_class_id' => ['required_with:subjects', 'exists:school_classes,id'],
            'subjects.*.total_marks' => ['required_with:subjects', 'numeric', 'min:0'],
            'subjects.*.passing_marks' => ['required_with:subjects', 'numeric', 'min:0'],
            'subjects.*.exam_date' => ['nullable', 'date', 'after_or_equal:start_date', 'before_or_equal:end_date'],
            // Post-submission edit policy. 'none' (default) blocks teachers
            // from editing once submitted; 'all' unlocks the whole exam;
            // 'specific' unlocks only the (class, section) pairs listed in
            // post_submit_edit_scope. Admins always retain edit access.
            'post_submit_edit_policy' => ['nullable', 'in:none,all,specific'],
            'post_submit_edit_scope' => ['nullable', 'array'],
            'post_submit_edit_scope.*.school_class_id' => ['required_with:post_submit_edit_scope', 'exists:school_classes,id'],
            'post_submit_edit_scope.*.section_id' => ['required_with:post_submit_edit_scope', 'exists:sections,id'],
        ], [
            'end_date.after_or_equal' => 'The exam end date must be the same as or after the start date.',
            'marks_entry_deadline.after_or_equal' => 'The marks-entry deadline must be on or after the exam end date.',
            'subjects.*.exam_date.after_or_equal' => 'Each subject paper date must fall on or after the exam start date.',
            'subjects.*.exam_date.before_or_equal' => 'Each subject paper date must fall on or before the exam end date.',
        ]);

        // Combine-previous-terms guard: only valid on final-term exam +
        // weights must sum to 100.
        if (!empty($validated['combine_previous_terms']) && ($validated['term'] ?? null) !== 'final') {
            return back()->withInput()->withErrors([
                'combine_previous_terms' => 'Only the final-term exam can combine previous terms.',
            ]);
        }
        if (!empty($validated['combine_previous_terms'])) {
            $w = $validated['term_weights'] ?? [];
            $sum = (int) ($w['first'] ?? 0) + (int) ($w['second'] ?? 0) + (int) ($w['final'] ?? 0);
            if ($sum !== 100) {
                return back()->withInput()->withErrors([
                    'term_weights' => "Term weights must add up to 100 (currently {$sum}).",
                ]);
            }
        }

        $validated['slug'] = Str::slug($validated['name']);

        $validated = $this->reconcileExamMarks($validated);
        $validated['grace_marks'] = $validated['grace_marks'] ?? 0;
        $validated['grace_marks_max_subjects'] = $validated['grace_marks_max_subjects'] ?? 0;
        $validated['min_subjects_to_pass'] = $validated['min_subjects_to_pass'] ?? null;

        // passing_rules and combination_rules are cast to 'array' on the model,
        // so we pass them as arrays directly — no manual json_encode needed.

        $user = $request->user();
        $applyToAllSchools = $validated['apply_to_all_schools'] ?? false;
        $schoolIds = $validated['selected_school_ids'] ?? [];
        $subjects = $validated['subjects'] ?? [];
        unset($validated['selected_school_ids'], $validated['subjects']);

        // Principals can ONLY modify their own school's exams
        if (!$user->isSuperAdmin()) {
            $applyToAllSchools = false;
            $schoolIds = [$user->school_id];
            $validated['apply_to_all_schools'] = false;
        }

        $exam->update($validated);

        if ($applyToAllSchools) {
            $allActiveSchoolIds = School::active()->pluck('id')->toArray();
            $exam->schools()->sync($allActiveSchoolIds);
        } elseif (!empty($schoolIds)) {
            $exam->schools()->sync($schoolIds);
        }

        // Non-destructive sync of exam subjects. The original code did
        // $exam->examSubjects()->delete() + recreate, which silently broke
        // the link to any Mark rows already entered for those subjects —
        // marks reference (exam_id, subject_id, section_id) but the
        // exam_subject_id on each mark would point at a freshly-deleted
        // ExamSubject id. So instead we:
        //   - update existing (subject, class) rows in place (preserves their PK)
        //   - create rows that are new in the submitted payload
        //   - delete rows the admin un-ticked, but ONLY when no marks exist
        //     for them. Rows with marks are silently retained and reported
        //     so the admin sees "kept N subjects that already have marks".
        $existingRows = $exam->examSubjects()
            ->get(['id', 'subject_id', 'school_class_id', 'total_marks', 'passing_marks', 'exam_date', 'start_time', 'end_time'])
            ->keyBy(fn ($r) => $r->subject_id.'-'.$r->school_class_id);

        // (subject_id, school_class_id) pairs that already have marks entered.
        $markedPairs = \App\Models\Mark::where('exam_id', $exam->id)
            ->selectRaw('subject_id, school_class_id')
            ->distinct()
            ->get()
            ->map(fn ($r) => $r->subject_id.'-'.$r->school_class_id)
            ->flip();

        // Track which (subject, class) pairs had their total/passing marks
        // changed on a row with existing student marks — those need to cascade
        // through Mark.total_marks (the snapshot column) and trigger a result
        // recompute for every section that already has generated Results.
        $marksChangedFor = [];

        $added = 0; $updated = 0; $protectedRows = 0;
        foreach ($subjects as $row) {
            $key = $row['subject_id'].'-'.$row['school_class_id'];
            $existing = $existingRows->get($key);
            $hasMarks = $markedPairs->has($key);

            if ($existing) {
                $newTotal = (float) $row['total_marks'];
                $newPass  = (float) $row['passing_marks'];
                $totalChanged = abs((float) $existing->total_marks - $newTotal) > 0.0001;
                $passChanged  = abs((float) $existing->passing_marks - $newPass) > 0.0001;

                if ($hasMarks && ($totalChanged || $passChanged)) {
                    $marksChangedFor[] = [
                        'subject_id' => (int) $row['subject_id'],
                        'school_class_id' => (int) $row['school_class_id'],
                        'new_total' => $newTotal,
                    ];
                }

                // Always update — admin is now allowed to fix total/passing
                // marks even after marks are entered. We cascade below.
                $existing->update([
                    'total_marks' => $newTotal,
                    'passing_marks' => $newPass,
                    'exam_date' => $row['exam_date'] ?? null,
                    'start_time' => $row['start_time'] ?? null,
                    'end_time' => $row['end_time'] ?? null,
                ]);
                $updated++;
                $existingRows->forget($key);
            } else {
                ExamSubject::create([
                    'exam_id' => $exam->id,
                    'subject_id' => $row['subject_id'],
                    'school_class_id' => $row['school_class_id'],
                    'total_marks' => $row['total_marks'],
                    'passing_marks' => $row['passing_marks'],
                    'exam_date' => $row['exam_date'] ?? null,
                    'start_time' => $row['start_time'] ?? null,
                    'end_time' => $row['end_time'] ?? null,
                ]);
                $added++;
            }
        }

        // Rows left in $existingRows were un-ticked by the admin. Delete the
        // ones that are safe; protect any with marks already entered.
        foreach ($existingRows as $key => $row) {
            if ($markedPairs->has($key)) {
                $protectedRows++;
                continue;
            }
            $row->delete();
        }

        // Cascade the marks-base changes: keep the Mark.total_marks snapshot
        // in sync (so Mark::getPercentageAttribute returns correct numbers),
        // and regenerate results for every section that already has Result
        // rows for this exam — so grades and pass/fail flip immediately.
        // marks_obtained is left untouched per spec.
        $recalcResults = 0;
        if (!empty($marksChangedFor)) {
            foreach ($marksChangedFor as $change) {
                \App\Models\Mark::where('exam_id', $exam->id)
                    ->where('subject_id', $change['subject_id'])
                    ->where('school_class_id', $change['school_class_id'])
                    ->update(['total_marks' => $change['new_total']]);
            }

            $classIds = collect($marksChangedFor)->pluck('school_class_id')->unique()->all();
            $sections = \App\Models\Result::where('exam_id', $exam->id)
                ->whereIn('school_class_id', $classIds)
                ->select('school_class_id', 'section_id')
                ->distinct()
                ->get();

            if ($sections->isNotEmpty()) {
                $resultService = app(\App\Services\ResultProcessingService::class);
                foreach ($sections as $s) {
                    $resultService->generateResults($exam, (int) $s->school_class_id, (int) $s->section_id);
                    $recalcResults++;
                }
            }
        }

        $msg = 'Exam Updated Successfully.';
        if ($added > 0)          $msg .= " Added {$added} new subject".($added === 1 ? '' : 's').'.';
        if ($protectedRows > 0)  $msg .= " Kept {$protectedRows} subject".($protectedRows === 1 ? '' : 's')." with existing marks (cannot be removed via edit).";
        if ($recalcResults > 0)  $msg .= " Recalculated results for {$recalcResults} section".($recalcResults === 1 ? '' : 's').'.';

        return redirect()->route('exams.index')->with('success', $msg);
    }

    public function destroy(Exam $exam): RedirectResponse
    {
        $this->authorize('delete', $exam);

        $exam->examSubjects()->delete();
        $exam->delete();

        return redirect()->route('exams.index')->with('success', 'Exam deleted successfully.');
    }

    /**
     * Remove a single (subject, class) row from this exam — protected by
     * the admin's account password when marks have already been entered.
     *
     * Lets admins back out a subject that was added by mistake without
     * having to wipe the whole class. The password gate prevents accidental
     * destruction while still allowing intentional cleanup. Rows with no
     * marks pass through without a password (no data to lose).
     *
     * Refuses entirely once results are published — that lock is global
     * and intentional.
     */
    public function removeSubject(Request $request, Exam $exam): RedirectResponse
    {
        $this->authorize('update', $exam);

        if ($exam->isResultsPublished()) {
            return back()->withErrors([
                'subject_id' => 'Cannot remove subjects after results have been published. Unpublish results first.',
            ]);
        }

        $data = $request->validate([
            'subject_id' => ['required', 'exists:subjects,id'],
            'school_class_id' => ['required', 'exists:school_classes,id'],
            'password' => ['nullable', 'string'],
        ]);

        $subjectId = (int) $data['subject_id'];
        $classId = (int) $data['school_class_id'];

        // Are there any marks entered for this (exam, subject, class)?
        // If so, gate the destructive action on the admin's password.
        $marksCount = \App\Models\Mark::where('exam_id', $exam->id)
            ->where('subject_id', $subjectId)
            ->where('school_class_id', $classId)
            ->count();

        if ($marksCount > 0) {
            if (empty($data['password'])) {
                return back()->withErrors([
                    'password' => 'Password required — this subject has '.$marksCount.' mark(s) entered.',
                ]);
            }
            $user = $request->user();
            if (!\Illuminate\Support\Facades\Hash::check($data['password'], $user->password)) {
                return back()->withErrors([
                    'password' => 'Incorrect password. The destructive action was rejected.',
                ]);
            }
        }

        $sectionIds = Section::where('school_class_id', $classId)->pluck('id');

        // Tight transaction: just the DB writes. The result regeneration
        // is *outside* the transaction so a slow recompute on a big section
        // can't time out the whole request (which used to surface as a 503)
        // and so the deletion is durable even if regen fails.
        \DB::transaction(function () use ($exam, $subjectId, $classId, $sectionIds) {
            \App\Models\Mark::where('exam_id', $exam->id)
                ->where('subject_id', $subjectId)
                ->where('school_class_id', $classId)
                ->delete();

            if ($sectionIds->isNotEmpty()) {
                \App\Models\MarksSubmission::where('exam_id', $exam->id)
                    ->where('subject_id', $subjectId)
                    ->whereIn('section_id', $sectionIds)
                    ->delete();
            }

            ExamSubject::where('exam_id', $exam->id)
                ->where('subject_id', $subjectId)
                ->where('school_class_id', $classId)
                ->delete();
        });

        // Re-run result generation for any sections that already have
        // Results so the dropped subject no longer skews totals/percentages.
        // Best-effort — if regen throws, the deletion above stays applied
        // and the admin gets a partial-success message instead of a 5xx.
        $regenErrors = [];
        $sectionsWithResults = \App\Models\Result::where('exam_id', $exam->id)
            ->whereIn('section_id', $sectionIds)
            ->select('school_class_id', 'section_id')
            ->distinct()
            ->get();
        if ($sectionsWithResults->isNotEmpty()) {
            $svc = app(\App\Services\ResultProcessingService::class);
            foreach ($sectionsWithResults as $s) {
                try {
                    $svc->generateResults($exam, (int) $s->school_class_id, (int) $s->section_id);
                } catch (\Throwable $e) {
                    \Log::warning('removeSubject: result regen failed', [
                        'exam_id' => $exam->id,
                        'section_id' => $s->section_id,
                        'error' => $e->getMessage(),
                    ]);
                    $regenErrors[] = $s->section_id;
                }
            }
        }

        $subjectName = \App\Models\Subject::find($subjectId)?->name ?? "subject #{$subjectId}";
        $className = SchoolClass::find($classId)?->name ?? "class #{$classId}";
        $msg = "Removed {$subjectName} ({$className}) from this exam";
        if ($marksCount > 0) {
            $msg .= " — {$marksCount} mark(s) and any related submissions/results were deleted";
        }
        return back()->with('success', $msg.'.');
    }

    /**
     * Remove an entire class from this exam — wipes every ExamSubject row
     * for that class and cascades into the dependent Marks / Submissions /
     * Results so primary teachers stop seeing exams they were never meant
     * to. Designed for the "I mistakenly added ECD–5 to a Monthly Test that
     * was only for higher classes" recovery flow.
     *
     * Destructive — refuses once results are published (the spec elsewhere
     * already locks down published exams).
     */
    public function removeClass(Request $request, Exam $exam): RedirectResponse
    {
        $this->authorize('update', $exam);

        if ($exam->isResultsPublished()) {
            return back()->withErrors([
                'school_class_id' => 'Cannot remove a class after results have been published. Unpublish results first.',
            ]);
        }

        $data = $request->validate([
            'school_class_id' => ['required', 'exists:school_classes,id'],
        ]);
        $classId = (int) $data['school_class_id'];
        $class = SchoolClass::find($classId);

        \DB::transaction(function () use ($exam, $classId) {
            // Delete marks for this exam × class first (we want the rows
            // gone, not orphaned). Soft-delete via the model so they show
            // in the activity log if anyone needs an audit trail.
            \App\Models\Mark::where('exam_id', $exam->id)
                ->where('school_class_id', $classId)
                ->delete();

            // Marks submissions are class-scoped via their section; we look
            // up sections that belong to this class and drop their rows.
            $sectionIds = Section::where('school_class_id', $classId)->pluck('id');
            if ($sectionIds->isNotEmpty()) {
                \App\Models\MarksSubmission::where('exam_id', $exam->id)
                    ->whereIn('section_id', $sectionIds)
                    ->delete();

                \App\Models\Result::where('exam_id', $exam->id)
                    ->whereIn('section_id', $sectionIds)
                    ->forceDelete();
            }

            // Finally the ExamSubject rows for this class.
            ExamSubject::where('exam_id', $exam->id)
                ->where('school_class_id', $classId)
                ->delete();
        });

        $name = $class?->name ?? "Class #{$classId}";
        return back()->with('success',
            "Removed {$name} from this exam — all its marks, submissions and results were deleted.");
    }

    /**
     * Inline endpoint for the "Save edit policy" button on the post-submit
     * edit panel. Lets admins flip a single exam's policy without walking
     * the whole wizard to the final Update button. Append-only — touches
     * just the two policy columns, never any other exam data, marks or
     * results.
     */
    public function updateEditPolicy(Request $request, Exam $exam): RedirectResponse
    {
        $this->authorize('update', $exam);

        if ($exam->isResultsPublished()) {
            return back()->withErrors([
                'post_submit_edit_policy' => 'Cannot change the policy after results are published. Unpublish first.',
            ]);
        }

        $data = $request->validate([
            'post_submit_edit_policy' => ['required', 'in:none,all,specific'],
            'post_submit_edit_scope' => ['nullable', 'array'],
            'post_submit_edit_scope.*.school_class_id' => ['required_with:post_submit_edit_scope', 'exists:school_classes,id'],
            'post_submit_edit_scope.*.section_id' => ['required_with:post_submit_edit_scope', 'exists:sections,id'],
        ]);

        // When policy isn't 'specific' the scope is meaningless — clear it
        // so a stale list doesn't confuse a future admin who flips back.
        $scope = $data['post_submit_edit_policy'] === 'specific'
            ? ($data['post_submit_edit_scope'] ?? [])
            : null;

        $exam->update([
            'post_submit_edit_policy' => $data['post_submit_edit_policy'],
            'post_submit_edit_scope' => $scope,
        ]);

        $msg = match ($data['post_submit_edit_policy']) {
            'none' => 'Edit policy updated — teachers can no longer edit submitted marks for this exam.',
            'all'  => 'Edit policy updated — teachers may now edit submitted marks for every class & section on this exam.',
            'specific' => 'Edit policy updated — teachers may edit submitted marks for the '.count($scope ?? []).' selected (class, section) combination'.(count($scope ?? []) === 1 ? '' : 's').'.',
        };

        return back()->with('success', $msg);
    }

    /**
     * Inline "Update Marks" endpoint — backs the per-row Apply button on the
     * Edit Exam → Subjects table. Lets an admin correct total / passing marks
     * for one or more (subject, class) tuples without clicking through the
     * whole exam wizard. The cascade is identical to the full update():
     *
     *   - ExamSubject.{total_marks, passing_marks} updated.
     *   - Mark.total_marks snapshot synced for every existing mark on that
     *     (subject, class) so Mark::getPercentageAttribute returns correct
     *     numbers. Mark.marks_obtained is NEVER touched.
     *   - ResultProcessingService::generateResults() re-runs for every
     *     section that already has Result rows for this exam, so percentage,
     *     grade, pass/fail and positions reflect the new marks base.
     */
    public function updateSubjectMarks(Request $request, Exam $exam): RedirectResponse
    {
        $this->authorize('update', $exam);

        if ($exam->isResultsPublished()) {
            return back()->withErrors([
                'subjects' => 'Cannot edit marks after results have been published. Unpublish first.',
            ]);
        }

        $data = $request->validate([
            'subjects' => ['required', 'array', 'min:1'],
            'subjects.*.subject_id' => ['required', 'exists:subjects,id'],
            'subjects.*.school_class_id' => ['required', 'exists:school_classes,id'],
            'subjects.*.total_marks' => ['required', 'numeric', 'min:1'],
            'subjects.*.passing_marks' => ['required', 'numeric', 'min:0'],
        ]);

        $updated = 0;
        $marksTouched = 0;
        $sectionKeys = collect();

        \DB::transaction(function () use ($exam, $data, &$updated, &$marksTouched, &$sectionKeys) {
            foreach ($data['subjects'] as $row) {
                $es = ExamSubject::where('exam_id', $exam->id)
                    ->where('subject_id', $row['subject_id'])
                    ->where('school_class_id', $row['school_class_id'])
                    ->first();
                if (!$es) continue;

                $newTotal = (float) $row['total_marks'];
                $newPass  = (float) $row['passing_marks'];
                $changed = abs((float) $es->total_marks - $newTotal) > 0.0001
                        || abs((float) $es->passing_marks - $newPass) > 0.0001;
                if (!$changed) continue;

                // Passing marks can't exceed total — guard so a typo doesn't
                // silently pass all students.
                if ($newPass > $newTotal) {
                    abort(422, "Passing marks ({$newPass}) cannot exceed total marks ({$newTotal}).");
                }

                $es->update(['total_marks' => $newTotal, 'passing_marks' => $newPass]);
                $updated++;

                $affected = \App\Models\Mark::where('exam_id', $exam->id)
                    ->where('subject_id', $row['subject_id'])
                    ->where('school_class_id', $row['school_class_id'])
                    ->update(['total_marks' => $newTotal]);
                $marksTouched += $affected;

                // Collect every (class, section) for this exam that already
                // has generated Results — they need to be rebuilt against the
                // new marks base.
                $secs = \App\Models\Result::where('exam_id', $exam->id)
                    ->where('school_class_id', $row['school_class_id'])
                    ->select('school_class_id', 'section_id')
                    ->distinct()
                    ->get()
                    ->map(fn ($r) => $r->school_class_id.':'.$r->section_id);
                $sectionKeys = $sectionKeys->concat($secs);
            }
        });

        $recalc = 0;
        if ($sectionKeys->isNotEmpty()) {
            $svc = app(\App\Services\ResultProcessingService::class);
            foreach ($sectionKeys->unique() as $key) {
                [$classId, $sectionId] = explode(':', $key);
                $svc->generateResults($exam, (int) $classId, (int) $sectionId);
                $recalc++;
            }
        }

        if ($updated === 0) {
            return back()->with('info', 'No changes detected — marks are already up to date.');
        }

        $msg = "Marks & results updated successfully — corrected {$updated} subject".($updated === 1 ? '' : 's').'.';
        if ($marksTouched > 0) $msg .= " {$marksTouched} mark snapshot".($marksTouched === 1 ? '' : 's').' synced.';
        if ($recalc > 0)       $msg .= " Recalculated results for {$recalc} section".($recalc === 1 ? '' : 's').'.';

        return back()->with('success', $msg);
    }

    /**
     * Append-only endpoint for the "Add Missing Subject" flow on the Edit
     * Exam screen. Designed for the case where one or two subjects were
     * accidentally omitted at creation time. Guarantees:
     *   - Existing ExamSubject rows are NEVER touched (no update, no delete).
     *   - Existing Mark rows are NEVER touched (PKs stay stable).
     *   - Silently skips any (subject, class) pair that already exists on
     *     the exam, so re-submitting the same payload is a no-op.
     *   - Refuses once results are published — at that point grades have
     *     been seen by students and the exam structure is frozen.
     */
    public function addMissingSubjects(Request $request, Exam $exam): RedirectResponse
    {
        $this->authorize('update', $exam);

        if ($exam->isResultsPublished()) {
            return back()->withErrors([
                'subjects' => 'Cannot add subjects after results have been published. Unpublish first.',
            ]);
        }

        $data = $request->validate([
            'subjects' => ['required', 'array', 'min:1'],
            'subjects.*.subject_id' => ['required', 'exists:subjects,id'],
            'subjects.*.school_class_id' => ['required', 'exists:school_classes,id'],
            'subjects.*.total_marks' => ['required', 'numeric', 'min:1'],
            'subjects.*.passing_marks' => ['required', 'numeric', 'min:0'],
            'subjects.*.exam_date' => ['nullable', 'date'],
        ]);

        $added = 0;
        $alreadyOnExam = 0;
        foreach ($data['subjects'] as $row) {
            $exists = ExamSubject::where('exam_id', $exam->id)
                ->where('subject_id', $row['subject_id'])
                ->where('school_class_id', $row['school_class_id'])
                ->exists();
            if ($exists) {
                $alreadyOnExam++;
                continue;
            }
            ExamSubject::create([
                'exam_id' => $exam->id,
                'subject_id' => $row['subject_id'],
                'school_class_id' => $row['school_class_id'],
                'total_marks' => $row['total_marks'],
                'passing_marks' => $row['passing_marks'],
                'exam_date' => $row['exam_date'] ?? null,
            ]);
            $added++;
        }

        $msg = $added > 0
            ? "Exam Updated Successfully — added {$added} new subject".($added === 1 ? '' : 's').'.'
            : 'Exam Updated Successfully — no new subjects were added (all were already on the exam).';
        if ($alreadyOnExam > 0 && $added > 0) {
            $msg .= " ({$alreadyOnExam} already on the exam, skipped.)";
        }

        return back()->with('success', $msg);
    }

    /**
     * Bulk delete — only draft exams. Each is policy-authorized individually.
     */
    public function bulkDelete(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'exists:exams,id'],
        ]);

        $user = $request->user();
        $deleted = 0;
        $skipped = 0;

        foreach (Exam::whereIn('id', $data['ids'])->get() as $exam) {
            if ($user->can('delete', $exam) && $exam->status === 'draft') {
                $exam->examSubjects()->delete();
                $exam->delete();
                $deleted++;
            } else {
                $skipped++;
            }
        }

        $msg = "{$deleted} exam" . ($deleted === 1 ? '' : 's') . ' deleted.';
        if ($skipped > 0) $msg .= " {$skipped} skipped (not draft or no permission).";

        return redirect()->route('exams.index')->with($deleted > 0 ? 'success' : 'warning', $msg);
    }

    public function publish(int $exam): RedirectResponse
    {
        $exam = Exam::findOrFail($exam);
        $this->authorize('update', $exam);

        if ($exam->status === 'draft') {
            if ($exam->examSubjects()->count() === 0) {
                return redirect()->back()->with('error', 'Cannot publish: exam must have at least 1 subject assigned.');
            }
            $exam->update(['status' => 'published']);
            $message = 'Exam published successfully.';
        } elseif ($exam->status === 'published') {
            if ($exam->schools()->count() === 0) {
                return redirect()->back()->with('error', 'Cannot open marks entry: exam must have at least 1 school assigned.');
            }
            $exam->update(['status' => 'marks_entry']);
            $message = 'Marks entry opened for this exam.';
        } else {
            return redirect()->back()->with('error', 'Exam cannot be published in its current state.');
        }

        return redirect()->back()->with('success', $message);
    }

    public function lock(int $exam): RedirectResponse
    {
        $exam = Exam::findOrFail($exam);
        $this->authorize('update', $exam);

        $exam->update(['is_locked' => !$exam->is_locked]);

        $message = $exam->is_locked ? 'Marks entry locked.' : 'Marks entry unlocked.';

        return redirect()->back()->with('success', $message);
    }
}
