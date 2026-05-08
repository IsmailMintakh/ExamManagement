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

        // Pre-flight duplicate check — school_classes has UNIQUE(school_id, slug)
        // which the model's auto-slug generator can collide with. We look at
        // BOTH active and soft-deleted rows because MySQL's unique index
        // doesn't ignore soft-deletes — a long-deleted "Nursery" still owns
        // its slug slot and blocks recreates.
        $candidateSlug = !empty($validated['slug'])
            ? $validated['slug']
            : \Illuminate\Support\Str::slug($validated['name']);

        $existing = SchoolClass::withTrashed()
            ->where('school_id', $validated['school_id'])
            ->where('slug', $candidateSlug)
            ->first();

        // Active duplicate → block with a clear message.
        if ($existing && $existing->deleted_at === null) {
            return redirect()->back()->withInput()
                ->withErrors(['name' => "A class with this name already exists in your school. Try a different name (e.g. \"{$validated['name']} - Section A\")."]);
        }

        // Track whether we restore an old row vs. create new — the model's
        // trashed() flag flips after restore() so we can't ask it later.
        $wasRestored = false;
        try {
            \DB::transaction(function () use ($validated, $subjectIds, $existing, &$class, &$wasRestored) {
                if ($existing && $existing->trashed()) {
                    // Soft-deleted match → revive it with the new data instead
                    // of creating a new row. Restoring keeps the original ID
                    // (good for any old foreign keys still pointing at it)
                    // and avoids the unique-index conflict entirely.
                    $existing->restore();
                    $existing->update($validated);
                    $class = $existing;
                    $wasRestored = true;
                } else {
                    $class = SchoolClass::create($validated);
                }
                if (!empty($subjectIds)) {
                    $class->subjects()->sync($subjectIds);
                }
            });
        } catch (\Throwable $e) {
            \Log::error('Class create failed', [
                'message' => $e->getMessage(),
                'trace_top' => $e->getFile() . ':' . $e->getLine(),
                'school_id' => $validated['school_id'] ?? null,
                'name' => $validated['name'] ?? null,
                'user_id' => $user->id,
            ]);

            // Show the real error message to admins (they're trusted and
            // need it to fix the problem). Generic message for everyone
            // else. Without this, users with the right access level were
            // stuck reading "please try again" forever on production.
            $isAdmin = $user->isSuperAdmin() || $user->isSchoolAdmin();
            $msg = $isAdmin
                ? 'Could not create class. Database error: ' . $e->getMessage()
                : 'Could not create the class — please try again. If this keeps happening, ask the administrator to check the server log.';

            return redirect()->back()->withInput()->withErrors(['name' => $msg]);
        }

        // Differentiate restore-from-trash from a fresh create so the user
        // understands what happened (otherwise "created successfully" feels
        // wrong when we actually revived an old soft-deleted row).
        $msg = $wasRestored
            ? 'A previously-deleted class with this name was restored with the new details.'
            : 'Class created successfully.';
        return redirect()->route('classes.index')->with('success', $msg);
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

        // Slug-rename collision guard. Same reasoning as ::store.
        $newSlug = !empty($validated['slug'])
            ? $validated['slug']
            : \Illuminate\Support\Str::slug($validated['name']);
        $duplicate = SchoolClass::where('school_id', $validated['school_id'])
            ->where('slug', $newSlug)
            ->where('id', '!=', $schoolClass->id)
            ->exists();
        if ($duplicate) {
            return redirect()->back()->withInput()
                ->withErrors(['name' => "Another class with this name already exists in this school. Pick a different name."]);
        }

        try {
            \DB::transaction(function () use ($validated, $subjectIds, $schoolClass) {
                $schoolClass->update($validated);
                $schoolClass->subjects()->sync($subjectIds);
            });
        } catch (\Throwable $e) {
            \Log::error('Class update failed', [
                'class_id' => $schoolClass->id,
                'message' => $e->getMessage(),
                'trace_top' => $e->getFile() . ':' . $e->getLine(),
                'user_id' => $user->id,
            ]);
            $isAdmin = $user->isSuperAdmin() || $user->isSchoolAdmin();
            $msg = $isAdmin
                ? 'Could not update class. Database error: ' . $e->getMessage()
                : 'Could not update the class — please try again. If this keeps happening, ask the administrator to check the server log.';
            return redirect()->back()->withInput()->withErrors(['name' => $msg]);
        }

        return redirect()->route('classes.index')->with('success', 'Class updated successfully.');
    }

    public function destroy(SchoolClass $schoolClass): RedirectResponse
    {
        $this->authorize('delete', $schoolClass);

        $schoolClass->delete();

        return redirect()->route('classes.index')->with('success', 'Class deleted successfully.');
    }
}
