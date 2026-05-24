<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Daily teacher absence flag. The substitution engine reads this table to
 * find out who's out today and which timetable cells need a cover.
 *
 * Unique on (user_id, absent_on) so toggling absence is idempotent.
 */
class TeacherAbsence extends Model
{
    protected $fillable = [
        'user_id', 'academic_session_id', 'absent_on',
        'reason', 'from_time', 'absent_slot_ids', 'was_backdated', 'marked_by',
    ];

    protected function casts(): array
    {
        return [
            'absent_on' => 'date',
            'was_backdated' => 'boolean',
            'absent_slot_ids' => 'array',
        ];
    }

    public function isPartialDay(): bool
    {
        return !empty($this->from_time) || !empty($this->absent_slot_ids);
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

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function markedBy()
    {
        return $this->belongsTo(User::class, 'marked_by');
    }
}
