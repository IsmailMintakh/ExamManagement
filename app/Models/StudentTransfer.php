<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class StudentTransfer extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'student_id',
        'from_school_id',
        'to_school_id',
        'from_class_id',
        'to_class_id',
        'from_section_id',
        'to_section_id',
        'type',
        'status',
        'reason',
        'rejection_reason',
        'initiated_by',
        'approved_by',
        'initiated_at',
        'approved_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => 'string',
            'type' => 'string',
            'initiated_at' => 'datetime',
            'approved_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function fromSchool()
    {
        return $this->belongsTo(School::class, 'from_school_id');
    }

    public function toSchool()
    {
        return $this->belongsTo(School::class, 'to_school_id');
    }

    public function fromClass()
    {
        return $this->belongsTo(SchoolClass::class, 'from_class_id');
    }

    public function toClass()
    {
        return $this->belongsTo(SchoolClass::class, 'to_class_id');
    }

    public function fromSection()
    {
        return $this->belongsTo(Section::class, 'from_section_id');
    }

    public function toSection()
    {
        return $this->belongsTo(Section::class, 'to_section_id');
    }

    public function initiatedBy()
    {
        return $this->belongsTo(User::class, 'initiated_by');
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
