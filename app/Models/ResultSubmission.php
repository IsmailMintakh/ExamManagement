<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ResultSubmission extends Model
{
    protected $fillable = [
        'exam_id', 'school_id', 'school_class_id', 'section_id',
        'submitted_by', 'status', 'remarks', 'submitted_at',
        'reviewed_by', 'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    public function exam()
    {
        return $this->belongsTo(Exam::class);
    }

    public function school()
    {
        return $this->belongsTo(School::class);
    }

    public function submittedBy()
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function reviewedBy()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
