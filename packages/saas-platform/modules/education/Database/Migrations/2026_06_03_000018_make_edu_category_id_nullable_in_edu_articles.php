<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Raw ALTER avoids doctrine/dbal quirks and handles FK constraints correctly.
        // edu_category_id is nullable for auto-generated articles that have no category yet.
        DB::statement('ALTER TABLE edu_articles MODIFY COLUMN edu_category_id BIGINT UNSIGNED NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE edu_articles MODIFY COLUMN edu_category_id BIGINT UNSIGNED NOT NULL');
    }
};
