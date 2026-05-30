<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mobile_conversation_messages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('conversation_id');
            $table->foreignId('sender_id')->constrained('users')->cascadeOnDelete();
            $table->string('type', 32)->default('text');  // text|image|file|voice|system|ai_generated
            $table->text('content')->nullable();
            $table->json('metadata')->nullable();
            $table->json('read_by')->nullable();
            $table->boolean('is_deleted')->default(false);
            $table->uuid('reply_to_id')->nullable();
            $table->timestamps();

            $table->index(['conversation_id', 'created_at']);
            $table->index(['conversation_id', 'is_deleted']);
            $table->index('sender_id');
            $table->index('reply_to_id');

            $table->foreign('conversation_id')->references('id')->on('mobile_conversations')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mobile_conversation_messages');
    }
};
