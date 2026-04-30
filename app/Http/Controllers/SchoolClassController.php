<?php

namespace App\Http\Controllers;

use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Subject;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SchoolClassController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', SchoolClass::class);

        $user = $request->user();

        $classes = SchoolClass::query()
            ->when(!$user->isSuperAdmin(), function ($query) use ($user) {
                $query->where('school_id', $user->school_id);
            })
            ->when($request->has('search'), function ($query) use ($request) {
                $query->where('name', 'like', '%' . $request->input('search') . '%');
            })
            ->when($request->has('school_id'), function ($query) use ($request) {
                $query->where('school_id', $request->input('school_id'));
            })
            ->with(['school', 'sections'])
            ->withCount(['sections', 'students'])
            ->ordered()
            ->paginate(15)
            ->withQueryString();

        $schools = $user->isSuperAdmin() ? School::active()->orderBy('name')->get(['id', 'name']) : [];

        return Inertia::render('Classes/Index', [
            'classes' => $classes,
            'schools' => $schools,
            'filters' => $request->only(['search', 'school_id']),
        ]);
    }

    public function create(Request $request): Response|RedirectResponse
    {
        $this->authorize('create', SchoolClass::class);

        $user = $request->user();
        $schools = $user->isSuperAdmin()
            ? School::active()->orderBy('name')->get(['id', 'name'])
            : School::where('id', $user->school_id)->get(['id', 'name']);

        if ($schools->isEmpty()) {
            return redirect()->route('schools.create')
                ->with('warning', 'Please add at least one school before creating a class.');
        }

        $subjects = Subject::active()->ordered()->get(['id', 'name', 'code']);

        return Inertia::render('Classes/Create', [
            'schools' => $schools,
            'subjects' => $subjects,
            'prerequisites' => [
                'hasSubjects' => $subjects->isNotEmpty(),
                'subjectsMessage' => $subjects->isEmpty() ? 'No subjects found. Add subjects first to assign them to this class, or create the class now and assign subjects later.' : null,
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', SchoolClass::class);

        $user = $request->user();

        $validated = $request->validate([
            'school_id' => ['required', 'exists:schools,id'],
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255'],
            'numeric_name' => ['nullable', 'integer'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['boolean'],
            'subject_ids' => ['nullable', 'array'],
            'subject_ids.*' => ['exists:subjects,id'],
        ]);

        if (!$user->isSuperAdmin()) {
            $validated['school_id'] = $user->school_id;
        }

        $subjectIds = $validated['subject_ids'] ?? [];
        unset($validated['subject_ids']);

        $class = SchoolClass::create($validated);

        if (!empty($subjectIds)) {
            $class->subjects()->sync($subjectIds);
        }

        return redirect()->route('classes.index')->with('success', 'Class created successfully.');
    }

    public function show(SchoolClass $schoolClass): RedirectResponse
    {
        return redirect()->route('classes.edit', $schoolClass);
    }

    public function edit(SchoolClass $schoolClass): Response
    {
        $this->authorize('update', $schoolClass);

        $user = request()->user();
        $schools = $user->isSuperAdmin()
            ? School::active()->orderBy('name')->get(['id', 'name'])
            : School::where('id', $user->school_id)->get(['id', 'name']);

        $subjects = Subject::active()->ordered()->get(['id', 'name', 'code']);
        $schoolClass->load('subjects');

        return Inertia::render('Classes/Create', [
            'schoolClass' => $schoolClass,
            'schools' => $schools,
            'subjects' => $subjects,
        ]);
    }

    public function update(Request $request, SchoolClass $schoolClass): RedirectResponse
    {
        $this->authorize('update', $schoolClass);

        $user = $request->user();

        $validated = $request->validate([
            'school_id' => ['required', 'exists:schools,id'],
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255'],
            'numeric_name' => ['nullable', 'integer'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['boolean'],
            'subject_ids' => ['nullable', 'array'],
            'subject_ids.*' => ['exists:subjects,id'],
        ]);

        if (!$user->isSuperAdmin()) {
            $validated['school_id'] = $user->school_id;
        }

        $subjectIds = $validated['subject_ids'] ?? [];
        unset($validated['subject_ids']);

        $schoolClass->update($validated);
        $schoolClass->subjects()->sync($subjectIds);

        return redirect()->route('classes.index')->with('success', 'Class updated successfully.');
    }

    public function destroy(SchoolClass $schoolClass): RedirectResponse
    {
        $this->authorize('delete', $schoolClass);

        $schoolClass->delete();

        return redirect()->route('classes.index')->with('success', 'Class deleted successfully.');
    }
}
