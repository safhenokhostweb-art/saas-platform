<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_generation_jobs', function (Blueprint $table) {
            $table->id();
            $table->string('uuid', 36)->unique();
            $table->string('input_type', 20);         // url | screenshot | html | prompt
            $table->string('input_source')->nullable(); // URL or original filename
            $table->text('input_notes')->nullable();
            $table->string('suggested_name')->nullable();
            $table->json('page_signals')->nullable();   // extracted page data
            $table->json('detected_sections')->nullable(); // [{key, label, confidence, enabled}]
            $table->json('selected_sections')->nullable(); // final user selection
            $table->unsignedBigInteger('theme_pack_id')->nullable();
            $table->string('status', 20)->default('pending');
            // pending | detecting | awaiting_selection | generating | complete | failed
            $table->text('error_message')->nullable();
            $table->json('meta')->nullable();           // provider, tokens, timing
            $table->timestamps();

            $table->index('status');
            $table->index('input_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_generation_jobs');
    }
};
