<?php

namespace SaaSPlatform\CoreUtils\MobileRuntime\Merge;

use Illuminate\Support\Facades\DB;
use App\Models\User;

/**
 * مدیریت دسترسی ادغام مکالمات.
 *
 * اولویت‌بندی دسترسی:
 *  1. super_admin / platform_admin (is_admin=true) → همیشه دارد
 *  2. tenant_admin role           → همیشه دارد
 *  3. مجوز صریح per-user در جدول mobile_conversation_merge_permissions → دارد
 */
class ConversationMergePermissionService
{
    /**
     * بررسی اینکه آیا کاربر می‌تواند مکالمات را در tenant مشخص ادغام کند.
     */
    public function canMerge(User $user, string $tenantId): bool
    {
        // ادمین اصلی پلتفرم
        if ($user->is_admin || $user->isSuperAdmin()) {
            return true;
        }

        // ادمین سایت مشتری
        if ($user->hasRole('tenant_admin')) {
            return true;
        }

        // مجوز صریح داده‌شده توسط ادمین سایت
        return DB::table('mobile_conversation_merge_permissions')
            ->where('tenant_id', $tenantId)
            ->where('user_id', (string) $user->id)
            ->exists();
    }

    /**
     * نوع کاربر برای لاگ ادغام.
     */
    public function resolveUserType(User $user): string
    {
        if ($user->is_admin || $user->isSuperAdmin()) {
            return 'platform_admin';
        }
        if ($user->hasRole('tenant_admin')) {
            return 'tenant_admin';
        }
        return 'end_user';
    }

    /**
     * اعطای مجوز ادغام به کاربر (فقط توسط tenant_admin یا بالاتر).
     */
    public function grant(string $tenantId, string $userId, string $grantedBy): void
    {
        DB::table('mobile_conversation_merge_permissions')->upsert(
            [
                'tenant_id'  => $tenantId,
                'user_id'    => $userId,
                'granted_by' => $grantedBy,
                'granted_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            ['tenant_id', 'user_id'],
            ['granted_by', 'granted_at', 'updated_at']
        );
    }

    /**
     * لغو مجوز ادغام از کاربر.
     */
    public function revoke(string $tenantId, string $userId): void
    {
        DB::table('mobile_conversation_merge_permissions')
            ->where('tenant_id', $tenantId)
            ->where('user_id', $userId)
            ->delete();
    }

    /**
     * لیست کاربرانی که مجوز ادغام دارند در یک tenant.
     */
    public function listGranted(string $tenantId): array
    {
        return DB::table('mobile_conversation_merge_permissions')
            ->where('tenant_id', $tenantId)
            ->orderByDesc('granted_at')
            ->get()
            ->toArray();
    }

    /**
     * بررسی اینکه درخواست‌دهنده می‌تواند مجوز بدهد (tenant_admin یا بالاتر).
     */
    public function canManagePermissions(User $user): bool
    {
        return $user->is_admin
            || $user->isSuperAdmin()
            || $user->hasRole('tenant_admin');
    }
}
