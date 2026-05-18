<?php

namespace App\Services;

use App\Models\AcademicSession;
use App\Models\ExamSchedule;
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
 * Class-Adjustment engine (a.k.a. auto-substitution).
 *
 * Input : a date (+ optional school scope).
 * Output: SubstitutionAssignment rows that cover every empty period left by
 *         an absent teacher, picking a free, fair, qualified colleague.
 *
 * ─────────────── HARD CONSTRAINTS (never violated) ───────────────
 *   H1  Substitute is free in that period (not teaching their own class).
 *   H2  Substitute is not the absent (original) teacher.
 *   H3  Substitute is not themselves absent for that period
 *       (full-day, or partial-day that has already started).
 *   H4  Substitute is not already covering another class in the SAME
 *       period — within this run AND against already-confirmed covers.
 *   H5  Same school as the class being covered.
 *   H6  Not invigilating an exam during that period.
 *   H7  Daily cover cap (MAX_COVERS_PER_DAY) per teacher.
 *   H8  A teacher who declined a specific period is not re-suggested for it.
 *
 * ─────────────── PRESERVATION ───────────────
 *   - Confirmed covers are never altered and they consume the substitute's
 *     slot (so a re-run won't double-book that teacher).
 *   - Declined covers are re-suggested with a DIFFERENT teacher.
 *   - Stale "suggested" rows for periods that no longer need a cover
 *     (teacher came back / partial-day window changed) are pruned.
 *
 * ─────────────── SOFT SCORING (tie-broken, recorded) ───────────────
 *   +10  already teaches that subject elsewhere
 *   + 5  already teaches in that class
 *   + 3  is the class-teacher of that section
 *   - 2  per cover already given today (load balancing)
 *   tie → fewer covers, then alphabetical.
 */
class SubstitutionService
{
    /** Hard cap: no teacher gets more than this many covers in one day. */
    public const MAX_COVERS_PER_DAY = 3;

    /** Map ISO weekday number → our 'mon'..'sat' enum values. */
    protected array $weekdayMap = [
        1 => 'mon', 2 => 'tue', 3 => 'wed', 4 => 'thu', 5 => 'fri', 6 => 'sat',
    ];

    public function generateForDate(Carbon $date, ?int $createdBy = null, ?int $schoolId = null): array
    {
        $weekday = $this->weekdayMap[$date->dayOfWeekIso] ?? null;
        if (!$weekday) {
            return $this->emptySummary($date); // Sunday / off-day
        }

        $sessionId = AcademicSession::currentSession()?->id;
        $dateStr = $date->toDateString();

        // ── 1. Absences today → per-teacher availability map ──
        $absencesQ = TeacherAbsence::where('absent_on', $dateStr);
        if ($sessionId) $absencesQ->where('academic_session_id', $sessionId);
        $absences = $absencesQ->get();
        if ($absences->isEmpty()) {
            // Nobody absent — but still prune any orphan suggestions.
            $this->pruneStaleSuggestions($dateStr, collect());
            return $this->emptySummary($date);
        }

        // user_id => ['full' => bool, 'from' => 'HH:MM'|null]
        $absenceByTeacher = $absences->mapWithKeys(fn ($a) => [
            $a->user_id => [
                'full' => empty($a->from_time),
                'from' => $a->from_time ? $this->hm($a->from_time) : null,
            ],
        ])->all();
        $absentTeacherIds = array_keys($absenceByTeacher);

        // ── 2. Their periods this weekday that actually need a cover ──
        $needsCover = TimetableEntry::query()
            ->when($sessionId, fn ($q) => $q->where('academic_session_id', $sessionId))
            ->whereIn('teacher_id', $absentTeacherIds)
            ->where('weekday', $weekday)
            ->whereNotNull('teacher_id')
            ->with(['timeSlot', 'subject', 'schoolClass', 'section', 'teacher'])
            ->when($schoolId, fn ($q) => $q->whereHas('schoolClass', fn ($x) => $x->where('school_id', $schoolId)))
            ->get()
            ->filter(function ($e) use ($absenceByTeacher) {
                if (!$e->timeSlot) return false;
                $info = $absenceByTeacher[$e->teacher_id] ?? null;
                if (!$info) return false;
                if ($info['full']) return true;
                // Partial: only periods that start at/after the leave time.
                return $this->hm($e->timeSlot->starts_at) >= $info['from'];
            })
            ->values();

        $needsByEntryId = $needsCover->keyBy('id');

        // Prune suggestions that are no longer needed (teacher returned etc.).
        $this->pruneStaleSuggestions($dateStr, $needsCover->pluck('id'));

        if ($needsCover->isEmpty()) {
            return $this->emptySummary($date);
        }

        // ── 3. Existing assignments — preserve confirmed/declined ──
        $existing = SubstitutionAssignment::where('date', $dateStr)
            ->whereIn('timetable_entry_id', $needsCover->pluck('id'))
            ->get()
            ->keyBy('timetable_entry_id');

        // entry_id => teacher_id the cell's cover was declined by (avoid re-pick)
        $declinedFor = $existing
            ->filter(fn ($a) => $a->status === 'declined')
            ->mapWithKeys(fn ($a) => [$a->timetable_entry_id => $a->substitute_teacher_id])
            ->all();

        // ── 4. Busy map (teacherId|slotId) ──
        // (a) every teacher teaching their own class this weekday
        $allEntriesToday = TimetableEntry::query()
            ->when($sessionId, fn ($q) => $q->where('academic_session_id', $sessionId))
            ->where('weekday', $weekday)
            ->whereNotNull('teacher_id')
            ->get(['teacher_id', 'time_slot_id', 'subject_id', 'school_class_id', 'section_id']);

        $busy = [];
        foreach ($allEntriesToday as $e) {
            $busy[$e->teacher_id . '|' . $e->time_slot_id] = true;
        }
        // (b) already-confirmed covers consume the substitute's slot too
        $confirmedCovers = SubstitutionAssignment::where('date', $dateStr)
            ->where('status', 'confirmed')
            ->with('timetableEntry:id,time_slot_id')
            ->get();
        foreach ($confirmedCovers as $c) {
            if ($c->substitute_teacher_id && $c->timetableEntry) {
                $busy[$c->substitute_teacher_id . '|' . $c->timetableEntry->time_slot_id] = true;
            }
        }

        // Context for soft scoring.
        $teacherSubjects = $allEntriesToday
            ->whereNotNull('subject_id')
            ->groupBy('teacher_id')
            ->map(fn ($rows) => $rows->pluck('subject_id')->unique()->all());
        $teacherClasses = $allEntriesToday
            ->groupBy('teacher_id')
            ->map(fn ($rows) => $rows->pluck('school_class_id')->unique()->all());
        $classTeacherBySection = \App\Models\Section::pluck('class_teacher_id', 'id')->all();

        $examInvigilators = $this->buildInvigilatorSet($date);

        $candidatePool = User::query()
            ->where('is_active', true)
            ->whereHas('roles', fn ($q) => $q->whereIn('name', ['class-teacher', 'subject-teacher']))
            ->whereNotIn('id', $absentTeacherIds) // H2 + (most of) H3
            ->when($schoolId, fn ($q) => $q->where('school_id', $schoolId))
            ->get(['id', 'name', 'school_id']);

        // Covers already counted toward each teacher's daily cap (confirmed
        // ones survive a re-run; suggested are about to be rebuilt).
        $subsAssigned = [];
        foreach ($confirmedCovers as $c) {
            $subsAssigned[$c->substitute_teacher_id] = ($subsAssigned[$c->substitute_teacher_id] ?? 0) + 1;
        }

        // ── 5. Decide a substitute for each uncovered period ──
        $assignments = [];
        $uncoveredPeriods = [];

        foreach ($needsCover as $entry) {
            $prior = $existing->get($entry->id);

            // Confirmed → leave exactly as is (already in busy + counts).
            if ($prior && $prior->status === 'confirmed') {
                continue;
            }

            $slotStart = $this->hm($entry->timeSlot->starts_at);
            $declinedTeacher = $declinedFor[$entry->id] ?? null;

            $best = null;
            $bestScore = -1e9;
            $bestBreakdown = null;

            foreach ($candidatePool as $cand) {
                // H1/H4 — free in this slot (own class OR an assigned cover)
                if (isset($busy[$cand->id . '|' . $entry->time_slot_id])) continue;
                // H5 — same school
                if ($cand->school_id && $entry->schoolClass?->school_id
                    && $cand->school_id !== $entry->schoolClass->school_id) continue;
                // H7 — daily cap
                if (($subsAssigned[$cand->id] ?? 0) >= self::MAX_COVERS_PER_DAY) continue;
                // H6 — not invigilating an exam in this slot
                if ($this->isInvigilating($examInvigilators, $cand->id, $entry)) continue;
                // H8 — don't re-suggest the teacher who declined this cell
                if ($declinedTeacher && $cand->id === $declinedTeacher) continue;
                // H3 — partially-absent teacher who has already left
                if ($this->isAwayAt($absenceByTeacher, $cand->id, $slotStart)) continue;

                [$score, $reasons] = $this->scoreCandidate(
                    $cand, $entry, $teacherSubjects, $teacherClasses,
                    $classTeacherBySection, $subsAssigned
                );

                if ($score > $bestScore
                    || ($score === $bestScore && $cand->name < ($best?->name ?? "\xff"))) {
                    $best = $cand;
                    $bestScore = $score;
                    $bestBreakdown = [
                        'teacher_name' => $cand->name,
                        'total' => $score,
                        'reasons' => $reasons,
                    ];
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

            // Reserve the slot so the next period can't reuse this teacher.
            $busy[$best->id . '|' . $entry->time_slot_id] = true;
            $subsAssigned[$best->id] = ($subsAssigned[$best->id] ?? 0) + 1;
            $assignments[] = [
                'entry' => $entry,
                'substitute' => $best,
                'score' => $bestScore,
                'breakdown' => $bestBreakdown,
            ];
        }

        // ── 6. Persist (never touching confirmed rows) ──
        $priorByEntry = $existing->mapWithKeys(fn ($a) => [
            $a->timetable_entry_id => $a->substitute_teacher_id,
        ]);

        $persistedRows = [];
        DB::transaction(function () use ($assignments, $dateStr, $createdBy, $sessionId, &$persistedRows) {
            foreach ($assignments as $a) {
                $row = SubstitutionAssignment::updateOrCreate(
                    [
                        'date' => $dateStr,
                        'timetable_entry_id' => $a['entry']->id,
                    ],
                    [
                        'academic_session_id' => $sessionId,
                        'original_teacher_id' => $a['entry']->teacher_id,
                        'substitute_teacher_id' => $a['substitute']->id,
                        'status' => 'suggested',
                        'created_by' => $createdBy,
                        'score_breakdown' => $a['breakdown'],
                        'notes' => null,
                    ]
                );
                $persistedRows[] = $row;
            }
        });

        // ── 7. Notify only newly-suggested / changed substitutes ──
        foreach ($persistedRows as $row) {
            if (($priorByEntry[$row->timetable_entry_id] ?? null) === $row->substitute_teacher_id) {
                continue;
            }
            $teacher = User::find($row->substitute_teacher_id);
            if (!$teacher) continue;
            $row->loadMissing(['timetableEntry.timeSlot', 'timetableEntry.subject', 'timetableEntry.schoolClass', 'timetableEntry.section', 'originalTeacher']);
            try {
                Notification::send($teacher, new SubstitutionAssignedNotification($row, 'suggested'));
            } catch (\Throwable $e) {
                report($e);
            }
        }

        $confirmedKept = $needsCover->filter(
            fn ($e) => ($existing->get($e->id)?->status) === 'confirmed'
        )->count();

        $bySubstitute = collect($assignments)
            ->groupBy(fn ($a) => $a['substitute']->id)
            ->map(fn ($rows) => [
                'teacher_id' => $rows->first()['substitute']->id,
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
            'date' => $dateStr,
            'weekday' => $weekday,
            'total_periods' => $needsCover->count(),
            'covered' => count($assignments) + $confirmedKept,
            'uncovered' => count($uncoveredPeriods),
            'absent_teachers' => User::whereIn('id', $absentTeacherIds)->pluck('name')->all(),
            'by_substitute' => $bySubstitute,
            'uncovered_periods' => $uncoveredPeriods,
        ];
    }

    /** Soft score for one candidate against one period. */
    protected function scoreCandidate(
        $cand, $entry, $teacherSubjects, $teacherClasses, $classTeacherBySection, $subsAssigned
    ): array {
        $score = 0;
        $reasons = [];

        if ($entry->subject_id && in_array($entry->subject_id, $teacherSubjects[$cand->id] ?? [], true)) {
            $score += 10;
            $reasons[] = ['+10', 'teaches ' . ($entry->subject?->name ?? 'this subject') . ' elsewhere'];
        }
        if (in_array($entry->school_class_id, $teacherClasses[$cand->id] ?? [], true)) {
            $score += 5;
            $reasons[] = ['+5', 'familiar with ' . ($entry->schoolClass?->name ?? 'this class')];
        }
        if (($classTeacherBySection[$entry->section_id] ?? null) === $cand->id) {
            $score += 3;
            $reasons[] = ['+3', 'class teacher of section ' . ($entry->section?->name ?? '')];
        }
        $existingCovers = $subsAssigned[$cand->id] ?? 0;
        if ($existingCovers > 0) {
            $penalty = -2 * $existingCovers;
            $score += $penalty;
            $reasons[] = [(string) $penalty, "already has {$existingCovers} cover(s) today"];
        }

        return [$score, $reasons];
    }

    /**
     * Is this teacher unavailable at $slotStart because of their own absence?
     * Used both to bar them as a substitute (H3) and by reassign validation.
     */
    public function isAwayAt(array $absenceByTeacher, int $teacherId, string $slotStart): bool
    {
        $info = $absenceByTeacher[$teacherId] ?? null;
        if (!$info) return false;
        if ($info['full']) return true;
        return $slotStart >= $info['from'];
    }

    /** Normalise a time value to 'HH:MM' for safe lexicographic comparison. */
    protected function hm(?string $t): string
    {
        return substr((string) $t, 0, 5);
    }

    /**
     * Delete 'suggested' covers for the date whose period is no longer in
     * the needs-cover set. Confirmed/declined rows are left intact.
     */
    protected function pruneStaleSuggestions(string $dateStr, Collection $stillNeededEntryIds): void
    {
        SubstitutionAssignment::where('date', $dateStr)
            ->where('status', 'suggested')
            ->when(
                $stillNeededEntryIds->isNotEmpty(),
                fn ($q) => $q->whereNotIn('timetable_entry_id', $stillNeededEntryIds->all()),
            )
            ->delete();
    }

    /**
     * Build a quick-lookup set of teachers invigilating exams on this date.
     */
    protected function buildInvigilatorSet(Carbon $date): Collection
    {
        if (!class_exists(ExamSchedule::class)) return collect();

        $rows = ExamSchedule::query()
            ->whereDate('exam_date', $date->toDateString())
            ->get();
        if ($rows->isEmpty()) return collect();

        $assignments = SubjectTeacher::query()
            ->where('is_active', true)
            ->whereIn('school_class_id', $rows->pluck('school_class_id'))
            ->whereIn('subject_id', $rows->pluck('subject_id'))
            ->get();

        $set = collect();
        foreach ($rows as $r) {
            $matching = $assignments->where('school_class_id', $r->school_class_id)
                ->where('subject_id', $r->subject_id);
            foreach ($matching as $st) {
                $set->push([
                    'teacher_id' => $st->user_id,
                    'start_time' => $r->start_time,
                    'end_time' => $r->end_time,
                ]);
            }
        }
        return $set;
    }

    /** Is `teacherId` in an exam during the candidate slot's window? */
    protected function isInvigilating(Collection $invigilators, int $teacherId, TimetableEntry $entry): bool
    {
        if ($invigilators->isEmpty() || !$entry->timeSlot) return false;
        $slotStart = $entry->timeSlot->starts_at;
        $slotEnd = $entry->timeSlot->ends_at;
        foreach ($invigilators as $inv) {
            if ($inv['teacher_id'] !== $teacherId) continue;
            if ($slotEnd > $inv['start_time'] && $slotStart < $inv['end_time']) {
                return true;
            }
        }
        return false;
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
