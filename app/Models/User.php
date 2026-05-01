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
        'name', 'email', 'password', 'phone', 'avatar',
        'school_id', 'is_active', 'last_login_at',
    ];

    protected $hidden = ['password', 'remember_token'];

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
        return $this->avatar ? asset('storage/' . $this->avatar) : null;
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
