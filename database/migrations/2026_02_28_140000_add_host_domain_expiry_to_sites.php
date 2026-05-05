<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('sites', function (Blueprint $table) {
            $table->date('host_expires_at')->nullable()->after('support_expires_at');
            $table->date('domain_expires_at')->nullable()->after('host_expires_at');
        });
    }

    public function down(): void
    {
        Schema::table('sites', function (Blueprint $table) {
            $table->dropColumn(['host_expires_at', 'domain_expires_at']);
        });
    }
};
