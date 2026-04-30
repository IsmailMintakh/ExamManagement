<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GradingScaleEntry extends Model
{
    protected $fillable = [
        'grading_scale_id', 'grade', 'label', 'min_percentage',
        'max_percentage', 'grade_point', 'remark', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'min_percentage' => 'decimal:2',
            'max_percentage' => 'decimal:2',
            'grade_point' => 'decimal:2',
        ];
    }

    public function gradingScale()
    {
        return $this->belongsTo(GradingScale::class);
    }
}
