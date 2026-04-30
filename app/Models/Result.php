<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Result extends Model
{
    use SoftDeletes, LogsActivity;

    protected $fillable = [
        'exam_id', 'student_id', 'school_id', 'school_class_id',
        'section_id', 'academic_session_id',
        'total_marks', 'obtained_marks', 'percentage', 'grade', 'grade_point',
        'position', 'total_students', 'subjects_passed', 'subjects_failed',
        'is_passed', 'remarks', 'subject_results',
        'status', 'generated_by', 'generated_at',
        'submitted_by', 'submitted_at', 'finalized_by', 'finalized_at',
        'is_supplementary_eligible', 'supplementary_subjects',
        'supplementary_status', 'supplementary_at',
    ];

    protected function casts(): array
    {
        return [
            'total_marks' => 'decimal:2',
            'obtained_marks' => 'decimal:2',
            'percentage' => 'decimal:2',
            'grade_point' => 'decimal:2',
            'is_passed' => 'boolean',
            'subject_results' => 'array',
            'generated_at' => 'datetime',
            'submitted_at' => 'datetime',
            'finalized_at' => 'datetime',
            'is_supplementary_eligible' => 'boolean',
            'supplementary_subjects' => 'array',
            'supplementary_at' => 'datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['status'])->logOnlyDirty();
    }

    public function exam()
    {
        return $this->belongsTo(Exam::class);
    }

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function school()
    {
        return $this->belongsTo(School::class);
    }

    public function schoolClass()
    {
        return $this->belongsTo(SchoolClass::class);
    }

    public function section()
    {
        return $this->belongsTo(Section::class);
    }

    public function generatedBy()
    {
        return $this->belongsTo(User::class, 'generated_by');
    }

    public function submittedBy()
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function finalizedBy()
    {
        return $this->belongsTo(User::class, 'finalized_by');
    }

    public function scopePassed($query)
    {
        return $query->where('is_passed', true);
    }

    public function scopeFailed($query)
    {
        return $query->where('is_passed', false);
    }
}
