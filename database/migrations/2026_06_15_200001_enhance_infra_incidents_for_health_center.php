<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Migrate 'warning' → 'medium' before altering column
        DB::statement("UPDATE infra_incidents SET severity='medium' WHERE severity='warning'");

        Schema::table('infra_incidents', function (Blueprint $table) {
            // Lifecycle state (open / investigating / resolved / closed)
            $table->string('status', 20)->default('open')->after('site_id');
            // 4-level severity index support
            $table->index('status');
            // Assigned admin
            $table->unsignedBigInteger('assigned_to_id')->nullable()->after('status');
            $table->foreign('assigned_to_id')->references('id')->on('users')->nullOnDelete();
            // Timestamps for lifecycle stages
            $table->timestamp('investigating_at')->nullable()->after('resolved_at');
            $table->timestamp('closed_at')->nullable()->after('investigating_at');
            // Linked support ticket
            $table->unsignedBigInteger('support_ticket_id')->nullable()->after('closed_at');
            // Customer notification tracking
            $table->timestamp('customer_notified_at')->nullable()->after('support_ticket_id');
        });

        // Sync status column with existing resolved_at data
        DB::statement("UPDATE infra_incidents SET status='resolved' WHERE resolved_at IS NOT NULL");
    }

    public function down(): void
    {
        Schema::table('infra_incidents', function (Blueprint $table) {
            $table->dropForeign(['assigned_to_id']);
            $table->dropColumn([
                'status', 'assigned_to_id', 'investigating_at', 'closed_at',
                'support_ticket_id', 'customer_notified_at',
            ]);
        });
        DB::statement("UPDATE infra_incidents SET severity='warning' WHERE severity='medium'");
    }
};
