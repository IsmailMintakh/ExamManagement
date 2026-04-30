<?php

namespace App\Policies;

use App\Models\AcademicSession;
use App\Models\User;

class AcademicSessionPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        if ($user->hasRole('super-admin')) return true;
        return null;
    }

    public function viewAny(User $user): bool { return $user->hasPermissionTo('sessions.view'); }
    public function view(User $user, AcademicSession $session): bool { return $user->hasPermissionTo('sessions.view'); }
    public function create(User $user): bool { return $user->hasPermissionTo('sessions.create'); }
    public function update(User $user, AcademicSession $session): bool { return $user->hasPermissionTo('sessions.edit'); }
    public function delete(User $user, AcademicSession $session): bool { return $user->hasPermissionTo('sessions.delete'); }
}
