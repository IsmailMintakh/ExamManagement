<?php

namespace App\Http\Controllers;

use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SectionController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Section::class);

        $user = $request->user();

        $sections = Section::query()
            ->when(!$user->isSuperAdmin(), function ($query) use ($user) {
                $query->whereHas('schoolClass', fn ($q) => $q->where('school_id', $user->school_id));
            })
            ->when($request->has('search'), function ($query) use ($request) {
                $query->where('name', 'like', '%' . $request->input('search') . '%');
            })
            ->when($request->has('class_id'), function ($query) use ($request) {
                $query->where('school_class_id', $request->input('class_id'));
            })
            ->with(['schoolClass.school', 'classTeacher'])
            ->withCount('students')
            ->paginate(15)
            ->withQueryString();

        $classes = SchoolClass::query()
            ->when(!$user->isSuperAdmin(), fn ($q) => $q->where('school_id', $user->school_id))
            ->active()
            ->ordered()
            ->get(['id', 'name', 'school_id']);

        return Inertia::render('Sections/Index', [
            'sections' => $sections,
            'classes' => $classes,
            'filters' => $request->only(['search', 'class_id']),
        ]);
    }

    public function create(Request $request): Response|RedirectResponse
    {
        $this->authorize('create', Section::class);

        $user = $request->user();

        $classes = SchoolClass::query()
            ->when(!$user->isSuperAdmin(), fn ($q) => $q->where('school_id', $user->school_id))
            ->active()
            ->ordered()
            ->get(['id', 'name', 'school_id']);

        if ($classes->isEmpty()) {
            return redirect()->route('classes.create')
                ->with('warning', 'Please add at least one class before creating a section.');
        }

        $teachers = User::role('class-teacher')
            ->when(!$user->isSuperAdmin(), fn ($q) => $q->where('school_id', $user->school_id))
            ->active()
            ->orderBy('name')
            ->get(['id', 'name']);

        return Inertia::render('Sections/Create', [
            'classes' => $classes,
            'teachers' => $teachers,
            'prerequisites' => [
                'hasTeachers' => $teachers->isNotEmpty(),
                'teachersMessage' => $teachers->isEmpty() ? 'No class teachers found. You can still create a section and assign a teacher later, or add a user with "Class Teacher" role first.' : null,
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Section::class);

        $validated = $request->validate([
            'school_class_id' => ['required', 'exists:school_classes,id'],
            'name' => ['required', 'string', 'max:50'],
            'slug' => ['nullable', 'string', 'max:100'],
            'class_teacher_id' => ['nullable', 'exists:users,id'],
            'capacity' => ['nullable', 'integer', 'min:1'],
            'is_active' => ['boolean'],
        ]);

        Section::create($validated);

        return redirect()->route('sections.index')->with('success', 'Section created successfully.');
    }

    public function show(Section $section): RedirectResponse
    {
        return redirect()->route('sections.edit', $section);
    }

    public function edit(Section $section): Response
    {
        $this->authorize('update', $section);

        $user = request()->user();

        $classes = SchoolClass::query()
            ->when(!$user->isSuperAdmin(), fn ($q) => $q->where('school_id', $user->school_id))
            ->active()
            ->ordered()
            ->get(['id', 'name', 'school_id']);

        $teachers = User::role('class-teacher')
            ->when(!$user->isSuperAdmin(), fn ($q) => $q->where('school_id', $user->school_id))
            ->active()
            ->orderBy('name')
            ->get(['id', 'name']);

        return Inertia::render('Sections/Create', [
            'section' => $section,
            'classes' => $classes,
            'teachers' => $teachers,
        ]);
    }

    public function update(Request $request, Section $section): RedirectResponse
    {
        $this->authorize('update', $section);

        $validated = $request->validate([
            'school_class_id' => ['required', 'exists:school_classes,id'],
            'name' => ['required', 'string', 'max:50'],
            'slug' => ['nullable', 'string', 'max:100'],
            'class_teacher_id' => ['nullable', 'exists:users,id'],
            'capacity' => ['nullable', 'integer', 'min:1'],
            'is_active' => ['boolean'],
        ]);

        $section->update($validated);

        return redirect()->route('sections.index')->with('success', 'Section updated successfully.');
    }

    public function destroy(Section $section): RedirectResponse
    {
        $this->authorize('delete', $section);

        $section->delete();

        return redirect()->route('sections.index')->with('success', 'Section deleted successfully.');
    }
}
