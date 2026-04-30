<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Question extends Model
{
    use HasFactory, SoftDeletes, LogsActivity;

    protected $fillable = [
        'school_id',
        'subject_id',
        'school_class_id',
        'created_by',
        'type',
        'difficulty',
        'question_text',
        'options',
        'correct_answer',
        'explanation',
        'marks',
        'topic',
        'times_used',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'options' => 'array',
            'is_active' => 'boolean',
            'marks' => 'decimal:2',
            'times_used' => 'integer',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['type', 'difficulty', 'subject_id', 'school_class_id', 'topic', 'marks', 'is_active'])
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

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // ---------------- Scopes ----------------

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByType($query, $type)
    {
        return $query->where('type', $type);
    }

    public function scopeByDifficulty($query, $diff)
    {
        return $query->where('difficulty', $diff);
    }

    // ---------------- Methods ----------------

    public function isMcq(): bool
    {
        return $this->type === 'mcq';
    }

    /**
     * For MCQ, returns the index (0-based) of the correct option, or null.
     */
    public function getCorrectOptionIndex(): ?int
    {
        if (!$this->isMcq() || !is_array($this->options)) {
            return null;
        }

        foreach ($this->options as $index => $option) {
            if (!empty($option['is_correct'])) {
                return $index;
            }
        }

        return null;
    }
}
