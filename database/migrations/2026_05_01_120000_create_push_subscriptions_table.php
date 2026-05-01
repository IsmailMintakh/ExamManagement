<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Stores Web Push subscriptions — one row per (user, device).
 *
 * The browser hands us a `PushSubscription` object on subscribe; we
 * persist its three fields (endpoint + p256dh public key + auth secret)
 * which together let the server send the user encrypted push messages
 * via FCM (Chrome/Edge), Mozilla Push (Firefox), or APNS (Safari).
 *
 * Subscriptions can become invalid (user uninstalled the app, denied
 * permission, cleared browser data). We delete them on 410 Gone responses
 * from the push provider — see WebPushChannel.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('push_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('endpoint', 500);           // unique URL provided by push service
            $table->string('public_key');              // p256dh — used to encrypt payloads
            $table->string('auth_token');              // auth secret
            $table->string('content_encoding', 20)->default('aesgcm');
            $table->string('user_agent', 500)->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();

            // SHA-1 of endpoint keeps the unique index narrow + endpoint-safe.
            $table->index(['user_id']);
            $table->unique(['endpoint']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('push_subscriptions');
    }
};
