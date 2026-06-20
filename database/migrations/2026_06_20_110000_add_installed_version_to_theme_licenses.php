<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('theme_licenses', 'installed_theme_version')) {
            DB::statement("ALTER TABLE theme_licenses ADD COLUMN installed_theme_version SMALLINT UNSIGNED NULL AFTER is_permanent");
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('theme_licenses', 'installed_theme_version')) {
            DB::statement("ALTER TABLE theme_licenses DROP COLUMN installed_theme_version");
        }
    }
};
