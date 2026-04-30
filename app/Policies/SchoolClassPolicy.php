<?php

namespace App\Policies;

use App\Models\SchoolClass;
use App\Models\User;

class SchoolClassPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        if ($user->hasRole('super-admin')) return true;
        return null;
    }

    public function viewAny(User $user): bool { return $user->hasPermissionTo('classes.view'); }
    public function view(User $user, SchoolClass $schoolClass): bool
    {
        if ($user->hasPermissionTo('classes.view')) {
            return !$user->school_id || $user->school_id === $schoolClass->school_id;
        }
        return false;
    }
    public function create(User $user): bool { return $user->hasPermissionTo('classes.create'); }
    public function update(User $user, SchoolClass $schoolClass): bool
    {
        return $user->hasPermissionTo('classes.edit') && (!$user->school_id || $user->school_id === $schoolClass->school_id);
    }
    public function delete(User $user, SchoolClass $schoolClass): bool
    {
        return $user->hasPermissionTo('classes.delete') && (!$user->school_id || $user->school_id === $schoolClass->school_id);
    }
}
