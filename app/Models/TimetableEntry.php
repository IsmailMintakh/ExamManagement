<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One cell in the master timetable matrix:
 *   (school_class_id, section_id, weekday, time_slot_id) → (subject, teacher, room)
 *
 * Each (section, weekday, slot) is unique by DB constraint, so an
 * upsert cleanly replaces an existing assignment.
 */
class TimetableEntry extends Model
{
    protected $fillable = [
        'school_class_id', 'section_id', 'weekday',
        'time_slot_id', 'subject_id', 'teacher_id', 'room',
    ];

    public function schoolClass()
    {
        return $this->belongsTo(SchoolClass::class);
    }

    public function section()
    {
        return $this->belongsTo(Section::class);
    }

    public function timeSlot()
    {
        return $this->belongsTo(TimeSlot::class);
    }

    public function subject()
    {
        return $this->belongsTo(Subject::class);
    }

    public function teacher()
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    public function substitutionAssignments()
    {
        return $this->hasMany(SubstitutionAssignment::class);
    }
}
