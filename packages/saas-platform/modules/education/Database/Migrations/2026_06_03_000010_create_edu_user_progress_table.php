<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('edu_user_progress', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            // site_id is REQUIRED — no cross-tenant progress leakage possible
            $table->foreignId('site_id')->constrained('sites')->cascadeOnDelete();
            $table->unsignedBigInteger('progressable_id');
            $table->string('progressable_type', 80);
            $table->string('status', 20)->default('not_started'); // not_started | in_progress | completed
            $table->unsignedTinyInteger('progress_percent')->default(0);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'site_id', 'progressable_id', 'progressable_type'], 'edu_progress_unique');
            $table->index(['user_id', 'site_id']);
            $table->index(['progressable_id', 'progressable_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('edu_user_progress');
    }
};
