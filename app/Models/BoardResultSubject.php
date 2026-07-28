<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * BoardResultSubject — per-subject marks under a BoardResult.
 *
 * FBISE mark sheets split every subject into theory + practical (practical
 * = 0 when the subject has no practical component). Both are stored so
 * the printed mark sheet mirrors the gazette exactly.
 *
 * `grade`, `is_pass`, and `subject_position` are all cache fields —
 * the calculator service writes them whenever marks change so downstream
 * queries don't need to recompute.
 */
class BoardResultSubject extends Model
{
    use HasFactory;

    protected $fillable = [
        'board_result_id', 'subject_id',
        'theory_marks', 'practical_marks', 'total_marks',
        'theory_max', 'practical_max', 'max_marks',
        'grade', 'is_pass', 'is_absent', 'subject_position',
    ];

    protected function casts(): array
    {
        return [
            'theory_marks'    => 'decimal:2',
            'practical_marks' => 'decimal:2',
            'total_marks'     => 'decimal:2',
            'theory_max'      => 'decimal:2',
            'practical_max'   => 'decimal:2',
            'max_marks'       => 'decimal:2',
            'is_pass'         => 'boolean',
            'is_absent'       => 'boolean',
        ];
    }

    public function boardResult() { return $this->belongsTo(BoardResult::class); }
    public function subject()     { return $this->belongsTo(Subject::class); }
}
