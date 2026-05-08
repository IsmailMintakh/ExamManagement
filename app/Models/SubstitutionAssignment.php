<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One generated cover assignment. The auto-substitute algorithm produces
 * rows in 'suggested' state; an admin reviews and confirms (or declines).
 *
 * UNIQUE(date, timetable_entry_id) means re-running the algorithm doesn't
 * create duplicates — it updates in place.
 */
class SubstitutionAssignment extends Model
{
    protected $fillable = [
        'date', 'timetable_entry_id', 'original_teacher_id',
        'substitute_teacher_id', 'status', 'notes', 'created_by',
    ];

    protected function casts(): array
    {
        return ['date' => 'date'];
    }

    public function timetableEntry()
    {
        return $this->belongsTo(TimetableEntry::class);
    }

    public function originalTeacher()
    {
        return $this->belongsTo(User::class, 'original_teacher_id');
    }

    public function substituteTeacher()
    {
        return $this->belongsTo(User::class, 'substitute_teacher_id');
    }
}
