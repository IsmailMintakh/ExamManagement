<?php

namespace App\Policies;

use App\Models\School;
use App\Models\User;

class SchoolPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        if ($user->hasRole('super-admin')) {
            return true;
        }
        return null;
    }

    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('schools.view');
    }

    public function view(User $user, School $school): bool
    {
        return $user->hasPermissionTo('schools.view') && $user->school_id === $school->id;
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('schools.create');
    }

    public function update(User $user, School $school): bool
    {
        return $user->hasPermissionTo('schools.edit') && $user->school_id === $school->id;
    }

    public function delete(User $user, School $school): bool
    {
        // Defense in depth: even if a non-super-admin somehow had
        // schools.delete permission, they couldn't delete a school other
        // than their own. Super-admins bypass this via before().
        return $user->hasPermissionTo('schools.delete')
            && $user->school_id === $school->id;
    }
}
