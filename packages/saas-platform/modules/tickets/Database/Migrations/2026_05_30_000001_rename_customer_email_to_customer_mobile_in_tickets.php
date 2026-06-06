<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('tickets', 'customer_email')) {
            Schema::table('tickets', function (Blueprint $table) {
                $table->renameColumn('customer_email', 'customer_mobile');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('tickets', 'customer_mobile')) {
            Schema::table('tickets', function (Blueprint $table) {
                $table->renameColumn('customer_mobile', 'customer_email');
            });
        }
    }
};
