<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase X — Custom/Bespoke Motion commercialization.
 *
 * Host-owned commerce + per-site assignment layer for CUSTOM (not generic)
 * Motion assets. Deliberately does not touch the standalone module's own
 * tables (motion_graphics, motion_graphic_versions) — a bespoke asset is
 * still just a MotionGraphic row with ownership_type=customer_specific in
 * the module (already existing, module-owned, unmodified). Every reference
 * to it here is an opaque asset_id string (the module's MotionGraphic.uuid),
 * exactly like Phase 3's motion_* tables — no FK into the module's schema.
 *
 * Also deliberately does not reuse motion_site_asset_packages /
 * motion_site_asset_package_items (Phase 3) — those model TEMPLATE-driven
 * bundles of generic assets (a required TemplateAssetPackage FK), which is
 * the wrong shape for "one bespoke asset, sold and assigned directly to one
 * site, unrelated to any template". These new tables are the same proven
 * pattern (current-pointer table + append-only activation history, see
 * motion_asset_activations) applied to that different, direct case.
 *
 * Safety: additive only, four new tables, nothing existing changes.
 * Foreign keys to existing host tables (sites, products, invoices,
 * invoice_items, users) use restrictOnDelete/nullOnDelete — nothing here
 * can cascade-delete platform data. Foreign keys between the new tables use
 * cascadeOnDelete, matching Phase 3's existing convention for child rows
 * that have no meaning without their parent.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('motion_bespoke_commissions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('site_id')->constrained('sites')->restrictOnDelete();
            // The module's MotionGraphic.uuid, once a designer has actually
            // created it. Nullable: a commission can exist in 'draft' business
            // status before any module-side asset row exists yet.
            $table->string('asset_id', 191)->nullable();
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->string('title', 191);
            // draft|designing|review|approved|published|archived — business
            // workflow status, distinct from the module's own per-version
            // content-readiness status (draft/review/approved/published/retired).
            $table->string('status', 20)->default('draft');
            $table->string('created_by_actor_identifier', 191)->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->index('site_id', 'motion_bespoke_commissions_site_idx');
            $table->index('asset_id', 'motion_bespoke_commissions_asset_idx');
            $table->index('status', 'motion_bespoke_commissions_status_idx');
        });

        Schema::create('motion_bespoke_assignments', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            // One current-pointer row per commission; history lives in
            // motion_bespoke_assignment_events, never overwritten in place.
            $table->foreignId('commission_id')->unique()
                ->constrained('motion_bespoke_commissions')->cascadeOnDelete();
            $table->foreignId('site_id')->constrained('sites')->restrictOnDelete();
            $table->string('asset_id', 191);
            $table->unsignedInteger('active_version');
            $table->string('status', 16)->default('active'); // active|revoked
            $table->string('assigned_by_actor_identifier', 191)->nullable();
            $table->timestamp('assigned_at');
            $table->timestamps();

            $table->index('site_id', 'motion_bespoke_assignments_site_idx');
        });

        Schema::create('motion_bespoke_assignment_events', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('assignment_id')
                ->constrained('motion_bespoke_assignments')->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->unsignedInteger('previous_version')->nullable();
            $table->string('event_type', 16); // install|upgrade|rollback|revoke
            $table->string('actor_identifier', 191)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('assignment_id', 'motion_bespoke_assignment_events_assignment_idx');
        });

        Schema::create('motion_bespoke_purchases', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('commission_id')
                ->constrained('motion_bespoke_commissions')->restrictOnDelete();
            $table->foreignId('site_id')->constrained('sites')->restrictOnDelete();
            $table->foreignId('invoice_id')->nullable()->constrained('invoices')->nullOnDelete();
            $table->foreignId('invoice_item_id')->nullable()->constrained('invoice_items')->nullOnDelete();
            $table->unsignedBigInteger('amount_irr')->default(0);
            $table->timestamp('purchased_at');
            $table->foreignId('granted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['commission_id', 'site_id'], 'motion_bespoke_purchases_commission_site_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('motion_bespoke_purchases');
        Schema::dropIfExists('motion_bespoke_assignment_events');
        Schema::dropIfExists('motion_bespoke_assignments');
        Schema::dropIfExists('motion_bespoke_commissions');
    }
};
