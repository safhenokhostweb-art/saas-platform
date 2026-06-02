<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('platform_conversation_participants');
        Schema::create('platform_conversation_participants', function (Blueprint $table) {
            $table->id();
            $table->uuid('conversation_id');
            $table->unsignedBigInteger('participant_id');
            // admin|platform_staff|customer_admin — NO end_user type allowed
            $table->string('participant_type', 32);
            $table->string('role', 32)->default('participant'); // owner|participant|observer
            $table->string('display_name', 128)->nullable();
            $table->timestamp('joined_at')->nullable();
            $table->timestamp('left_at')->nullable();
            $table->timestamp('last_read_at')->nullable();
            $table->unsignedInteger('unread_count')->default(0);
            $table->timestamps();

            $table->foreign('conversation_id')
                ->references('id')
                ->on('platform_conversations')
                ->cascadeOnDelete();

            $table->unique(
                ['conversation_id', 'participant_id', 'participant_type'],
                'uniq_platform_participant'
            );
            $table->index(['participant_id', 'participant_type'], 'idx_pcp_participant');

            $table->comment('Only admin|platform_staff|customer_admin participant types allowed.');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_conversation_participants');
    }
};
