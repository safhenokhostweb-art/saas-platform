<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Makes edu_category_id nullable so AI-generated articles can exist without a category.
 *
 * Original implementation used DB::statement('ALTER TABLE ... MODIFY COLUMN ...') to
 * avoid a Laravel <10 / Doctrine DBAL bug with foreign-key columns. That bug no longer
 * exists in Laravel 10+ which implements Schema::change() natively — no Doctrine DBAL
 * required, and the native implementation is compatible with MySQL, MariaDB, and SQLite.
 *
 * SQLite path: Laravel rebuilds the table (CREATE → copy → DROP → RENAME) which
 * preserves all indexes and constraints while changing the column nullability.
 *
 * MySQL / MariaDB path: generates ALTER TABLE ... MODIFY COLUMN as before.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('edu_articles', function (Blueprint $table) {
            $table->unsignedBigInteger('edu_category_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('edu_articles', function (Blueprint $table) {
            $table->unsignedBigInteger('edu_category_id')->nullable(false)->change();
        });
    }
};
