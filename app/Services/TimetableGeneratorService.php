<?php

namespace App\Services;

use App\Models\AcademicSession;
use App\Models\ClassSubject;
use App\Models\School;
use App\Models\Section;
use App\Models\SubjectTeacher;
use App\Models\TimeSlot;
use App\Models\TimetableEntry;
use Illuminate\Support\Facades\DB;

/**
 * Auto-timetable engine — FIXED DAILY ROUTINE model.
 *
 * Schools here run the same routine every day: Period 1 of Class 8 is
 * Computer on Mon, Tue, Wed … Sat. So we decide ONE (subject, teacher) per
 * period slot per section, then repeat it across every weekday the slot runs.
 *
 * Source of truth is the `subject_teachers` table — "teacher T teaches
 * subject S to section X".
 *
 * DISTRIBUTION
 *   Period slots are split evenly across the section's subjects. Leftover
 *   slots go to the "important" subjects first (is_main, then sort_order).
 *   Subjects are spread so the same one isn't in two back-to-back periods
 *   when it can be avoided. A subject with several teachers round-robins.
 *
 * HARD RULE (never violated)
 *   Because a period is taught every day, a teacher can hold at most ONE
 *   section in a given period slot across the whole school. Tracked by a
 *   busy map keyed `teacherId|slotId`, seeded from sections we don't touch
 *   (locked / skipped) and grown as we assign.
 *
 * SKIPS (reported, not fatal)
 *   - Locked sections are never modified.
 *   - Non-empty sections are left alone unless $overwrite = true.
 *   - Subjects with no teacher assignment can't be placed.
 *   - A period left blank when every candidate teacher is already taken.
 */
class TimetableGeneratorService
{
    /** Canonical weekday order. */
    protected const WEEKDAYS = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat'];

    /**
     * @param  int[]|null  $sectionIds  limit to these sections; null = every active section of the school
     * @param  bool  $overwrite  rebuild sections that already have a timetable (locked sections still skipped)
     */
    public function generate(School $school, ?int $sessionId = null, ?array $sectionIds = null, bool $overwrite = false): array
    {
        $sessionId ??= AcademicSession::currentSession()?->id;
        if (!$sessionId) {
            return $this->summary($school, error: 'No active academic session configured.');
        }

        $periodSlots = TimeSlot::where('school_id', $school->id)
            ->where('type', 'period')
            ->ordered()
            ->get();
        if ($periodSlots->isEmpty()) {
            return $this->summary($school, error: 'No bell schedule (period slots) configured for this school.');
        }

        $sections = Section::query()
            ->whereHas('schoolClass', fn ($q) => $q->where('school_id', $school->id))
            ->when($sectionIds, fn ($q) => $q->whereIn('id', $sectionIds))
            ->with('schoolClass:id,name,sort_order')
            ->active()
            ->get()
            ->sortBy(fn ($s) => sprintf('%05d-%s', $s->schoolClass?->sort_order ?? 999, $s->name))
            ->values();

        // Decide per section: build / skip-locked / skip-existing.
        $targetIds = [];
        $skipped = [];
        foreach ($sections as $sec) {
            if ($sec->timetable_locked) {
                $skipped[] = $this->row($sec, 'locked');
                continue;
            }
            $hasEntries = TimetableEntry::forSession($sessionId)
                ->where('section_id', $sec->id)->exists();
            if ($hasEntries && !$overwrite) {
                $skipped[] = $this->row($sec, 'already has a timetable (use overwrite to rebuild)');
                continue;
            }
            $targetIds[] = $sec->id;
        }

        // Seed the teacher-busy map from every entry that survives — i.e.
        // sections we are NOT regenerating. A period is every-day, so the
        // key is teacher|slot (no weekday).
        $busy = [];
        $survivingEntries = TimetableEntry::forSession($sessionId)
            ->whereHas('section.schoolClass', fn ($q) => $q->where('school_id', $school->id))
            ->whereNotIn('section_id', $targetIds)
            ->whereNotNull('teacher_id')
            ->get(['teacher_id', 'time_slot_id']);
        foreach ($survivingEntries as $e) {
            $busy[$e->teacher_id . '|' . $e->time_slot_id] = true;
        }

        $results = [];
        $created = 0;

        DB::transaction(function () use (
            $sections, $targetIds, $sessionId, $periodSlots, &$busy, &$results, &$created
        ) {
            TimetableEntry::forSession($sessionId)
                ->whereIn('section_id', $targetIds)
                ->delete();

            foreach ($sections as $sec) {
                if (!in_array($sec->id, $targetIds, true)) {
                    continue;
                }
                $results[] = $this->buildSection(
                    $sec, $sessionId, $periodSlots, $busy, $created
                );
            }
        });

        return $this->summary(
            $school,
            sessionId: $sessionId,
            built: $results,
            skipped: $skipped,
            created: $created,
        );
    }

    /**
     * Build one section's fixed daily routine.
     * Mutates $busy + $created; returns a per-section report.
     */
    protected function buildSection(
        Section $sec,
        int $sessionId,
        \Illuminate\Support\Collection $periodSlots,
        array &$busy,
        int &$created
    ): array {
        $assignments = SubjectTeacher::query()
            ->where('section_id', $sec->id)
            ->where('academic_session_id', $sessionId)
            ->where('is_active', true)
            ->with('subject:id,name,is_main,sort_order')
            ->get()
            ->filter(fn ($a) => $a->subject !== null)
            ->groupBy('subject_id');

        if ($assignments->isEmpty()) {
            return $this->row($sec, 'no teacher assignments — nothing to schedule');
        }

        // Order: main subjects first, then sort_order, then name. Leading
        // subjects also receive the leftover (remainder) slots.
        $subjects = $assignments->map(function ($rows, $subjectId) {
            $subject = $rows->first()->subject;
            return [
                'id' => (int) $subjectId,
                'name' => $subject->name,
                'is_main' => (bool) $subject->is_main,
                'sort_order' => (int) ($subject->sort_order ?? 0),
                'teachers' => $rows->pluck('user_id')->unique()->values()->all(),
            ];
        })->sortBy([
            ['is_main', 'desc'],
            ['sort_order', 'asc'],
            ['name', 'asc'],
        ])->values();

        $slotCount = $periodSlots->count();
        $subjectCount = $subjects->count();

        // Even split of period slots across subjects; first `remainder`
        // subjects (main ones) get one extra slot.
        $base = intdiv($slotCount, $subjectCount);
        $remainder = $slotCount - ($base * $subjectCount);

        $quota = [];          // subjectId => slots to fill
        $teacherCursor = [];  // subjectId => round-robin pointer over its teachers
        $unscheduled = [];    // subjects that got zero slots (more subjects than periods)
        foreach ($subjects as $i => $s) {
            $q = $base + ($i < $remainder ? 1 : 0);
            if ($q === 0) {
                $unscheduled[] = $s['name'];
                continue;
            }
            $quota[$s['id']] = $q;
            $teacherCursor[$s['id']] = 0;
        }

        $subjectById = $subjects->keyBy('id');
        $blankSlots = 0;
        $prevSubjectId = null;

        // One decision per period slot, then fan out to every weekday it runs.
        foreach ($periodSlots as $slot) {
            $picked = $this->pickSubject(
                $quota, $prevSubjectId, $subjectById, $teacherCursor, $busy, $slot->id
            );

            if ($picked === null) {
                $blankSlots++;
                $prevSubjectId = null;
                continue;
            }

            [$subjectId, $teacherId] = $picked;
            $days = $slot->weekdays ?: self::WEEKDAYS;

            foreach ($days as $day) {
                if (!in_array($day, self::WEEKDAYS, true)) {
                    continue;
                }
                TimetableEntry::create([
                    'section_id' => $sec->id,
                    'school_class_id' => $sec->school_class_id,
                    'academic_session_id' => $sessionId,
                    'weekday' => $day,
                    'time_slot_id' => $slot->id,
                    'subject_id' => $subjectId,
                    'teacher_id' => $teacherId,
                    'room' => null,
                ]);
                $created++;
            }

            $busy[$teacherId . '|' . $slot->id] = true;
            $quota[$subjectId]--;
            $teacherCursor[$subjectId]++;
            $prevSubjectId = $subjectId;
        }

        // Class subjects that never got a teacher assigned at all.
        $classSubjectIds = ClassSubject::where('school_class_id', $sec->school_class_id)
            ->where('is_active', true)
            ->pluck('subject_id');
        $missingTeacher = $classSubjectIds
            ->reject(fn ($id) => $assignments->has($id))
            ->count();

        $unplaced = array_sum($quota);
        $notes = [];
        if ($blankSlots > 0) {
            $notes[] = "{$blankSlots} period(s) left blank — every assigned teacher is already taken in that period elsewhere";
        }
        if ($unplaced > 0) {
            $notes[] = "{$unplaced} planned period(s) could not be placed (teacher clashes)";
        }
        if (!empty($unscheduled)) {
            $notes[] = count($unscheduled) . ' subject(s) unscheduled — more subjects than periods: ' . implode(', ', $unscheduled);
        }
        if ($missingTeacher > 0) {
            $notes[] = "{$missingTeacher} class subject(s) have no teacher assigned and were skipped";
        }

        return $this->row(
            $sec,
            status: $blankSlots === 0 && $unplaced === 0 ? 'complete' : 'partial',
            periods: $slotCount - $blankSlots,
            totalCells: $slotCount,
            subjects: $subjectCount,
            notes: $notes,
        );
    }

    /**
     * Pick the best (subjectId, teacherId) for one period slot, or null.
     *
     * Preference: a subject that isn't the immediately-previous one (so the
     * routine reads nicely) with a free teacher; relaxed to allow a repeat
     * only if nothing else fits. Within that, the subject with the most
     * remaining quota wins to keep counts balanced.
     */
    protected function pickSubject(
        array $quota,
        ?int $prevSubjectId,
        \Illuminate\Support\Collection $subjectById,
        array $teacherCursor,
        array $busy,
        int $slotId
    ): ?array {
        foreach ([false, true] as $allowRepeat) {
            $candidates = [];
            foreach ($quota as $subjectId => $left) {
                if ($left <= 0) {
                    continue;
                }
                if (!$allowRepeat && $subjectId === $prevSubjectId) {
                    continue;
                }
                $candidates[$subjectId] = $left;
            }
            arsort($candidates); // most-remaining first → balanced spread

            foreach ($candidates as $subjectId => $left) {
                $subject = $subjectById->get($subjectId);
                $teachers = $subject['teachers'];
                $count = count($teachers);
                for ($i = 0; $i < $count; $i++) {
                    $teacherId = $teachers[($teacherCursor[$subjectId] + $i) % $count];
                    if (!isset($busy[$teacherId . '|' . $slotId])) {
                        return [$subjectId, $teacherId];
                    }
                }
            }
        }

        return null;
    }

    /** One section's report line. */
    protected function row(
        Section $sec,
        string $status,
        int $periods = 0,
        int $totalCells = 0,
        int $subjects = 0,
        array $notes = []
    ): array {
        return [
            'section_id' => $sec->id,
            'class_name' => $sec->schoolClass?->name,
            'section_name' => $sec->name,
            'status' => $status,
            'periods_filled' => $periods,
            'total_cells' => $totalCells,
            'subjects' => $subjects,
            'notes' => $notes,
        ];
    }

    /** Final summary payload (also used for early-exit error states). */
    protected function summary(
        School $school,
        ?int $sessionId = null,
        array $built = [],
        array $skipped = [],
        int $created = 0,
        ?string $error = null
    ): array {
        return [
            'school' => ['id' => $school->id, 'name' => $school->name],
            'session_id' => $sessionId,
            'error' => $error,
            'entries_created' => $created,
            'sections_built' => count($built),
            'sections_skipped' => count($skipped),
            'built' => $built,
            'skipped' => $skipped,
        ];
    }
}
