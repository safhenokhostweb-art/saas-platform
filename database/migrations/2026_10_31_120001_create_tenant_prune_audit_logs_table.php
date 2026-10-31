<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per prune run (dry-run or execute), written before anything is
 * deleted and finalized afterwards, so an interrupted run still leaves a trace.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_prune_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('site_id');
            $table->foreign('site_id')->references('id')->on('sites')->onDelete('cascade');

            $table->string('triggered_by', 20);          // auto_agent | admin_user
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('mode', 10);                   // dry_run | execute
            $table->string('profile', 20)->default('routine');
            $table->string('status', 20)->default('running'); // running | completed | partial | failed

            $table->unsignedBigInteger('reclaimed_bytes')->default(0);
            $table->unsignedInteger('pruned_items_count')->default(0);
            $table->json('details')->nullable();
            $table->string('error', 1000)->nullable();

            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['site_id', 'created_at']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_prune_audit_logs');
    }
};
