<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Mark extends Model
{
    use SoftDeletes, LogsActivity;

    protected $fillable = [
        'exam_id', 'exam_subject_id', 'student_id', 'school_id',
        'school_class_id', 'section_id', 'subject_id', 'academic_session_id',
        'marks_obtained', 'total_marks', 'grace_marks', 'is_absent',
        'remarks', 'status', 'entered_by', 'submitted_at',
        'is_supplementary', 'supplementary_marks',
    ];

    protected function casts(): array
    {
        return [
            'marks_obtained' => 'decimal:2',
            'total_marks' => 'decimal:2',
            'grace_marks' => 'decimal:2',
            'is_absent' => 'boolean',
            'submitted_at' => 'datetime',
            'is_supplementary' => 'boolean',
            'supplementary_marks' => 'decimal:2',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['marks_obtained', 'status'])->logOnlyDirty();
    }

    public function exam()
    {
        return $this->belongsTo(Exam::class);
    }

    public function examSubject()
    {
        return $this->belongsTo(ExamSubject::class);
    }

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function subject()
    {
        return $this->belongsTo(Subject::class);
    }

    public function section()
    {
        return $this->belongsTo(Section::class);
    }

    public function schoolClass()
    {
        return $this->belongsTo(SchoolClass::class);
    }

    public function enteredByUser()
    {
        return $this->belongsTo(User::class, 'entered_by');
    }

    public function getEffectiveMarksAttribute(): float
    {
        if ($this->is_absent) return 0;
        return $this->marks_obtained + $this->grace_marks;
    }

    public function getPercentageAttribute(): float
    {
        if ($this->total_marks == 0) return 0;
        return round(($this->effective_marks / $this->total_marks) * 100, 2);
    }

    public function scopeSubmitted($query)
    {
        return $query->where('status', 'submitted');
    }
}
