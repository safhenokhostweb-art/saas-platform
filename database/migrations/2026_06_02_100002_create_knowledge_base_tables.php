<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SP-5.0 — Knowledge Base (feat_sp_005)
 * دانشنامه پشتیبانی: دسته‌بندی + مقالات + ردیابی نمایش
 */
return new class extends Migration
{
    public function up(): void
    {
        // دسته‌بندی‌های دانشنامه
        Schema::create('kb_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->nullable()->constrained('sites')->nullOnDelete();
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('icon', 50)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['site_id', 'is_active']);
        });

        // مقالات دانشنامه
        Schema::create('kb_articles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kb_category_id')->constrained('kb_categories')->cascadeOnDelete();
            $table->foreignId('site_id')->nullable()->constrained('sites')->nullOnDelete();
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('excerpt')->nullable();
            $table->longText('body');
            $table->string('status', 20)->default('draft'); // draft | published | archived
            $table->unsignedInteger('view_count')->default(0);
            $table->unsignedSmallInteger('helpful_count')->default(0);
            $table->unsignedSmallInteger('not_helpful_count')->default(0);
            $table->json('tags')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['kb_category_id', 'status']);
            $table->index(['site_id', 'status']);
            $table->fullText(['title', 'excerpt']); // جستجوی متن
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kb_articles');
        Schema::dropIfExists('kb_categories');
    }
};
