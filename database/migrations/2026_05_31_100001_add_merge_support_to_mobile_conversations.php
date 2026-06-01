<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mobile_conversations', function (Blueprint $table) {
            $table->uuid('merged_into_id')->nullable()->after('status');
            $table->index('merged_into_id');
        });

        Schema::create('mobile_conversation_merge_log', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->uuid('target_conversation_id');
            $table->json('source_conversation_ids');
            $table->string('merged_by');
            $table->string('merged_by_type', 32)->default('end_user'); // platform_admin|tenant_admin|end_user
            $table->integer('messages_moved')->default(0);
            $table->integer('participants_added')->default(0);
            $table->timestamps();

            $table->index(['tenant_id', 'target_conversation_id']);
            $table->index('merged_by');
        });

        Schema::create('mobile_conversation_merge_permissions', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->string('user_id');
            $table->string('granted_by');
            $table->timestamp('granted_at')->useCurrent();
            $table->timestamps();

            $table->unique(['tenant_id', 'user_id']);
            $table->index('tenant_id');
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mobile_conversation_merge_permissions');
        Schema::dropIfExists('mobile_conversation_merge_log');
        Schema::table('mobile_conversations', function (Blueprint $table) {
            $table->dropIndex(['merged_into_id']);
            $table->dropColumn('merged_into_id');
        });
    }
};
