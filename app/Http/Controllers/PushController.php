<?php

namespace App\Http\Controllers;

use App\Models\PushSubscription;
use App\Notifications\TestPushNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Endpoints the PWA frontend uses to manage browser push subscriptions.
 *
 * Flow:
 *  1. Frontend GETs /push/public-key to learn our VAPID public key
 *  2. Frontend asks the browser for permission, then subscribes via the
 *     PushManager API. The browser hands back an endpoint + crypto keys.
 *  3. Frontend POSTs that subscription here to persist it.
 *  4. Server can now deliver notifications via WebPushChannel.
 *  5. On logout / disable, frontend DELETEs the subscription.
 */
class PushController extends Controller
{
    /**
     * The VAPID public key the browser needs to subscribe. Safe to expose.
     */
    public function publicKey(): JsonResponse
    {
        return response()->json([
            'public_key' => config('services.webpush.public_key'),
        ]);
    }

    /**
     * Persist a browser push subscription against the current user.
     */
    public function subscribe(Request $request): JsonResponse
    {
        $data = $request->validate([
            'endpoint'         => ['required', 'string', 'max:500'],
            'public_key'       => ['required', 'string', 'max:255'],
            'auth_token'       => ['required', 'string', 'max:255'],
            'content_encoding' => ['nullable', 'string', 'max:20'],
        ]);

        $sub = PushSubscription::updateOrCreate(
            ['endpoint' => $data['endpoint']],
            [
                'user_id'          => $request->user()->id,
                'public_key'       => $data['public_key'],
                'auth_token'       => $data['auth_token'],
                'content_encoding' => $data['content_encoding'] ?? 'aesgcm',
                'user_agent'       => substr((string) $request->userAgent(), 0, 500),
                'last_used_at'     => now(),
            ]
        );

        return response()->json(['ok' => true, 'id' => $sub->id]);
    }

    /**
     * Remove a subscription (user clicked "disable notifications").
     */
    public function unsubscribe(Request $request): JsonResponse
    {
        $request->validate([
            'endpoint' => ['required', 'string', 'max:500'],
        ]);

        PushSubscription::where('user_id', $request->user()->id)
            ->where('endpoint', $request->input('endpoint'))
            ->delete();

        return response()->json(['ok' => true]);
    }

    /**
     * Send a test push to the current user — useful for the "Send test"
     * button so users can confirm notifications actually pop up.
     */
    public function sendTest(Request $request): JsonResponse
    {
        $user = $request->user();
        $count = $user->pushSubscriptions()->count();

        if ($count === 0) {
            return response()->json([
                'ok'    => false,
                'error' => 'No active push subscriptions for this account.',
            ], 422);
        }

        $user->notify(new TestPushNotification());

        return response()->json([
            'ok'           => true,
            'sent_to_devices' => $count,
        ]);
    }
}
