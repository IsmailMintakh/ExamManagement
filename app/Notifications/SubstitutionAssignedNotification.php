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
            'confirmed' => 'Cover Duty Confirmed',
            'reassigned' => 'Cover Duty Reassigned to You',
            default => 'Possible Cover Duty Today',
        };
        $when = Carbon::parse($this->assignment->date)->format('D, d M');
        return [
            'title' => $title,
            'body'  => "{$when} — {$this->periodLabel()}. Replaces " . ($this->assignment->originalTeacher?->name ?? 'absent teacher') . '.',
            'tag'   => "subst-{$this->assignment->id}-{$this->action}",
            'url'   => '/timetable/substitutions?date=' . $this->assignment->date,
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $when = Carbon::parse($this->assignment->date)->format('l, d F Y');
        $verb = match ($this->action) {
            'confirmed' => 'Your substitution duty has been **confirmed**',
            'reassigned' => 'You have been **reassigned** for this substitution',
            default => 'You have been **suggested** as a substitute',
        };
        return (new MailMessage)
            ->subject('Substitution Duty — ' . $when)
            ->greeting("Hello {$notifiable->name},")
            ->line($verb . ' for the following period:')
            ->line("**Date:** {$when}")
            ->line("**Period:** {$this->periodLabel()}")
            ->line('**Replacing:** ' . ($this->assignment->originalTeacher?->name ?? 'an absent colleague'))
            ->action('View Substitution Schedule', url('/timetable/substitutions?date=' . $this->assignment->date))
            ->line('Please be in the assigned classroom on time.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => match ($this->action) {
                'confirmed' => 'Cover duty confirmed',
                'reassigned' => 'Cover duty reassigned to you',
                default => 'Possible cover duty',
            },
            'message' => $this->periodLabel() . ' on ' . Carbon::parse($this->assignment->date)->format('D, d M Y'),
            'icon' => 'arrows-right-left',
            'assignment_id' => $this->assignment->id,
            'date' => $this->assignment->date,
            'status' => $this->assignment->status,
            'action_type' => $this->action,
            'url' => '/timetable/substitutions?date=' . $this->assignment->date,
        ];
    }
}
