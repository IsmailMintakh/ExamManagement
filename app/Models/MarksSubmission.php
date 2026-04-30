<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MarksSubmission extends Model
{
    protected $fillable = [
        'exam_id', 'subject_id', 'school_class_id', 'section_id',
        'school_id', 'submitted_by', 'status', 'remarks',
        'submitted_at', 'verified_at', 'verified_by',
    ];

    protected function casts(): array
    {
        return [
            'submitted_at' => 'datetime',
            'verified_at' => 'datetime',
        ];
    }

    public function exam()
    {
        return $this->belongsTo(Exam::class);
    }

    public function subject()
    {
        return $this->belongsTo(Subject::class);
    }

    public function section()
    {
        return $this->belongsTo(Section::class);
    }

    public function submittedBy()
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function verifiedBy()
    {
        return $this->belongsTo(User::class, 'verified_by');
    }
}
