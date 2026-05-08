<?php

namespace App\Notifications;

use App\Notifications\Channels\WebPushChannel;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * "Here's your day at a glance" — sent once at start of day instead of
 * pushing 7 separate per-period reminders.
 *
 * Receives a list of period descriptors (own classes + cover duties) and
 * renders a compact summary in push, mail and DB notification entry.
 */
class DailyScheduleDigestNotification extends Notification
{
    /**
     * @param array $periods  Each item: [
     *   'starts_at', 'ends_at', 'subject', 'class', 'section',
     *   'is_cover' (bool), 'replaces' (?string)
     * ]
     */
    public function __construct(
        public array $periods,
        public string $dayLabel
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', WebPushChannel::class];
    }

    protected function summary(): string
    {
        $count = count($this->periods);
        $covers = array_filter($this->periods, fn ($p) => $p['is_cover'] ?? false);
        $coverCount = count($covers);
        $own = $count - $coverCount;
        $first = $this->periods[0] ?? null;
        $firstStart = $first ? $first['starts_at'] : '';

        $parts = [];
        if ($own > 0) $parts[] = "{$own} class" . ($own === 1 ? '' : 'es');
        if ($coverCount > 0) $parts[] = "{$coverCount} cover" . ($coverCount === 1 ? '' : 's');
        $line = implode(' + ', $parts);
        if ($firstStart) $line .= " starting {$firstStart}";
        return $line;
    }

    public function toWebPush(object $notifiable): array
    {
        return [
            'title' => "Your day — {$this->dayLabel}",
            'body'  => $this->summary(),
            'tag'   => 'daily-digest-' . now()->toDateString(),
            'url'   => '/dashboard',
            'requireInteraction' => false,
        ];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => "Today's schedule — {$this->dayLabel}",
            'message' => $this->summary(),
            'icon' => 'calendar-days',
            'url' => '/dashboard',
            'periods' => $this->periods,
        ];
    }
}
