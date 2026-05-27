<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticket_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained('tickets')->cascadeOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action_type', 60); // created, status_changed, assigned, unassigned, replied, note_added, priority_changed, watcher_added, watcher_removed, sla_breached, closed, reopened
            $table->string('old_value', 255)->nullable();
            $table->string('new_value', 255)->nullable();
            $table->json('metadata')->nullable(); // additional context (actor_name, reply_excerpt, etc.)
            $table->timestamp('created_at')->useCurrent();

            $table->index(['ticket_id', 'created_at']);
            $table->index('action_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_activities');
    }
};
