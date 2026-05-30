<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('support_tickets', 'customer_email')) {
            Schema::table('support_tickets', function (Blueprint $table) {
                $table->renameColumn('customer_email', 'customer_mobile');
            });
        }

        if (Schema::hasColumn('support_ticket_replies', 'customer_email')) {
            Schema::table('support_ticket_replies', function (Blueprint $table) {
                $table->renameColumn('customer_email', 'customer_mobile');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('support_tickets', 'customer_mobile')) {
            Schema::table('support_tickets', function (Blueprint $table) {
                $table->renameColumn('customer_mobile', 'customer_email');
            });
        }

        if (Schema::hasColumn('support_ticket_replies', 'customer_mobile')) {
            Schema::table('support_ticket_replies', function (Blueprint $table) {
                $table->renameColumn('customer_mobile', 'customer_email');
            });
        }
    }
};
