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
    protected $fillable = ['user_id', 'absent_on', 'reason', 'from_time', 'marked_by'];

    protected function casts(): array
    {
        return ['absent_on' => 'date'];
    }

    public function isPartialDay(): bool
    {
        return !empty($this->from_time);
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
