<?php

namespace App\Services;

use App\Models\SubstitutionAssignment;
use App\Models\SubjectTeacher;
use App\Models\TeacherAbsence;
use App\Models\TimetableEntry;
use App\Models\User;
use App\Notifications\SubstitutionAssignedNotification;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * Auto-substitution engine.
 *
 * Inputs: a date.
 * Outputs: SubstitutionAssignment rows in 'suggested' state for every empty
 * timetable cell caused by an absent teacher.
 *
 * Algorithm:
 *   1. Pull all teachers absent on `date`.
 *   2. For each, find their timetable entries on that weekday.
 *   3. For each entry, score every potentially-free other teacher on:
 *        +10  same-subject teacher elsewhere
 *        + 5  teaches any subject in the same class
 *        + 3  class-teacher in the same class
 *        - 2  per substitution already assigned to them today
 *   4. Pick the top score; tie → fewest current subs → alphabetical.
 *   5. Insert / update the assignment row (UPSERT on date + entry).
 *
 * Side-effect-free aside from the DB writes — re-running the same date is
 * safe (existing rows update, nothing duplicates).
 */
class SubstitutionService
{
    /** Map ISO weekday number → our 'mon'..'sat' enum values. */
    protected array $weekdayMap = [
        1 => 'mon', 2 => 'tue', 3 => 'wed', 4 => 'thu', 5 => 'fri', 6 => 'sat',
    ];

    /**
     * Generate suggestions for the given date. Returns a summary array
     * suitable for showing the admin: {date, total_periods, covered, uncovered,
     * by_substitute: [...], uncovered_periods: [...]}.
     */
    public function generateForDate(Carbon $date, ?int $createdBy = null): array
    {
        $weekday = $this->weekdayMap[$date->dayOfWeekIso] ?? null;
        if (!$weekday) {
            // Sunday — no school, return zero summary.
            return $this->emptySummary($date);
        }

        // ── 1. Absent teachers today (each carries optional from_time cutoff) ──
        $absences = TeacherAbsence::where('absent_on', $date->toDateString())->get();
        if ($absences->isEmpty()) {
            return $this->emptySummary($date);
        }

        $absentTeacherIds = $absences->pluck('user_id')->all();
        // Map: user_id → from_time (HH:MM:SS string) or null (full-day).
        $cutoffByTeacher = $absences->mapWithKeys(fn ($a) => [
            $a->user_id => $a->from_time,
        ])->all();

        // ── 2. Their entries this weekday — filter by per-teacher cutoff ──
        // Pull all candidates first, then drop entries whose slot starts before
        // the teacher's cutoff (those are still being taught by them).
        $needsCover = TimetableEntry::query()
            ->whereIn('teacher_id', $absentTeacherIds)
            ->where('weekday', $weekday)
            ->with(['timeSlot', 'subject', 'schoolClass', 'section', 'teacher'])
            ->get()
            ->filter(function ($e) use ($cutoffByTeacher) {
                $cutoff = $cutoffByTeacher[$e->teacher_id] ?? null;
                if (!$cutoff) return true; // full-day absence → all periods need cover
                if (!$e->timeSlot) return false;
                // Period starts at or after the cutoff → cover needed.
                return $e->timeSlot->starts_at >= $cutoff;
            })
            ->values();

        if ($needsCover->isEmpty()) {
            return $this->emptySummary($date);
        }

        // ── 3. Pre-load context: every teacher's slot occupancy + subject mastery ──
        $allEntriesToday = TimetableEntry::where('weekday', $weekday)
            ->select(['teacher_id', 'time_slot_id', 'subject_id', 'school_class_id'])
            ->get();

        // Map: "<teacher_id>|<time_slot_id>" → true (already busy)
        $busy = $allEntriesToday
            ->whereNotNull('teacher_id')
            ->mapWithKeys(fn ($e) => [$e->teacher_id . '|' . $e->time_slot_id => true])
            ->all();

        // Map: teacher_id → set<subject_id> (subjects they teach anywhere)
        $teacherSubjects = $allEntriesToday
            ->whereNotNull('teacher_id')
            ->whereNotNull('subject_id')
            ->groupBy('teacher_id')
            ->map(fn ($rows) => $rows->pluck('subject_id')->unique()->all());

        // Map: teacher_id → set<class_id> (classes they teach in)
        $teacherClasses = $allEntriesToday
            ->whereNotNull('teacher_id')
            ->groupBy('teacher_id')
            ->map(fn ($rows) => $rows->pluck('school_class_id')->unique()->all());

        // Class-teacher relation: section_id → user_id (for the +3 bonus)
        $classTeacherBySection = \App\Models\Section::pluck('class_teacher_id', 'id')->all();

        // Pool of candidate teachers: any active class/subject teacher not absent today,
        // scoped to the school of the absent entries (admins of the same school).
        $candidatePool = User::query()
            ->where('is_active', true)
            ->whereHas('roles', fn ($q) => $q->whereIn('name', ['class-teacher', 'subject-teacher']))
            ->whereNotIn('id', $absentTeacherIds)
            ->get(['id', 'name', 'school_id']);

        // ── 4. Process each uncovered cell, accumulating substitution counts as we go ──
        $subsAssigned = []; // user_id → count of subs given today, used for load balancing
        // Pre-seed with confirmed subs from this date (so re-runs don't double-count).
        $existing = SubstitutionAssignment::where('date', $date->toDateString())->get();
        foreach ($existing as $row) {
            $subsAssigned[$row->substitute_teacher_id] = ($subsAssigned[$row->substitute_teacher_id] ?? 0) + 1;
        }

        $assignments = [];
        $uncoveredPeriods = [];

        foreach ($needsCover as $entry) {
            // Score every candidate
            $best = null;
            $bestScore = -1e9;
            foreach ($candidatePool as $cand) {
                // Hard filter: must be free in this slot
                if (isset($busy[$cand->id . '|' . $entry->time_slot_id])) continue;
                // Hard filter: school match (skip if the entry's class isn't in their school)
                if ($cand->school_id && $entry->schoolClass?->school_id
                    && $cand->school_id !== $entry->schoolClass->school_id) continue;

                $score = 0;
                // Same-subject bonus
                if ($entry->subject_id && in_array($entry->subject_id, $teacherSubjects[$cand->id] ?? [], true)) {
                    $score += 10;
                }
                // Same-class familiarity bonus
                if (in_array($entry->school_class_id, $teacherClasses[$cand->id] ?? [], true)) {
                    $score += 5;
                }
                // Class-teacher of this exact section
                if (($classTeacherBySection[$entry->section_id] ?? null) === $cand->id) {
                    $score += 3;
                }
                // Load penalty: -2 per substitution already assigned today
                $score -= 2 * ($subsAssigned[$cand->id] ?? 0);

                if ($score > $bestScore
                    || ($score === $bestScore && $cand->name < ($best?->name ?? "\xff"))) {
                    $best = $cand;
                    $bestScore = $score;
                }
            }

            if (!$best) {
                $uncoveredPeriods[] = [
                    'time_slot' => $entry->timeSlot?->name,
                    'class' => $entry->schoolClass?->name,
                    'section' => $entry->section?->name,
                    'subject' => $entry->subject?->name,
                    'absent_teacher' => $entry->teacher?->name,
                ];
                continue;
            }

            $subsAssigned[$best->id] = ($subsAssigned[$best->id] ?? 0) + 1;
            $assignments[] = [
                'entry' => $entry,
                'substitute' => $best,
                'score' => $bestScore,
            ];
        }

        // ── 5. Persist (upsert) inside a transaction ──
        // Snapshot prior substitute per entry so we can tell which rows are
        // genuinely new/changed and only notify those teachers (running
        // generate twice should not spam the same person twice).
        $priorByEntry = $existing->keyBy('timetable_entry_id')
            ->map(fn ($a) => $a->substitute_teacher_id);

        $persistedRows = [];
        DB::transaction(function () use ($assignments, $date, $createdBy, &$persistedRows) {
            foreach ($assignments as $a) {
                $row = SubstitutionAssignment::updateOrCreate(
                    [
                        'date' => $date->toDateString(),
                        'timetable_entry_id' => $a['entry']->id,
                    ],
                    [
                        'original_teacher_id' => $a['entry']->teacher_id,
                        'substitute_teacher_id' => $a['substitute']->id,
                        'status' => 'suggested',
                        'created_by' => $createdBy,
                    ]
                );
                $persistedRows[] = ['row' => $row, 'meta' => $a];
            }
        });

        // ── 6. Notify only substitutes whose assignment is new or reassigned ──
        foreach ($persistedRows as $p) {
            $row = $p['row'];
            $prevSubId = $priorByEntry->get($row->timetable_entry_id);
            if ($prevSubId === $row->substitute_teacher_id) continue; // unchanged
            $teacher = User::find($row->substitute_teacher_id);
            if (!$teacher) continue;
            $row->loadMissing(['timetableEntry.timeSlot', 'timetableEntry.subject', 'timetableEntry.schoolClass', 'timetableEntry.section', 'originalTeacher']);
            try {
                Notification::send($teacher, new SubstitutionAssignedNotification($row, 'suggested'));
            } catch (\Throwable $e) {
                report($e);
            }
        }

        // ── Build summary ──
        $bySubstitute = collect($assignments)
            ->groupBy(fn ($a) => $a['substitute']->id)
            ->map(fn ($rows, $uid) => [
                'teacher_id' => (int) $uid,
                'teacher_name' => $rows->first()['substitute']->name,
                'count' => $rows->count(),
                'periods' => $rows->map(fn ($a) => [
                    'time_slot' => $a['entry']->timeSlot?->name,
                    'class' => $a['entry']->schoolClass?->name,
                    'section' => $a['entry']->section?->name,
                    'subject' => $a['entry']->subject?->name,
                    'replaces' => $a['entry']->teacher?->name,
                ])->values()->all(),
            ])
            ->values()
            ->all();

        return [
            'date' => $date->toDateString(),
            'weekday' => $weekday,
            'total_periods' => $needsCover->count(),
            'covered' => count($assignments),
            'uncovered' => count($uncoveredPeriods),
            'absent_teachers' => User::whereIn('id', $absentTeacherIds)->pluck('name')->all(),
            'by_substitute' => $bySubstitute,
            'uncovered_periods' => $uncoveredPeriods,
        ];
    }

    protected function emptySummary(Carbon $date): array
    {
        return [
            'date' => $date->toDateString(),
            'weekday' => $this->weekdayMap[$date->dayOfWeekIso] ?? null,
            'total_periods' => 0,
            'covered' => 0,
            'uncovered' => 0,
            'absent_teachers' => [],
            'by_substitute' => [],
            'uncovered_periods' => [],
        ];
    }
}
