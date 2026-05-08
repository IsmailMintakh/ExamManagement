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
        'academic_session_id', 'date', 'timetable_entry_id',
        'original_teacher_id', 'substitute_teacher_id',
        'status', 'notes', 'score_breakdown', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'score_breakdown' => 'array',
        ];
    }

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
