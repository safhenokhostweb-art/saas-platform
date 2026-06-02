<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SP-2.0 — Proper attachment tracking table for mobile upload pipeline.
 * The canonical `tickets` table stores attachments as JSON (paths only).
 * This table stores the full upload metadata for lifecycle management,
 * access control, and deletion.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticket_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained('tickets')->cascadeOnDelete();
            $table->unsignedBigInteger('reply_id')->nullable();
            $table->string('original_name');
            $table->string('stored_path');
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('size_bytes');
            $table->string('disk', 30)->default('private');
            $table->string('uploaded_by_type', 20)->default('staff'); // staff | customer
            $table->unsignedBigInteger('uploaded_by_user_id')->nullable();
            $table->timestamps();

            $table->index(['ticket_id', 'created_at']);
            $table->index('reply_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_attachments');
    }
};
