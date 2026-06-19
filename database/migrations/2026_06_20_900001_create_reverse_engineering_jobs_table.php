<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reverse_engineering_jobs', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            // Input
            $table->string('input_type', 20);        // url | screenshot | html | prompt
            $table->string('input_source')->nullable(); // URL or filename
            $table->text('input_notes')->nullable();
            $table->string('suggested_name')->nullable();

            // Phase 1 — DOM Extraction
            $table->json('dom_structure')->nullable();

            // Phase 2 — Layout Reconstruction
            $table->json('layout_map')->nullable();

            // Phase 3 — Visual Analysis
            $table->json('visual_profile')->nullable();

            // Phase 4 — Commerce Analysis
            $table->json('commerce_profile')->nullable();

            // Phase 5 — Similarity Scores
            $table->json('similarity_scores')->nullable();

            // Phase 6 — Generated Theme Pack
            $table->unsignedBigInteger('theme_pack_id')->nullable();

            // Phase 7 — Validation
            $table->json('quality_report')->nullable();

            // Status lifecycle
            $table->string('status', 30)->default('pending');
            // pending | extracting | analyzing | scoring | reconstructing | validating | complete | failed

            $table->text('error_message')->nullable();
            $table->json('meta')->nullable();

            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index('input_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reverse_engineering_jobs');
    }
};
