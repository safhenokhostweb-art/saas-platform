<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 19 — Part 3: foundational entity, no dependencies on any other
 * CRM table. `lifecycle_stage` absorbs what would otherwise be a separate
 * "Customer" entity (a Company becomes a customer once it reaches that
 * stage — no separate table, see Part 2's design rationale).
 *
 * `site_id`/`owner_user_id` are real DB-level foreign keys to the host
 * app's `sites`/`users` tables (matching the Tickets module's own
 * migrations, e.g. create_tickets_table.php) — this is a schema-level
 * reference only, never a PHP `App\Models\*` import from this module's
 * own src/ (see config/crm.php + crm:validate-isolation).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_companies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->nullable()->constrained('sites')->nullOnDelete();
            $table->string('name', 255);
            $table->string('domain', 255)->nullable();
            $table->string('industry', 100)->nullable();
            $table->string('phone', 64)->nullable();
            $table->string('address', 500)->nullable();
            $table->string('lifecycle_stage', 20)->default('lead'); // lead|prospect|customer
            $table->foreignId('owner_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_favorite')->default(false);
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['site_id', 'lifecycle_stage']);
            $table->index(['site_id', 'archived_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_companies');
    }
};
