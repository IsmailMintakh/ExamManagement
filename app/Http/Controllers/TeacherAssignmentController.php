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
        $this->syncCurriculum([(int) $validated['school_class_id'] => [(int) $validated['subject_id']]]);

        return redirect()->back()->with('success', 'Teacher assigned successfully.');
    }

    /**
     * Mirror new (class, subject) tuples into class_subjects so the exam
     * Create form (which reads its curriculum from the pivot) auto-ticks
     * everything that's actually being taught. Idempotent — uses upsert.
     */
    private function syncCurriculum(array $pairsByClass): void
    {
        $rows = [];
        foreach ($pairsByClass as $classId => $subjectIds) {
            foreach (array_unique($subjectIds) as $sid) {
                $rows[] = [
                    'school_class_id' => $classId,
                    'subject_id' => (int) $sid,
                    'is_active' => true,
                ];
            }
        }
        if (!empty($rows)) {
            \DB::table('class_subjects')->upsert(
                $rows,
                ['school_class_id', 'subject_id'],
                ['is_active'],
            );
        }
    }

    /**
     * Bulk assign / re-sync: pick a teacher once, post every (subject,
     * class, section) tuple they teach, save in one shot. Removes any
     * existing assignments for that teacher in the same session that are
     * NOT in the submitted list, so the form acts as "this is the full
     * picture for this teacher this session".
     */
    public function bulkStore(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->isSuperAdmin() || $user->isSchoolAdmin(), 403);

        $data = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'academic_session_id' => ['required', 'exists:academic_sessions,id'],
            'assignments' => ['present', 'array'],
            'assignments.*.subject_id' => ['required', 'exists:subjects,id'],
            'assignments.*.school_class_id' => ['required', 'exists:school_classes,id'],
            'assignments.*.section_id' => ['required', 'exists:sections,id'],
        ]);

        // School-scope guard for principals.
        if (!$user->isSuperAdmin()) {
            $target = User::find($data['user_id']);
            abort_unless($target && $target->school_id === $user->school_id, 403,
                'You can only manage teachers in your school.');
        }

        // Build a unique key set for the submitted assignments.
        $wanted = collect($data['assignments'])
            ->map(fn ($a) => $a['subject_id'].':'.$a['school_class_id'].':'.$a['section_id'])
            ->unique()->values();

        \DB::transaction(function () use ($data, $wanted) {
            // Delete existing assignments for this teacher+session that are
            // no longer in the wanted set.
            $existing = SubjectTeacher::where('user_id', $data['user_id'])
                ->where('academic_session_id', $data['academic_session_id'])->get();

            foreach ($existing as $row) {
                $key = $row->subject_id.':'.$row->school_class_id.':'.$row->section_id;
                if (!$wanted->contains($key)) {
                    $row->delete();
                }
            }

            // Insert any new tuples (firstOrCreate keeps it idempotent).
            foreach ($data['assignments'] as $a) {
                SubjectTeacher::firstOrCreate(
                    [
                        'user_id' => $data['user_id'],
                        'subject_id' => $a['subject_id'],
                        'school_class_id' => $a['school_class_id'],
                        'section_id' => $a['section_id'],
                        'academic_session_id' => $data['academic_session_id'],
                    ],
                    ['is_active' => true],
                );
            }
        });

        // Keep class_subjects curriculum in sync with what's actually taught.
        $pairsByClass = collect($data['assignments'])
            ->groupBy('school_class_id')
            ->map(fn ($rows) => $rows->pluck('subject_id')->all())
            ->all();
        $this->syncCurriculum($pairsByClass);

        return redirect()->back()->with('success',
            'Saved '.$wanted->count().' assignment(s) for this teacher.');
    }

    /** Current (subject, class, section) tuples for a teacher in the current session. */
    public function current(Request $request, int $user): \Illuminate\Http\JsonResponse
    {
        $actor = $request->user();
        abort_unless($actor->isSuperAdmin() || $actor->isSchoolAdmin(), 403);

        if (!$actor->isSuperAdmin()) {
            $target = User::find($user);
            abort_unless($target && $target->school_id === $actor->school_id, 403);
        }

        $sessionId = AcademicSession::currentSession()?->id;

        $rows = SubjectTeacher::where('user_id', $user)
            ->when($sessionId, fn ($q) => $q->where('academic_session_id', $sessionId))
            ->where('is_active', true)
            ->get(['subject_id', 'school_class_id', 'section_id'])
            ->map(fn ($r) => [
                'subject_id' => $r->subject_id,
                'school_class_id' => $r->school_class_id,
                'section_id' => $r->section_id,
            ]);

        return response()->json(['assignments' => $rows]);
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
