<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SUPPORT-SUITE-01 — Section E
 * Web Push notification subscriptions (VAPID-based)
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('push_subscriptions')) {
            return;
        }
        Schema::create('push_subscriptions', function (Blueprint $table) {
            $table->id();

            // who owns this subscription
            $table->unsignedBigInteger('user_id')->nullable();
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');

            // Web Push endpoint (browser-specific) — stored as TEXT, indexed via hash
            $table->text('endpoint');
            // SHA-256 hash of endpoint for unique indexing (TEXT can't have a full unique key in MySQL)
            $table->string('endpoint_hash', 64)->unique('uq_push_endpoint_hash');

            // VAPID keys returned by browser
            $table->text('public_key')->nullable();
            $table->text('auth_token')->nullable();

            // encoding: aesgcm or aes128gcm
            $table->string('encoding', 20)->default('aesgcm');

            // last ping timestamp
            $table->timestamp('last_used_at')->nullable();

            $table->timestamps();

            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('push_subscriptions');
    }
};
