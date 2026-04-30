<?php

namespace App\Http\Controllers;

use App\Models\AcademicSession;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AcademicSessionController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', AcademicSession::class);

        $sessions = AcademicSession::query()
            ->when($request->has('search'), function ($query) use ($request) {
                $query->where('name', 'like', '%' . $request->input('search') . '%');
            })
            ->orderByDesc('start_date')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('AcademicSessions/Index', [
            'sessions' => $sessions,
            'filters' => $request->only(['search']),
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', AcademicSession::class);

        return Inertia::render('AcademicSessions/Create');
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', AcademicSession::class);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:academic_sessions,name'],
            'slug' => ['nullable', 'string', 'max:255'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after:start_date'],
            'is_current' => ['boolean'],
            'is_active' => ['boolean'],
        ]);

        if (!empty($validated['is_current'])) {
            AcademicSession::where('is_current', true)->update(['is_current' => false]);
        }

        AcademicSession::create($validated);

        return redirect()->route('academic-sessions.index')->with('success', 'Academic session created successfully.');
    }

    public function show(AcademicSession $academicSession): RedirectResponse
    {
        return redirect()->route('academic-sessions.edit', $academicSession);
    }

    public function edit(AcademicSession $academicSession): Response
    {
        $this->authorize('update', $academicSession);

        return Inertia::render('AcademicSessions/Create', [
            'session' => $academicSession,
        ]);
    }

    public function update(Request $request, AcademicSession $academicSession): RedirectResponse
    {
        $this->authorize('update', $academicSession);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:academic_sessions,name,' . $academicSession->id],
            'slug' => ['nullable', 'string', 'max:255'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after:start_date'],
            'is_current' => ['boolean'],
            'is_active' => ['boolean'],
        ]);

        if (!empty($validated['is_current']) && !$academicSession->is_current) {
            AcademicSession::where('is_current', true)->update(['is_current' => false]);
        }

        $academicSession->update($validated);

        return redirect()->route('academic-sessions.index')->with('success', 'Academic session updated successfully.');
    }

    public function destroy(AcademicSession $academicSession): RedirectResponse
    {
        $this->authorize('delete', $academicSession);

        $academicSession->delete();

        return redirect()->route('academic-sessions.index')->with('success', 'Academic session deleted successfully.');
    }

    public function switchSession(int $academic_session): RedirectResponse
    {
        $this->authorize('update', AcademicSession::class);

        $session = AcademicSession::findOrFail($academic_session);

        AcademicSession::where('is_current', true)->update(['is_current' => false]);
        $session->update(['is_current' => true]);

        return redirect()->back()->with('success', 'Academic session switched to ' . $session->name . '.');
    }
}
