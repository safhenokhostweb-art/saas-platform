<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('support_tickets', function (Blueprint $table) {
            $table->string('ticket_number', 32)->nullable()->unique()->after('id');
            $table->string('support_level', 32)->default('basic')->after('priority');
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete()->after('customer_name');
            $table->timestamp('first_response_at')->nullable()->after('closed_at');
            $table->timestamp('resolved_at')->nullable()->after('first_response_at');
        });
    }

    public function down(): void
    {
        Schema::table('support_tickets', function (Blueprint $table) {
            $table->dropForeign(['assigned_to']);
            $table->dropColumn([
                'ticket_number',
                'support_level',
                'assigned_to',
                'first_response_at',
                'resolved_at',
            ]);
        });
    }
};
