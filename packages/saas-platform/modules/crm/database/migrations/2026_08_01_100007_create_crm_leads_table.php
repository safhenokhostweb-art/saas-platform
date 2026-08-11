<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 19 — Part 2/3: a Lead is deliberately its own table, distinct
 * from Contact — it represents an unqualified inbound inquiry with
 * different fields (source, denormalized company_name since no real
 * Company exists yet) and a genuine "conversion" event, not just a
 * status flag. `converted_*_id` columns record what the lead became
 * once qualified (see CrmLead::convert() in Part 4).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_leads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->nullable()->constrained('sites')->nullOnDelete();
            $table->string('first_name', 100);
            $table->string('last_name', 100)->nullable();
            $table->string('email', 255)->nullable();
            $table->string('phone', 64)->nullable();
            $table->string('company_name', 255)->nullable();
            $table->string('source', 100)->nullable(); // e.g. website, referral, campaign
            $table->string('status', 20)->default('new'); // new|contacted|qualified|unqualified|converted
            $table->foreignId('owner_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('converted_contact_id')->nullable()->constrained('crm_suite_contacts')->nullOnDelete();
            $table->foreignId('converted_company_id')->nullable()->constrained('crm_companies')->nullOnDelete();
            $table->foreignId('converted_opportunity_id')->nullable()->constrained('crm_opportunities')->nullOnDelete();
            $table->timestamp('converted_at')->nullable();
            $table->boolean('is_favorite')->default(false);
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['site_id', 'status']);
            $table->index(['site_id', 'archived_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_leads');
    }
};
