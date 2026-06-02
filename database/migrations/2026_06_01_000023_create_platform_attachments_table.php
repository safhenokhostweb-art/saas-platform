<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_attachments', function (Blueprint $table) {
            $table->id();
            $table->uuid('message_id');
            $table->uuid('conversation_id');
            $table->string('original_name', 255);
            $table->string('stored_path', 500);  // saas-platform filesystem only
            $table->string('disk', 64)->default('local');
            $table->string('mime_type', 128)->nullable();
            $table->unsignedBigInteger('file_size')->default(0);
            $table->string('file_hash', 64)->nullable();
            $table->unsignedBigInteger('uploader_id');
            $table->string('uploader_type', 32); // admin|platform_staff|customer_admin
            $table->boolean('is_deleted')->default(false);
            $table->timestamps();

            $table->foreign('message_id')
                ->references('id')
                ->on('platform_messages')
                ->cascadeOnDelete();

            $table->foreign('conversation_id')
                ->references('id')
                ->on('platform_conversations')
                ->cascadeOnDelete();

            $table->index(['conversation_id', 'created_at']);

            $table->comment('Attachments for platform conversations only. No shared paths with tenant attachments.');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_attachments');
    }
};
