<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('edu_articles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('edu_category_id')->constrained('edu_categories')->cascadeOnDelete();
            $table->foreignId('site_id')->nullable()->constrained('sites')->nullOnDelete();
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('excerpt')->nullable();
            $table->longText('body');
            $table->string('status', 20)->default('draft'); // draft | published | archived
            $table->string('content_type', 20)->default('article'); // article | guide | tutorial | faq | reference
            $table->json('target_roles')->nullable();
            $table->string('module_slug', 60)->nullable();
            $table->string('route_pattern', 120)->nullable();
            $table->unsignedInteger('view_count')->default(0);
            $table->unsignedSmallInteger('helpful_count')->default(0);
            $table->unsignedSmallInteger('not_helpful_count')->default(0);
            $table->json('tags')->nullable();
            $table->json('meta')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->fullText(['title', 'excerpt']);
            $table->index(['edu_category_id', 'status']);
            $table->index(['site_id', 'status']);
            $table->index('module_slug');
            $table->index('route_pattern');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('edu_articles');
    }
};
