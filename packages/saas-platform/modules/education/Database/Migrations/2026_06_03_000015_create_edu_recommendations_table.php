<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('edu_recommendations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->index();
            $table->unsignedBigInteger('site_id')->nullable()->index();
            // article | walkthrough | learning_path | feature_intro | usage_tip
            $table->string('recommendation_type', 50)->index();
            $table->unsignedBigInteger('article_id')->nullable();
            $table->unsignedBigInteger('walkthrough_id')->nullable();
            $table->unsignedBigInteger('learning_path_id')->nullable();
            $table->unsignedSmallInteger('priority')->default(50);
            $table->string('reason', 500)->nullable();
            $table->timestamp('dismissed_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'site_id', 'dismissed_at', 'completed_at'], 'edu_rec_active_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('edu_recommendations');
    }
};
