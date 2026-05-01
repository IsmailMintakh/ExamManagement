<?php

namespace App\Notifications\Channels;

use App\Models\PushSubscription as PushSubscriptionModel;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;

/**
 * Custom Laravel notification channel that delivers via Web Push.
 *
 * Notification classes that want to support push need to implement
 * `toWebPush(User $user): array` returning at minimum `title` + `body`.
 * The array is JSON-encoded into the push payload and decoded by the
 * service worker's `push` event listener.
 *
 * Failed deliveries with status 404/410 mean the subscription is dead
 * (uninstalled, permission revoked, expired) — we delete those rows
 * to keep the table self-cleaning.
 */
class WebPushChannel
{
    public function send(mixed $notifiable, Notification $notification): void
    {
        if (!method_exists($notification, 'toWebPush')) {
            return;
        }

        $subscriptions = $notifiable->pushSubscriptions ?? collect();
        if ($subscriptions->isEmpty()) {
            return;
        }

        $payload = $notification->toWebPush($notifiable);
        if (!$payload || empty($payload['title'])) {
            return;
        }

        $config = config('services.webpush');
        if (empty($config['public_key']) || empty($config['private_key'])) {
            Log::warning('WebPushChannel: VAPID keys not configured; skipping notification.');
            return;
        }

        try {
            $webPush = new WebPush([
                'VAPID' => [
                    'subject'    => $config['subject'],
                    'publicKey'  => $config['public_key'],
                    'privateKey' => $config['private_key'],
                ],
            ]);
        } catch (\Throwable $e) {
            Log::error('WebPushChannel: failed to init WebPush — ' . $e->getMessage());
            return;
        }

        foreach ($subscriptions as $row) {
            try {
                $sub = Subscription::create($row->toSubscriptionArray());
                $webPush->queueNotification($sub, json_encode($payload));
            } catch (\Throwable $e) {
                Log::warning('WebPushChannel: bad subscription row #' . $row->id . ' — ' . $e->getMessage());
            }
        }

        // Flush queue and prune dead subscriptions.
        foreach ($webPush->flush() as $report) {
            if (!$report->isSuccess()) {
                $statusCode = $report->getResponse()?->getStatusCode();
                if ($statusCode === 404 || $statusCode === 410) {
                    PushSubscriptionModel::where('endpoint', $report->getEndpoint())->delete();
                } else {
                    Log::info('WebPush delivery failed', [
                        'endpoint' => $report->getEndpoint(),
                        'reason'   => $report->getReason(),
                        'status'   => $statusCode,
                    ]);
                }
            }
        }

        // Touch last_used_at on still-valid subscriptions.
        PushSubscriptionModel::where('user_id', $notifiable->id ?? null)
            ->whereIn('endpoint', $subscriptions->pluck('endpoint'))
            ->update(['last_used_at' => now()]);
    }
}
