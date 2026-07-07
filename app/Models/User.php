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

    /**
     * Derives the teacher's "level" from their assignments.
     *
     *   - 'primary'  → user teaches / leads only primary-stage classes (ECD–5)
     *   - 'higher'   → user teaches / leads only non-primary classes (6–12)
     *   - 'mixed'    → user has assignments in both buckets
     *   - null       → user has no teaching assignments at all
     *
     * Drives the primary/higher separation across dashboards, exam-create
     * filters, marks-entry visibility, etc. Memoized so repeated calls
     * during one request don't re-query.
     */
    public function teacherStage(): ?string
    {
        static $cache = [];
        if (array_key_exists($this->id, $cache)) return $cache[$this->id];

        $classIds = collect()
            ->merge(SubjectTeacher::where('user_id', $this->id)
                ->where('is_active', true)
                ->pluck('school_class_id'))
            ->merge(\App\Models\Section::where('class_teacher_id', $this->id)
                ->where('is_active', true)
                ->pluck('school_class_id'))
            ->unique()
            ->filter()
            ->values();

        if ($classIds->isEmpty()) return $cache[$this->id] = null;

        $stages = \App\Models\SchoolClass::whereIn('id', $classIds)
            ->pluck('stage')
            ->unique();

        $hasPrimary = $stages->contains(fn ($s) => in_array($s, \App\Models\SchoolClass::PRIMARY_STAGES, true));
        $hasHigher  = $stages->contains(fn ($s) => $s && !in_array($s, \App\Models\SchoolClass::PRIMARY_STAGES, true));

        if ($hasPrimary && $hasHigher) return $cache[$this->id] = 'mixed';
        if ($hasPrimary)               return $cache[$this->id] = 'primary';
        if ($hasHigher)                return $cache[$this->id] = 'higher';
        return $cache[$this->id] = null;
    }

    /**
     * For DDO/super-admin: which school is the user currently "viewing"?
     *
     * DDO accounts span multiple schools. Rather than dropping a school
     * filter onto every page, we let the DDO pick a single school via a
     * topbar selector and stash the id in session. This helper resolves
     * the effective school id for the current request:
     *
     *   - super-admin → session('viewing_school_id') OR null (meaning "all schools")
     *   - everyone else → their own school_id
     *
     * Controllers that previously hard-coded `$user->school_id` should
     * route through this so the DDO selector works everywhere.
     */
    public function effectiveSchoolId(): ?int
    {
        if (!$this->isSuperAdmin()) return $this->school_id;
        $picked = session('viewing_school_id');
        return $picked ? (int) $picked : null;
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
        // Try every storage layout — same fallback list used by
        // School::getLogoAbsolutePath / Student::getPhotoAbsolutePath so
        // signatures resolve whether the deploy uses storage:link, direct
        // storage_path, legacy /uploads, or cPanel's public_html layout.
        $rel = ltrim($this->signature_image, '/');
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

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOfSchool($query, $schoolId)
    {
        return $query->where('school_id', $schoolId);
    }
}
