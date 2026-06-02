<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ConversationMessage model uses sender_type + message_type,
 * but the original migration only created a `type` column.
 * This migration adds the missing columns.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mobile_conversation_messages', function (Blueprint $table) {
            // sender_type: agent | end_user | bot | system
            if (! Schema::hasColumn('mobile_conversation_messages', 'sender_type')) {
                $table->string('sender_type', 32)->default('agent')->after('sender_id');
            }

            // message_type: same purpose as the existing `type` column
            if (! Schema::hasColumn('mobile_conversation_messages', 'message_type')) {
                $table->string('message_type', 32)->default('text')->after('sender_type');
            }

            // Extra columns the model fillable references
            if (! Schema::hasColumn('mobile_conversation_messages', 'deleted_at')) {
                $table->timestamp('deleted_at')->nullable()->after('is_deleted');
            }
            if (! Schema::hasColumn('mobile_conversation_messages', 'delivered_at')) {
                $table->timestamp('delivered_at')->nullable()->after('deleted_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('mobile_conversation_messages', function (Blueprint $table) {
            $table->dropColumn(array_filter([
                Schema::hasColumn('mobile_conversation_messages', 'sender_type')  ? 'sender_type'  : null,
                Schema::hasColumn('mobile_conversation_messages', 'message_type') ? 'message_type' : null,
                Schema::hasColumn('mobile_conversation_messages', 'deleted_at')   ? 'deleted_at'   : null,
                Schema::hasColumn('mobile_conversation_messages', 'delivered_at') ? 'delivered_at' : null,
            ]));
        });
    }
};
