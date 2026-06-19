<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('theme_templates', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('type', 40);         // section|page|header|footer|mega_menu|popup|landing_page
            $table->string('category', 40);     // hero|features|pricing|testimonials|faq|contact|team|gallery|blog|product|checkout|corporate|personal|store|education
            $table->string('name');
            $table->text('description')->nullable();
            $table->json('tags')->nullable();
            $table->string('author', 100)->nullable();
            $table->string('version', 20)->default('1.0.0');
            $table->string('license_type', 20)->default('free'); // free|premium|commercial|mit
            $table->json('compatibility')->nullable();            // ['1.x', '2.x']
            $table->json('blocks');                              // the actual block data array
            $table->json('global_styles')->nullable();           // optional global style snapshot
            $table->json('demo_data')->nullable();               // placeholder resolution map
            $table->string('thumbnail_url', 500)->nullable();
            $table->string('preview_image_url', 500)->nullable();
            $table->boolean('is_public')->default(false);        // future marketplace
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('usage_count')->default(0);
            $table->unsignedBigInteger('site_id')->nullable();   // null = platform template
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->index(['type', 'is_active']);
            $table->index(['category', 'is_active']);
            $table->index(['site_id', 'is_active']);
            $table->index('is_public');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('theme_templates');
    }
};
