<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('edu_feature_usage', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->index();
            $table->unsignedBigInteger('site_id')->nullable()->index();
            $table->string('module_slug', 100)->index();
            $table->string('feature_slug', 100)->index();
            $table->unsignedInteger('usage_count')->default(0);
            $table->timestamp('first_used_at')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'module_slug', 'feature_slug', 'site_id'], 'edu_usage_unique');
            $table->index(['module_slug', 'feature_slug', 'site_id'], 'edu_usage_feature_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('edu_feature_usage');
    }
};
