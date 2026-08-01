<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 19 — Part 6: polymorphic file attachments — built last since it
 * can attach to any other CRM entity (Contact, Company, Opportunity,
 * Activity, Task). Stores metadata only; actual bytes live on the
 * configured disk (see config/crm.php: attachments.disk).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->nullable()->constrained('sites')->nullOnDelete();
            $table->string('attachable_type', 100);
            $table->unsignedBigInteger('attachable_id');
            $table->string('disk', 50);
            $table->string('path', 500);
            $table->string('original_name', 255);
            $table->string('mime_type', 150)->nullable();
            $table->unsignedBigInteger('size_bytes')->default(0);
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->nullable();

            $table->index(['attachable_type', 'attachable_id']);
            $table->index('site_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_attachments');
    }
};
