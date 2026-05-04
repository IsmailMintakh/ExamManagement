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
use App\Models\Subject;
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
            ->when(!$user->isSuperAdmin(), function ($query) use ($user) {
                $query->whereHas('schools', fn ($q) => $q->where('schools.id', $user->school_id));
            })
            ->with(['examType', 'academicSession', 'gradingScale'])
            ->withCount(['examSubjects', 'marks', 'results'])
            ->latest()
            ->paginate(15)
            ->withQueryString();

        $examTypes = ExamType::active()->orderBy('sort_order')->get(['id', 'name']);
        $sessions = AcademicSession::active()->orderByDesc('start_date')->get(['id', 'name']);

        return Inertia::render('Exams/Index', [
            'exams' => $exams,
            'examTypes' => $examTypes,
            'sessions' => $sessions,
            'filters' => $request->only(['search', 'exam_type_id', 'status', 'session_id']),
        ]);
    }

    public function create(): Response|RedirectResponse
    {
        $this->authorize('create', Exam::class);

        $user = request()->user();

        $examTypes = ExamType::active()->orderBy('sort_order')->get();
        if ($examTypes->isEmpty()) {
            return redirect()->route('exam-types.create')
                ->with('warning', 'Please create at least one exam type before creating an exam.');
        }

        $sessions = AcademicSession::active()->orderByDesc('start_date')->get();
        if ($sessions->isEmpty()) {
            return redirect()->route('academic-sessions.create')
                ->with('warning', 'Please create an academic session before creating an exam.');
        }

        $gradingScales = GradingScale::where('is_active', true)->with('entries')->get();

        // Scope schools/classes to the principal's own school. Super-admin sees all.
        $schools = School::active()
            ->when(!$user->isSuperAdmin(), fn ($q) => $q->where('id', $user->school_id))
            ->orderBy('name')->get(['id', 'name']);

        $classes = SchoolClass::active()->ordered()
            ->when(!$user->isSuperAdmin(), fn ($q) => $q->where('school_id', $user->school_id))
            ->with(['sections', 'subjects'])->get();

        $subjects = Subject::active()->ordered()->get();

        return Inertia::render('Exams/Create', [
            'examTypes' => $examTypes,
            'sessions' => $sessions,
            'gradingScales' => $gradingScales,
            'schools' => $schools,
            'classes' => $classes,
            'subjects' => $subjects,
            'isSuperAdmin' => $user->isSuperAdmin(),
            'currentSchoolId' => $user->school_id,
        ]);
    }

    public function store(StoreExamRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $data['slug'] = Str::slug($data['name']);
        $data['created_by'] = $request->user()->id;
        $data['status'] = 'draft';

        // Ensure numeric fields default to 0 when left blank
        $data['total_marks'] = $data['total_marks'] ?? 100;
        $data['passing_marks'] = $data['passing_marks'] ?? 33;
        $data['passing_percentage'] = $data['passing_percentage'] ?? 33;
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

        return Inertia::render('Exams/Show', [
            'exam' => $exam,
            'examSubjects' => $exam->examSubjects,
            'schools' => $exam->schools,
        ]);
    }

    public function edit(Exam $exam): Response
    {
        $this->authorize('update', $exam);

        $user = request()->user();
        $exam->load(['examSubjects', 'schools']);

        $examTypes = ExamType::active()->orderBy('sort_order')->get();
        $sessions = AcademicSession::active()->orderByDesc('start_date')->get();
        $gradingScales = GradingScale::where('is_active', true)->with('entries')->get();

        $schools = School::active()
            ->when(!$user->isSuperAdmin(), fn ($q) => $q->where('id', $user->school_id))
            ->orderBy('name')->get(['id', 'name']);

        $classes = SchoolClass::active()->ordered()
            ->when(!$user->isSuperAdmin(), fn ($q) => $q->where('school_id', $user->school_id))
            ->with(['sections', 'subjects'])->get();

        $subjects = Subject::active()->ordered()->get();

        return Inertia::render('Exams/Create', [
            'exam' => $exam,
            'examTypes' => $examTypes,
            'sessions' => $sessions,
            'gradingScales' => $gradingScales,
            'schools' => $schools,
            'classes' => $classes,
            'subjects' => $subjects,
            'isSuperAdmin' => $user->isSuperAdmin(),
            'currentSchoolId' => $user->school_id,
        ]);
    }

    public function update(Request $request, Exam $exam): RedirectResponse
    {
        $this->authorize('update', $exam);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'exam_type_id' => ['required', 'exists:exam_types,id'],
            'academic_session_id' => ['required', 'exists:academic_sessions,id'],
            'grading_scale_id' => ['nullable', 'exists:grading_scales,id'],
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
            'selected_school_ids' => ['nullable', 'array'],
            'selected_school_ids.*' => ['exists:schools,id'],
            'subjects' => ['nullable', 'array'],
            'subjects.*.subject_id' => ['required_with:subjects', 'exists:subjects,id'],
            'subjects.*.school_class_id' => ['required_with:subjects', 'exists:school_classes,id'],
            'subjects.*.total_marks' => ['required_with:subjects', 'numeric', 'min:0'],
            'subjects.*.passing_marks' => ['required_with:subjects', 'numeric', 'min:0'],
            'subjects.*.exam_date' => ['nullable', 'date', 'after_or_equal:start_date', 'before_or_equal:end_date'],
        ], [
            'end_date.after_or_equal' => 'The exam end date must be the same as or after the start date.',
            'marks_entry_deadline.after_or_equal' => 'The marks-entry deadline must be on or after the exam end date.',
            'subjects.*.exam_date.after_or_equal' => 'Each subject paper date must fall on or after the exam start date.',
            'subjects.*.exam_date.before_or_equal' => 'Each subject paper date must fall on or before the exam end date.',
        ]);

        $validated['slug'] = Str::slug($validated['name']);

        $validated['total_marks'] = $validated['total_marks'] ?? 100;
        $validated['passing_marks'] = $validated['passing_marks'] ?? 33;
        $validated['passing_percentage'] = $validated['passing_percentage'] ?? 33;
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

        $exam->examSubjects()->delete();
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

        return redirect()->route('exams.index')->with('success', 'Exam updated successfully.');
    }

    public function destroy(Exam $exam): RedirectResponse
    {
        $this->authorize('delete', $exam);

        $exam->examSubjects()->delete();
        $exam->delete();

        return redirect()->route('exams.index')->with('success', 'Exam deleted successfully.');
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
