<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('theme_marketplace', 'demo_data_config')) {
            DB::statement("ALTER TABLE theme_marketplace ADD COLUMN demo_data_config JSON NULL AFTER supported_modules_json");
        }
        if (!Schema::hasColumn('theme_marketplace', 'has_demo_data')) {
            DB::statement("ALTER TABLE theme_marketplace ADD COLUMN has_demo_data TINYINT(1) NOT NULL DEFAULT 0 AFTER demo_data_config");
        }
    }

    public function down(): void
    {
        foreach (['has_demo_data', 'demo_data_config'] as $col) {
            if (Schema::hasColumn('theme_marketplace', $col)) {
                DB::statement("ALTER TABLE theme_marketplace DROP COLUMN {$col}");
            }
        }
    }
};
