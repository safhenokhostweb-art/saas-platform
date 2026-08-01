<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 19 — Part 3: depends on crm_companies (nullable — a Contact need
 * not belong to a Company). `lifecycle_stage` again absorbs the
 * "Customer" concept — see crm_companies migration's docblock.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->nullable()->constrained('sites')->nullOnDelete();
            $table->foreignId('company_id')->nullable()->constrained('crm_companies')->nullOnDelete();
            $table->string('first_name', 100);
            $table->string('last_name', 100)->nullable();
            $table->string('email', 255)->nullable();
            $table->string('phone', 64)->nullable();
            $table->string('job_title', 150)->nullable();
            $table->string('lifecycle_stage', 20)->default('lead'); // lead|prospect|customer
            $table->foreignId('owner_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_favorite')->default(false);
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['site_id', 'lifecycle_stage']);
            $table->index(['site_id', 'archived_at']);
            $table->index('company_id');
            $table->index('email');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_contacts');
    }
};
