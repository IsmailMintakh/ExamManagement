<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Subject extends Model
{
    use HasFactory, SoftDeletes, LogsActivity;

    protected $fillable = ['name', 'code', 'type', 'is_main', 'is_active', 'sort_order'];

    protected function casts(): array
    {
        return [
            'is_main' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['name', 'code', 'type', 'is_main'])->logOnlyDirty();
    }

    public function classSubjects()
    {
        return $this->hasMany(ClassSubject::class);
    }

    public function classes()
    {
        return $this->belongsToMany(SchoolClass::class, 'class_subjects');
    }

    public function teachers()
    {
        return $this->hasMany(SubjectTeacher::class);
    }

    // Qualify the column with the table name so this scope is safe when
    // chained off a belongsToMany (e.g. $class->subjects()->active()) where
    // the join with class_subjects also has an `is_active` column → bare
    // `where('is_active', ...)` raises "Column 'is_active' is ambiguous".
    public function scopeActive($query)
    {
        return $query->where('subjects.is_active', true);
    }

    public function scopeMain($query)
    {
        return $query->where('subjects.is_main', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('subjects.sort_order')->orderBy('subjects.name');
    }
}
