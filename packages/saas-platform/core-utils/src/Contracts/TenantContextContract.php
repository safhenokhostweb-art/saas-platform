<?php

namespace SaaSPlatform\CoreUtils\Contracts;

/**
 * قرارداد مرکزی برای دسترسی به Tenant Context.
 * تمام کلاس‌هایی که نیاز به context tenant دارند باید این اینترفیس را پیاده‌سازی کنند.
 */
interface TenantContextContract
{
    /**
     * Tenant فعلی را برگردان.
     */
    public function current(): ?object;

    /**
     * نوع tenant فعلی را برگردان (reseller, site, ...).
     */
    public function type(): ?string;

    /**
     * آیا tenant فعلی از نوع Reseller است؟
     */
    public function isReseller(): bool;

    /**
     * آیا tenant فعلی از نوع Site است؟
     */
    public function isSite(): bool;

    /**
     * Reseller فعلی را برگردان (در صورت وجود).
     */
    public function reseller(): ?object;

    /**
     * Site فعلی را برگردان (در صورت وجود).
     */
    public function site(): ?object;

    /**
     * شناسهٔ tenant فعلی.
     */
    public function id(): ?int;

    /**
     * آیا در یک tenant context هستیم؟
     */
    public function hasTenant(): bool;

    /**
     * بررسی کن که آیا یک feature خاص برای tenant فعلی فعال است یا خیر.
     *
     * Feature flags معمولاً در مدل Tenant (Reseller/Site)
     * یا در config/feature-flags.php ذخیره می‌شوند.
     *
     * @param string $feature نام یکتای feature (مثلاً 'tickets.attachments', 'sla.enabled')
     * @return bool
     */
    public function hasFeature(string $feature): bool;
}
