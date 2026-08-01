<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 19 — Part 3: a real, first-class Tag entity — neither Tickets
 * nor Forms has this precedent (both just use a JSON/text column); CRM
 * needs genuine many-to-many, filterable tags across every entity, hence
 * this table + crm_taggables (see the next migration).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_tags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->nullable()->constrained('sites')->nullOnDelete();
            $table->string('name', 100);
            $table->string('color', 20)->default('#6b7280');
            $table->timestamps();

            $table->unique(['site_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_tags');
    }
};
