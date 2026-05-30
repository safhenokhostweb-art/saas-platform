<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mobile_conversation_participants', function (Blueprint $table) {
            $table->id();
            $table->uuid('conversation_id');
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('user_type', 32)->default('end_user');  // platform_admin|tenant_admin|support_agent|end_user|ai_assistant
            $table->string('role', 32)->default('participant');     // owner|participant|observer
            $table->boolean('is_muted')->default(false);
            $table->json('metadata')->nullable();
            $table->timestamp('joined_at')->useCurrent();
            $table->timestamp('left_at')->nullable();
            $table->timestamp('last_read_at')->nullable();
            $table->timestamps();

            $table->unique(['conversation_id', 'user_id']);
            $table->index(['conversation_id', 'role']);
            $table->index(['user_id', 'left_at']);

            $table->foreign('conversation_id')->references('id')->on('mobile_conversations')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mobile_conversation_participants');
    }
};
