<?php

namespace SaaSPlatform\CoreUtils\Traits;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;

/**
 * Trait HasTenantContext
 *
 * پیاده‌سازی پیش‌فرض اینترفیس TenantContextContract.
 * این trait را به کلاس سرویس Tenant Context خود اضافه کنید.
 *
 * متد hasFeature() وضعیت feature flags را از منابع زیر به ترتیب اولویت بررسی می‌کند:
 * 1. config('feature-flags.{feature}') — تنظیمات سراسری
 * 2. $tenant->features[{feature}] ?? false — فیلد features در مدل Tenant (JSON)
 * 3. $tenant->{feature} ?? false — فیلد مستقیم روی مدل Tenant
 */
trait HasTenantContext
{
    /**
     * بررسی کن که آیا یک feature خاص برای tenant فعلی فعال است یا خیر.
     *
     * @param string $feature نام یکتای feature (مثلاً 'tickets.attachments', 'sla.enabled')
     * @return bool
     */
    public function hasFeature(string $feature): bool
    {
        // 1. ابتدا از config سراسری بررسی کن
        $globalConfig = Config::get("feature-flags.{$feature}");
        if ($globalConfig !== null) {
            return (bool) $globalConfig;
        }

        // 2. از tenant فعلی بررسی کن
        $tenant = $this->current();
        if ($tenant === null) {
            return false;
        }

        // 2a. اگر مدل tenant دارای فیلد features (JSON) باشد
        if (isset($tenant->features) && is_array($tenant->features)) {
            return (bool) ($tenant->features[$feature] ?? false);
        }

        // 2b. اگر مدل tenant دارای فیلد مستقیم با نام feature باشد
        if (isset($tenant->{$feature})) {
            return (bool) $tenant->{$feature};
        }

        // 3. اگر هیچکدام پیدا نشد، false برگردان
        return false;
    }

    /**
     * فعال کردن یک feature برای tenant فعلی.
     * این متد صرفاً در سشن جاری فعال می‌کند (برای ذخیرهٔ دائمی باید از ریپازیتوری استفاده کرد).
     *
     * @param string $feature
     * @param bool $value
     */
    public function setFeature(string $feature, bool $value = true): void
    {
        Config::set("feature-flags.{$feature}", $value);
    }
}
