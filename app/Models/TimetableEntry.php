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
        'school_class_id', 'section_id', 'academic_session_id', 'weekday',
        'time_slot_id', 'subject_id', 'teacher_id', 'room',
    ];

    /** Limit to a single academic session — defaults to currentSession() if no id given. */
    public function scopeForSession($query, ?int $sessionId = null)
    {
        $sid = $sessionId ?? AcademicSession::currentSession()?->id;
        if (!$sid) return $query;
        return $query->where('academic_session_id', $sid);
    }

    public function academicSession()
    {
        return $this->belongsTo(AcademicSession::class, 'academic_session_id');
    }

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
