<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExamSubject extends Model
{
    protected $fillable = [
        'exam_id', 'subject_id', 'teacher_id', 'school_class_id',
        'total_marks', 'passing_marks', 'exam_date', 'start_time', 'end_time',
        'excluded_section_ids',
    ];

    protected function casts(): array
    {
        return [
            'total_marks' => 'decimal:2',
            'passing_marks' => 'decimal:2',
            'exam_date' => 'date',
            'excluded_section_ids' => 'array',
        ];
    }

    /**
     * True when this exam-subject applies to the given section. Default
     * behaviour (excluded_section_ids null/empty) → applies to every
     * section of the class. Non-empty list → excludes those specific
     * sections. Anything outside this exam-subject's class is out of
     * scope and always returns false.
     */
    public function appliesToSection(int $sectionId, ?int $sectionClassId = null): bool
    {
        // Optional early-out: if the caller knows the section's class,
        // reject cross-class requests here so we don't need to hit the DB.
        if ($sectionClassId !== null && (int) $this->school_class_id !== $sectionClassId) {
            return false;
        }
        $excluded = $this->excluded_section_ids ?? [];
        return !in_array($sectionId, array_map('intval', $excluded), true);
    }

    public function exam()
    {
        return $this->belongsTo(Exam::class);
    }

    public function subject()
    {
        return $this->belongsTo(Subject::class);
    }

    public function schoolClass()
    {
        return $this->belongsTo(SchoolClass::class);
    }

    public function marks()
    {
        return $this->hasMany(Mark::class);
    }

    /**
     * Teacher assigned to this subject FOR this specific exam. NULL when
     * the school hasn't overridden the section-level SubjectTeacher for
     * this exam — the report layer falls back to that assignment.
     */
    public function teacher()
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }
}
