<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('edu_walkthrough_progress', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            // site_id is REQUIRED — no cross-tenant walkthrough state leakage possible
            $table->foreignId('site_id')->constrained('sites')->cascadeOnDelete();
            $table->foreignId('edu_walkthrough_id')->constrained('edu_walkthroughs')->cascadeOnDelete();
            $table->unsignedTinyInteger('current_step')->default(0);
            $table->string('status', 20)->default('not_started'); // not_started | in_progress | completed | dismissed
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'site_id', 'edu_walkthrough_id'], 'edu_wt_progress_unique');
            $table->index(['user_id', 'site_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('edu_walkthrough_progress');
    }
};
