<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        if ($user->hasRole('super-admin')) return true;
        return null;
    }

    public function viewAny(User $user): bool { return $user->hasPermissionTo('users.view'); }
    public function view(User $user, User $model): bool
    {
        if (!$user->hasPermissionTo('users.view')) return false;
        if ($user->hasRole('school-admin')) return $user->school_id === $model->school_id;
        return false;
    }
    public function create(User $user): bool { return $user->hasPermissionTo('users.create'); }
    public function update(User $user, User $model): bool
    {
        if (!$user->hasPermissionTo('users.edit')) return false;
        if ($user->hasRole('school-admin')) return $user->school_id === $model->school_id;
        return false;
    }
    public function delete(User $user, User $model): bool
    {
        if ($user->id === $model->id) return false;
        if (!$user->hasPermissionTo('users.delete')) return false;
        if ($user->hasRole('school-admin')) return $user->school_id === $model->school_id;
        return false;
    }
}
