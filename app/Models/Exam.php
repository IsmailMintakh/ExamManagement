<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Exam extends Model
{
    use HasFactory, SoftDeletes, LogsActivity;

    protected $fillable = [
        'name', 'slug', 'exam_type_id', 'academic_session_id', 'grading_scale_id',
        'start_date', 'end_date', 'description',
        'total_marks', 'passing_marks', 'passing_percentage',
        'min_subjects_to_pass', 'main_subjects_must_pass', 'all_subjects_must_pass',
        'grace_marks', 'grace_marks_max_subjects',
        'position_calculation', 'passing_rules', 'combination_rules',
        'apply_to_all_schools', 'applicable_class_ids',
        'status', 'marks_entry_deadline', 'is_locked', 'created_by',
        'exam_controller_id',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'marks_entry_deadline' => 'datetime',
            'total_marks' => 'decimal:2',
            'passing_marks' => 'decimal:2',
            'passing_percentage' => 'decimal:2',
            'grace_marks' => 'decimal:2',
            'main_subjects_must_pass' => 'boolean',
            'all_subjects_must_pass' => 'boolean',
            'apply_to_all_schools' => 'boolean',
            'is_locked' => 'boolean',
            'passing_rules' => 'array',
            'combination_rules' => 'array',
            'applicable_class_ids' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function ($model) {
            if (empty($model->slug)) {
                $model->slug = Str::slug($model->name);
            }
        });
        static::updating(function ($model) {
            if ($model->isDirty('name') && !$model->isDirty('slug')) {
                $model->slug = Str::slug($model->name);
            }
        });
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['name', 'status'])->logOnlyDirty();
    }

    public function examType()
    {
        return $this->belongsTo(ExamType::class);
    }

    public function academicSession()
    {
        return $this->belongsTo(AcademicSession::class);
    }

    /** Optional teacher designated as Exam Controller for THIS exam. */
    public function examController()
    {
        return $this->belongsTo(User::class, 'exam_controller_id');
    }

    public function gradingScale()
    {
        return $this->belongsTo(GradingScale::class);
    }

    public function schools()
    {
        return $this->belongsToMany(School::class, 'exam_schools');
    }

    public function examSubjects()
    {
        return $this->hasMany(ExamSubject::class);
    }

    public function marks()
    {
        return $this->hasMany(Mark::class);
    }

    public function results()
    {
        return $this->hasMany(Result::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isPublished(): bool
    {
        return $this->status !== 'draft';
    }

    public function isMarksEntryOpen(): bool
    {
        return $this->status === 'marks_entry' && !$this->is_locked;
    }

    public function scopePublished($query)
    {
        return $query->where('status', '!=', 'draft');
    }
}
