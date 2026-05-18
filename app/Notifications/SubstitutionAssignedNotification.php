<?php

namespace App\Notifications;

use App\Models\SubstitutionAssignment;
use App\Notifications\Channels\WebPushChannel;
use Carbon\Carbon;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to a teacher when they are assigned (or auto-suggested) as a
 * substitute for an absent colleague's period. Sync delivery — same
 * pattern as MarksSubmittedNotification.
 *
 * Action types:
 *   - 'suggested'  → "You may be needed for cover today"
 *   - 'confirmed'  → "Your cover duty is confirmed"
 *   - 'reassigned' → "You have been reassigned this cover"
 */
class SubstitutionAssignedNotification extends Notification
{
    public function __construct(
        public SubstitutionAssignment $assignment,
        public string $action = 'suggested'
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail', WebPushChannel::class];
    }

    protected function periodLabel(): string
    {
        $e = $this->assignment->timetableEntry;
        $time = $e?->timeSlot
            ? substr($e->timeSlot->starts_at, 0, 5) . '–' . substr($e->timeSlot->ends_at, 0, 5)
            : '';
        $cls = trim(($e?->schoolClass?->name ?? '') . ' ' . ($e?->section?->name ?? ''));
        $sub = $e?->subject?->name ?? 'Class';
        return trim("{$sub} · {$cls} · {$time}");
    }

    public function toWebPush(object $notifiable): array
    {
        $title = match ($this->action) {
            'confirmed' => 'Class Adjustment Confirmed',
            'reassigned' => 'Class Adjustment Reassigned to You',
            default => 'Possible Class Adjustment Today',
        };
        $when = Carbon::parse($this->assignment->date)->format('D, d M');
        return [
            'title' => $title,
            'body'  => "{$when} — {$this->periodLabel()}. Replaces " . ($this->assignment->originalTeacher?->name ?? 'absent teacher') . '.',
            'tag'   => "adjustment-{$this->assignment->id}-{$this->action}",
            'url'   => '/timetable/adjustments?date=' . $this->assignment->date,
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $when = Carbon::parse($this->assignment->date)->format('l, d F Y');
        $verb = match ($this->action) {
            'confirmed' => 'Your class adjustment has been **confirmed**',
            'reassigned' => 'You have been **reassigned** for this class adjustment',
            default => 'You have been **suggested** for a class adjustment',
        };
        return (new MailMessage)
            ->subject('Class Adjustment — ' . $when)
            ->greeting("Hello {$notifiable->name},")
            ->line($verb . ' for the following period:')
            ->line("**Date:** {$when}")
            ->line("**Period:** {$this->periodLabel()}")
            ->line('**Replacing:** ' . ($this->assignment->originalTeacher?->name ?? 'an absent colleague'))
            ->action('View Class Adjustments', url('/timetable/adjustments?date=' . $this->assignment->date))
            ->line('Please be in the assigned classroom on time.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => match ($this->action) {
                'confirmed' => 'Class adjustment confirmed',
                'reassigned' => 'Class adjustment reassigned to you',
                default => 'Possible class adjustment',
            },
            'message' => $this->periodLabel() . ' on ' . Carbon::parse($this->assignment->date)->format('D, d M Y'),
            'icon' => 'arrows-right-left',
            'assignment_id' => $this->assignment->id,
            'date' => $this->assignment->date,
            'status' => $this->assignment->status,
            'action_type' => $this->action,
            'url' => '/timetable/adjustments?date=' . $this->assignment->date,
        ];
    }
}
