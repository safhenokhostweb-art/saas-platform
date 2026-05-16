<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tables = DB::select('SHOW TABLES');
        $databaseName = DB::getDatabaseName();
        $key = "Tables_in_{$databaseName}";

        $skipTables = [
            'migrations', 'password_reset_tokens', 'failed_jobs',
            'personal_access_tokens', 'sessions', 'cache',
            'cache_locks', 'jobs', 'job_batches',
        ];

        foreach ($tables as $table) {
            $tableName = $table->$key;
            if (in_array($tableName, $skipTables, true)) {
                continue;
            }
            // فقط جداولی که هنوز deleted_at ندارند
            if (!Schema::hasColumn($tableName, 'deleted_at')) {
                // بررسی وجود ستون 'id' برای تعیین موقعیت
                if (Schema::hasColumn($tableName, 'id')) {
                    Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                        $table->timestamp('deleted_at')->nullable()->after(
                            Schema::hasColumn($tableName, 'updated_at') ? 'updated_at' : 'id'
                        );
                    });
                } else {
                    // جداول بدون id (pivot tables): ستون را بدون after اضافه کن
                    Schema::table($tableName, function (Blueprint $table) {
                        $table->timestamp('deleted_at')->nullable();
                    });
                }
                echo "✅ Added 'deleted_at' to [{$tableName}]" . PHP_EOL;
            } else {
                echo "⏭️ [{$tableName}] already has 'deleted_at'" . PHP_EOL;
            }
        }
    }

    public function down(): void
    {
        $tables = DB::select('SHOW TABLES');
        $databaseName = DB::getDatabaseName();
        $key = "Tables_in_{$databaseName}";

        foreach ($tables as $table) {
            $tableName = $table->$key;
            if (Schema::hasColumn($tableName, 'deleted_at')) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->dropColumn('deleted_at');
                });
                echo "❌ Dropped 'deleted_at' from [{$tableName}]" . PHP_EOL;
            }
        }
    }
};
