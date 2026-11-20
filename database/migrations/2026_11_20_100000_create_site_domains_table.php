<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 3 (custom domains): connection state of each domain a site answers.
 * sites.domain stays the source of truth for the primary domain; a row
 * records whether its DNS reaches the server and whether SSL is active, plus
 * a pending domain being connected and previous domains kept as redirects.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_domains', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained('sites')->cascadeOnDelete();
            $table->string('domain', 253)->unique();
            // primary (= sites.domain) | pending (being connected) | alias (old domain, 301 to primary)
            $table->string('role', 16)->default('primary');
            // awaiting_dns | connected | issuing | active | failed | redirect
            $table->string('status', 32)->default('awaiting_dns');
            $table->boolean('www_in_certificate')->default(false);
            $table->json('dns_report')->nullable();
            $table->timestamp('dns_checked_at')->nullable();
            $table->date('ssl_expires_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamp('last_job_at')->nullable();
            $table->timestamps();

            $table->index(['site_id', 'role']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_domains');
    }
};
