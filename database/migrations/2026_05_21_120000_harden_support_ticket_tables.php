<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('support_tickets', function (Blueprint $table) {
            if (!Schema::hasColumn('support_tickets', 'deleted_at')) {
                $table->softDeletes()->after('updated_at');
            }

            $table->index(['site_id', 'status', 'created_at'], 'support_tickets_site_status_created_idx');
            $table->index(['site_id', 'updated_at'], 'support_tickets_site_updated_idx');
            $table->index(['assigned_to', 'status'], 'support_tickets_assignee_status_idx');
            $table->index('ticket_number', 'support_tickets_ticket_number_idx');
        });

        Schema::table('support_ticket_replies', function (Blueprint $table) {
            if (!Schema::hasColumn('support_ticket_replies', 'deleted_at')) {
                $table->softDeletes()->after('updated_at');
            }

            $table->index(['support_ticket_id', 'created_at'], 'support_replies_ticket_created_idx');
            $table->index(['author_type', 'created_at'], 'support_replies_author_created_idx');
        });
    }

    public function down(): void
    {
        Schema::table('support_ticket_replies', function (Blueprint $table) {
            $table->dropIndex('support_replies_ticket_created_idx');
            $table->dropIndex('support_replies_author_created_idx');
            if (Schema::hasColumn('support_ticket_replies', 'deleted_at')) {
                $table->dropSoftDeletes();
            }
        });

        Schema::table('support_tickets', function (Blueprint $table) {
            $table->dropIndex('support_tickets_site_status_created_idx');
            $table->dropIndex('support_tickets_site_updated_idx');
            $table->dropIndex('support_tickets_assignee_status_idx');
            $table->dropIndex('support_tickets_ticket_number_idx');
            if (Schema::hasColumn('support_tickets', 'deleted_at')) {
                $table->dropSoftDeletes();
            }
        });
    }
};
