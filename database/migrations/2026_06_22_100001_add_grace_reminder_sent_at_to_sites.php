<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sites', function (Blueprint $table) {
            if (! Schema::hasColumn('sites', 'grace_reminder_sent_at')) {
                $table->timestamp('grace_reminder_sent_at')->nullable()->after('grace_period_ends_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('sites', function (Blueprint $table) {
            $table->dropColumnIfExists('grace_reminder_sent_at');
        });
    }
};
