<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mobile_conversations', function (Blueprint $table) {
            if (!Schema::hasColumn('mobile_conversations', 'merged_into_id')) {
                $table->uuid('merged_into_id')->nullable()->after('status');
                $table->index('merged_into_id');
            }
        });

        if (!Schema::hasTable('mobile_conversation_merge_log')) Schema::create('mobile_conversation_merge_log', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->uuid('target_conversation_id');
            $table->json('source_conversation_ids');
            $table->string('merged_by');
            $table->string('merged_by_type', 32)->default('end_user'); // platform_admin|tenant_admin|end_user
            $table->integer('messages_moved')->default(0);
            $table->integer('participants_added')->default(0);
            $table->timestamps();

            $table->index(['tenant_id', 'target_conversation_id'], 'merge_log_tenant_target_idx');
            $table->index('merged_by', 'merge_log_merged_by_idx');
        });

        if (!Schema::hasTable('mobile_conversation_merge_permissions')) Schema::create('mobile_conversation_merge_permissions', function (Blueprint $table) {
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
            $table->dropIndex('mobile_conversations_merged_into_id_index');
            $table->dropColumn('merged_into_id');
        });
    }
};
