<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreUserRequest;
use App\Models\School;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', User::class);

        $user = $request->user();

        $users = User::query()
            ->when(!$user->isSuperAdmin(), function ($query) use ($user) {
                $query->where('school_id', $user->school_id);
            })
            ->when($request->has('search'), function ($query) use ($request) {
                $search = $request->input('search');
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%")
                      ->orWhere('phone', 'like', "%{$search}%");
                });
            })
            ->when($request->has('role'), function ($query) use ($request) {
                $query->role($request->input('role'));
            })
            ->when($request->has('school_id'), fn ($q) => $q->where('school_id', $request->input('school_id')))
            ->when($request->has('status'), function ($query) use ($request) {
                $query->where('is_active', $request->input('status') === 'active');
            })
            ->with(['school', 'roles'])
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        $schools = $user->isSuperAdmin() ? School::active()->orderBy('name')->get(['id', 'name']) : [];

        return Inertia::render('Users/Index', [
            'users' => $users,
            'schools' => $schools,
            'roles' => ['super-admin', 'school-admin', 'class-teacher', 'subject-teacher'],
            'filters' => $request->only(['search', 'role', 'school_id', 'status']),
        ]);
    }

    public function create(Request $request): Response|RedirectResponse
    {
        $this->authorize('create', User::class);

        $user = $request->user();

        $schools = $user->isSuperAdmin()
            ? School::active()->orderBy('name')->get(['id', 'name'])
            : School::where('id', $user->school_id)->get(['id', 'name']);

        // Prerequisite check: every role except super-admin needs to belong to
        // a school. Without one, the form would have no school to pick.
        // Super-admin (DDO) is the exception — they live above schools.
        if ($schools->isEmpty()) {
            return redirect()->route('schools.create')
                ->with('warning', 'Add a school first — every Principal, class teacher and subject teacher belongs to one.');
        }

        $availableRoles = $user->isSuperAdmin()
            ? ['super-admin', 'school-admin', 'class-teacher', 'subject-teacher']
            : ['class-teacher', 'subject-teacher'];

        return Inertia::render('Users/Create', [
            'schools' => $schools,
            'roles' => $availableRoles,
        ]);
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $role = $data['role'];
        unset($data['role']);

        // School admins can only create users for their own school
        $currentUser = $request->user();
        if ($currentUser->isSchoolAdmin()) {
            $data['school_id'] = $currentUser->school_id;
            if (in_array($role, ['super-admin', 'school-admin'])) {
                abort(403, 'You cannot assign this role.');
            }
        }

        if (isset($data['avatar']) && $request->hasFile('avatar')) {
            $data['avatar'] = $request->file('avatar')->store('users/avatars', 'public');
        } else {
            unset($data['avatar']);
        }

        $data['password'] = Hash::make($data['password']);

        $user = User::create($data);
        $user->assignRole($role);
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        return redirect()->route('users.index')->with('success', 'User created successfully.');
    }

    public function show(User $user): RedirectResponse
    {
        return redirect()->route('users.edit', $user);
    }

    public function edit(User $user): Response
    {
        $this->authorize('update', $user);

        $currentUser = request()->user();

        $schools = $currentUser->isSuperAdmin()
            ? School::active()->orderBy('name')->get(['id', 'name'])
            : School::where('id', $currentUser->school_id)->get(['id', 'name']);

        $availableRoles = $currentUser->isSuperAdmin()
            ? ['super-admin', 'school-admin', 'class-teacher', 'subject-teacher']
            : ['class-teacher', 'subject-teacher'];

        $user->load('roles');

        return Inertia::render('Users/Create', [
            'user' => $user,
            'schools' => $schools,
            'roles' => $availableRoles,
        ]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $this->authorize('update', $user);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email,' . $user->id],
            'password' => ['nullable', 'confirmed', Password::defaults()],
            'phone' => ['nullable', 'string', 'max:20'],
            'avatar' => ['nullable', 'image', 'mimes:jpg,jpeg,png', 'max:1024'],
            'school_id' => ['nullable', 'exists:schools,id'],
            'is_active' => ['boolean'],
            'role' => ['required', 'string', 'in:super-admin,school-admin,class-teacher,subject-teacher'],
        ]);

        $role = $validated['role'];
        unset($validated['role']);

        $currentUser = $request->user();
        if ($currentUser->isSchoolAdmin()) {
            $validated['school_id'] = $currentUser->school_id;
            if (in_array($role, ['super-admin', 'school-admin'])) {
                abort(403, 'You cannot assign this role.');
            }
        }

        if ($request->hasFile('avatar')) {
            if ($user->avatar) {
                Storage::disk('public')->delete($user->avatar);
            }
            $validated['avatar'] = $request->file('avatar')->store('users/avatars', 'public');
        } else {
            unset($validated['avatar']);
        }

        if (!empty($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        } else {
            unset($validated['password']);
        }

        $user->update($validated);
        $user->syncRoles([$role]);

        return redirect()->route('users.index')->with('success', 'User updated successfully.');
    }

    public function destroy(User $user): RedirectResponse
    {
        $this->authorize('delete', $user);

        $actor = request()->user();
        if ($user->id === $actor->id) {
            return redirect()->back()->with('error', 'You cannot delete your own account.');
        }

        try {
            $sectionsFreed = 0;
            $subjectAssignmentsRemoved = 0;

            \DB::transaction(function () use ($user, &$sectionsFreed, &$subjectAssignmentsRemoved) {
                // Auto-detach load-bearing relationships so the soft-delete
                // doesn't leave broken FK references behind. Earlier the
                // controller blocked the delete when these existed, but in
                // practice admins want the user gone — they'll reassign
                // sections later. Strip first, delete second.

                // Sections where this user is the assigned class teacher —
                // null the FK so the section keeps existing without the user.
                $sectionsFreed = \App\Models\Section::where('class_teacher_id', $user->id)
                    ->update(['class_teacher_id' => null]);

                // Subject-teaching pivot rows — drop them entirely; they're
                // pure assignment records with no other meaning.
                $subjectAssignmentsRemoved = \App\Models\SubjectTeacher::where('user_id', $user->id)
                    ->delete();

                // Strip role/permission attachments so a future restore
                // wouldn't accidentally bring back stale access.
                $user->syncRoles([]);
                $user->syncPermissions([]);

                $user->delete();
            });
            app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

            $detail = [];
            if ($sectionsFreed > 0) $detail[] = "{$sectionsFreed} section(s) had this user removed as class teacher";
            if ($subjectAssignmentsRemoved > 0) $detail[] = "{$subjectAssignmentsRemoved} subject assignment(s) removed";
            $msg = 'User deleted successfully.' . (empty($detail) ? '' : ' (' . implode(', ', $detail) . '.)');

            return redirect()->route('users.index')->with('success', $msg);
        } catch (\Throwable $e) {
            \Log::error('User delete failed', [
                'target_user_id' => $user->id,
                'message' => $e->getMessage(),
                'trace_top' => $e->getFile() . ':' . $e->getLine(),
                'actor_user_id' => $actor->id,
            ]);
            $isAdmin = $actor->isSuperAdmin() || $actor->isSchoolAdmin();
            $msg = $isAdmin
                ? 'Could not delete user. Database error: ' . $e->getMessage()
                : 'Could not delete the user — please try again. If this keeps happening, ask the administrator to check the server log.';
            return redirect()->back()->withErrors(['user' => $msg]);
        }
    }
}
