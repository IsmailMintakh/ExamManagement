<?php

namespace App\Policies;

use App\Models\GradingScale;
use App\Models\User;

class GradingScalePolicy
{
    public function before(User $user, string $ability): ?bool
    {
        if ($user->hasRole('super-admin')) return true;
        return null;
    }

    public function viewAny(User $user): bool { return $user->hasPermissionTo('grading.view'); }
    public function view(User $user, GradingScale $gradingScale): bool { return $user->hasPermissionTo('grading.view'); }
    public function create(User $user): bool { return $user->hasPermissionTo('grading.create'); }
    public function update(User $user, GradingScale $gradingScale): bool { return $user->hasPermissionTo('grading.edit'); }
    public function delete(User $user, GradingScale $gradingScale): bool { return $user->hasPermissionTo('grading.delete'); }
}
