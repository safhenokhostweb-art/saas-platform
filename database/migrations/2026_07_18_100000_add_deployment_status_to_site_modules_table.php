<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 17-C — Part 1: separates Module Entitlement (is_active — unchanged
 * meaning: is this site allowed to use this module at all) from Module
 * Deployment State (deployment_status — is the module's code actually
 * present/working on the customer's remote server).
 *
 * Before this migration, is_active was the only signal, and it was set the
 * instant an admin toggled a module on — regardless of whether any code was
 * ever deployed. deployment_status now tracks the real state:
 *   pending_install -> installing -> installed
 *                                 -> upgrading -> installed
 *                                 -> failed
 *   disabled (entitlement removed)
 *
 * Safe default for existing rows: since this lifecycle is new and every
 * pre-existing site_modules row predates it, there is no real "pending"
 * state to recover — an is_active=true row is presumed already deployed
 * (this platform has been live), and an is_active=false row is presumed
 * intentionally turned off. Neither backfill value can break existing
 * behavior since deployment_status is not read by any pre-existing code
 * path yet.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('site_modules', function (Blueprint $table) {
            $table->string('deployment_status', 20)->default('installed')->after('is_active');
            $table->foreignId('last_installation_id')->nullable()->after('deployment_status')
                ->constrained('customer_installations')->nullOnDelete();
        });

        DB::table('site_modules')->where('is_active', false)->update(['deployment_status' => 'disabled']);
    }

    public function down(): void
    {
        Schema::table('site_modules', function (Blueprint $table) {
            $table->dropConstrainedForeignId('last_installation_id');
            $table->dropColumn('deployment_status');
        });
    }
};
