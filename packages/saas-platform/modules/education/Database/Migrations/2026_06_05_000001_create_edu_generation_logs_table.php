<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('edu_generation_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('site_id')->nullable()->index();
            $table->string('module_slug', 120)->index();
            $table->string('content_type', 60);
            $table->string('provider', 40)->default('null');
            // success | validation_failed | provider_error
            $table->string('status', 30)->default('success')->index();
            $table->decimal('confidence_score', 5, 2)->nullable();
            $table->string('source_reason', 60)->default('content_deficit');
            $table->unsignedBigInteger('edu_article_id')->nullable()->index();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['site_id', 'status']);
            $table->index(['site_id', 'provider']);
            $table->index(['created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('edu_generation_logs');
    }
};
