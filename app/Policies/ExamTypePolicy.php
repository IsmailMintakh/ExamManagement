<?php

namespace App\Policies;

use App\Models\ExamType;
use App\Models\User;

class ExamTypePolicy
{
    public function before(User $user, string $ability): ?bool
    {
        if ($user->hasRole('super-admin')) return true;
        return null;
    }

    public function viewAny(User $user): bool { return $user->hasPermissionTo('exam-types.view'); }
    public function view(User $user, ExamType $examType): bool { return $user->hasPermissionTo('exam-types.view'); }
    public function create(User $user): bool { return $user->hasPermissionTo('exam-types.create'); }
    public function update(User $user, ExamType $examType): bool { return $user->hasPermissionTo('exam-types.edit'); }
    public function delete(User $user, ExamType $examType): bool { return $user->hasPermissionTo('exam-types.delete'); }
}
