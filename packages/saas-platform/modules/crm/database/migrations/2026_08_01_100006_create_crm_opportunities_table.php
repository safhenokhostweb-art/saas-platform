<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 19 — Part 3/5: the Deal entity. Depends on crm_contacts,
 * crm_companies, crm_pipelines, crm_stages — created before crm_leads
 * specifically so crm_leads.converted_opportunity_id can carry a real FK
 * (a Lead's conversion event points here; Opportunity never points back
 * to Lead, so this ordering has no circularity).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_opportunities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->nullable()->constrained('sites')->nullOnDelete();
            $table->string('name', 255);
            $table->foreignId('contact_id')->nullable()->constrained('crm_suite_contacts')->nullOnDelete();
            $table->foreignId('company_id')->nullable()->constrained('crm_companies')->nullOnDelete();
            $table->foreignId('pipeline_id')->constrained('crm_pipelines')->restrictOnDelete();
            $table->foreignId('stage_id')->constrained('crm_stages')->restrictOnDelete();
            $table->unsignedBigInteger('amount')->default(0); // expected revenue, smallest currency unit
            $table->unsignedTinyInteger('probability')->nullable(); // overrides the stage's default when set
            $table->date('expected_close_date')->nullable();
            $table->string('status', 10)->default('open'); // open|won|lost
            $table->string('lost_reason', 255)->nullable();
            $table->string('won_reason', 255)->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('owner_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_favorite')->default(false);
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['site_id', 'status']);
            $table->index(['site_id', 'archived_at']);
            $table->index(['pipeline_id', 'stage_id']);
            $table->index('expected_close_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_opportunities');
    }
};
