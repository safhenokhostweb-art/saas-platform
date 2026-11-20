<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * ADR-090 — `sites.purge`: irreversible full purge of a customer site
 * (server files, Nginx, SSL, database, Arvan backups/media).
 *
 * Owner decision 2026-11-20: super-admin only. The permission row exists so
 * the capability is visible and auditable in RBAC, but it is deliberately
 * assigned to NO role; SitePurgeRequest additionally requires
 * User::isSuperAdmin(), so granting it to another role does not open it up.
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('permissions')) {
            return;
        }

        DB::table('permissions')->insertOrIgnore([
            'name'        => 'حذف کامل و غیرقابل بازگشت سایت',
            'slug'        => 'sites.purge',
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

        $permissionId = DB::table('permissions')->where('slug', 'sites.purge')->value('id');

        if ($permissionId && Schema::hasTable('role_has_permissions')) {
            DB::table('role_has_permissions')->where('permission_id', $permissionId)->delete();
        }

        DB::table('permissions')->where('slug', 'sites.purge')->delete();
    }
};
