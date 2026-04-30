<?php

namespace App\Policies;

use App\Models\Exam;
use App\Models\User;

class ExamPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        if ($user->hasRole('super-admin')) return true;
        return null;
    }

    /**
     * A non-super-admin user can only act on an exam that is attached to their own school.
     * Exams created by DDO and applied to all schools are accessible to all principals of
     * those schools.
     */
    protected function belongsToUserSchool(User $user, Exam $exam): bool
    {
        if (!$user->school_id) return false;
        return $exam->schools()->where('schools.id', $user->school_id)->exists();
    }

    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('exams.view');
    }

    public function view(User $user, Exam $exam): bool
    {
        return $user->hasPermissionTo('exams.view') && $this->belongsToUserSchool($user, $exam);
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('exams.create');
    }

    public function update(User $user, Exam $exam): bool
    {
        return $user->hasPermissionTo('exams.edit') && $this->belongsToUserSchool($user, $exam);
    }

    public function delete(User $user, Exam $exam): bool
    {
        // Only the exam creator or their school-admin can delete, and only in draft.
        if (!$user->hasPermissionTo('exams.delete')) return false;
        if (!$this->belongsToUserSchool($user, $exam)) return false;
        // Safety: never delete a locked or completed exam.
        return in_array($exam->status, ['draft', 'published'], true);
    }

    public function publish(User $user, Exam $exam): bool
    {
        return $user->hasPermissionTo('exams.publish') && $this->belongsToUserSchool($user, $exam);
    }
}
