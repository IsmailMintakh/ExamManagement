<?php

namespace App\Http\Controllers;

use App\Models\ExamType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ExamTypeController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', ExamType::class);

        $examTypes = ExamType::query()
            ->when($request->has('search'), function ($query) use ($request) {
                $query->where('name', 'like', '%' . $request->input('search') . '%');
            })
            ->withCount('exams')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('ExamTypes/Index', [
            'examTypes' => $examTypes,
            'filters' => $request->only(['search']),
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', ExamType::class);

        return Inertia::render('ExamTypes/Create');
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', ExamType::class);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:exam_types,name'],
            'slug' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:500'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['boolean'],
        ]);

        ExamType::create($validated);

        return redirect()->route('exam-types.index')->with('success', 'Exam type created successfully.');
    }

    public function show(ExamType $examType): RedirectResponse
    {
        return redirect()->route('exam-types.edit', $examType);
    }

    public function edit(ExamType $examType): Response
    {
        $this->authorize('update', $examType);

        return Inertia::render('ExamTypes/Create', [
            'examType' => $examType,
        ]);
    }

    public function update(Request $request, ExamType $examType): RedirectResponse
    {
        $this->authorize('update', $examType);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:exam_types,name,' . $examType->id],
            'slug' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:500'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['boolean'],
        ]);

        $examType->update($validated);

        return redirect()->route('exam-types.index')->with('success', 'Exam type updated successfully.');
    }

    public function destroy(ExamType $examType): RedirectResponse
    {
        $this->authorize('delete', $examType);

        $examType->delete();

        return redirect()->route('exam-types.index')->with('success', 'Exam type deleted successfully.');
    }
}
