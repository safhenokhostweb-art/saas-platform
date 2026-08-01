<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 19 — Part 3/5: ordered stages within a Pipeline. `probability`
 * is the stage's default win-probability (0-100) — an Opportunity can
 * override it per-deal (see crm_opportunities). `type` distinguishes the
 * two closing stages (won/lost) from ordinary open stages so Forecast
 * logic (Part 5) doesn't need to hardcode stage names.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_stages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pipeline_id')->constrained('crm_pipelines')->cascadeOnDelete();
            $table->string('name', 150);
            $table->unsignedSmallInteger('position')->default(0);
            $table->unsignedTinyInteger('probability')->default(0); // 0-100
            $table->string('type', 10)->default('open'); // open|won|lost
            $table->timestamps();

            $table->index(['pipeline_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_stages');
    }
};
