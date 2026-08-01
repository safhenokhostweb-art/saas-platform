<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 19 — Part 3: polymorphic pivot attaching a crm_tag to any CRM
 * entity (Contact, Company, Lead, Opportunity). No `updated_at` — a
 * tag-attachment event doesn't change once created.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_taggables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tag_id')->constrained('crm_tags')->cascadeOnDelete();
            $table->string('taggable_type', 100);
            $table->unsignedBigInteger('taggable_id');
            $table->timestamp('created_at')->nullable();

            $table->unique(['tag_id', 'taggable_type', 'taggable_id']);
            $table->index(['taggable_type', 'taggable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_taggables');
    }
};
