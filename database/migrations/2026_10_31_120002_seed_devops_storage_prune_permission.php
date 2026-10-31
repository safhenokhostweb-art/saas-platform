<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Permission boundary for deleting files on customer servers (DevOps
 * storage pruner), following 2026_07_11_100000_seed_tenant_upgrade_execute_permission.php.
 *
 * Deliberately assigned to no role: this is a new capability, so there is no
 * existing access to preserve. super_admin passes through
 * AuthorizationService::can() via isSuperAdmin(); any other role gets it only
 * when an owner grants it explicitly.
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('permissions')) {
            return;
        }

        DB::table('permissions')->insertOrIgnore([
            'name'        => 'پاک‌سازی فضای دیسک سایت‌ها',
            'slug'        => 'devops.storage.prune',
            'module_slug' => 'sites',
            'guard_name'  => 'web',
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);
    }

    public function down(): void
    {
        if (!Schema::hasTable('permissions')) {
            return;
        }

        $permissionId = DB::table('permissions')->where('slug', 'devops.storage.prune')->value('id');

        if ($permissionId && Schema::hasTable('role_has_permissions')) {
            DB::table('role_has_permissions')->where('permission_id', $permissionId)->delete();
        }

        DB::table('permissions')->where('slug', 'devops.storage.prune')->delete();
    }
};
