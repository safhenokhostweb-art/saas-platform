<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('theme_packs', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('version', 20)->default('1.0.0');
            $table->string('author', 100)->nullable();
            $table->string('license_type', 20)->default('free');
            $table->json('tags')->nullable();
            $table->string('preview_image_url', 500)->nullable();
            // What this pack contains: global_styles, headers, footers, pages, templates, demo_data
            $table->json('includes')->nullable();
            // The full pack content as a nested JSON object
            // {global_styles:{...}, headers:[...], footers:[...], pages:[...], template_ids:[...], demo_data:{...}}
            $table->json('pack_data');
            $table->boolean('is_public')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('site_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->index(['is_active', 'is_public']);
            $table->index('site_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('theme_packs');
    }
};
