<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasFactory, Notifiable, HasRoles, SoftDeletes, LogsActivity;

    protected $fillable = [
        'name', 'email', 'password', 'phone', 'avatar', 'signature_image',
        'school_id', 'is_active', 'last_login_at',
    ];

    protected $hidden = ['password', 'remember_token'];

    /** Auto-serialize computed URLs so Vue can render them directly. */
    protected $appends = ['avatar_url', 'signature_url'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'email', 'phone', 'is_active', 'school_id'])
            ->logOnlyDirty();
    }

    public function school()
    {
        return $this->belongsTo(School::class);
    }

    /**
     * Browser push subscriptions — one per (user, device).
     * Loaded automatically by WebPushChannel.
     */
    public function pushSubscriptions()
    {
        return $this->hasMany(PushSubscription::class);
    }

    public function classSections()
    {
        return $this->hasMany(Section::class, 'class_teacher_id');
    }

    public function subjectTeachings()
    {
        return $this->hasMany(SubjectTeacher::class);
    }

    public function isSuperAdmin(): bool
    {
        return $this->hasRole('super-admin');
    }

    public function isSchoolAdmin(): bool
    {
        return $this->hasRole('school-admin');
    }

    public function isClassTeacher(): bool
    {
        return $this->hasRole('class-teacher');
    }

    public function isSubjectTeacher(): bool
    {
        return $this->hasRole('subject-teacher');
    }

    public function studentProfile()
    {
        return $this->hasOne(Student::class, 'user_id');
    }

    public function children()
    {
        return $this->hasMany(Student::class, 'parent_user_id');
    }

    public function isStudent(): bool
    {
        return $this->hasRole('student');
    }

    public function isParent(): bool
    {
        return $this->hasRole('parent');
    }

    public function getAvatarUrlAttribute(): ?string
    {
        return $this->avatar ? $this->publicAssetUrl($this->avatar) : null;
    }

    public function getSignatureUrlAttribute(): ?string
    {
        return $this->signature_image ? $this->publicAssetUrl($this->signature_image) : null;
    }

    /**
     * Build a public-disk URL against the current request host (instead of
     * the cached APP_URL). Lets uploads survive a domain change without
     * needing to rebuild the config cache.
     */
    protected function publicAssetUrl(string $path): string
    {
        $prefix = trim(env('FILESYSTEM_PUBLIC_URL_PREFIX', '/uploads'), '/');
        return url($prefix.'/'.ltrim($path, '/'));
    }

    /**
     * Absolute filesystem path of the signature image — used by DomPDF/mPDF
     * which need a file path (not a public URL) to embed the image into PDFs.
     */
    public function signaturePath(): ?string
    {
        if (!$this->signature_image) return null;
        $path = public_path('storage/'.$this->signature_image);
        return file_exists($path) ? $path : null;
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOfSchool($query, $schoolId)
    {
        return $query->where('school_id', $schoolId);
    }
}
