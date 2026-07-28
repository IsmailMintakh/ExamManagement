<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Per-subject template row under a BoardExam — defines the theory_max /
 * practical_max / passing that every student's entry form starts with.
 * Populated on exam creation (from the class's ClassSubject list) and
 * editable via the Subjects tab.
 */
class BoardExamSubject extends Model
{
    protected $fillable = [
        'board_exam_id', 'subject_id',
        'theory_max', 'practical_max', 'pass_percentage', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'theory_max'      => 'decimal:2',
            'practical_max'   => 'decimal:2',
            'pass_percentage' => 'decimal:2',
        ];
    }

    public function boardExam() { return $this->belongsTo(BoardExam::class); }
    public function subject()   { return $this->belongsTo(Subject::class); }
}
