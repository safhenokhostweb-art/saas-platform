<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('theme_profiles', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->boolean('is_default')->default(false);
            $table->boolean('is_system')->default(false);
            $table->boolean('is_active')->default(false);
            $table->string('preset_key')->nullable();

            // Color groups
            $table->json('colors');     // primary, secondary, accent, success, warning, danger, info
            $table->json('surfaces');   // background, surface, card, sidebar, header
            $table->json('typography'); // text_primary, text_secondary, text_muted, link, link_hover
            $table->json('borders');    // border, divider, strong
            $table->json('sidebar');    // bg, bg_bottom, text, muted, accent, border
            $table->json('header');     // bg, text, link_active

            $table->timestamps();
        });

        // Tenant theme assignments — one active theme per site (null = platform)
        Schema::create('tenant_theme_overrides', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained('sites')->cascadeOnDelete();
            $table->foreignId('theme_profile_id')->constrained('theme_profiles')->cascadeOnDelete();
            $table->timestamps();

            $table->unique('site_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_theme_overrides');
        Schema::dropIfExists('theme_profiles');
    }
};
