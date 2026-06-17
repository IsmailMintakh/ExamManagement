<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Primary-section overall assessment mark — out of 10, pass at 4.
 *
 * One row per (student, academic_session). The Annual Result calculation
 * for ECD–5 students reads this value and fails the student outright when
 * marks_obtained < passing_marks, even if every subject is passed.
 */
class AssessmentMark extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'student_id', 'academic_session_id', 'school_id',
        'school_class_id', 'section_id',
        'marks_obtained', 'marks_total', 'passing_marks',
        'remarks', 'entered_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'marks_obtained' => 'decimal:2',
            'marks_total' => 'decimal:2',
            'passing_marks' => 'decimal:2',
        ];
    }

    public function student()         { return $this->belongsTo(Student::class); }
    public function academicSession() { return $this->belongsTo(AcademicSession::class); }
    public function school()          { return $this->belongsTo(School::class); }
    public function schoolClass()     { return $this->belongsTo(SchoolClass::class); }
    public function section()         { return $this->belongsTo(Section::class); }
    public function enteredBy()       { return $this->belongsTo(User::class, 'entered_by_user_id'); }

    public function isPassed(): bool
    {
        return (float) $this->marks_obtained >= (float) $this->passing_marks;
    }
}
