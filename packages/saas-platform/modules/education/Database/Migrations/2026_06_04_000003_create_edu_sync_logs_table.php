<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('edu_sync_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('site_id')->index();
            $table->string('sync_type', 30)->default('full'); // full | differential | verify | archive
            $table->unsignedInteger('articles_synced')->default(0);
            $table->unsignedInteger('walkthroughs_synced')->default(0);
            $table->unsignedInteger('learning_paths_synced')->default(0);
            $table->unsignedInteger('articles_archived')->default(0);
            $table->unsignedInteger('articles_skipped')->default(0);
            $table->string('status', 20)->default('success'); // success | partial | failed
            $table->json('summary')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->timestamp('ran_at')->useCurrent();
            $table->timestamps();

            $table->index(['site_id', 'ran_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('edu_sync_logs');
    }
};
