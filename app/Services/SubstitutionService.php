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
 * Auto-substitution engine.
 *
 * Inputs: a date.
 * Outputs: SubstitutionAssignment rows in 'suggested' state for every empty
 * timetable cell caused by an absent teacher.
 *
 * Algorithm (with hard-stops + soft scoring):
 *   HARD FILTERS (any one fails → candidate skipped):
 *     - busy in this slot
 *     - school mismatch
 *     - already at max_periods_per_day cover cap
 *     - invigilating an exam at this date+time (ExamSchedule cross-check)
 *
 *   SOFT SCORING (additive):
 *     +10  same-subject teacher elsewhere
 *     + 5  teaches any subject in the same class
 *     + 3  class-teacher in the same section
 *     - 2  per substitution already given today (load balancing)
 *
 *   TIE-BREAK: fewer existing covers → alphabetical by name.
 *
 * Output stores `score_breakdown` json on each SubstitutionAssignment so the
 * admin "why this teacher?" popover can render the score components.
 */
class SubstitutionService
{
    /** Hard cap: no teacher gets more than this many covers in one day. */
    public const MAX_COVERS_PER_DAY = 3;

    /** Map ISO weekday number → our 'mon'..'sat' enum values. */
    protected array $weekdayMap = [
        1 => 'mon', 2 => 'tue', 3 => 'wed', 4 => 'thu', 5 => 'fri', 6 => 'sat',
    ];

    public function generateForDate(Carbon $date, ?int $createdBy = null): array
    {
        $weekday = $this->weekdayMap[$date->dayOfWeekIso] ?? null;
        if (!$weekday) {
            return $this->emptySummary($date);
        }

        $sessionId = AcademicSession::currentSession()?->id;

        // ── 1. Absent teachers today ──
        $absencesQ = TeacherAbsence::where('absent_on', $date->toDateString());
        if ($sessionId) $absencesQ->where('academic_session_id', $sessionId);
        $absences = $absencesQ->get();
        if ($absences->isEmpty()) {
            return $this->emptySummary($date);
        }

        $absentTeacherIds = $absences->pluck('user_id')->all();
        $cutoffByTeacher = $absences->mapWithKeys(fn ($a) => [
            $a->user_id => $a->from_time,
        ])->all();

        // ── 2. Their entries this weekday — filter by per-teacher cutoff ──
        $needsCover = TimetableEntry::query()
            ->when($sessionId, fn ($q) => $q->where('academic_session_id', $sessionId))
            ->whereIn('teacher_id', $absentTeacherIds)
            ->where('weekday', $weekday)
            ->with(['timeSlot', 'subject', 'schoolClass', 'section', 'teacher'])
            ->get()
            ->filter(function ($e) use ($cutoffByTeacher) {
                $cutoff = $cutoffByTeacher[$e->teacher_id] ?? null;
                if (!$cutoff) return true;
                if (!$e->timeSlot) return false;
                return $e->timeSlot->starts_at >= $cutoff;
            })
            ->values();

        if ($needsCover->isEmpty()) {
            return $this->emptySummary($date);
        }

        // ── 3. Pre-load context ──
        $allEntriesToday = TimetableEntry::query()
            ->when($sessionId, fn ($q) => $q->where('academic_session_id', $sessionId))
            ->where('weekday', $weekday)
            ->select(['teacher_id', 'time_slot_id', 'subject_id', 'school_class_id'])
            ->get();

        $busy = $allEntriesToday
            ->whereNotNull('teacher_id')
            ->mapWithKeys(fn ($e) => [$e->teacher_id . '|' . $e->time_slot_id => true])
            ->all();

        $teacherSubjects = $allEntriesToday
            ->whereNotNull('teacher_id')
            ->whereNotNull('subject_id')
            ->groupBy('teacher_id')
            ->map(fn ($rows) => $rows->pluck('subject_id')->unique()->all());

        $teacherClasses = $allEntriesToday
            ->whereNotNull('teacher_id')
            ->groupBy('teacher_id')
            ->map(fn ($rows) => $rows->pluck('school_class_id')->unique()->all());

        $classTeacherBySection = \App\Models\Section::pluck('class_teacher_id', 'id')->all();

        // ── ExamSchedule cross-check ──
        // Pull every (subject_id, school_class_id) being examined today between
        // the bell-schedule period boundaries. If the candidate teacher is the
        // assigned teacher for any of those exams, they're invigilating —
        // treat that as busy.
        $examInvigilators = $this->buildInvigilatorSet($date);

        $candidatePool = User::query()
            ->where('is_active', true)
            ->whereHas('roles', fn ($q) => $q->whereIn('name', ['class-teacher', 'subject-teacher']))
            ->whereNotIn('id', $absentTeacherIds)
            ->get(['id', 'name', 'school_id']);

        // ── 4. Process each uncovered cell ──
        $subsAssigned = [];
        $existing = SubstitutionAssignment::where('date', $date->toDateString())->get();
        foreach ($existing as $row) {
            $subsAssigned[$row->substitute_teacher_id] = ($subsAssigned[$row->substitute_teacher_id] ?? 0) + 1;
        }

        $assignments = [];
        $uncoveredPeriods = [];

        foreach ($needsCover as $entry) {
            $best = null;
            $bestScore = -1e9;
            $bestBreakdown = null;

            foreach ($candidatePool as $cand) {
                // Hard filter 1: must be free in this slot
                if (isset($busy[$cand->id . '|' . $entry->time_slot_id])) continue;

                // Hard filter 2: school match
                if ($cand->school_id && $entry->schoolClass?->school_id
                    && $cand->school_id !== $entry->schoolClass->school_id) continue;

                // Hard filter 3: max-covers-per-day cap
                if (($subsAssigned[$cand->id] ?? 0) >= self::MAX_COVERS_PER_DAY) continue;

                // Hard filter 4: not invigilating an exam in this slot
                if ($this->isInvigilating($examInvigilators, $cand->id, $entry)) continue;

                // ── Scoring ──
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

            $subsAssigned[$best->id] = ($subsAssigned[$best->id] ?? 0) + 1;
            $assignments[] = [
                'entry' => $entry,
                'substitute' => $best,
                'score' => $bestScore,
                'breakdown' => $bestBreakdown,
            ];
        }

        // ── 5. Persist + collect for notification ──
        $priorByEntry = $existing->keyBy('timetable_entry_id')
            ->map(fn ($a) => $a->substitute_teacher_id);

        $persistedRows = [];
        DB::transaction(function () use ($assignments, $date, $createdBy, $sessionId, &$persistedRows) {
            foreach ($assignments as $a) {
                $row = SubstitutionAssignment::updateOrCreate(
                    [
                        'date' => $date->toDateString(),
                        'timetable_entry_id' => $a['entry']->id,
                    ],
                    [
                        'academic_session_id' => $sessionId,
                        'original_teacher_id' => $a['entry']->teacher_id,
                        'substitute_teacher_id' => $a['substitute']->id,
                        'status' => 'suggested',
                        'created_by' => $createdBy,
                        'score_breakdown' => $a['breakdown'],
                    ]
                );
                $persistedRows[] = ['row' => $row, 'meta' => $a];
            }
        });

        // ── 6. Notify only new/reassigned substitutes ──
        foreach ($persistedRows as $p) {
            $row = $p['row'];
            $prevSubId = $priorByEntry->get($row->timetable_entry_id);
            if ($prevSubId === $row->substitute_teacher_id) continue;
            $teacher = User::find($row->substitute_teacher_id);
            if (!$teacher) continue;
            $row->loadMissing(['timetableEntry.timeSlot', 'timetableEntry.subject', 'timetableEntry.schoolClass', 'timetableEntry.section', 'originalTeacher']);
            try {
                Notification::send($teacher, new SubstitutionAssignedNotification($row, 'suggested'));
            } catch (\Throwable $e) {
                report($e);
            }
        }

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

    /**
     * Build a quick-lookup set of teachers invigilating exams on this date.
     *
     * Returns: collection of arrays {teacher_id, school_class_id, subject_id, start_time, end_time}
     * — the caller checks if a candidate is in this set for the entry's slot.
     */
    protected function buildInvigilatorSet(Carbon $date): Collection
    {
        if (!class_exists(ExamSchedule::class)) return collect();

        $rows = ExamSchedule::query()
            ->whereDate('exam_date', $date->toDateString())
            ->with('exam:id')
            ->get();

        // Each ExamSchedule row covers one (subject, class, time-window).
        // Cross-reference with SubjectTeacher to find who'd normally be teaching
        // (and therefore likely invigilating) that subject in that class.
        if ($rows->isEmpty()) return collect();

        $assignments = SubjectTeacher::query()
            ->where('is_active', true)
            ->whereIn('school_class_id', $rows->pluck('school_class_id'))
            ->whereIn('subject_id', $rows->pluck('subject_id'))
            ->get();

        // Build the set keyed by teacher_id with the time windows.
        $set = collect();
        foreach ($rows as $r) {
            $matchingTeachers = $assignments->where('school_class_id', $r->school_class_id)
                ->where('subject_id', $r->subject_id);
            foreach ($matchingTeachers as $st) {
                $set->push([
                    'teacher_id' => $st->user_id,
                    'school_class_id' => $r->school_class_id,
                    'subject_id' => $r->subject_id,
                    'start_time' => $r->start_time,
                    'end_time' => $r->end_time,
                ]);
            }
        }
        return $set;
    }

    /**
     * Is `teacherId` in an exam during the candidate slot's window?
     */
    protected function isInvigilating(Collection $invigilators, int $teacherId, TimetableEntry $entry): bool
    {
        if ($invigilators->isEmpty() || !$entry->timeSlot) return false;
        $slotStart = $entry->timeSlot->starts_at;
        $slotEnd = $entry->timeSlot->ends_at;
        foreach ($invigilators as $inv) {
            if ($inv['teacher_id'] !== $teacherId) continue;
            // Overlap check: slot intersects exam window?
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
