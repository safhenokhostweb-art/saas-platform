<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE customer_installations MODIFY COLUMN status ENUM('pending','running','complete','failed','rolling_back','rolled_back','rollback_failed','deleted') NOT NULL DEFAULT 'pending'");
    }

    public function down(): void
    {
        // Note: existing 'deleted' rows must be removed first before reverting
        DB::statement("UPDATE customer_installations SET status = 'complete' WHERE status = 'deleted'");
        DB::statement("ALTER TABLE customer_installations MODIFY COLUMN status ENUM('pending','running','complete','failed','rolling_back','rolled_back','rollback_failed') NOT NULL DEFAULT 'pending'");
    }
};
