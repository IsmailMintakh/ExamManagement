<?php

namespace App\Http\Controllers;

use App\Models\GradingScale;
use App\Models\GradingScaleEntry;
use App\Models\School;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class GradingScaleController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', GradingScale::class);

        $user = $request->user();

        // Source filter (mine | library | all). Default 'all' so a school
        // with no custom scales still sees the DDO-shipped defaults — those
        // are what makes the feature work out of the box.
        $source = $this->resolveSource($request);

        $gradingScales = GradingScale::query()
            ->when(true, fn ($q) => $this->scopeForUser($q, $user, $source))
            ->when($request->has('search'), function ($query) use ($request) {
                $query->where('name', 'like', '%' . $request->input('search') . '%');
            })
            ->with(['school', 'entries' => fn ($q) => $q->orderBy('sort_order')->orderByDesc('min_percentage')])
            ->orderBy('name')
            ->get();

        $sourceCounts = $user->isSuperAdmin()
            ? null
            : [
                'mine' => $this->scopeForUser(GradingScale::query(), $user, 'mine')->count(),
                'library' => $this->scopeForUser(GradingScale::query(), $user, 'library')->count(),
                'all' => $this->scopeForUser(GradingScale::query(), $user, 'all')->count(),
            ];

        return Inertia::render('GradingScales/Index', [
            'gradingScales' => $gradingScales,
            'filters' => array_merge($request->only(['search']), ['source' => $source]),
            'sourceCounts' => $sourceCounts,
        ]);
    }

    /**
     * Apply the role-based + source filter to a GradingScale query.
     */
    protected function scopeForUser($query, $user, string $source = 'all')
    {
        if ($user->isSuperAdmin()) {
            return $query;
        }

        return match ($source) {
            'library' => $query->whereNull('school_id'),
            'mine' => $query->where('school_id', $user->school_id),
            default /* 'all' */ => $query->where(function ($q) use ($user) {
                $q->where('school_id', $user->school_id)->orWhereNull('school_id');
            }),
        };
    }

    protected function resolveSource(Request $request, string $default = 'all'): string
    {
        $s = $request->input('source', $default);
        return in_array($s, ['mine', 'library', 'all'], true) ? $s : $default;
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', GradingScale::class);

        $user = $request->user();
        $schools = $user->isSuperAdmin()
            ? School::active()->orderBy('name')->get(['id', 'name'])
            : School::where('id', $user->school_id)->get(['id', 'name']);

        return Inertia::render('GradingScales/Create', [
            'schools' => $schools,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', GradingScale::class);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'school_id' => ['nullable', 'exists:schools,id'],
            'is_default' => ['boolean'],
            'is_active' => ['boolean'],
            'entries' => ['required', 'array', 'min:1'],
            'entries.*.grade' => ['required', 'string', 'max:5'],
            'entries.*.label' => ['nullable', 'string', 'max:50'],
            'entries.*.min_percentage' => ['required', 'numeric', 'min:0', 'max:100'],
            'entries.*.max_percentage' => ['required', 'numeric', 'min:0', 'max:100'],
            'entries.*.grade_point' => ['required', 'numeric', 'min:0'],
            'entries.*.remark' => ['nullable', 'string', 'max:100'],
            'entries.*.sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        if (!empty($validated['is_default'])) {
            GradingScale::where('is_default', true)
                ->when($validated['school_id'] ?? null, fn ($q, $id) => $q->where('school_id', $id))
                ->update(['is_default' => false]);
        }

        $entries = $validated['entries'];
        unset($validated['entries']);

        $gradingScale = GradingScale::create($validated);

        foreach ($entries as $entry) {
            $entry['grading_scale_id'] = $gradingScale->id;
            GradingScaleEntry::create($entry);
        }

        return redirect()->route('grading-scales.index')->with('success', 'Grading scale created successfully.');
    }

    public function show(GradingScale $gradingScale): RedirectResponse
    {
        return redirect()->route('grading-scales.index');
    }

    public function edit(GradingScale $gradingScale): Response
    {
        $this->authorize('update', $gradingScale);

        $gradingScale->load('entries');

        $user = request()->user();
        $schools = $user->isSuperAdmin()
            ? School::active()->orderBy('name')->get(['id', 'name'])
            : School::where('id', $user->school_id)->get(['id', 'name']);

        return Inertia::render('GradingScales/Create', [
            'gradingScale' => $gradingScale,
            'schools' => $schools,
        ]);
    }

    public function update(Request $request, GradingScale $gradingScale): RedirectResponse
    {
        $this->authorize('update', $gradingScale);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'school_id' => ['nullable', 'exists:schools,id'],
            'is_default' => ['boolean'],
            'is_active' => ['boolean'],
            'entries' => ['required', 'array', 'min:1'],
            'entries.*.id' => ['nullable', 'exists:grading_scale_entries,id'],
            'entries.*.grade' => ['required', 'string', 'max:5'],
            'entries.*.label' => ['nullable', 'string', 'max:50'],
            'entries.*.min_percentage' => ['required', 'numeric', 'min:0', 'max:100'],
            'entries.*.max_percentage' => ['required', 'numeric', 'min:0', 'max:100'],
            'entries.*.grade_point' => ['required', 'numeric', 'min:0'],
            'entries.*.remark' => ['nullable', 'string', 'max:100'],
            'entries.*.sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        if (!empty($validated['is_default']) && !$gradingScale->is_default) {
            GradingScale::where('is_default', true)
                ->when($validated['school_id'] ?? null, fn ($q, $id) => $q->where('school_id', $id))
                ->where('id', '!=', $gradingScale->id)
                ->update(['is_default' => false]);
        }

        $entries = $validated['entries'];
        unset($validated['entries']);

        $gradingScale->update($validated);

        // Sync entries: delete removed, update existing, create new
        $existingIds = collect($entries)->pluck('id')->filter()->toArray();
        $gradingScale->entries()->whereNotIn('id', $existingIds)->delete();

        foreach ($entries as $entryData) {
            if (!empty($entryData['id'])) {
                GradingScaleEntry::where('id', $entryData['id'])->update($entryData);
            } else {
                $entryData['grading_scale_id'] = $gradingScale->id;
                GradingScaleEntry::create($entryData);
            }
        }

        return redirect()->route('grading-scales.index')->with('success', 'Grading scale updated successfully.');
    }

    public function destroy(GradingScale $gradingScale): RedirectResponse
    {
        $this->authorize('delete', $gradingScale);

        $gradingScale->entries()->delete();
        $gradingScale->delete();

        return redirect()->route('grading-scales.index')->with('success', 'Grading scale deleted successfully.');
    }
}
