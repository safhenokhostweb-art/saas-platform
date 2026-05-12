<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Wave 5.1 / P1-1 — Customer Module Entitlements Ledger.
 *
 * Append-only audit trail of every grant / revoke that affects a site's
 * access to a module. Independent from `site_modules.is_active`
 * (the runtime gate) so that history is never lost when an admin toggles
 * a checkbox.
 *
 * Safety:
 *  - Additive only (creates a new table, no schema changes elsewhere).
 *  - All foreign keys use restrictOnDelete or nullOnDelete; nothing here
 *    can cascade-delete a row in modules / sites / customers / users /
 *    invoices etc.
 *  - No data is back-filled here. Use `php artisan entitlements:backfill`
 *    explicitly (idempotent).
 */
return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('module_entitlements')) {
            return;
        }

        Schema::create('module_entitlements', function (Blueprint $table) {
            $table->id();

            $table->foreignId('site_id')
                ->constrained('sites')
                ->restrictOnDelete();

            $table->foreignId('customer_id')
                ->constrained('customers')
                ->restrictOnDelete();

            $table->foreignId('module_id')
                ->constrained('modules')
                ->restrictOnDelete();

            // Snapshot of the module slug at grant time. Survives rename.
            $table->string('module_slug', 191);

            // purchase | license | admin_grant | free_assign | seed | import | sync
            $table->string('source', 32);

            // active | revoked | suspended
            $table->string('status', 16)->default('active');

            $table->timestamp('granted_at');
            $table->foreignId('granted_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('revoked_at')->nullable();
            $table->foreignId('revoked_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->text('revoke_reason')->nullable();

            // Optional linkage to the originating commercial artefacts.
            $table->foreignId('invoice_id')
                ->nullable()
                ->constrained('invoices')
                ->nullOnDelete();
            $table->foreignId('invoice_item_id')
                ->nullable()
                ->constrained('invoice_items')
                ->nullOnDelete();
            $table->foreignId('module_purchase_id')
                ->nullable()
                ->constrained('site_module_purchases')
                ->nullOnDelete();
            $table->foreignId('module_license_id')
                ->nullable()
                ->constrained('module_licenses')
                ->nullOnDelete();

            $table->json('metadata')->nullable();

            $table->timestamps();

            $table->index(['site_id', 'module_id', 'status'], 'mod_ent_site_mod_status_idx');
            $table->index(['customer_id', 'status'], 'mod_ent_customer_status_idx');
            $table->index(['module_slug', 'status'], 'mod_ent_slug_status_idx');
            $table->index('granted_at', 'mod_ent_granted_at_idx');
            $table->index('revoked_at', 'mod_ent_revoked_at_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('module_entitlements');
    }
};
