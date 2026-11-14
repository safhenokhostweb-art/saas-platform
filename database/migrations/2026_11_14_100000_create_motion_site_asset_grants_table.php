<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ADR-086: per-site, per-asset entitlement for PRIVATE motions.
 *
 * One row = "this site may receive this private motion", created only by a
 * Platform Admin action (or a recorded purchase) in the central panel. The
 * runtime manifest delivers a private motion to a site only while such a row
 * is `active`; the `motion-graphics-premium` module licence alone never
 * unlocks one.
 *
 * Like the bespoke tables, `asset_id` is the module's opaque
 * MotionGraphic.uuid (no FK into the module schema). Exactly one row per
 * (site, asset): granting again re-activates it, revoking flips the status,
 * and the who/when columns plus the audit log keep the history.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('motion_site_asset_grants', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('site_id')->constrained('sites')->cascadeOnDelete();
            $table->string('asset_id', 191);
            // active|revoked
            $table->string('status', 16)->default('active');
            // admin_grant|purchase|license — why the site holds it.
            $table->string('source', 24)->default('admin_grant');
            // Order/invoice/licence reference, free text, optional.
            $table->string('reference', 191)->nullable();
            $table->string('note', 255)->nullable();
            $table->string('granted_by_actor_identifier', 191)->nullable();
            $table->timestamp('granted_at')->nullable();
            $table->string('revoked_by_actor_identifier', 191)->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->unique(['site_id', 'asset_id'], 'motion_site_asset_grants_site_asset_unique');
            $table->index(['asset_id', 'status'], 'motion_site_asset_grants_asset_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('motion_site_asset_grants');
    }
};
