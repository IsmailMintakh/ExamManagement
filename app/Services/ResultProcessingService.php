<?php

namespace App\Services;

use App\Models\Exam;
use App\Models\ExamSubject;
use App\Models\GradingScale;
use App\Models\Mark;
use App\Models\Result;
use App\Models\Student;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class ResultProcessingService
{
    /**
     * Generate results for all students in a section for a given exam.
     *
     * @return Collection<int, Result>
     */
    public function generateResults(Exam $exam, int $schoolClassId, int $sectionId): Collection
    {
        $exam->loadMissing(['gradingScale.entries']);

        $gradingScale = $exam->gradingScale;

        // Get all active students in this section
        $students = Student::where('school_class_id', $schoolClassId)
            ->where('section_id', $sectionId)
            ->where('status', 'active')
            ->get();

        if ($students->isEmpty()) {
            return new Collection();
        }

        // Get exam subjects for this class (with subject details for is_main check)
        $examSubjects = ExamSubject::where('exam_id', $exam->id)
            ->where('school_class_id', $schoolClassId)
            ->with('subject')
            ->get()
            ->keyBy('subject_id');

        if ($examSubjects->isEmpty()) {
            return new Collection();
        }

        // Get all submitted marks for these students in this exam/section
        $allMarks = Mark::where('exam_id', $exam->id)
            ->where('school_class_id', $schoolClassId)
            ->where('section_id', $sectionId)
            ->whereIn('student_id', $students->pluck('id'))
            ->where('status', 'submitted')
            ->with('subject')
            ->get()
            ->groupBy('student_id');

        $results = new Collection();

        DB::transaction(function () use (
            $exam, $schoolClassId, $sectionId, $students, $examSubjects,
            $allMarks, $gradingScale, &$results
        ) {
            // Delete any previously generated results for this exam/section
            Result::where('exam_id', $exam->id)
                ->where('school_class_id', $schoolClassId)
                ->where('section_id', $sectionId)
                ->forceDelete();

            $totalStudents = $students->count();

            foreach ($students as $student) {
                $studentMarks = $allMarks->get($student->id, collect());

                $result = $this->processStudentResult(
                    $exam,
                    $student,
                    $studentMarks,
                    $examSubjects,
                    $gradingScale,
                    $schoolClassId,
                    $sectionId,
                    $totalStudents
                );

                $results->push($result);
            }

            // Calculate positions after all results are created
            $this->calculatePositions(
                $results,
                $exam->position_calculation ?? 'section'
            );

            // Persist position and total_students updates
            foreach ($results as $result) {
                $result->save();
            }
        });

        return $results;
    }

    /**
     * Process and create a result record for a single student.
     */
    protected function processStudentResult(
        Exam $exam,
        Student $student,
        \Illuminate\Support\Collection $studentMarks,
        \Illuminate\Support\Collection $examSubjects,
        ?GradingScale $gradingScale,
        int $schoolClassId,
        int $sectionId,
        int $totalStudents
    ): Result {
        $subjectResults = [];
        $totalMarks = 0;
        $obtainedMarks = 0;
        $subjectsPassed = 0;
        $subjectsFailed = 0;

        // Build subject-wise results
        foreach ($examSubjects as $subjectId => $examSubject) {
            $mark = $studentMarks->firstWhere('subject_id', $subjectId);
            $subject = $examSubject->subject;

            $subjectTotal = (float) $examSubject->total_marks;
            $subjectPassing = (float) $examSubject->passing_marks;

            $isAbsent = false;
            $marksObtained = 0.0;
            $graceApplied = 0.0;

            if ($mark) {
                $isAbsent = (bool) $mark->is_absent;
                $marksObtained = $isAbsent ? 0.0 : (float) $mark->marks_obtained;
                $graceApplied = (float) ($mark->grace_marks ?? 0);
            } else {
                // No marks record means absent/not entered
                $isAbsent = true;
            }

            $effectiveMarks = $isAbsent ? 0.0 : $marksObtained + $graceApplied;
            $subjectPercentage = $subjectTotal > 0
                ? round(($effectiveMarks / $subjectTotal) * 100, 2)
                : 0.0;
            $passed = !$isAbsent && $effectiveMarks >= $subjectPassing;

            $subjectResults[] = [
                'subject_id' => $subjectId,
                'subject_name' => $subject->name ?? '',
                'subject_code' => $subject->code ?? '',
                'is_main' => (bool) ($subject->is_main ?? false),
                'total_marks' => $subjectTotal,
                'passing_marks' => $subjectPassing,
                'marks_obtained' => $marksObtained,
                'grace_marks' => $graceApplied,
                'effective_marks' => $effectiveMarks,
                'percentage' => $subjectPercentage,
                'is_absent' => $isAbsent,
                'is_passed' => $passed,
            ];

            $totalMarks += $subjectTotal;
            $obtainedMarks += $effectiveMarks;

            if ($passed) {
                $subjectsPassed++;
            } else {
                $subjectsFailed++;
            }
        }

        // Apply grace marks if the exam allows it and student has failing subjects
        if ($exam->grace_marks > 0 && $subjectsFailed > 0) {
            $subjectResults = $this->applyGraceMarks($subjectResults, $exam);

            // Recalculate totals after grace marks
            $obtainedMarks = 0;
            $subjectsPassed = 0;
            $subjectsFailed = 0;

            foreach ($subjectResults as $sr) {
                $obtainedMarks += $sr['effective_marks'];
                if ($sr['is_passed']) {
                    $subjectsPassed++;
                } else {
                    $subjectsFailed++;
                }
            }
        }

        // Calculate overall percentage
        $percentage = $totalMarks > 0
            ? round(($obtainedMarks / $totalMarks) * 100, 2)
            : 0.0;

        // Calculate grade
        [$grade, $gradePoint] = $this->calculateGrade($percentage, $gradingScale);

        // Determine pass/fail based on exam rules
        $isPassed = $this->determinePassStatus(
            $exam,
            $subjectResults,
            $subjectsPassed,
            $subjectsFailed,
            $percentage
        );

        return Result::create([
            'exam_id' => $exam->id,
            'student_id' => $student->id,
            'school_id' => $student->school_id,
            'school_class_id' => $schoolClassId,
            'section_id' => $sectionId,
            'academic_session_id' => $exam->academic_session_id,
            'total_marks' => $totalMarks,
            'obtained_marks' => $obtainedMarks,
            'percentage' => $percentage,
            'grade' => $grade,
            'grade_point' => $gradePoint,
            'position' => null, // will be set by calculatePositions
            'total_students' => $totalStudents,
            'subjects_passed' => $subjectsPassed,
            'subjects_failed' => $subjectsFailed,
            'is_passed' => $isPassed,
            'subject_results' => $subjectResults,
            'status' => 'generated',
            'generated_by' => auth()->id(),
            'generated_at' => now(),
        ]);
    }

    /**
     * Determine whether the student has passed based on all exam rules.
     */
    protected function determinePassStatus(
        Exam $exam,
        array $subjectResults,
        int $subjectsPassed,
        int $subjectsFailed,
        float $percentage
    ): bool {
        $totalSubjects = count($subjectResults);

        // Check if any subject was attempted (all absent = fail)
        $allAbsent = collect($subjectResults)->every(fn ($sr) => $sr['is_absent']);
        if ($allAbsent) {
            return false;
        }

        // Rule: all_subjects_must_pass
        if ($exam->all_subjects_must_pass && $subjectsFailed > 0) {
            return false;
        }

        // Rule: main_subjects_must_pass
        if ($exam->main_subjects_must_pass) {
            $mainSubjectsFailed = collect($subjectResults)
                ->filter(fn ($sr) => $sr['is_main'] && !$sr['is_passed'])
                ->count();

            if ($mainSubjectsFailed > 0) {
                return false;
            }
        }

        // Rule: min_subjects_to_pass
        if ($exam->min_subjects_to_pass !== null && $exam->min_subjects_to_pass > 0) {
            if ($subjectsPassed < $exam->min_subjects_to_pass) {
                return false;
            }
        }

        // Rule: passing_percentage at exam level
        if ($exam->passing_percentage !== null && $exam->passing_percentage > 0) {
            if ($percentage < (float) $exam->passing_percentage) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get grade and grade point for a given percentage using the grading scale.
     *
     * @return array{0: string|null, 1: float|null}
     */
    public function calculateGrade(float $percentage, ?GradingScale $gradingScale): array
    {
        if ($gradingScale === null) {
            return [null, null];
        }

        $entry = $gradingScale->getGrade($percentage);

        if ($entry === null) {
            return [null, null];
        }

        return [$entry->grade, (float) $entry->grade_point];
    }

    /**
     * Calculate and assign positions (ranks) to students based on percentage.
     *
     * For "section" scope, positions are assigned within the provided results collection.
     * For "class" or "school" scope, existing results from the broader scope are loaded
     * and positions are recalculated for all students in that scope.
     */
    public function calculatePositions(Collection $results, string $method = 'section'): void
    {
        if ($results->isEmpty()) {
            return;
        }

        $resultsToRank = $results;

        if ($method === 'class') {
            // Rank across the entire class (all sections)
            $sample = $results->first();
            $resultsToRank = Result::where('exam_id', $sample->exam_id)
                ->where('school_class_id', $sample->school_class_id)
                ->orderByDesc('percentage')
                ->get();
        } elseif ($method === 'school') {
            // Rank across the entire school
            $sample = $results->first();
            $resultsToRank = Result::where('exam_id', $sample->exam_id)
                ->where('school_id', $sample->school_id)
                ->orderByDesc('percentage')
                ->get();
        }

        // Sort by percentage descending for ranking
        $sorted = $resultsToRank->sortByDesc('percentage')->values();
        $totalStudents = $sorted->count();

        $position = 0;
        $prevPercentage = null;

        foreach ($sorted as $index => $result) {
            $pct = (float) $result->percentage;

            if ($prevPercentage === null || $pct !== $prevPercentage) {
                // New rank = previous position + number of ties skipped
                $position = $index + 1;
            }
            // If same percentage as previous, keep the same position (standard competition ranking)

            $result->position = $position;
            $result->total_students = $totalStudents;

            $prevPercentage = $pct;
        }

        // For class/school scope, persist positions for results outside our current batch
        if ($method !== 'section') {
            $currentIds = $results->pluck('id')->toArray();

            foreach ($sorted as $result) {
                if (!in_array($result->id, $currentIds)) {
                    $result->save();
                }
            }

            // Update our in-memory collection with computed positions
            foreach ($results as $result) {
                $ranked = $sorted->firstWhere('id', $result->id);
                if ($ranked) {
                    $result->position = $ranked->position;
                    $result->total_students = $ranked->total_students;
                }
            }
        }
    }

    /**
     * Apply grace marks to subjects where the student nearly passed.
     *
     * Grace marks are added to subjects where the student failed, starting
     * with the subject that needs the fewest additional marks to pass.
     * Limited to `grace_marks_max_subjects` subjects.
     */
    public function applyGraceMarks(array $subjectResults, Exam $exam): array
    {
        $graceMarksAvailable = (float) $exam->grace_marks;
        $maxSubjects = $exam->grace_marks_max_subjects ?? count($subjectResults);

        if ($graceMarksAvailable <= 0) {
            return $subjectResults;
        }

        // Identify failed, non-absent subjects and calculate the deficit
        $failedIndices = [];
        foreach ($subjectResults as $index => $sr) {
            if (!$sr['is_passed'] && !$sr['is_absent']) {
                $deficit = $sr['passing_marks'] - $sr['effective_marks'];
                if ($deficit > 0 && $deficit <= $graceMarksAvailable) {
                    $failedIndices[] = [
                        'index' => $index,
                        'deficit' => $deficit,
                    ];
                }
            }
        }

        if (empty($failedIndices)) {
            return $subjectResults;
        }

        // Sort by deficit ascending so we help subjects closest to passing first
        usort($failedIndices, fn ($a, $b) => $a['deficit'] <=> $b['deficit']);

        // Limit to max subjects
        $failedIndices = array_slice($failedIndices, 0, $maxSubjects);

        foreach ($failedIndices as $item) {
            $i = $item['index'];
            $deficit = $item['deficit'];

            $subjectResults[$i]['grace_marks'] += $deficit;
            $subjectResults[$i]['effective_marks'] += $deficit;
            $subjectResults[$i]['is_passed'] = true;

            // Recalculate subject percentage
            if ($subjectResults[$i]['total_marks'] > 0) {
                $subjectResults[$i]['percentage'] = round(
                    ($subjectResults[$i]['effective_marks'] / $subjectResults[$i]['total_marks']) * 100,
                    2
                );
            }
        }

        return $subjectResults;
    }
}
