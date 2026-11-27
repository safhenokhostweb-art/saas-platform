<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Add is_free_public to themes table
        if (Schema::hasTable('themes') && !Schema::hasColumn('themes', 'is_free_public')) {
            Schema::table('themes', function (Blueprint $table) {
                $table->boolean('is_free_public')->default(false)->after('is_marketplace');
            });
        }

        // 2. Add blocks to canvas_header_presets
        if (Schema::hasTable('canvas_header_presets') && !Schema::hasColumn('canvas_header_presets', 'blocks')) {
            Schema::table('canvas_header_presets', function (Blueprint $table) {
                $table->longText('blocks')->nullable()->after('preview_image');
            });
        }

        // 3. Create canvas_footer_presets table
        if (!Schema::hasTable('canvas_footer_presets')) {
            Schema::create('canvas_footer_presets', function (Blueprint $table) {
                $table->id();
                $table->string('slug', 120)->unique();
                $table->string('title', 191);
                $table->text('description')->nullable();
                $table->string('category_label', 120)->default('فوتر');
                $table->string('preview_image', 255)->nullable();
                $table->longText('blocks')->nullable();
                $table->string('access_type', 20)->default('free'); // 'free' | 'paid'
                $table->unsignedBigInteger('price')->default(0); // تومان
                $table->unsignedBigInteger('discount_price')->nullable();
                $table->string('current_version', 32)->default('1.0.0');
                $table->boolean('is_active')->default(true);
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();
            });
        }

        // 4. Create canvas_section_presets table
        if (!Schema::hasTable('canvas_section_presets')) {
            Schema::create('canvas_section_presets', function (Blueprint $table) {
                $table->id();
                $table->string('slug', 120)->unique();
                $table->string('title', 191);
                $table->text('description')->nullable();
                $table->string('category_label', 120)->default('سکشن');
                $table->string('preview_image', 255)->nullable();
                $table->longText('blocks')->nullable();
                $table->string('access_type', 20)->default('free'); // 'free' | 'paid'
                $table->unsignedBigInteger('price')->default(0); // تومان
                $table->unsignedBigInteger('discount_price')->nullable();
                $table->string('current_version', 32)->default('1.0.0');
                $table->boolean('is_active')->default(true);
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('canvas_section_presets');
        Schema::dropIfExists('canvas_footer_presets');

        if (Schema::hasTable('canvas_header_presets') && Schema::hasColumn('canvas_header_presets', 'blocks')) {
            Schema::table('canvas_header_presets', function (Blueprint $table) {
                $table->dropColumn('blocks');
            });
        }

        if (Schema::hasTable('themes') && Schema::hasColumn('themes', 'is_free_public')) {
            Schema::table('themes', function (Blueprint $table) {
                $table->dropColumn('is_free_public');
            });
        }
    }
};
