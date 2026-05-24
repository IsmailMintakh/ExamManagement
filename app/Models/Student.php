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

    /**
     * Default sort students by roll number 1 → N (numeric, not alphabetical)
     * everywhere they're listed: marks entry, students index, award lists,
     * mark sheets, admit cards, etc. Roll numbers are stored as varchar so
     * a plain orderBy('roll_no') puts "10" before "2". The LENGTH() prefix
     * groups by digit count first, then sorts lexically — gives the natural
     * 1, 2, 3, …, 9, 10, 11 order without DB-specific CAST.
     *
     * Callers needing a different order (e.g. by name) can use ->reorder().
     */
    protected static function booted(): void
    {
        // Default students to numeric roll-number order (1, 2, 3, …, 10) on
        // every list across the app: marks entry, students index, mark sheets,
        // award lists, etc. We hook in via beforeQuery so we can inspect the
        // *final* query state and:
        //   - skip when GROUP BY / aggregate is set (only_full_group_by)
        //   - inject LENGTH() in front of an existing orderBy('roll_no') so
        //     the controller's sort becomes numeric instead of lexical
        //   - apply a sensible default when no order was specified
        //   - leave explicit non-roll orders (e.g. orderBy('name')) untouched
        static::addGlobalScope('byRollNo', function ($query) {
            $query->getQuery()->beforeQuery(function ($q) {
                if (!empty($q->groups) || !empty($q->aggregate)) {
                    return;
                }

                $isRollNoCol = fn ($o) => isset($o['column'])
                    && in_array($o['column'], ['roll_no', 'students.roll_no'], true);

                if (empty($q->orders)) {
                    $q->orderBy('students.school_class_id')
                        ->orderBy('students.section_id')
                        ->orderByRaw("LENGTH(COALESCE(students.roll_no, ''))")
                        ->orderBy('students.roll_no');
                    return;
                }

                $hasRollNo = collect($q->orders)->contains($isRollNoCol);
                if (!$hasRollNo) {
                    return;
                }

                // Insert LENGTH() right before the first roll_no orderBy so
                // strings sort numerically: "1" < "2" < … < "9" < "10".
                $rewritten = [];
                $injected = false;
                foreach ($q->orders as $o) {
                    if (!$injected && $isRollNoCol($o)) {
                        $rewritten[] = [
                            'type' => 'Raw',
                            'sql' => "LENGTH(COALESCE(students.roll_no, ''))",
                        ];
                        $injected = true;
                    }
                    $rewritten[] = $o;
                }
                $q->orders = $rewritten;
            });
        });
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

    /**
     * Photo URL. Tries the standard /storage/ symlink path first, but
     * the actual delivery is via Storage::url() which respects the disk
     * config — works whether the storage symlink exists or not as long
     * as the public disk is configured correctly.
     */
    public function getPhotoUrlAttribute(): ?string
    {
        if (!$this->photo) return null;
        // Build against the CURRENT request host instead of APP_URL so the
        // image still loads after a domain change (Storage::url() bakes in
        // whatever was in APP_URL when the cache was warmed — which was the
        // old `bhssno1skd.skardutech.com` for this project).
        $prefix = trim(env('FILESYSTEM_PUBLIC_URL_PREFIX', '/uploads'), '/');
        return url($prefix.'/'.ltrim($this->photo, '/'));
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
