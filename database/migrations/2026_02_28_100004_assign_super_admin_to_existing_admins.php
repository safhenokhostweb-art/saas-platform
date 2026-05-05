<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('roles') || !Schema::hasTable('role_user')) {
            return;
        }

        $superAdminRoleId = DB::table('roles')->where('slug', 'super_admin')->value('id');
        if (!$superAdminRoleId) {
            return;
        }

        $adminUserIds = DB::table('users')->where('is_admin', true)->pluck('id');
        foreach ($adminUserIds as $userId) {
            DB::table('role_user')->insertOrIgnore([
                'role_id' => $superAdminRoleId,
                'user_id' => $userId,
            ]);
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('role_user')) {
            return;
        }
        $superAdminRoleId = DB::table('roles')->where('slug', 'super_admin')->value('id');
        if ($superAdminRoleId) {
            DB::table('role_user')->where('role_id', $superAdminRoleId)->delete();
        }
    }
};
