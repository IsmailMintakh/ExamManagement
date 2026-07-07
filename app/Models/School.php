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
        'is_main', 'is_active', 'settings', 'cover_stage_policy',
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
        if (!$this->logo) return null;
        // Build against the current request host so the logo still loads
        // after a domain change (APP_URL may still be the old domain).
        $prefix = trim(env('FILESYSTEM_PUBLIC_URL_PREFIX', '/uploads'), '/');
        return url($prefix.'/'.ltrim($this->logo, '/'));
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Resolve the on-disk absolute path to the school's logo, checking every
     * plausible location. Returns null if nothing exists — templates use
     * this to decide between rendering <img> vs the initials placeholder.
     *
     * Why so many candidates: on Hostinger and other shared hosts the
     * public/storage symlink is fragile, deploys can strip it, and older
     * builds of this app uploaded logos under public/uploads/ directly.
     * Trying each path in order means the PDF renderer finds the file
     * regardless of which storage layout the deploy ended up with.
     */
    public function getLogoAbsolutePath(): ?string
    {
        if (empty($this->logo)) return null;
        $rel = ltrim($this->logo, '/');

        $candidates = [
            public_path('storage/' . $rel),           // standard storage:link symlink
            storage_path('app/public/' . $rel),       // real path behind the symlink
            public_path('uploads/' . $rel),           // legacy /uploads route target
            public_path($rel),                        // if stored directly under public/
            base_path('public_html/storage/' . $rel), // some cPanel layouts
            base_path('public_html/uploads/' . $rel),
        ];

        foreach ($candidates as $p) {
            if (is_string($p) && file_exists($p)) return $p;
        }
        return null;
    }

    /**
     * Generic resolver for any file-path column on the school (signature,
     * stamp, letterhead, …). Same multi-path lookup as logo. Templates use:
     *   $school->resolveAssetPath('principal_signature')
     */
    public function resolveAssetPath(string $column): ?string
    {
        $val = $this->{$column} ?? null;
        if (empty($val)) return null;
        $rel = ltrim((string) $val, '/');
        foreach ([
            public_path('storage/' . $rel),
            storage_path('app/public/' . $rel),
            public_path('uploads/' . $rel),
            public_path($rel),
            base_path('public_html/storage/' . $rel),
            base_path('public_html/uploads/' . $rel),
        ] as $p) {
            if (is_string($p) && file_exists($p)) return $p;
        }
        return null;
    }
}
