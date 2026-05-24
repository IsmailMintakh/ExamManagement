<?php

namespace App\Services;

use App\Models\Exam;
use App\Models\ExamSchedule;
use App\Models\ExamSubject;
use App\Models\TimetableEntry;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Auto-builds a complete exam date-sheet from a few inputs.
 *
 * Two modes:
 *
 *  MODE 'terminal' (default — First Term, Mid Term, Annual, Send-up etc.)
 *      Each class sits one paper per working day. Subjects of a class are
 *      spread across consecutive days starting from $startDate, skipping
 *      Sundays + admin-supplied holidays. Every paper runs from
 *      $defaultStartTime for $defaultDurationMinutes.
 *
 *  MODE 'period_based' (Monthly/Unit Tests)
 *      One paper per class per working day (same cadence as terminal), but
 *      each paper runs at the period when that subject is normally taught
 *      (read from timetable_entries) instead of at a fixed exam-block time.
 *      So Computer (P3) is tested at P3, Math (P1) at P1 — students sit
 *      tests in their usual classroom rhythm.
 *
 * Conflict guard: within the run we never schedule two papers for the same
 * class in overlapping time on the same day. With per-period mode that's
 * impossible by construction; with terminal mode the algorithm advances the
 * date by one working day for each subject of a class.
 *
 * Returns a summary [scheduled, skipped, byClass[], warnings[]] so the UI
 * can show "Auto-scheduled 21 papers across 7 working days" + flag anything
 * weird (e.g. period not found in timetable).
 */
class DatesheetGeneratorService
{
    /** Default day-of-week numbers (Carbon::SUNDAY) to skip. */
    public const DEFAULT_OFF_DAYS = [0]; // Sunday only

    public function generate(Exam $exam, array $opts): array
    {
        $mode = in_array($opts['mode'] ?? null, ['terminal', 'period_based'], true)
            ? $opts['mode']
            : 'terminal';

        $startDate = $this->parseDate($opts['start_date'] ?? null) ?? $exam->start_date ?? now();
        $endDate = $this->parseDate($opts['end_date'] ?? null) ?? $exam->end_date;

        $defaultStartTime = $this->parseTime($opts['default_start_time'] ?? null) ?: '09:00';
        $defaultDuration = max(15, (int) ($opts['default_duration_minutes'] ?? 120));

        $offDays = $opts['off_days'] ?? self::DEFAULT_OFF_DAYS;
        $offDays = array_map('intval', $offDays);

        $holidays = collect($opts['holidays'] ?? [])
            ->map(fn ($d) => $this->parseDate($d)?->toDateString())
            ->filter()
            ->values()
            ->all();

        $overwrite = (bool) ($opts['overwrite_existing'] ?? false);

        $examSubjects = ExamSubject::where('exam_id', $exam->id)
            ->with(['subject:id,name,sort_order,is_main', 'schoolClass:id,name,sort_order'])
            ->get();

        if ($examSubjects->isEmpty()) {
            return $this->emptySummary('No exam subjects mapped yet — add them in Step 3 of the exam form first.');
        }

        // Block out existing schedules so we don't double-book unless asked to.
        $existingByPair = ExamSchedule::where('exam_id', $exam->id)
            ->get()
            ->keyBy(fn ($s) => $s->subject_id.'|'.$s->school_class_id);

        $warnings = [];
        $rowsToWrite = [];

        if ($mode === 'period_based') {
            $rowsToWrite = $this->buildPeriodBased(
                $exam, $examSubjects, $startDate, $endDate, $offDays, $holidays,
                $existingByPair, $overwrite, $defaultStartTime, $defaultDuration, $warnings
            );
        } else {
            $rowsToWrite = $this->buildTerminal(
                $exam, $examSubjects, $startDate, $endDate, $offDays, $holidays,
                $existingByPair, $overwrite, $defaultStartTime, $defaultDuration, $warnings
            );
        }

        if (empty($rowsToWrite)) {
            return $this->emptySummary('Nothing to schedule — every (subject, class) pair already had a date, and overwrite was off.');
        }

        $scheduled = 0;
        DB::transaction(function () use ($rowsToWrite, $exam, $overwrite, &$scheduled) {
            foreach ($rowsToWrite as $row) {
                if ($overwrite) {
                    ExamSchedule::updateOrCreate(
                        [
                            'exam_id' => $exam->id,
                            'subject_id' => $row['subject_id'],
                            'school_class_id' => $row['school_class_id'],
                        ],
                        $row
                    );
                } else {
                    // Only fill empty rows; never overwrite an admin's hand-set entry.
                    $exists = ExamSchedule::where('exam_id', $exam->id)
                        ->where('subject_id', $row['subject_id'])
                        ->where('school_class_id', $row['school_class_id'])
                        ->exists();
                    if (!$exists) {
                        ExamSchedule::create($row);
                    } else {
                        continue;
                    }
                }
                $scheduled++;
            }
        });

        return [
            'scheduled' => $scheduled,
            'skipped' => count($examSubjects) - $scheduled,
            'warnings' => $warnings,
            'mode' => $mode,
        ];
    }

    /**
     * Terminal mode: one paper per class per working day, in subject-sort
     * order (main subjects first). All classes start on the same first
     * working day; each class advances independently.
     */
    protected function buildTerminal(
        Exam $exam, $examSubjects, Carbon $startDate, ?Carbon $endDate, array $offDays, array $holidays,
        $existingByPair, bool $overwrite, string $defaultStart, int $defaultDuration, array &$warnings
    ): array {
        $rows = [];
        $byClass = $examSubjects->groupBy('school_class_id');
        $defaultEnd = $this->addMinutes($defaultStart, $defaultDuration);

        foreach ($byClass as $subjects) {
            // Sort: main first → sort_order → name. We pack the three keys
            // into one comparable string so a single sortBy(callable) call does
            // the lot. The leading "0/1" inverts is_main so true comes first.
            $sorted = $subjects->sortBy(fn ($es) => sprintf(
                '%d-%05d-%s',
                ($es->subject?->is_main ?? false) ? 0 : 1,
                $es->subject?->sort_order ?? 9999,
                strtolower((string) ($es->subject?->name ?? ''))
            ))->values();

            $cursor = $startDate->copy();
            foreach ($sorted as $es) {
                // Skip if already scheduled and not overwriting
                $pair = $es->subject_id.'|'.$es->school_class_id;
                if (!$overwrite && $existingByPair->has($pair)) {
                    continue;
                }

                $cursor = $this->nextWorkingDay($cursor, $offDays, $holidays, $endDate);
                if (!$cursor) {
                    $warnings[] = "Ran out of working days for class \"{$es->schoolClass?->name}\" — last subject didn't get a date.";
                    break;
                }

                $rows[] = [
                    'exam_id' => $exam->id,
                    'subject_id' => $es->subject_id,
                    'school_class_id' => $es->school_class_id,
                    'exam_date' => $cursor->toDateString(),
                    'start_time' => $defaultStart,
                    'end_time' => $defaultEnd,
                    'duration_minutes' => $defaultDuration,
                    'instructions' => null,
                ];
                $cursor = $cursor->copy()->addDay(); // next paper of this class on the next working day
            }
        }

        return $rows;
    }

    /**
     * Period-based mode: one paper per class per working day (same cadence
     * as terminal), but each paper runs at the period when the subject is
     * normally taught. So a class's English paper happens at the period
     * English is on the routine, Maths at the Maths period, etc.
     *
     * This naturally avoids the "two papers same time same class" conflict
     * because (a) only one paper per class per day, (b) different subjects
     * have different periods anyway.
     */
    protected function buildPeriodBased(
        Exam $exam, $examSubjects, Carbon $startDate, ?Carbon $endDate, array $offDays, array $holidays,
        $existingByPair, bool $overwrite, string $defaultStart, int $defaultDuration, array &$warnings
    ): array {
        // Pre-load every (class, subject) → timetable slot mapping once.
        $classIds = $examSubjects->pluck('school_class_id')->unique();
        $subjectIds = $examSubjects->pluck('subject_id')->unique();
        $entries = TimetableEntry::query()
            ->whereIn('school_class_id', $classIds)
            ->whereIn('subject_id', $subjectIds)
            ->with('timeSlot:id,name,starts_at,ends_at')
            ->get()
            ->groupBy(fn ($e) => $e->school_class_id.'|'.$e->subject_id);

        $rows = [];
        $byClass = $examSubjects->groupBy('school_class_id');

        foreach ($byClass as $subjects) {
            // Same sort as terminal — main subjects first.
            $sorted = $subjects->sortBy(fn ($es) => sprintf(
                '%d-%05d-%s',
                ($es->subject?->is_main ?? false) ? 0 : 1,
                $es->subject?->sort_order ?? 9999,
                strtolower((string) ($es->subject?->name ?? ''))
            ))->values();

            $cursor = $startDate->copy();
            foreach ($sorted as $es) {
                $pair = $es->subject_id.'|'.$es->school_class_id;
                if (!$overwrite && $existingByPair->has($pair)) {
                    continue;
                }

                $cursor = $this->nextWorkingDay($cursor, $offDays, $holidays, $endDate);
                if (!$cursor) {
                    $warnings[] = "Ran out of working days for class \"{$es->schoolClass?->name}\" — last subject didn't get a date.";
                    break;
                }

                // Time = the period this subject is normally taught.
                $hits = $entries->get($es->school_class_id.'|'.$es->subject_id) ?? collect();
                $slot = $hits->first()?->timeSlot;
                if ($slot) {
                    $start = substr($slot->starts_at, 0, 5);
                    $end = substr($slot->ends_at, 0, 5);
                    $duration = $this->durationMinutes($start, $end);
                } else {
                    $warnings[] = "No timetable period for {$es->subject?->name} → {$es->schoolClass?->name}. Using default {$defaultStart}.";
                    $start = $defaultStart;
                    $end = $this->addMinutes($defaultStart, $defaultDuration);
                    $duration = $defaultDuration;
                }

                $rows[] = [
                    'exam_id' => $exam->id,
                    'subject_id' => $es->subject_id,
                    'school_class_id' => $es->school_class_id,
                    'exam_date' => $cursor->toDateString(),
                    'start_time' => $start,
                    'end_time' => $end,
                    'duration_minutes' => $duration,
                    'instructions' => null,
                ];
                $cursor = $cursor->copy()->addDay();
            }
        }

        return $rows;
    }

    // ─── helpers ───

    protected function nextWorkingDay(Carbon $from, array $offDays, array $holidays, ?Carbon $endDate): ?Carbon
    {
        $d = $from->copy();
        $hardStop = $endDate ? $endDate->copy()->addDays(60) : $from->copy()->addYears(1);

        while (true) {
            if ($d->gt($hardStop)) return null;
            if ($endDate && $d->gt($endDate)) return null;
            if (in_array($d->dayOfWeek, $offDays, true)) {
                $d->addDay();
                continue;
            }
            if (in_array($d->toDateString(), $holidays, true)) {
                $d->addDay();
                continue;
            }
            return $d;
        }
    }

    protected function parseDate($value): ?Carbon
    {
        if (!$value) return null;
        try {
            return $value instanceof Carbon ? $value : Carbon::parse((string) $value);
        } catch (\Throwable $e) {
            return null;
        }
    }

    protected function parseTime($value): ?string
    {
        if (!$value) return null;
        $s = trim((string) $value);
        return preg_match('/^([01]?\d|2[0-3]):([0-5]\d)$/', $s) ? str_pad($s, 5, '0', STR_PAD_LEFT) : null;
    }

    protected function addMinutes(string $time, int $minutes): string
    {
        [$h, $m] = array_map('intval', explode(':', $time));
        $total = $h * 60 + $m + $minutes;
        $hh = str_pad((string) (intdiv($total, 60) % 24), 2, '0', STR_PAD_LEFT);
        $mm = str_pad((string) ($total % 60), 2, '0', STR_PAD_LEFT);
        return "{$hh}:{$mm}";
    }

    protected function durationMinutes(string $start, string $end): int
    {
        [$sh, $sm] = array_map('intval', explode(':', $start));
        [$eh, $em] = array_map('intval', explode(':', $end));
        return max(0, ($eh * 60 + $em) - ($sh * 60 + $sm));
    }

    protected function emptySummary(string $note): array
    {
        return ['scheduled' => 0, 'skipped' => 0, 'warnings' => [$note], 'mode' => null];
    }
}
