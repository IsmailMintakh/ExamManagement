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
        'published_at', 'last_amended_at',
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
            'published_at' => 'datetime',
            'last_amended_at' => 'datetime',
            'is_supplementary_eligible' => 'boolean',
            'supplementary_subjects' => 'array',
            'supplementary_at' => 'datetime',
        ];
    }

    /**
     * Visible-to-public scope: only results that have either been published
     * directly (per-row) OR whose parent exam has been published. Used by
     * the Family Portal and public result-lookup page.
     */
    public function scopePublished($query)
    {
        return $query->where(function ($q) {
            $q->whereNotNull('published_at')
              ->orWhereHas('exam', fn ($q2) => $q2->whereNotNull('results_published_at'));
        });
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

    /** Amendment history — most-recent first. Used by the Family Portal banner. */
    public function amendments()
    {
        return $this->hasMany(ResultAmendment::class)->latest();
    }

    public function academicSession()
    {
        return $this->belongsTo(AcademicSession::class);
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

    /**
     * Order results by the student's roll number, numerically — so "1, 2,
     * 3 … 9, 10, 11" instead of the lexical "1, 10, 11, 2, 3, …" you get
     * from a bare orderBy on a varchar column.
     *
     * Implemented as a correlated subquery instead of a JOIN so the scope
     * doesn't introduce column-name conflicts: Students has its own
     * `school_id`, `school_class_id`, `section_id` columns, and any
     * existing `where('section_id', …)` on the Result query would become
     * ambiguous the moment we joined Students in.
     */
    public function scopeOrderByRollNo($query)
    {
        $rollSub = "(select `roll_no` from `students` where `students`.`id` = `results`.`student_id` and `students`.`deleted_at` is null)";
        return $query
            ->orderBy('results.section_id')
            ->orderByRaw("LENGTH(COALESCE({$rollSub}, ''))")
            ->orderByRaw("{$rollSub}");
    }
}
