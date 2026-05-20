<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class SchoolClass extends Model
{
    use HasFactory, SoftDeletes, LogsActivity;

    protected $fillable = ['school_id', 'name', 'slug', 'numeric_name', 'sort_order', 'is_active'];

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

        // Default grade-order everywhere. Callers needing a different
        // order can use ->reorder('column') to clear and replace it.
        static::addGlobalScope('gradeOrder', function ($query) {
            $query->orderBy('school_classes.sort_order')
                ->orderBy('school_classes.name');
        });
    }

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['name', 'school_id'])->logOnlyDirty();
    }

    public function school()
    {
        return $this->belongsTo(School::class);
    }

    public function sections()
    {
        return $this->hasMany(Section::class);
    }

    public function subjects()
    {
        // Explicit select on the related table prevents `is_active` /
        // `id` ambiguity when callers chain ->where('is_active', true)
        // or use bare select columns. Both `subjects` and `class_subjects`
        // share an `is_active` column, and unqualified columns blow up.
        return $this->belongsToMany(Subject::class, 'class_subjects')
            ->select('subjects.*');
    }

    public function classSubjects()
    {
        return $this->hasMany(ClassSubject::class);
    }

    public function students()
    {
        return $this->hasMany(Student::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }
}
