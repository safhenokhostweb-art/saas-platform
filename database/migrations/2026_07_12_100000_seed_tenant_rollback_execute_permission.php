<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 13-O8 — permission boundary for manual installation rollback.
 *
 * Seeds `tenant.rollback.execute` following the exact pattern established
 * by 2026_07_11_100000_seed_tenant_upgrade_execute_permission.php
 * (insertOrIgnore into permissions, then role_has_permissions).
 *
 * Assigned to `staff` — the only non-super-admin role that currently
 * passes AuthorizationService::canAccessAdmin() and therefore the only
 * role that could reach CustomerDeploymentController::rollback() before
 * this phase. This preserves staff's existing de facto ability to trigger
 * manual rollback; it does not expand access to any role that couldn't
 * already reach the controller. super_admin needs no row here —
 * User::hasPermission() and AuthorizationService::can() both short-circuit
 * via isSuperAdmin().
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('permissions') || !Schema::hasTable('roles') || !Schema::hasTable('role_has_permissions')) {
            return;
        }

        DB::table('permissions')->insertOrIgnore([
            'name' => 'اجرای بازگشت (Rollback) نصب تنانت',
            'slug' => 'tenant.rollback.execute',
            'module_slug' => 'sites',
            'guard_name' => 'web',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $permissionId = DB::table('permissions')->where('slug', 'tenant.rollback.execute')->value('id');
        $staffRoleId  = DB::table('roles')->where('slug', 'staff')->value('id');

        if ($permissionId && $staffRoleId) {
            DB::table('role_has_permissions')->insertOrIgnore([
                'permission_id' => $permissionId,
                'role_id'       => $staffRoleId,
            ]);
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('permissions')) {
            return;
        }

        $permissionId = DB::table('permissions')->where('slug', 'tenant.rollback.execute')->value('id');

        if ($permissionId && Schema::hasTable('role_has_permissions')) {
            DB::table('role_has_permissions')->where('permission_id', $permissionId)->delete();
        }

        DB::table('permissions')->where('slug', 'tenant.rollback.execute')->delete();
    }
};
