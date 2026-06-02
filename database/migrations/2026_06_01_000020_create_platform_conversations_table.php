<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PHASE M3.2 — DOMAIN B: Platform Communication Runtime
 *
 * PARTICIPANTS: Main Admin, Platform Staff, Customer Admin ONLY.
 * NO end-user records allowed.
 * NO support conversations allowed.
 * NO tenant user records allowed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_conversations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type', 32)->default('direct'); // direct|group|announcement
            $table->string('status', 32)->default('active'); // active|archived|closed
            $table->string('subject', 255)->nullable();
            $table->string('priority', 16)->default('normal');
            $table->uuid('created_by');
            $table->string('created_by_type', 32)->default('admin'); // admin|platform_staff|customer_admin
            $table->json('metadata')->nullable();
            $table->timestamp('last_message_at')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['status', 'type']);
            $table->index('last_message_at');
            $table->index('created_by');

            // Comment documents the boundary guarantee in schema itself
            $table->comment('Platform-only messaging. No tenant user_id ever stored here.');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_conversations');
    }
};
