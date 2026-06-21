<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reverse_engineering_jobs', function (Blueprint $table) {
            if (!Schema::hasColumn('reverse_engineering_jobs', 'extraction_mode')) {
                $table->string('extraction_mode', 20)->default('http')->after('status');
            }
            if (!Schema::hasColumn('reverse_engineering_jobs', 'force_headless')) {
                $table->boolean('force_headless')->default(false)->after('extraction_mode');
            }
            if (!Schema::hasColumn('reverse_engineering_jobs', 'headless_data')) {
                $table->json('headless_data')->nullable()->after('dom_structure');
            }
        });
    }

    public function down(): void
    {
        Schema::table('reverse_engineering_jobs', function (Blueprint $table) {
            $table->dropColumn(['extraction_mode', 'force_headless', 'headless_data']);
        });
    }
};
