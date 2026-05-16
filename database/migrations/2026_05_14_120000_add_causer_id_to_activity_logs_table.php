<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('activity_logs')) {
            return;
        }
        if (Schema::hasColumn('activity_logs', 'causer_id')) {
            return;
        }

        Schema::table('activity_logs', function (Blueprint $table) {
            $table->foreignId('causer_id')->nullable()->after('user_id')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('activity_logs') || ! Schema::hasColumn('activity_logs', 'causer_id')) {
            return;
        }

        Schema::table('activity_logs', function (Blueprint $table) {
            $table->dropForeign(['causer_id']);
            $table->dropColumn('causer_id');
        });
    }
};
