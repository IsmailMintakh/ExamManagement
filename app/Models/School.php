<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class School extends Model
{
    use HasFactory, SoftDeletes, LogsActivity;

    protected $fillable = [
        'name', 'code', 'logo', 'address', 'phone', 'email',
        'website', 'principal_signature', 'principal_name',
        'school_stamp',
        'exam_officer_signature', 'exam_officer_name',
        'is_main', 'is_active', 'settings',
    ];

    /** Auto-serialize the computed logo URL so Vue can use school.logo_url. */
    protected $appends = ['logo_url'];

    protected function casts(): array
    {
        return [
            'is_main' => 'boolean',
            'is_active' => 'boolean',
            'settings' => 'array',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['name', 'code', 'is_active'])->logOnlyDirty();
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function principal()
    {
        return $this->hasOne(User::class)->whereHas('roles', fn ($q) => $q->where('name', 'school-admin'));
    }

    public function classes()
    {
        return $this->hasMany(SchoolClass::class);
    }

    public function students()
    {
        return $this->hasMany(Student::class);
    }

    public function exams()
    {
        return $this->belongsToMany(Exam::class, 'exam_schools');
    }

    /**
     * Resolves the logo URL via the public disk's configured URL prefix
     * (controlled by FILESYSTEM_PUBLIC_URL_PREFIX in .env). On shared hosts
     * where the public/storage symlink is broken, this points at our
     * Laravel-served /uploads/{path} or /storage/{path} fallback route
     * instead of the symlink, so logos always render.
     */
    public function getLogoUrlAttribute(): ?string
    {
        return $this->logo ? \Illuminate\Support\Facades\Storage::disk('public')->url($this->logo) : null;
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
