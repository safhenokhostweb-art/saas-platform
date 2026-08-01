<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 19 — Part 6: the Timeline entity — polymorphic against
 * Contact/Company/Lead/Opportunity via subject_type/subject_id. `type`
 * covers call/meeting/email/sms/note/system; Note is deliberately NOT a
 * separate table — a note is simply an Activity with type=note (see
 * Part 2's design rationale). Append-only, matching TicketActivity's
 * precedent (packages/saas-platform/modules/tickets/src/Models/
 * TicketActivity.php) — no `updated_at`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->nullable()->constrained('sites')->nullOnDelete();
            $table->string('subject_type', 100);
            $table->unsignedBigInteger('subject_id');
            $table->string('type', 20); // call|meeting|email|sms|note|system
            $table->string('subject', 255)->nullable();
            $table->text('body')->nullable();
            $table->timestamp('occurred_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['subject_type', 'subject_id']);
            $table->index(['site_id', 'type']);
            $table->index('occurred_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_activities');
    }
};
