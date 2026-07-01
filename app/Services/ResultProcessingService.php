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
     * Read-only on the marks table.
     * This method is a hard guarantee: teacher-submitted marks are the
     * source of truth, and this method *only reads* from the `marks` table
     * to compute Result rows. It NEVER calls Mark::update / Mark::delete /
     * Mark::create. Re-running generateResults is always safe — running it
     * 100 times produces the same Result rows and leaves the marks data
     * completely untouched.
     *
     * The only DB writes here are:
     *   - Result::forceDelete() for the section's prior result rows (the
     *     replacement rows are then created via Result::create).
     *   - Result::create() and Result::save() for the fresh rows.
     *
     * If a future change to this method touches marks_obtained, is_absent
     * or any other Mark column, that's a bug — re-revert.
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

            // Resolve prior-term exams once (for combine-mode). NULL when this
            // exam isn't a final-term combine or the prior terms don't exist.
            $combineCtx = $this->resolveTermCombination($exam);

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

                // If admin enabled "combine previous terms" on this final-term
                // exam, blend in the student's 1st + 2nd term percentages and
                // overwrite the result totals so the final card shows the
                // year-end aggregate.
                //
                // Primary classes (ECD–5) follow a different rule per spec:
                // each subject's annual total is the RAW SUM of all three
                // terms (30 + 30 + 40 = 100), an overall 10-mark Assessment
                // is added, and a sub-4 Assessment forces a Fail. We branch
                // here instead of calling the weighted combiner.
                if ($combineCtx) {
                    if ($this->isPrimaryClass($schoolClassId)) {
                        $this->applyPrimaryAnnualCombination($result, $student, $exam, $gradingScale, $combineCtx);
                    } else {
                        $this->applyTermCombination($result, $student, $exam, $gradingScale, $combineCtx);
                    }
                }

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
            // 0 sentinel; calculatePositions overwrites with the real rank
            // before the result is saved. Some DB columns can't accept null
            // here so we send a safe placeholder.
            'position' => 0,
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
     * Is this class in the primary section (ECD/Pre-primary or Classes 1–5)?
     * Drives the branch into the spec-defined raw-sum + assessment aggregator
     * instead of the weighted percentage blend used elsewhere.
     */
    protected function isPrimaryClass(int $schoolClassId): bool
    {
        static $cache = [];
        if (array_key_exists($schoolClassId, $cache)) return $cache[$schoolClassId];
        $stage = \App\Models\SchoolClass::whereKey($schoolClassId)->value('stage');
        return $cache[$schoolClassId] = in_array($stage, \App\Models\SchoolClass::PRIMARY_STAGES, true);
    }

    /**
     * Primary-section Annual Result aggregator. Per spec:
     *   - Each subject annual = RAW SUM of all three terms (T1 + T2 + Final).
     *     With the standard primary scales that's 30 + 30 + 40 = 100, so the
     *     subject denominator is 100 and a 40% passing mark is 40.
     *   - The class teacher's 10-mark Assessment score is added on top so
     *     grand total = subjects×100 + 10.
     *   - Pass criteria: every subject ≥ subject pass marks AND the
     *     assessment score ≥ assessment passing marks (4/10). A sub-pass
     *     Assessment forces an overall Fail even if every subject is passed.
     */
    protected function applyPrimaryAnnualCombination(
        Result $finalResult,
        Student $student,
        Exam $finalExam,
        ?GradingScale $gradingScale,
        array $ctx
    ): void {
        $first = Result::where('exam_id', $ctx['first_exam_id'])
            ->where('student_id', $student->id)
            ->first();
        $second = Result::where('exam_id', $ctx['second_exam_id'])
            ->where('student_id', $student->id)
            ->first();
        if (!$first || !$second) return;

        $firstBySubj  = collect($first->subject_results ?? [])->keyBy('subject_id');
        $secondBySubj = collect($second->subject_results ?? [])->keyBy('subject_id');

        $combined = [];
        $totalMarks = 0.0;
        $obtainedMarks = 0.0;
        $passed = 0;
        $failed = 0;

        foreach ($finalResult->subject_results ?? [] as $row) {
            $sid = $row['subject_id'];

            // Raw obtained marks per term — exactly what the student got.
            $obtFirst  = (float) ($firstBySubj[$sid]['effective_marks']  ?? $firstBySubj[$sid]['marks_obtained']  ?? 0);
            $obtSecond = (float) ($secondBySubj[$sid]['effective_marks'] ?? $secondBySubj[$sid]['marks_obtained'] ?? 0);
            $obtFinal  = (float) ($row['effective_marks'] ?? $row['marks_obtained'] ?? 0);

            // Per-subject annual denominator = sum of each term's subject total.
            $totFirst  = (float) ($firstBySubj[$sid]['total_marks']  ?? 30);
            $totSecond = (float) ($secondBySubj[$sid]['total_marks'] ?? 30);
            $totFinal  = (float) ($row['total_marks'] ?? 40);
            $annualTotal = $totFirst + $totSecond + $totFinal;

            $annualObtained = round($obtFirst + $obtSecond + $obtFinal, 2);
            $annualPassing = $annualTotal > 0 ? round($annualTotal * 0.40, 2) : 0; // 40% per spec
            $isPassed = !($row['is_absent'] ?? false) && $annualObtained >= $annualPassing;

            $combined[] = array_merge($row, [
                'total_marks' => $annualTotal,
                'passing_marks' => $annualPassing,
                'marks_obtained' => $annualObtained,
                'effective_marks' => $annualObtained,
                'grace_marks' => 0,
                'percentage' => $annualTotal > 0 ? round(($annualObtained / $annualTotal) * 100, 2) : 0,
                'is_passed' => $isPassed,
                'primary_breakdown' => [
                    'first'  => ['obtained' => $obtFirst,  'total' => $totFirst],
                    'second' => ['obtained' => $obtSecond, 'total' => $totSecond],
                    'final'  => ['obtained' => $obtFinal,  'total' => $totFinal],
                ],
            ]);

            $totalMarks    += $annualTotal;
            $obtainedMarks += $annualObtained;
            $isPassed ? $passed++ : $failed++;
        }

        // ─── Assessment marks (per student, per academic session) ───
        $assessment = \App\Models\AssessmentMark::where('student_id', $student->id)
            ->where('academic_session_id', $finalExam->academic_session_id)
            ->first();
        $assessmentObtained = $assessment ? (float) $assessment->marks_obtained : 0.0;
        $assessmentTotal    = $assessment ? (float) $assessment->marks_total    : 10.0;
        $assessmentPassing  = $assessment ? (float) $assessment->passing_marks  : 4.0;
        $assessmentPassed   = $assessmentObtained >= $assessmentPassing;

        $totalMarks    += $assessmentTotal;
        $obtainedMarks += $assessmentObtained;

        $combinedPercentage = $totalMarks > 0 ? round(($obtainedMarks / $totalMarks) * 100, 2) : 0;
        [$grade, $gradePoint] = $this->calculateGrade($combinedPercentage, $gradingScale);

        // Subject-pass via the exam's existing pass policy, then overlay the
        // assessment rule: failing assessment forces an overall Fail no
        // matter what the subjects look like.
        $subjectsOK = $this->determinePassStatus($finalExam, $combined, $passed, $failed, $combinedPercentage);
        $aggPassed = $subjectsOK && $assessmentPassed;

        // Remarks line — make the assessment fail reason visible in the row.
        $remarks = $finalResult->remarks;
        if (!$assessmentPassed && $assessment) {
            $remarks = trim(($remarks ? $remarks.' · ' : '').sprintf(
                'Needs retry (assessment %s/%s).',
                rtrim(rtrim(number_format($assessmentObtained, 2, '.', ''), '0'), '.'),
                rtrim(rtrim(number_format($assessmentTotal,    2, '.', ''), '0'), '.'),
            ));
        }

        $finalResult->update([
            'subject_results' => $combined,
            'total_marks' => $totalMarks,
            'obtained_marks' => $obtainedMarks,
            'percentage' => $combinedPercentage,
            'grade' => $grade,
            'grade_point' => $gradePoint,
            'subjects_passed' => $passed,
            'subjects_failed' => $failed,
            'is_passed' => $aggPassed,
            'remarks' => $remarks,
        ]);
    }

    /**
     * Resolve the prior-term exam IDs + the weight set for a final-term
     * exam that wants to combine. Returns null when this isn't a combine
     * exam or when either prior-term exam is missing for this session.
     */
    protected function resolveTermCombination(Exam $exam): ?array
    {
        if ($exam->term !== 'final' || !$exam->combine_previous_terms) {
            return null;
        }
        // Weights are validated downstream by the weighted aggregator only.
        // The primary aggregator ignores weights entirely (raw sum per spec),
        // so we don't want to gate combine resolution on a 100% sum here.
        $weights = $exam->term_weights ?: Exam::DEFAULT_TERM_WEIGHTS;

        $priors = Exam::where('academic_session_id', $exam->academic_session_id)
            ->whereIn('term', ['first', 'second'])
            ->where('id', '!=', $exam->id)
            ->orderByDesc('created_at')
            ->get(['id', 'term']);
        $firstId = $priors->firstWhere('term', 'first')?->id;
        $secondId = $priors->firstWhere('term', 'second')?->id;
        if (!$firstId || !$secondId) return null;

        return [
            'first_exam_id' => $firstId,
            'second_exam_id' => $secondId,
            'weights' => $weights,
        ];
    }

    /**
     * Overwrite a final-term result with the weighted aggregate of the same
     * student's 1st + 2nd term results plus this final term. The blend
     * happens at the *percentage* level (since the three exams can have
     * different total marks), then percentages are converted back to marks
     * out of each subject's total so the rest of the system stays consistent.
     */
    protected function applyTermCombination(
        Result $finalResult,
        Student $student,
        Exam $finalExam,
        ?GradingScale $gradingScale,
        array $ctx
    ): void {
        $first = Result::where('exam_id', $ctx['first_exam_id'])
            ->where('student_id', $student->id)
            ->first();
        $second = Result::where('exam_id', $ctx['second_exam_id'])
            ->where('student_id', $student->id)
            ->first();

        if (!$first || !$second) {
            return; // student didn't sit one of the prior terms — leave final marks as-is
        }

        // Weighted aggregator is sane only when the three weights add up to
        // 100. Skip the combine if they don't — the final exam's own numbers
        // stay in place, no silent miscalculation.
        $weights = $ctx['weights'];
        $sum = (int) ($weights['first'] ?? 0) + (int) ($weights['second'] ?? 0) + (int) ($weights['final'] ?? 0);
        if ($sum !== 100) return;

        $firstBySubj = collect($first->subject_results ?? [])->keyBy('subject_id');
        $secondBySubj = collect($second->subject_results ?? [])->keyBy('subject_id');

        $combined = [];
        $totalMarks = 0.0;
        $obtainedMarks = 0.0;
        $passed = 0;
        $failed = 0;

        foreach ($finalResult->subject_results ?? [] as $row) {
            $sid = $row['subject_id'];
            $pctFinal = (float) ($row['percentage'] ?? 0);
            $pctFirst = (float) ($firstBySubj[$sid]['percentage'] ?? 0);
            $pctSecond = (float) ($secondBySubj[$sid]['percentage'] ?? 0);

            $combinedPct = round(
                ($pctFirst * $weights['first']
                    + $pctSecond * $weights['second']
                    + $pctFinal * $weights['final']) / 100,
                2
            );
            $subjectTotal = (float) ($row['total_marks'] ?? 0);
            $combinedMarks = round(($combinedPct / 100) * $subjectTotal, 2);
            $isPassed = !($row['is_absent'] ?? false) && $combinedMarks >= (float) ($row['passing_marks'] ?? 0);

            $combined[] = array_merge($row, [
                'percentage' => $combinedPct,
                'effective_marks' => $combinedMarks,
                'marks_obtained' => $combinedMarks,
                'grace_marks' => 0,           // grace is reset — combined isn't a fresh exam
                'is_passed' => $isPassed,
                'combined_from' => [
                    'first' => $pctFirst,
                    'second' => $pctSecond,
                    'final' => $pctFinal,
                    'weights' => $weights,
                ],
            ]);

            $totalMarks += $subjectTotal;
            $obtainedMarks += $combinedMarks;
            $isPassed ? $passed++ : $failed++;
        }

        $combinedPercentage = $totalMarks > 0 ? round(($obtainedMarks / $totalMarks) * 100, 2) : 0;
        [$grade, $gradePoint] = $this->calculateGrade($combinedPercentage, $gradingScale);
        $aggPassed = $this->determinePassStatus($finalExam, $combined, $passed, $failed, $combinedPercentage);

        $finalResult->update([
            'subject_results' => $combined,
            'total_marks' => $totalMarks,
            'obtained_marks' => $obtainedMarks,
            'percentage' => $combinedPercentage,
            'grade' => $grade,
            'grade_point' => $gradePoint,
            'subjects_passed' => $passed,
            'subjects_failed' => $failed,
            'is_passed' => $aggPassed,
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
    /**
     * Returns [grade, gradePoint] for a percentage.
     *
     * When no grading scale is configured on the exam, or the scale doesn't
     * cover this percentage band, we still return safe non-null defaults
     * ('—' / 0.0) — the `results.grade_point` column is NOT NULL and the
     * insert otherwise dies with a constraint-violation 5xx. Admins can
     * always re-generate after attaching a grading scale.
     */
    public function calculateGrade(float $percentage, ?GradingScale $gradingScale): array
    {
        if ($gradingScale === null) {
            return ['—', 0.0];
        }

        $entry = $gradingScale->getGrade($percentage);

        if ($entry === null) {
            return ['—', 0.0];
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
            // Round to 2 decimals so two students with the same "displayed"
            // percentage (e.g. 78.50%) actually compare as equal here — a
            // strict `!==` on raw floats can mis-fire on 78.5000000001 vs
            // 78.5, which would assign two ranks to tied students.
            $pct = round((float) $result->percentage, 2);

            if ($prevPercentage === null || abs($pct - $prevPercentage) > 0.0001) {
                // New rank = previous position + number of ties skipped
                // (standard competition ranking — e.g. 1, 2, 2, 4).
                $position = $index + 1;
            }
            // If percentage matches the previous student, keep the same
            // position — tied students share a rank, the next distinct
            // percentage gets the rank after the tie span.

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
        $graceRemaining = (float) $exam->grace_marks;
        $maxSubjects = $exam->grace_marks_max_subjects ?: count($subjectResults);

        if ($graceRemaining <= 0) {
            return $subjectResults;
        }

        // Identify every failed, non-absent subject + how many marks short.
        $failed = [];
        foreach ($subjectResults as $index => $sr) {
            if (!$sr['is_passed'] && !$sr['is_absent']) {
                $deficit = (float) $sr['passing_marks'] - (float) $sr['effective_marks'];
                if ($deficit > 0) {
                    $failed[] = ['index' => $index, 'deficit' => $deficit];
                }
            }
        }
        if (empty($failed)) {
            return $subjectResults;
        }

        // Help the subjects closest to passing first.
        usort($failed, fn ($a, $b) => $a['deficit'] <=> $b['deficit']);

        $applied = 0;
        foreach ($failed as $item) {
            if ($applied >= $maxSubjects) break;
            if ($graceRemaining <= 0) break;
            // Only apply if the deficit fits in what's left — partial grace
            // wouldn't make the student pass, so it'd be wasted marks.
            if ($item['deficit'] > $graceRemaining) {
                continue;
            }

            $i = $item['index'];
            $subjectResults[$i]['grace_marks'] += $item['deficit'];
            $subjectResults[$i]['effective_marks'] += $item['deficit'];
            $subjectResults[$i]['is_passed'] = true;
            if ($subjectResults[$i]['total_marks'] > 0) {
                $subjectResults[$i]['percentage'] = round(
                    ($subjectResults[$i]['effective_marks'] / $subjectResults[$i]['total_marks']) * 100,
                    2
                );
            }
            $graceRemaining -= $item['deficit'];
            $applied++;
        }

        return $subjectResults;
    }
}
