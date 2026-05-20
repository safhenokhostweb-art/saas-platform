<?php
namespace App\Services;

use App\Models\Site;
use App\Models\User;
use App\Services\TenantContext;

/**
 * سرویس مرکزی احراز هویت و دسترسی با پشتیبانی از tenant isolation.
 */
class AuthorizationService
{
    public function __construct(
        protected TenantContext $tenantContext
    ) {}

    /**
     * آیا کاربر دسترسی به permission دارد؟
     */
    public function can(User $user, string $permission): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }
        if (!$user->hasPermission($permission)) {
            return false;
        }
        return $this->passesTenantCheck($user, $permission);
    }

    /**
     * آیا کاربر دسترسی به سایت دارد؟ (tenant isolation)
     */
    public function canAccessSite(User $user, Site $site): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }
        if ($user->hasRole(\App\Models\Role::RESELLER) && $user->reseller) {
            $customer = $site->customer;
            return $customer && (int) $customer->reseller_id === (int) $user->reseller->id;
        }
        return true;
    }

    /**
     * آیا کاربر می‌تواند به مسیر ادمین پلتفرم دسترسی داشته باشد؟
     */
    public function canAccessAdmin(User $user): bool
    {
        if ($user->is_admin ?? false) {
            return true;
        }
        return $user->hasRole(\App\Models\Role::SUPER_ADMIN) || $user->hasRole(\App\Models\Role::STAFF);
    }

    /**
     * بررسی tenant: برای Reseller فقط دادهٔ خودش. Staff و Super Admin محدودیت ندارند.
     */
    protected function passesTenantCheck(User $user, string $permission): bool
    {
        if ($user->hasRole(\App\Models\Role::RESELLER)) {
            return $this->tenantContext->reseller() && (int) $this->tenantContext->reseller()->id === (int) $user->reseller?->id;
        }
        return true;
    }
}
