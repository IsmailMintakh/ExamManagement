<?php

namespace App\Console\Commands;

use App\Models\SubstitutionAssignment;
use App\Models\TeacherAbsence;
use App\Models\TimetableEntry;
use App\Models\User;
use App\Notifications\DailyScheduleDigestNotification;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

/**
 * Sends each teacher one daily digest push at start of day, listing all
 * the periods + cover duties they have today. Replaces the bursty per-period
 * reminders for routine classes — those are still triggered 2 min before the
 * NEXT class only (not every period) by NotifyUpcomingClasses.
 *
 * Schedule: weekdays 07:00 (or whenever you want admin to send it).
 */
class SendDailyScheduleDigest extends Command
{
    protected $signature = 'timetable:send-daily-digest {--dry-run}';
    protected $description = 'Push a "today\'s schedule" digest to every teacher with classes/covers today.';

    protected array $weekdayMap = [
        1 => 'mon', 2 => 'tue', 3 => 'wed', 4 => 'thu', 5 => 'fri', 6 => 'sat',
    ];

    public function handle(): int
    {
        $now = Carbon::now();
        $code = $this->weekdayMap[$now->dayOfWeekIso] ?? null;
        if (!$code) {
            $this->info('Sunday — no digest.');
            return self::SUCCESS;
        }

        $today = $now->toDateString();
        $absentToday = TeacherAbsence::whereDate('absent_on', $today)->pluck('user_id')->all();
        $absentSet = array_flip($absentToday);

        $coversByEntry = SubstitutionAssignment::query()
            ->whereDate('date', $today)
            ->whereIn('status', ['suggested', 'confirmed'])
            ->get()
            ->keyBy('timetable_entry_id');

        // All entries on this weekday across all sections — group by teacher
        // so we can build one digest per recipient.
        $entries = TimetableEntry::query()
            ->where('weekday', $code)
            ->whereNotNull('teacher_id')
            ->with(['timeSlot', 'subject:id,name', 'schoolClass:id,name', 'section:id,name'])
            ->get();

        $byTeacher = [];

        foreach ($entries as $e) {
            if (!$e->timeSlot || $e->timeSlot->type !== 'period') continue;
            $isAbsent = isset($absentSet[$e->teacher_id]);
            $cover = $coversByEntry->get($e->id);

            // Original teacher's own classes (only if not absent)
            if (!$isAbsent) {
                $byTeacher[$e->teacher_id][] = $this->shape($e, isCover: false);
            }

            // Cover periods — push to substitute teacher
            if ($cover && $cover->substitute_teacher_id) {
                $byTeacher[$cover->substitute_teacher_id][] = $this->shape($e, isCover: true, replaces: $e->teacher?->name);
            }
        }

        if (empty($byTeacher)) {
            $this->info('Nothing to send.');
            return self::SUCCESS;
        }

        $sent = 0;
        $dry = (bool) $this->option('dry-run');
        $dayLabel = $now->format('l, d M Y');

        foreach ($byTeacher as $teacherId => $periods) {
            // Sort by start time
            usort($periods, fn ($a, $b) => strcmp($a['starts_at'], $b['starts_at']));
            $teacher = User::find($teacherId);
            if (!$teacher) continue;

            if ($dry) {
                $this->line("→ {$teacher->name}: " . count($periods) . " periods");
                continue;
            }
            try {
                Notification::send($teacher, new DailyScheduleDigestNotification($periods, $dayLabel));
                $sent++;
            } catch (\Throwable $e) {
                $this->error("Failed for {$teacher->name}: " . $e->getMessage());
                report($e);
            }
        }

        $this->info(($dry ? '[DRY-RUN] would send to ' : 'Sent digest to ') . $sent . ' teachers.');
        return self::SUCCESS;
    }

    protected function shape($e, bool $isCover, ?string $replaces = null): array
    {
        return [
            'starts_at' => substr($e->timeSlot->starts_at, 0, 5),
            'ends_at' => substr($e->timeSlot->ends_at, 0, 5),
            'subject' => $e->subject?->name,
            'class' => $e->schoolClass?->name,
            'section' => $e->section?->name,
            'is_cover' => $isCover,
            'replaces' => $replaces,
        ];
    }
}
