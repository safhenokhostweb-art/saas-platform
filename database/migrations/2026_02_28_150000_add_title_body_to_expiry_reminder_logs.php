<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('expiry_reminder_logs', function (Blueprint $table) {
            $table->string('title')->nullable()->after('type');
            $table->text('body')->nullable()->after('title');
        });
    }

    public function down(): void
    {
        Schema::table('expiry_reminder_logs', function (Blueprint $table) {
            $table->dropColumn(['title', 'body']);
        });
    }
};
