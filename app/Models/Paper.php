<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Paper extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'school_id',
        'subject_id',
        'school_class_id',
        'generated_by',
        'title',
        'exam_name',
        'duration_minutes',
        'total_marks',
        'instructions',
        'sections',
        'question_ids',
        'shuffle_enabled',
        'set_code',
    ];

    protected function casts(): array
    {
        return [
            'sections' => 'array',
            'question_ids' => 'array',
            'shuffle_enabled' => 'boolean',
            'total_marks' => 'decimal:2',
            'duration_minutes' => 'integer',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['title', 'subject_id', 'school_class_id', 'total_marks', 'set_code'])
            ->logOnlyDirty();
    }

    // ---------------- Relationships ----------------

    public function school()
    {
        return $this->belongsTo(School::class);
    }

    public function subject()
    {
        return $this->belongsTo(Subject::class);
    }

    public function schoolClass()
    {
        return $this->belongsTo(SchoolClass::class, 'school_class_id');
    }

    public function generator()
    {
        return $this->belongsTo(User::class, 'generated_by');
    }
}
