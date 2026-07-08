<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarkSnapshot extends Model
{
    protected $fillable = [
        'exam_id', 'subject_id', 'section_id', 'school_class_id',
        'taken_at', 'taken_by', 'trigger', 'payload', 'student_count', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'taken_at' => 'datetime',
        ];
    }

    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    public function takenBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'taken_by');
    }
}
