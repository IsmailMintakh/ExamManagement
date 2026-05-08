<?php

namespace App\Console\Commands;

use App\Models\SubstitutionAssignment;
use App\Models\TeacherAbsence;
use App\Models\TimeSlot;
use App\Models\TimetableEntry;
use App\Models\User;
use App\Notifications\UpcomingClassNotification;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

/**
 * Fires "your class starts in 2 minutes" web-push notifications.
 *
 * Schedule: every minute. The command picks period slots whose start time
 * falls in the [now+2, now+3) window and pushes a notification to the
 * teacher (or substitute, if the original is absent today).
 *
 * Idempotency:
 *   We use a signature ("upcoming-{date}-{slot}-{teacher}") that the
 *   service-worker push handler treats as a tag, so a repeated push for
 *   the same slot is silently replaced — duplicate alerts won't surface
 *   to the user even if scheduling drifts and we fire the same window
 *   twice within the minute.
 */
class NotifyUpcomingClasses extends Command
{
    protected $signature = 'timetable:notify-upcoming
        {--minutes=2 : How many minutes ahead to look (default 2)}
        {--dry-run : Print what would be sent without dispatching}';

    protected $description = 'Push notifications to teachers ~2 min before their next period.';

    /** Map ISO weekday → our codes. */
    protected array $weekdayMap = [
        1 => 'mon', 2 => 'tue', 3 => 'wed', 4 => 'thu', 5 => 'fri', 6 => 'sat',
    ];

    public function handle(): int
    {
        $now = Carbon::now();
        $code = $this->weekdayMap[$now->dayOfWeekIso] ?? null;
        if (!$code) {
            $this->info('Sunday — no notifications.');
            return self::SUCCESS;
        }

        $lead = max(1, (int) $this->option('minutes'));
        $dry = (bool) $this->option('dry-run');

        // The slots whose start time falls in [now+lead, now+lead+1).
        $windowStart = $now->copy()->addMinutes($lead)->format('H:i:s');
        $windowEnd = $now->copy()->addMinutes($lead + 1)->format('H:i:s');

        $slots = TimeSlot::query()
            ->where('type', 'period')
            ->where('starts_at', '>=', $windowStart)
            ->where('starts_at', '<', $windowEnd)
            ->get();

        if ($slots->isEmpty()) {
            $this->info("No periods starting in the next {$lead}–" . ($lead + 1) . ' minutes.');
            return self::SUCCESS;
        }

        $today = $now->toDateString();

        // Cache today's absent teacher set (subbing-out triggers a cover lookup).
        $absentToday = TeacherAbsence::whereDate('absent_on', $today)->pluck('user_id')->all();
        $absentSet = array_flip($absentToday);

        // Cache today's confirmed/suggested covers keyed by entry_id → substitute.
        $coversByEntry = SubstitutionAssignment::query()
            ->whereDate('date', $today)
            ->whereIn('status', ['suggested', 'confirmed'])
            ->get()
            ->keyBy('timetable_entry_id');

        $sent = 0;
        $skipped = 0;

        foreach ($slots as $slot) {
            // Slot doesn't run on this weekday → skip.
            $slotDays = $slot->weekdays ?: ['mon','tue','wed','thu','fri','sat'];
            if (!in_array($code, $slotDays, true)) continue;

            $entries = TimetableEntry::query()
                ->where('time_slot_id', $slot->id)
                ->where('weekday', $code)
                ->whereNotNull('teacher_id')
                ->with(['teacher', 'subject:id,name,code', 'schoolClass:id,name', 'section:id,name', 'timeSlot'])
                ->get();

            foreach ($entries as $entry) {
                // Decide who to notify: original teacher, unless absent and a cover is set.
                $cover = $coversByEntry->get($entry->id);
                $isAbsent = isset($absentSet[$entry->teacher_id]);

                $recipient = null;
                $isCover = false;
                $replaces = null;

                if ($isAbsent && $cover && $cover->substitute_teacher_id) {
                    $recipient = User::find($cover->substitute_teacher_id);
                    $isCover = true;
                    $replaces = $entry->teacher?->name;
                } elseif (!$isAbsent) {
                    $recipient = $entry->teacher;
                }

                if (!$recipient) { $skipped++; continue; }

                $notification = new UpcomingClassNotification(
                    subject: $entry->subject?->name ?? 'Class',
                    className: $entry->schoolClass?->name ?? '',
                    sectionName: $entry->section?->name ?? '',
                    startsAt: substr($slot->starts_at, 0, 5),
                    endsAt: substr($slot->ends_at, 0, 5),
                    room: $entry->room,
                    isCover: $isCover,
                    replaces: $replaces
                );

                if ($dry) {
                    $this->line("→ {$recipient->name}: " . ($entry->subject?->name ?? 'Class') . " ({$entry->schoolClass?->name} {$entry->section?->name}) at " . substr($slot->starts_at, 0, 5));
                } else {
                    try {
                        Notification::send($recipient, $notification);
                        $sent++;
                    } catch (\Throwable $e) {
                        $this->error("Failed for {$recipient->name}: " . $e->getMessage());
                        report($e);
                        $skipped++;
                    }
                }
            }
        }

        $this->info(($dry ? '[DRY-RUN] would send ' : 'Sent ') . $sent . ' notifications, skipped ' . $skipped . '.');
        return self::SUCCESS;
    }
}
