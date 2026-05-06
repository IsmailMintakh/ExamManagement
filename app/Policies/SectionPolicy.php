<?php

namespace App\Policies;

use App\Models\Section;
use App\Models\User;

/**
 * Section authorization. Mirrors StudentPolicy for cross-tenant safety:
 * a school-admin may only act on sections inside their own school, and a
 * class-teacher may only act on the sections they're assigned to (via
 * Section::class_teacher_id → User::classSections).
 *
 * The list page (SectionController::index) does its own school scoping, so
 * we only need to harden per-row checks here for view/update/delete to stop
 * direct PATCH-by-ID attempts at hopping schools.
 */
class SectionPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        if ($user->hasRole('super-admin')) return true;
        return null;
    }

    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('sections.view');
    }

    public function view(User $user, Section $section): bool
    {
        if (!$user->hasPermissionTo('sections.view')) return false;
        if ($user->hasRole('school-admin')) {
            return $user->school_id === $this->sectionSchoolId($section);
        }
        if ($user->hasRole('class-teacher')) {
            return $user->classSections->pluck('id')->contains($section->id);
        }
        return true;
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('sections.create');
    }

    public function update(User $user, Section $section): bool
    {
        if (!$user->hasPermissionTo('sections.edit')) return false;
        if ($user->hasRole('school-admin')) {
            return $user->school_id === $this->sectionSchoolId($section);
        }
        if ($user->hasRole('class-teacher')) {
            return $user->classSections->pluck('id')->contains($section->id);
        }
        return false;
    }

    public function delete(User $user, Section $section): bool
    {
        if (!$user->hasPermissionTo('sections.delete')) return false;
        if ($user->hasRole('school-admin')) {
            return $user->school_id === $this->sectionSchoolId($section);
        }
        return false;
    }

    /**
     * Resolve the school_id for a section via its school class.
     * Loads the relation lazily if it hasn't been hydrated yet so callers
     * don't have to remember to ->load('schoolClass') first.
     */
    protected function sectionSchoolId(Section $section): ?int
    {
        return $section->schoolClass?->school_id
            ?? $section->loadMissing('schoolClass')->schoolClass?->school_id;
    }
}
