<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasColumn('form_site', 'is_active')) {
            return;
        }
        Schema::table('form_site', function (Blueprint $table) {
            $table->boolean('is_active')->default(true)->after('site_id');
        });
    }

    public function down(): void
    {
        Schema::table('form_site', function (Blueprint $table) {
            $table->dropColumn('is_active');
        });
    }
};
