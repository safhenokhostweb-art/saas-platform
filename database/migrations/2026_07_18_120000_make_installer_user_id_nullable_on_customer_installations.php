<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Phase 17-D — Part 5: installer_user_id was NOT NULL, an assumption that
 * every CustomerInstallation is triggered by a logged-in admin. That
 * stopped being universally true once ModuleAssignmentService::
 * activatePaidPurchase() (OrderFulfillmentService's payment-webhook
 * fulfillment path) started dispatching real deployments — a paid module
 * purchase can complete with no admin session at all, so the "who
 * triggered this" value is legitimately absent.
 *
 * Uses raw DB::statement() (matching the enum-ALTER migrations elsewhere in
 * this pipeline) rather than Schema::table(...)->change(), since
 * doctrine/dbal is not a project dependency.
 *
 * Purely additive/relaxing: does not touch CustomerInstallationOrchestrator,
 * Stages, Provisioning, or Rollback — no behavior in the pipeline itself
 * changes. Every existing row keeps its non-null value.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE customer_installations MODIFY COLUMN installer_user_id BIGINT UNSIGNED NULL');
    }

    public function down(): void
    {
        // Safety net: a null installer_user_id row created after this
        // migration (a system-triggered install) would violate the
        // restored NOT NULL constraint — this should not happen in
        // practice at rollback time, but fail loudly rather than silently
        // corrupt data if it ever does.
        DB::statement('ALTER TABLE customer_installations MODIFY COLUMN installer_user_id BIGINT UNSIGNED NOT NULL');
    }
};
