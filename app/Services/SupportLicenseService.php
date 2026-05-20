<?php
namespace App\Services;

use App\Models\Site;
use Carbon\Carbon;

/**
 * مدیریت لایسنس پشتیبانی: واجد شرایط بروزرسانی، grace period، سطح پشتیبانی.
 */
class SupportLicenseService
{
    public const LEVEL_BASIC = 'basic';
    public const LEVEL_PRO = 'pro';
    public const LEVEL_ENTERPRISE = 'enterprise';

    public function __construct()
    {
        // grace_days از config خوانده می‌شود
    }

    /**
     * آیا سایت واجد دریافت بروزرسانی است؟
     * در صورت فعال بودن پشتیبانی یا بودن در grace period، true برمی‌گرداند.
     */
    public function isEligibleForUpdates(Site $site): bool
    {
        $effectiveEnd = $this->getEffectiveSupportEnd($site);
        if ($effectiveEnd === null) {
            return false;
        }
        return $effectiveEnd->isFuture();
    }

    /**
     * تاریخ پایان مؤثر پشتیبانی (support_expires_at + grace_days).
     * اگر support_expires_at خالی باشد، null برمی‌گرداند.
     */
    public function getEffectiveSupportEnd(Site $site): ?Carbon
    {
        if ($site->support_expires_at === null) {
            return null;
        }
        $graceDays = (int) config('support.grace_days', 7);
        return $site->support_expires_at->copy()->addDays($graceDays);
    }

    /**
     * آیا سایت در grace period است؟ (پشتیبانی منقضی شده ولی هنوز در grace)
     */
    public function isInGracePeriod(Site $site): bool
    {
        if ($site->support_expires_at === null) {
            return false;
        }
        if (!$site->support_expires_at->isPast()) {
            return false;
        }
        $effectiveEnd = $this->getEffectiveSupportEnd($site);
        return $effectiveEnd !== null && $effectiveEnd->isFuture();
    }

    /**
     * وضعیت پشتیبانی برای نمایش: active، grace، expired، none.
     */
    public function getStatus(Site $site): string
    {
        if ($site->support_expires_at === null) {
            return 'none';
        }
        if ($site->support_expires_at->isFuture()) {
            return 'active';
        }
        if ($this->isInGracePeriod($site)) {
            return 'grace';
        }
        return 'expired';
    }

    /**
     * سطح پشتیبانی سایت (basic، pro، enterprise).
     */
    public function getLevel(Site $site): string
    {
        return $this->getLevelFromConfig($site->support_level ?? self::LEVEL_BASIC);
    }

    /**
     * نرمال‌سازی سطح پشتیبانی به یکی از مقادیر config.
     */
    public function getLevelFromConfig(?string $level): string
    {
        $levels = config('support.levels', [self::LEVEL_BASIC, self::LEVEL_PRO, self::LEVEL_ENTERPRISE]);
        return in_array($level, $levels, true) ? $level : self::LEVEL_BASIC;
    }

    /**
     * آیا support_starts_at گذشته است؟ (پشتیبانی شروع شده)
     */
    public function hasSupportStarted(Site $site): bool
    {
        if ($site->support_starts_at === null) {
            return true; // اگر تاریخ شروع نباشد، فرض بر شروع است
        }
        return $site->support_starts_at->isPast();
    }

    /**
     * آیا سایت می‌تواند به پشتیبانی (ارسال تیکت) دسترسی داشته باشد؟
     * شرط: پشتیبانی فعال یا در grace period + support_starts_at گذشته باشد.
     */
    public function canAccessSupport(Site $site): bool
    {
        if (!$this->hasSupportStarted($site)) {
            return false;
        }
        $status = $this->getStatus($site);
        return in_array($status, ['active', 'grace'], true);
    }
}
