<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('forms', function (Blueprint $table) {
            if (!Schema::hasColumn('forms', 'intro_text')) {
                $table->text('intro_text')->nullable();
            }
            if (!Schema::hasColumn('forms', 'meta_title')) {
                $table->string('meta_title', 255)->nullable();
            }
            if (!Schema::hasColumn('forms', 'meta_description')) {
                $table->string('meta_description', 500)->nullable();
            }
            if (!Schema::hasColumn('forms', 'canonical_url')) {
                $table->string('canonical_url', 500)->nullable();
            }
            if (!Schema::hasColumn('forms', 'meta_robots')) {
                $table->string('meta_robots', 80)->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('forms', function (Blueprint $table) {
            $drop = [];
            foreach (['intro_text', 'meta_title', 'meta_description', 'canonical_url', 'meta_robots'] as $col) {
                if (Schema::hasColumn('forms', $col)) {
                    $drop[] = $col;
                }
            }
            if ($drop) {
                $table->dropColumn($drop);
            }
        });
    }
};
