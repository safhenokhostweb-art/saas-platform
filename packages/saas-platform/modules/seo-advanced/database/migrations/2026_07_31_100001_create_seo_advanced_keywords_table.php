<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 18-D — Part 4: Keyword Tracking Foundation. Deliberately simple —
 * storage + manual position history only, no external rank-tracking
 * service integration. `position_history` is a json array of
 * {"date": "Y-m-d", "position": int} entries, appended to by
 * SeoAdvancedKeyword::recordPosition() every time an admin manually
 * enters a new position.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seo_advanced_keywords', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('site_id');
            $table->string('keyword', 255);
            $table->string('target_url', 500)->nullable();
            $table->string('status', 20)->default('active'); // active|paused|archived
            $table->unsignedSmallInteger('position')->nullable();
            $table->json('position_history')->nullable();
            $table->timestamps();

            $table->index(['site_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seo_advanced_keywords');
    }
};
