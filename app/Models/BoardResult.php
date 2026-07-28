<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * BoardResult — one student's Board mark sheet under one BoardExam.
 *
 * Aggregates on this row (total, percentage, grade, division, is_pass,
 * position) are ALL cached fields — they are recomputed by
 * BoardResultCalculatorService whenever any child subject row changes.
 * Reports and leaderboards read them directly without re-summing.
 */
class BoardResult extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'board_exam_id', 'student_id', 'board_roll_no',
        'total_obtained', 'total_max', 'percentage',
        'grade', 'division',
        'is_pass', 'is_supplementary', 'position',
        'remarks', 'entered_by', 'entered_at',
    ];

    protected function casts(): array
    {
        return [
            'total_obtained'   => 'decimal:2',
            'total_max'        => 'decimal:2',
            'percentage'       => 'decimal:2',
            'is_pass'          => 'boolean',
            'is_supplementary' => 'boolean',
            'entered_at'       => 'datetime',
        ];
    }

    public function boardExam() { return $this->belongsTo(BoardExam::class); }
    public function student()   { return $this->belongsTo(Student::class); }
    public function subjects()  { return $this->hasMany(BoardResultSubject::class); }
    public function enterer()   { return $this->belongsTo(User::class, 'entered_by'); }
}
