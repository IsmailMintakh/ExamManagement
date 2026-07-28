<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * BoardExam — a container for one Board's (FBISE) result cycle for one
 * class of one school in one academic session.
 *
 * Every student in that class gets a `BoardResult` under this exam,
 * which in turn has one `BoardResultSubject` row per subject taken.
 */
class BoardExam extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'academic_session_id', 'school_class_id', 'school_id',
        'board_name', 'level', 'title', 'announced_on',
        'total_marks', 'pass_percentage', 'grading_scale_id', 'notes',
        'is_locked', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'announced_on'    => 'date',
            'total_marks'     => 'integer',
            'pass_percentage' => 'integer',
            'is_locked'       => 'boolean',
        ];
    }

    public function school()          { return $this->belongsTo(School::class); }
    public function schoolClass()     { return $this->belongsTo(SchoolClass::class); }
    public function academicSession() { return $this->belongsTo(AcademicSession::class); }
    public function creator()         { return $this->belongsTo(User::class, 'created_by'); }
    public function results()         { return $this->hasMany(BoardResult::class); }
    public function examSubjects()    { return $this->hasMany(BoardExamSubject::class); }
    public function gradingScale()    { return $this->belongsTo(GradingScale::class); }
}
