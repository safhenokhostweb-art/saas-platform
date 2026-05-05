<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('seo_keywords')) {
            Schema::create('seo_keywords', function (Blueprint $table) {
                $table->id();
                $table->foreignId('site_id')->constrained()->cascadeOnDelete();
                $table->string('keyword');
                $table->string('target_url')->nullable();
                $table->unsignedInteger('position')->nullable();
                $table->unsignedInteger('search_volume')->nullable();
                $table->date('tracked_at');
                $table->timestamps();
                $table->unique(['site_id', 'keyword', 'tracked_at']);
            });
        }

        if (! Schema::hasTable('seo_page_scores')) {
            Schema::create('seo_page_scores', function (Blueprint $table) {
                $table->id();
                $table->foreignId('site_id')->constrained()->cascadeOnDelete();
                $table->string('page_url');
                $table->string('page_type')->nullable(); // page, post, product
                $table->unsignedInteger('score')->default(0);
                $table->json('checks')->nullable(); // title_ok, meta_ok, h1_ok, etc.
                $table->timestamp('scored_at');
                $table->timestamps();
                $table->index(['site_id', 'page_url']);
            });
        }

        if (! Schema::hasTable('seo_redirects')) {
            Schema::create('seo_redirects', function (Blueprint $table) {
                $table->id();
                $table->foreignId('site_id')->constrained()->cascadeOnDelete();
                $table->string('from_url');
                $table->string('to_url');
                $table->unsignedSmallInteger('status_code')->default(301); // 301, 302, etc.
                $table->boolean('is_active')->default(true);
                $table->timestamps();
                $table->unique(['site_id', 'from_url']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('seo_redirects');
        Schema::dropIfExists('seo_page_scores');
        Schema::dropIfExists('seo_keywords');
    }
};
