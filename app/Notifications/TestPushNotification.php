<?php

namespace App\Notifications;

use App\Notifications\Channels\WebPushChannel;
use Illuminate\Notifications\Notification;

/**
 * Sent by the user's own "Send test" button in Profile → Notifications.
 * Delivers via WebPushChannel only — no DB row, no email.
 */
class TestPushNotification extends Notification
{
    public function via(mixed $notifiable): array
    {
        return [WebPushChannel::class];
    }

    public function toWebPush(mixed $notifiable): array
    {
        return [
            'title' => 'Test notification',
            'body'  => "Hello {$notifiable->name}! Push notifications are working on this device.",
            'icon'  => '/pwa-192x192.png',
            'badge' => '/pwa-64x64.png',
            'tag'   => 'test-' . now()->timestamp,
            'url'   => '/dashboard',
        ];
    }
}
