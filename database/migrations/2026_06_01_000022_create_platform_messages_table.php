<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_messages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('conversation_id');
            $table->unsignedBigInteger('sender_id');
            // admin|platform_staff|customer_admin — NO end_user sender allowed
            $table->string('sender_type', 32);
            $table->string('message_type', 32)->default('text'); // text|file|system|announcement
            $table->text('content');
            $table->uuid('reply_to_message_id')->nullable();
            $table->boolean('is_deleted')->default(false);
            $table->json('metadata')->nullable();
            $table->timestamp('edited_at')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->foreign('conversation_id')
                ->references('id')
                ->on('platform_conversations')
                ->cascadeOnDelete();

            $table->index(['conversation_id', 'created_at']);
            $table->index(['sender_id', 'sender_type']);

            $table->comment('Platform-only messages. No tenant user content ever stored here.');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_messages');
    }
};
