<?php

namespace App\Services;

use App\Models\BoardExam;
use App\Models\BoardResult;
use App\Models\BoardResultSubject;
use App\Models\GradingScale;

/**
 * FBISE result calculator.
 *
 * Single source of truth for every derived field on a BoardResult /
 * BoardResultSubject row. Called after every save so the cached fields
 * (percentage, grade, division, position, is_pass, is_supplementary)
 * stay in lockstep with the raw marks.
 *
 * Public API:
 *   • gradeFor(float $percentage): string           — A1..F letter
 *   • divisionFor(float $percentage, bool $isPass): string — 1st/2nd/3rd/Fail
 *   • recomputeSubject(BoardResultSubject $row): void
 *   • recomputeResult(BoardResult $row): void        — cascades to subjects
 *   • recomputePositions(int $boardExamId): void     — re-ranks the whole exam
 */
class BoardResultCalculatorService
{
    /** FBISE overall pass threshold. Kept as class const so tests can pin it. */
    public const PASS_PERCENTAGE = 33.0;

    /** Max # of failed subjects allowed for a "Supplementary" (else full fail). */
    public const SUPPLY_MAX_FAILS = 2;

    /**
     * FBISE default grade scale — the highest-matching band wins.
     *   A1  ≥ 80
     *   A   ≥ 70
     *   B   ≥ 60
     *   C   ≥ 50
     *   D   ≥ 40
     *   E   ≥ 33   (bare pass)
     *   F   <  33  (fail)
     *
     * When a GradingScale is passed in, its entries are used instead —
     * so a school can override to any custom bands via the existing
     * grading-scales module without touching this service.
     */
    public function gradeFor(float $percentage, ?GradingScale $scale = null): string
    {
        if ($scale) {
            $scale->loadMissing('entries');
            $entry = $scale->getGrade($percentage);
            if ($entry) return $entry->grade;
        }
        return match (true) {
            $percentage >= 80 => 'A1',
            $percentage >= 70 => 'A',
            $percentage >= 60 => 'B',
            $percentage >= 50 => 'C',
            $percentage >= 40 => 'D',
            $percentage >= 33 => 'E',
            default            => 'F',
        };
    }

    /**
     * FBISE division scale (only meaningful if the student passed overall):
     *   1st   ≥ 60
     *   2nd   ≥ 45
     *   3rd   ≥ 33  (bare pass)
     *   Fail  < 33
     * When the student didn't pass overall, we return "Fail" regardless.
     */
    public function divisionFor(float $percentage, bool $isPass): string
    {
        if (!$isPass) return 'Fail';
        return match (true) {
            $percentage >= 60 => '1st',
            $percentage >= 45 => '2nd',
            default            => '3rd',
        };
    }

    /**
     * Snap the derived fields on a subject row from its theory/practical.
     * Does NOT save — caller decides whether to persist (usually via
     * recomputeResult which batches the whole result at once).
     */
    public function recomputeSubject(BoardResultSubject $row, ?GradingScale $scale = null): void
    {
        $total     = (float) $row->theory_marks + (float) $row->practical_marks;
        $totalMax  = (float) $row->theory_max   + (float) $row->practical_max;
        $row->total_marks = round($total, 2);
        $row->max_marks   = round($totalMax, 2);

        $pct = $totalMax > 0 ? ($total / $totalMax) * 100 : 0;
        $row->grade   = $row->is_absent ? 'F' : $this->gradeFor($pct, $scale);
        $row->is_pass = !$row->is_absent && $pct >= self::PASS_PERCENTAGE;
    }

    /**
     * Snap the aggregate fields on a result row + all its subject children.
     * Does one save at the end. Call this after any subject edit.
     */
    public function recomputeResult(BoardResult $result): void
    {
        $result->loadMissing(['subjects', 'boardExam.gradingScale.entries']);
        // Pull the exam's chosen scale (nullable — hardcoded FBISE bands
        // apply when no scale is picked).
        $scale = $result->boardExam?->gradingScale;

        $totalObt = 0.0;
        $totalMax = 0.0;
        $failedSubjects = 0;
        $subjectsCount  = 0;

        foreach ($result->subjects as $sub) {
            $this->recomputeSubject($sub, $scale);
            $sub->save();
            $totalObt += (float) $sub->total_marks;
            $totalMax += (float) $sub->max_marks;
            $subjectsCount++;
            if (!$sub->is_pass) $failedSubjects++;
        }

        $pct = $totalMax > 0 ? round(($totalObt / $totalMax) * 100, 2) : 0.0;
        $everySubjectPassed = ($subjectsCount > 0 && $failedSubjects === 0);
        $overallPass = $everySubjectPassed && $pct >= self::PASS_PERCENTAGE;

        $result->total_obtained   = round($totalObt, 2);
        $result->total_max        = round($totalMax, 2);
        $result->percentage       = $pct;
        $result->grade            = $this->gradeFor($pct, $scale);
        $result->division         = $this->divisionFor($pct, $overallPass);
        $result->is_pass          = $overallPass;
        $result->is_supplementary = !$overallPass
            && $failedSubjects > 0
            && $failedSubjects <= self::SUPPLY_MAX_FAILS;

        $result->save();
    }

    /**
     * Re-rank every result under one board exam by total_obtained desc.
     * Ties share the higher rank (standard competition ranking: 1, 2, 2, 4).
     * Fail rows still get a rank so position-history stays useful.
     */
    public function recomputePositions(int $boardExamId): void
    {
        $rows = BoardResult::where('board_exam_id', $boardExamId)
            ->orderByDesc('total_obtained')
            ->orderBy('id')            // stable secondary
            ->get();

        $lastMarks = null;
        $lastRank  = 0;
        $i = 0;
        foreach ($rows as $row) {
            $i++;
            if ($lastMarks === null || (float) $row->total_obtained < $lastMarks) {
                $lastRank  = $i;
                $lastMarks = (float) $row->total_obtained;
            }
            $row->position = $lastRank;
            $row->save();
        }
    }
}
