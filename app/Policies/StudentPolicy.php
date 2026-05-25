<?php

namespace App\Policies;

use App\Models\Student;
use App\Models\User;

class StudentPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        if ($user->hasRole('super-admin')) return true;
        return null;
    }

    public function viewAny(User $user): bool { return $user->hasPermissionTo('students.view'); }
    public function view(User $user, Student $student): bool
    {
        if (!$user->hasPermissionTo('students.view')) return false;
        if ($user->hasRole('school-admin')) return $user->school_id === $student->school_id;
        if ($user->hasRole('class-teacher')) {
            return $user->classSections->pluck('id')->contains($student->section_id);
        }
        return true;
    }
    public function create(User $user): bool { return $user->hasPermissionTo('students.create'); }
    public function update(User $user, Student $student): bool
    {
        if (!$user->hasPermissionTo('students.edit')) return false;
        if ($user->hasRole('school-admin')) return $user->school_id === $student->school_id;
        if ($user->hasRole('class-teacher')) {
            return $user->classSections->pluck('id')->contains($student->section_id);
        }
        return false;
    }
    public function delete(User $user, Student $student): bool
    {
        if (!$user->hasPermissionTo('students.delete')) return false;
        if ($user->hasRole('school-admin')) return $user->school_id === $student->school_id;
        if ($user->hasRole('class-teacher')) {
            return $user->classSections->pluck('id')->contains($student->section_id);
        }
        return false;
    }
    public function import(User $user): bool { return $user->hasPermissionTo('students.import'); }
}
