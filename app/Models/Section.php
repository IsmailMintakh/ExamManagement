<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Section extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'school_class_id', 'name', 'slug', 'class_teacher_id', 'capacity', 'is_active',
        'timetable_locked', 'timetable_locked_at', 'timetable_locked_by',
    ];

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

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'timetable_locked' => 'boolean',
            'timetable_locked_at' => 'datetime',
        ];
    }

    public function timetableLockedBy()
    {
        return $this->belongsTo(User::class, 'timetable_locked_by');
    }

    public function timetableEntries()
    {
        return $this->hasMany(TimetableEntry::class);
    }

    public function schoolClass()
    {
        return $this->belongsTo(SchoolClass::class);
    }

    public function classTeacher()
    {
        return $this->belongsTo(User::class, 'class_teacher_id');
    }

    public function students()
    {
        return $this->hasMany(Student::class);
    }

    public function subjectTeachers()
    {
        return $this->hasMany(SubjectTeacher::class);
    }

    public function getFullNameAttribute(): string
    {
        return $this->schoolClass->name . ' - ' . $this->name;
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
