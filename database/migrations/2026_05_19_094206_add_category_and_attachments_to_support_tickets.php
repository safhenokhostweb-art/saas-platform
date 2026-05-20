<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('support_tickets', function (Blueprint $table) {
            if (!Schema::hasColumn('support_tickets', 'category')) {
                $table->string('category')->nullable()->after('priority');
            }
            if (!Schema::hasColumn('support_tickets', 'tags')) {
                $table->text('tags')->nullable()->after('category');
            }
            if (!Schema::hasColumn('support_tickets', 'attachments')) {
                $table->text('attachments')->nullable()->after('message');
            }
        });

        Schema::table('support_ticket_replies', function (Blueprint $table) {
            if (!Schema::hasColumn('support_ticket_replies', 'attachments')) {
                $table->text('attachments')->nullable()->after('message');
            }
        });
    }

    public function down(): void
    {
        Schema::table('support_tickets', function (Blueprint $table) {
            $drops = ['category', 'tags', 'attachments'];
            foreach ($drops as $col) {
                if (Schema::hasColumn('support_tickets', $col)) {
                    $table->dropColumn($col);
                }
            }
        });

        Schema::table('support_ticket_replies', function (Blueprint $table) {
            if (Schema::hasColumn('support_ticket_replies', 'attachments')) {
                $table->dropColumn('attachments');
            }
        });
    }
};
