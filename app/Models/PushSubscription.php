<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One row per (user, device) browser push subscription.
 *
 * The `endpoint` is a unique URL handed to us by the browser when the
 * user clicks "Allow". We POST encrypted messages to it via web-push
 * and the browser displays them.
 *
 * Endpoints can become invalid at any time (uninstall, permission revoke,
 * cleared cache). The WebPushChannel deletes rows that get a 404/410
 * response from the push provider, keeping the table self-cleaning.
 */
class PushSubscription extends Model
{
    protected $fillable = [
        'user_id', 'endpoint', 'public_key', 'auth_token',
        'content_encoding', 'user_agent', 'last_used_at',
    ];

    protected $casts = [
        'last_used_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Convert this row to the array shape minishlink/web-push expects.
     */
    public function toSubscriptionArray(): array
    {
        return [
            'endpoint'        => $this->endpoint,
            'publicKey'       => $this->public_key,
            'authToken'       => $this->auth_token,
            'contentEncoding' => $this->content_encoding,
        ];
    }
}
