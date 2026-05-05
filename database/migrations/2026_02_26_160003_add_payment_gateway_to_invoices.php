<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasColumn('invoices', 'payment_gateway_id')) {
            Schema::table('invoices', function (Blueprint $table) {
                $table->foreignId('payment_gateway_id')->nullable()->after('gateway')->constrained('payment_gateways')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('invoices', 'payment_gateway_id')) {
            Schema::table('invoices', function (Blueprint $table) {
                $table->dropForeign(['payment_gateway_id']);
            });
        }
    }
};
