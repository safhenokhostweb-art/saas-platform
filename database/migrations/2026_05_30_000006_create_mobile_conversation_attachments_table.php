<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mobile_conversation_attachments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('message_id');
            $table->uuid('conversation_id');
            $table->foreignId('uploaded_by')->constrained('users')->cascadeOnDelete();
            $table->string('file_name', 255);
            $table->string('file_path', 512);
            $table->string('mime_type', 128);
            $table->unsignedBigInteger('file_size');         // bytes
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index('message_id');
            $table->index('conversation_id');
            $table->index('uploaded_by');

            $table->foreign('message_id')->references('id')->on('mobile_conversation_messages')->cascadeOnDelete();
            $table->foreign('conversation_id')->references('id')->on('mobile_conversations')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mobile_conversation_attachments');
    }
};
