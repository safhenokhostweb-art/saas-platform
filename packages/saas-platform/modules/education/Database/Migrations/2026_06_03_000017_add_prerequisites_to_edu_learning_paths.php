<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('edu_learning_paths', function (Blueprint $table) {
            // Array of edu_learning_path IDs that must be completed first
            $table->json('prerequisite_path_ids')->nullable()->after('module_slug');
            // usage | role | manual
            $table->string('path_type', 30)->default('manual')->after('prerequisite_path_ids')->index();
        });
    }

    public function down(): void
    {
        Schema::table('edu_learning_paths', function (Blueprint $table) {
            $table->dropColumn(['prerequisite_path_ids', 'path_type']);
        });
    }
};
