<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('resellers', function (Blueprint $table) {
            $table->string('brand_name')->nullable()->after('is_white_label');
            $table->string('logo_path')->nullable()->after('brand_name');
            $table->string('custom_domain')->nullable()->after('logo_path');
            $table->string('subdomain')->nullable()->after('custom_domain');
        });
    }

    public function down(): void
    {
        Schema::table('resellers', function (Blueprint $table) {
            $table->dropColumn(['brand_name', 'logo_path', 'custom_domain', 'subdomain']);
        });
    }
};
