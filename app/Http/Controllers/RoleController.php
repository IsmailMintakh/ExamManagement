<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    protected function ensureSuperAdmin(): void
    {
        abort_unless(request()->user()?->can('roles.manage'), 403, 'You do not have permission to manage roles.');
    }

    public function index(): Response
    {
        $this->ensureSuperAdmin();

        $roles = Role::withCount('users', 'permissions')->orderBy('name')->get()->map(fn ($r) => [
            'id' => $r->id,
            'name' => $r->name,
            'label' => $this->roleLabel($r->name),
            'description' => $this->roleDescription($r->name),
            'users_count' => $r->users_count,
            'permissions_count' => $r->permissions_count,
            'is_system' => in_array($r->name, ['super-admin', 'school-admin', 'class-teacher', 'subject-teacher', 'student', 'parent']),
        ]);

        $totalPermissions = Permission::count();
        $totalUsers = User::count();

        return Inertia::render('Roles/Index', [
            'roles' => $roles,
            'stats' => [
                'totalRoles' => $roles->count(),
                'totalPermissions' => $totalPermissions,
                'totalUsers' => $totalUsers,
            ],
        ]);
    }

    public function show(Role $role): Response
    {
        $this->ensureSuperAdmin();
        $permissions = Permission::orderBy('name')->get();

        // Group by module (first part of name before dot)
        $grouped = $permissions->groupBy(fn ($p) => explode('.', $p->name)[0])
            ->map(fn ($items, $module) => [
                'module' => $module,
                'label' => ucwords(str_replace('-', ' ', $module)),
                'permissions' => $items->map(fn ($p) => [
                    'id' => $p->id,
                    'name' => $p->name,
                    'action' => explode('.', $p->name)[1] ?? $p->name,
                ])->values(),
            ])
            ->values();

        $rolePermissionIds = $role->permissions->pluck('id')->toArray();

        $users = $role->users()->with('school:id,name')->latest()->take(20)->get()->map(fn ($u) => [
            'id' => $u->id,
            'name' => $u->name,
            'email' => $u->email,
            'school' => $u->school?->name,
        ]);

        return Inertia::render('Roles/Show', [
            'role' => [
                'id' => $role->id,
                'name' => $role->name,
                'label' => $this->roleLabel($role->name),
                'description' => $this->roleDescription($role->name),
                'is_system' => in_array($role->name, ['super-admin', 'school-admin', 'class-teacher', 'subject-teacher', 'student', 'parent']),
            ],
            'permissionGroups' => $grouped,
            'rolePermissionIds' => $rolePermissionIds,
            'users' => $users,
            'usersCount' => $role->users()->count(),
        ]);
    }

    public function updatePermissions(Request $request, Role $role): RedirectResponse
    {
        $this->ensureSuperAdmin();

        // Don't allow editing super-admin permissions (it's the master role)
        if ($role->name === 'super-admin') {
            return redirect()->back()->with('error', 'Super admin permissions cannot be modified.');
        }

        $validated = $request->validate([
            'permission_ids' => ['array'],
            'permission_ids.*' => ['integer', 'exists:permissions,id'],
        ]);

        $permissions = Permission::whereIn('id', $validated['permission_ids'] ?? [])->get();
        $role->syncPermissions($permissions);

        // Spatie caches every user's effective permissions. Without an
        // explicit forget after syncPermissions(), users who already have
        // this role keep seeing the OLD permission set until the cache
        // TTL expires (24h by default). That was why the certificate
        // button stayed hidden for principals even after admin granted
        // certificates.view / certificates.generate to the school-admin
        // role — the DB was updated but the browser session saw stale.
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        // Log activity
        activity()
            ->performedOn($role)
            ->causedBy($request->user())
            ->withProperties(['permissions' => $permissions->pluck('name')->toArray()])
            ->log('Updated role permissions');

        return redirect()->back()->with('success', "Permissions updated for {$this->roleLabel($role->name)}.");
    }

    public function assignUsers(Request $request): Response
    {
        $this->ensureSuperAdmin();

        $users = User::with('roles', 'school:id,name')
            ->when($request->filled('search'), function ($q) use ($request) {
                $search = $request->input('search');
                $q->where(function ($q2) use ($search) {
                    $q2->where('name', 'like', "%{$search}%")
                       ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString();

        $roles = Role::orderBy('name')->get(['id', 'name']);

        return Inertia::render('Roles/AssignUsers', [
            'users' => $users,
            'roles' => $roles->map(fn ($r) => [
                'id' => $r->id,
                'name' => $r->name,
                'label' => $this->roleLabel($r->name),
            ]),
        ]);
    }

    public function syncUserRoles(Request $request, User $user): RedirectResponse
    {
        $this->ensureSuperAdmin();

        $validated = $request->validate([
            'role_names' => ['array'],
            'role_names.*' => ['string', 'exists:roles,name'],
        ]);

        // Prevent removing super-admin from self
        $currentUser = $request->user();
        if ($currentUser->id === $user->id && $currentUser->hasRole('super-admin') && !in_array('super-admin', $validated['role_names'] ?? [])) {
            return redirect()->back()->with('error', 'You cannot remove the super-admin role from yourself.');
        }

        $user->syncRoles($validated['role_names'] ?? []);

        activity()
            ->performedOn($user)
            ->causedBy($request->user())
            ->withProperties(['roles' => $validated['role_names'] ?? []])
            ->log('Updated user roles');

        return redirect()->back()->with('success', "Roles updated for {$user->name}.");
    }

    protected function roleLabel(string $name): string
    {
        return [
            'super-admin' => 'Super Admin (DDO)',
            'school-admin' => 'School Admin (Principal)',
            'class-teacher' => 'Class Teacher',
            'subject-teacher' => 'Subject Teacher',
            'student' => 'Student',
            'parent' => 'Parent',
        ][$name] ?? ucwords(str_replace('-', ' ', $name));
    }

    protected function roleDescription(string $name): string
    {
        return [
            'super-admin' => 'Full system access. Can manage all schools, users, exams, and approve final results.',
            'school-admin' => 'Manages a single school. Assigns teachers, generates results, submits to DDO.',
            'class-teacher' => 'Manages students of assigned section. Can enter co-curricular marks.',
            'subject-teacher' => 'Enters marks for subjects assigned to them in specific classes/sections.',
            'student' => 'Read-only access to own results, grades, and academic history.',
            'parent' => 'Read-only access to children\'s results and academic progress.',
        ][$name] ?? '';
    }
}
