<?php

namespace App\Http\Controllers;

use App\Models\AcademicSession;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Subject;
use App\Models\SubjectTeacher;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TeacherAssignmentController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $currentSession = AcademicSession::currentSession();

        $assignments = SubjectTeacher::query()
            ->when(!$user->isSuperAdmin(), function ($query) use ($user) {
                $query->whereHas('schoolClass', fn ($q) => $q->where('school_id', $user->school_id));
            })
            ->when($request->has('search'), function ($query) use ($request) {
                $search = $request->input('search');
                $query->whereHas('user', fn ($q) => $q->where('name', 'like', "%{$search}%"))
                    ->orWhereHas('subject', fn ($q) => $q->where('name', 'like', "%{$search}%"));
            })
            ->when($request->has('class_id'), fn ($q) => $q->where('school_class_id', $request->input('class_id')))
            ->when($request->has('section_id'), fn ($q) => $q->where('section_id', $request->input('section_id')))
            ->when($request->has('subject_id'), fn ($q) => $q->where('subject_id', $request->input('subject_id')))
            ->when($currentSession, fn ($q) => $q->where('academic_session_id', $currentSession->id))
            ->with(['user', 'subject', 'schoolClass', 'section', 'academicSession'])
            ->paginate(15)
            ->withQueryString();

        $classes = SchoolClass::query()
            ->when(!$user->isSuperAdmin(), fn ($q) => $q->where('school_id', $user->school_id))
            ->active()->ordered()->with('sections')->get();

        $subjects = Subject::active()->ordered()->get(['id', 'name', 'code']);

        $teachers = User::role(['class-teacher', 'subject-teacher'])
            ->when(!$user->isSuperAdmin(), fn ($q) => $q->where('school_id', $user->school_id))
            ->active()->orderBy('name')->get(['id', 'name']);

        $sections = Section::query()
            ->whereHas('schoolClass', function ($q) use ($user) {
                $q->when(!$user->isSuperAdmin(), fn ($q2) => $q2->where('school_id', $user->school_id));
            })
            ->active()->get(['id', 'name', 'school_class_id']);

        $sessions = AcademicSession::active()->orderByDesc('start_date')->get(['id', 'name', 'is_current']);

        return Inertia::render('TeacherAssignments/Index', [
            'assignments' => $assignments,
            'classes' => $classes,
            'sections' => $sections,
            'subjects' => $subjects,
            'teachers' => $teachers,
            'sessions' => $sessions,
            'currentSession' => $currentSession,
            'filters' => $request->only(['search', 'class_id', 'section_id', 'subject_id']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'subject_id' => ['required', 'exists:subjects,id'],
            'school_class_id' => ['required', 'exists:school_classes,id'],
            'section_id' => ['required', 'exists:sections,id'],
            'academic_session_id' => ['required', 'exists:academic_sessions,id'],
            'is_active' => ['boolean'],
        ]);

        // Check for duplicate assignment
        $exists = SubjectTeacher::where('user_id', $validated['user_id'])
            ->where('subject_id', $validated['subject_id'])
            ->where('school_class_id', $validated['school_class_id'])
            ->where('section_id', $validated['section_id'])
            ->where('academic_session_id', $validated['academic_session_id'])
            ->exists();

        if ($exists) {
            return redirect()->back()->with('error', 'This teacher is already assigned to this subject/section.');
        }

        SubjectTeacher::create($validated);

        return redirect()->back()->with('success', 'Teacher assigned successfully.');
    }

    public function destroy(int $id): RedirectResponse
    {
        $assignment = SubjectTeacher::findOrFail($id);

        $user = request()->user();
        if (!$user->isSuperAdmin()) {
            $class = SchoolClass::findOrFail($assignment->school_class_id);
            if ($class->school_id !== $user->school_id) {
                abort(403, 'You can only manage assignments for your school.');
            }
        }

        $assignment->delete();

        return redirect()->back()->with('success', 'Teacher assignment removed successfully.');
    }
}
