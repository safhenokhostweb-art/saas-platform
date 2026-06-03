<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('edu_media', function (Blueprint $table) {
            $table->id();
            $table->foreignId('edu_article_id')->nullable()->constrained('edu_articles')->nullOnDelete();
            $table->unsignedBigInteger('edu_walkthrough_step_id')->nullable();
            $table->string('type', 20)->default('image'); // image | video | animation | screenshot
            $table->string('video_provider', 20)->nullable(); // local | aparat | youtube
            $table->string('video_path', 500)->nullable();
            $table->string('thumbnail', 500)->nullable();
            $table->unsignedSmallInteger('duration')->nullable(); // seconds
            $table->string('alt_text', 255)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('auto_generated')->default(false); // Playwright readiness flag
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index('edu_article_id');
            $table->index('edu_walkthrough_step_id');
        });

        // Add FK for walkthrough_steps after edu_media exists
        Schema::table('edu_walkthrough_steps', function (Blueprint $table) {
            $table->foreign('media_id')->references('id')->on('edu_media')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('edu_walkthrough_steps', function (Blueprint $table) {
            $table->dropForeign(['media_id']);
        });
        Schema::dropIfExists('edu_media');
    }
};
