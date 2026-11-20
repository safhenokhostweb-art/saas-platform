<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 4: marks servers the platform owns and operates. Only those receive
 * the tenant-media object-storage key in a customer site's .env; a server
 * that belongs to the customer never does. Defaults to false (safe).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_remote_servers', function (Blueprint $table) {
            $table->boolean('is_platform_managed')->default(false)->after('type');
        });
    }

    public function down(): void
    {
        Schema::table('customer_remote_servers', function (Blueprint $table) {
            $table->dropColumn('is_platform_managed');
        });
    }
};
