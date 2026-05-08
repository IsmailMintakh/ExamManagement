<?php

namespace App\Notifications;

use App\Notifications\Channels\WebPushChannel;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * "Your class starts in 2 minutes" reminder. Fires from
 * App\Console\Commands\NotifyUpcomingClasses (scheduled every minute).
 *
 * Push-only by default — email would be too noisy for every period of
 * every day. Database also writes so the bell-icon list shows reminders.
 */
class UpcomingClassNotification extends Notification
{
    public function __construct(
        public string $subject,
        public string $className,
        public string $sectionName,
        public string $startsAt,
        public string $endsAt,
        public ?string $room = null,
        public bool $isCover = false,
        public ?string $replaces = null
    ) {}

    public function via(object $notifiable): array
    {
        // Push first (the whole point), database second so it's auditable.
        return ['database', WebPushChannel::class];
    }

    protected function ctx(): string
    {
        $cls = trim("{$this->className} · {$this->sectionName}");
        return $this->subject . ' — ' . $cls;
    }

    public function toWebPush(object $notifiable): array
    {
        $title = $this->isCover ? 'Cover starting in 2 min' : 'Class starting in 2 min';
        $body = $this->ctx() . " · {$this->startsAt}–{$this->endsAt}";
        if ($this->room) $body .= " · Room {$this->room}";
        if ($this->isCover && $this->replaces) $body .= " · replaces {$this->replaces}";

        return [
            'title' => $title,
            'body'  => $body,
            'tag'   => 'class-soon-' . $this->startsAt . '-' . md5($this->ctx()),
            'url'   => '/dashboard',
            'requireInteraction' => true,
        ];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => $this->isCover ? 'Cover starts in 2 minutes' : 'Class starts in 2 minutes',
            'message' => $this->ctx() . ' · ' . $this->startsAt,
            'icon' => 'bell-alert',
            'subject' => $this->subject,
            'class' => $this->className,
            'section' => $this->sectionName,
            'starts_at' => $this->startsAt,
            'ends_at' => $this->endsAt,
            'room' => $this->room,
            'is_cover' => $this->isCover,
            'replaces' => $this->replaces,
            'url' => '/dashboard',
        ];
    }
}
