<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 18-E — Part 2: one row per site's Google Search Console
 * connection. access_token/refresh_token are stored via Eloquent's
 * built-in 'encrypted' cast (APP_KEY-based, same primitive as
 * RemoteAgent::secret_key elsewhere in this platform) — never plain
 * text at rest. One connection per site (unique site_id) — a site
 * either has a GSC account linked or it doesn't.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seo_advanced_search_console_connections', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('site_id')->unique();
            $table->string('google_account_email', 255)->nullable();
            $table->text('access_token')->nullable();
            $table->text('refresh_token')->nullable();
            $table->timestamp('token_expires_at')->nullable();
            $table->string('property_url', 500)->nullable();
            $table->timestamp('connected_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seo_advanced_search_console_connections');
    }
};
