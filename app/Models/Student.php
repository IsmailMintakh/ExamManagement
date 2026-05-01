<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Student extends Model
{
    use HasFactory, SoftDeletes, LogsActivity;

    protected $fillable = [
        'admission_no', 'roll_no', 'name', 'father_name', 'mother_name',
        'guardian_phone', 'date_of_birth', 'gender', 'photo', 'address',
        'blood_group', 'religion', 'cnic',
        'school_id', 'school_class_id', 'previous_class_id', 'section_id',
        'academic_session_id', 'status', 'promotion_status', 'promoted_at',
        'is_transferred', 'transferred_from_school_id',
        'user_id', 'parent_user_id',
    ];

    /**
     * Always serialize the computed `photo_url` so Vue can show
     * <img :src="student.photo_url"> without per-controller wiring.
     */
    protected $appends = ['photo_url'];

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'promoted_at' => 'date',
            'is_transferred' => 'boolean',
        ];
    }

    public function previousClass()
    {
        return $this->belongsTo(SchoolClass::class, 'previous_class_id');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['name', 'admission_no', 'status'])->logOnlyDirty();
    }

    public function school()
    {
        return $this->belongsTo(School::class);
    }

    public function schoolClass()
    {
        return $this->belongsTo(SchoolClass::class);
    }

    public function section()
    {
        return $this->belongsTo(Section::class);
    }

    public function academicSession()
    {
        return $this->belongsTo(AcademicSession::class);
    }

    public function marks()
    {
        return $this->hasMany(Mark::class);
    }

    public function results()
    {
        return $this->hasMany(Result::class);
    }

    public function transfers()
    {
        return $this->hasMany(StudentTransfer::class);
    }

    public function transferredFromSchool()
    {
        return $this->belongsTo(School::class, 'transferred_from_school_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function parentUser()
    {
        return $this->belongsTo(User::class, 'parent_user_id');
    }

    public function getPhotoUrlAttribute(): ?string
    {
        return $this->photo ? asset('storage/' . $this->photo) : null;
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeOfSection($query, $sectionId)
    {
        return $query->where('section_id', $sectionId);
    }
}
