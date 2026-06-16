<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Exam extends Model
{
    use HasFactory, SoftDeletes, LogsActivity;

    protected $fillable = [
        'name', 'slug', 'exam_type_id', 'academic_session_id', 'grading_scale_id',
        'term', 'combine_previous_terms', 'term_weights',
        'start_date', 'end_date', 'description',
        'total_marks', 'passing_marks', 'passing_percentage',
        'min_subjects_to_pass', 'main_subjects_must_pass', 'all_subjects_must_pass',
        'grace_marks', 'grace_marks_max_subjects',
        'position_calculation', 'passing_rules', 'combination_rules',
        'apply_to_all_schools', 'applicable_class_ids',
        'status', 'marks_entry_deadline', 'is_locked', 'created_by',
        'exam_controller_id', 'results_published_at',
        'post_submit_edit_policy', 'post_submit_edit_scope',
    ];

    /** Default weights used when admin enables combine but leaves them blank. */
    public const DEFAULT_TERM_WEIGHTS = ['first' => 25, 'second' => 25, 'final' => 50];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'marks_entry_deadline' => 'datetime',
            'results_published_at' => 'datetime',
            'total_marks' => 'decimal:2',
            'passing_marks' => 'decimal:2',
            'passing_percentage' => 'decimal:2',
            'grace_marks' => 'decimal:2',
            'main_subjects_must_pass' => 'boolean',
            'all_subjects_must_pass' => 'boolean',
            'apply_to_all_schools' => 'boolean',
            'is_locked' => 'boolean',
            'passing_rules' => 'array',
            'combination_rules' => 'array',
            'applicable_class_ids' => 'array',
            'combine_previous_terms' => 'boolean',
            'term_weights' => 'array',
            'post_submit_edit_scope' => 'array',
        ];
    }

    /**
     * May a teacher edit marks that have already been submitted for this
     * (subject, section)? The single source of truth — controllers and the
     * Vue entry page both consult this via the controller.
     *
     *   - 'none'     → never
     *   - 'all'      → always
     *   - 'specific' → only if the (school_class_id, section_id) pair
     *                  appears in post_submit_edit_scope
     *
     * Once results are published the exam is frozen regardless of policy
     * (changing marks would silently shift grades students have already seen).
     */
    public function allowsPostSubmitEdit(int $schoolClassId, int $sectionId): bool
    {
        if ($this->isResultsPublished()) return false;
        $policy = $this->post_submit_edit_policy ?? 'none';
        if ($policy === 'all') return true;
        if ($policy !== 'specific') return false;

        $scope = collect($this->post_submit_edit_scope ?? []);
        return $scope->contains(function ($entry) use ($schoolClassId, $sectionId) {
            return (int) ($entry['school_class_id'] ?? 0) === $schoolClassId
                && (int) ($entry['section_id'] ?? 0) === $sectionId;
        });
    }

    protected static function booted(): void
    {
        static::creating(function ($model) {
            if (empty($model->slug)) {
                $model->slug = Str::slug($model->name);
            }
        });
        static::updating(function ($model) {
            if ($model->isDirty('name') && !$model->isDirty('slug')) {
                $model->slug = Str::slug($model->name);
            }
        });
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['name', 'status'])->logOnlyDirty();
    }

    public function examType()
    {
        return $this->belongsTo(ExamType::class);
    }

    public function academicSession()
    {
        return $this->belongsTo(AcademicSession::class);
    }

    /** Optional teacher designated as Exam Controller for THIS exam. */
    public function examController()
    {
        return $this->belongsTo(User::class, 'exam_controller_id');
    }

    public function gradingScale()
    {
        return $this->belongsTo(GradingScale::class);
    }

    public function schools()
    {
        return $this->belongsToMany(School::class, 'exam_schools');
    }

    public function examSubjects()
    {
        return $this->hasMany(ExamSubject::class);
    }

    public function marks()
    {
        return $this->hasMany(Mark::class);
    }

    public function results()
    {
        return $this->hasMany(Result::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isPublished(): bool
    {
        return $this->status !== 'draft';
    }

    public function isMarksEntryOpen(): bool
    {
        return $this->status === 'marks_entry' && !$this->is_locked;
    }

    /**
     * Has the DDO/Principal officially announced the results?
     * Used by the Family Portal and the public result-lookup page to gate
     * visibility — students don't see their own results until this is set.
     */
    public function isResultsPublished(): bool
    {
        return $this->results_published_at !== null;
    }

    public function scopePublished($query)
    {
        return $query->where('status', '!=', 'draft');
    }

    /**
     * Scope: narrow exams to those a TEACHER actually teaches in.
     *
     * - class-teacher: exam has at least one exam_subjects row whose
     *   school_class_id matches one of the teacher's assigned sections'
     *   classes (User::classSections → school_class_id).
     * - subject-teacher: exam has at least one exam_subjects row matching
     *   any of the teacher's (school_class_id, subject_id) tuples in
     *   subject_teachers.
     * - both roles: union of the two above.
     * - super-admin / school-admin: pass through (use visibleToSchool for
     *   admin scoping).
     *
     * If a teacher has no assignments at all, the result is an empty set —
     * which is the right answer ("you teach nothing yet, you see nothing").
     */
    public function scopeForTeacher($query, \App\Models\User $user)
    {
        if ($user->isSuperAdmin() || $user->isSchoolAdmin()) {
            return $query;
        }

        $classIds = $user->classSections->pluck('school_class_id')->filter()->unique()->values();
        $subjectAssignments = $user->subjectTeachings ?? collect();

        // No assignments → no exams.
        if ($classIds->isEmpty() && $subjectAssignments->isEmpty()) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereHas('examSubjects', function ($q) use ($classIds, $subjectAssignments) {
            $q->where(function ($inner) use ($classIds, $subjectAssignments) {
                if ($classIds->isNotEmpty()) {
                    // Class-teacher branch: any subject for one of my classes
                    $inner->orWhereIn('school_class_id', $classIds->all());
                }
                foreach ($subjectAssignments as $a) {
                    if (!$a->school_class_id || !$a->subject_id) continue;
                    // Subject-teacher branch: exact (class, subject) tuple
                    $inner->orWhere(function ($t) use ($a) {
                        $t->where('school_class_id', $a->school_class_id)
                          ->where('subject_id', $a->subject_id);
                    });
                }
            });
        });
    }

    /**
     * Scope: only exams that are visible to the given school.
     *
     * Two conditions must hold (defense in depth):
     *   1. The school is in the exam_schools pivot — i.e. the DDO either
     *      picked this school explicitly OR turned on "apply to all schools"
     *      (which fans out the pivot).
     *   2. The exam has at least one exam_subjects row whose school_class_id
     *      belongs to a class at this school. This prevents a "Class 6
     *      district test" from showing up at a Nursery–5 primary school —
     *      that school's classes simply aren't in exam_subjects, so the
     *      whereHas misses.
     *
     * Pass null/0 to disable scoping (super-admin / DDO sees everything).
     */
    public function scopeVisibleToSchool($query, ?int $schoolId)
    {
        if (!$schoolId) {
            return $query;
        }

        return $query
            ->whereHas('schools', fn ($q) => $q->where('schools.id', $schoolId))
            ->whereHas(
                'examSubjects.schoolClass',
                fn ($q) => $q->where('school_id', $schoolId)
            );
    }
}
