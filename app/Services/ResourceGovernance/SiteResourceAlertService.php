<?php

namespace App\Services\ResourceGovernance;

use App\Models\Site;
use App\Models\SiteResourceUsage;
use App\Models\SiteResourceLimit;
use App\Services\ActivityLogger;
use App\Services\Infrastructure\SlackAlertService;
use App\Services\Notification\NotificationCenterService;
use Illuminate\Support\Facades\Log;

class SiteResourceAlertService
{
    public function __construct(
        private NotificationCenterService $nc,
        private SlackAlertService $slack
    ) {}

    /**
     * Check and fire alerts for the hard disk quota (80% / 95%) and the soft
     * quota grace period.
     *
     * 2026-11-19 (P1): only on a REAL measurement (storage_bytes now holds the
     * governor's du result; SiteResourceCalculatorService::measuredDiskUsage()).
     * Every transition is logged on both sides: the platform activity log and
     * the customer site's own admin inbox / activity log.
     */
    public function checkAndAlertStorage(Site $site, SiteResourceUsage $usage, SiteResourceLimit $limit): void
    {
        $usedBytes = $this->measuredBytes($usage);
        if ($usedBytes === null) {
            return; // no fresh measurement: never alert on an unknown or estimated figure
        }

        $hardLimit = (int) ($limit->disk_hard_limit_bytes ?: $limit->storage_limit_bytes ?: 5368709120);
        $pct      = $hardLimit > 0 ? round($usedBytes / $hardLimit * 100, 1) : 0.0;
        $warning  = config('resource_governance.alert_warning_percent', 80);
        $critical = config('resource_governance.alert_critical_percent', 95);

        if ($pct >= $critical && !$usage->alerted_95) {
            $this->alertStorageCritical($site, $pct);
            $usage->update(['alerted_95' => true, 'alerted_80' => true]);
            $this->logBothSides($site, 'resource.storage_critical', "مصرف دیسک سایت به {$pct}٪ سقف دیسک سخت رسید (بحرانی).", [
                'storage_pct' => $pct, 'used_bytes' => $usedBytes, 'hard_limit_bytes' => $hardLimit,
            ]);
        } elseif ($pct >= $warning && $pct < $critical && !$usage->alerted_80) {
            $this->alertStorageWarning($site, $pct);
            $usage->update(['alerted_80' => true]);
            $this->logBothSides($site, 'resource.storage_warning', "مصرف دیسک سایت به {$pct}٪ سقف دیسک سخت رسید (هشدار).", [
                'storage_pct' => $pct, 'used_bytes' => $usedBytes, 'hard_limit_bytes' => $hardLimit,
            ]);
        }

        $this->checkAndAlertSoftDisk($site, $usage, $limit);
    }

    /**
     * Soft disk quota (default 70% of the hard quota) with a real 7-day grace
     * period, tracked by its own flag (it used to share alerted_80 with the
     * hard 80% warning, so it never set a flag and re-fired every run):
     *
     *   crosses the soft quota  → soft_exceeded_at = now, alert once (grace starts)
     *   still over after N days → escalate once (critical, Slack), soft_grace_escalated_at
     *   back under the quota    → state cleared and the resolution logged
     */
    public function checkAndAlertSoftDisk(Site $site, SiteResourceUsage $usage, SiteResourceLimit $limit): void
    {
        $usedBytes = $this->measuredBytes($usage);
        if ($usedBytes === null) {
            return;
        }

        $hardLimit = (int) ($limit->disk_hard_limit_bytes ?: $limit->storage_limit_bytes ?: 5368709120);
        $softLimit = (int) ($limit->disk_soft_limit_bytes ?: ($hardLimit * 0.70));
        $graceDays = (int) config('resource_governance.soft_disk_grace_days', 7);
        $softGb    = round($softLimit / (1024 ** 3), 1);
        $usedGb    = round($usedBytes / (1024 ** 3), 1);

        if ($softLimit <= 0) {
            return;
        }

        if ($usedBytes < $softLimit) {
            if ($usage->soft_exceeded_at !== null) {
                $since = $usage->soft_exceeded_at;
                $usage->update(['soft_exceeded_at' => null, 'alerted_soft' => false, 'soft_grace_escalated_at' => null]);
                $this->logBothSides($site, 'resource.soft_quota_resolved', "مصرف دیسک ({$usedGb} گیگابایت) دوباره زیر سقف نرم ({$softGb} گیگابایت) است؛ مهلت پاکسازی بسته شد.", [
                    'used_bytes' => $usedBytes, 'soft_limit_bytes' => $softLimit, 'exceeded_since' => $since->toIso8601String(),
                ]);
            }

            return;
        }

        if ($usage->soft_exceeded_at === null) {
            $endsAt = now()->addDays($graceDays);
            $usage->update(['soft_exceeded_at' => now(), 'alerted_soft' => true, 'soft_grace_escalated_at' => null]);
            $message = "سایت {$site->title} — مصرف دیسک ({$usedGb} گیگابایت) از سقف نرم ({$softGb} گیگابایت) فراتر رفت. مهلت {$graceDays} روزه برای ارتقا یا پاکسازی تا {$endsAt->toDateString()} فعال شد.";

            $this->nc->notify([
                'type'          => 'soft_disk_warning',
                'priority'      => 'medium',
                'title'         => "⚠️ هشدار دیسک نرم: {$site->title}",
                'message'       => $message,
                'audience_type' => 'platform_admin',
                'channel'       => 'dashboard',
                'site_id'       => $site->id,
                'link'          => route('admin.resource-governance.site', $site->id),
            ]);
            $this->logBothSides($site, 'resource.soft_quota_exceeded', $message, [
                'used_bytes' => $usedBytes, 'soft_limit_bytes' => $softLimit, 'grace_days' => $graceDays, 'grace_ends_at' => $endsAt->toIso8601String(),
            ]);

            return;
        }

        if ($usage->soft_grace_escalated_at === null && $usage->soft_exceeded_at->lte(now()->subDays($graceDays))) {
            $usage->update(['soft_grace_escalated_at' => now()]);
            $message = "سایت {$site->title} — مهلت {$graceDays} روزه دیسک نرم از {$usage->soft_exceeded_at->toDateString()} به پایان رسید و مصرف ({$usedGb} گیگابایت) هنوز بالای سقف نرم ({$softGb} گیگابایت) است. اقدام لازم است.";

            $this->nc->notify([
                'type'          => 'soft_disk_grace_expired',
                'priority'      => 'critical',
                'title'         => "🚨 پایان مهلت دیسک نرم: {$site->title}",
                'message'       => $message,
                'audience_type' => 'platform_admin',
                'channel'       => 'both',
                'site_id'       => $site->id,
                'link'          => route('admin.resource-governance.site', $site->id),
            ]);
            try {
                $this->slack->critical("پایان مهلت دیسک نرم: {$site->title}", $message);
            } catch (\Throwable $e) {
                Log::debug('ResourceAlertService: Slack critical failed: ' . $e->getMessage());
            }
            $this->logBothSides($site, 'resource.soft_quota_grace_expired', $message, [
                'used_bytes' => $usedBytes, 'soft_limit_bytes' => $softLimit, 'exceeded_since' => $usage->soft_exceeded_at->toIso8601String(),
            ]);
        }
    }

    /** The real web-root usage, or null when there is no fresh measurement. */
    private function measuredBytes(SiteResourceUsage $usage): ?int
    {
        if ($usage->disk_usage_bytes === null || $usage->disk_audited_at === null
            || $usage->disk_audited_at->lt(now()->subHours(SiteResourceCalculatorService::MEASUREMENT_MAX_AGE_HOURS))) {
            return null;
        }

        return (int) $usage->disk_usage_bytes;
    }

    /** @param array<string, mixed> $properties */
    private function logBothSides(Site $site, string $action, string $description, array $properties): void
    {
        ActivityLogger::log($action, $description, $site, $properties + ['site_id' => $site->id]);

        $recorded = app(SiteResourceCalculatorService::class)
            ->recordCustomerSiteEvent($site, $action, $description, $description, $properties);
        if (! $recorded) {
            Log::warning("ResourceAlertService: {$action} logged on the platform only (customer site unreachable)", ['site_id' => $site->id]);
        }
    }

    /**
     * Notify platform admin that storage is approaching limit (80%).
     */
    public function alertStorageWarning(Site $site, float $percent): void
    {
        $message = "سایت {$site->title} — فضای ذخیره‌سازی به {$percent}٪ رسیده است.";

        $this->nc->notify([
            'type'          => 'resource_warning',
            'priority'      => 'medium',
            'title'         => "⚠️ هشدار فضا: {$site->title}",
            'message'       => $message,
            'audience_type' => 'platform_admin',
            'channel'       => 'dashboard',
            'site_id'       => $site->id,
            'link'          => route('admin.resource-governance.site', $site->id),
        ]);

        try {
            $this->slack->warning("هشدار فضای ذخیره‌سازی: {$site->title}", $message);
        } catch (\Throwable $e) {
            Log::debug('ResourceAlertService: Slack warning failed: ' . $e->getMessage());
        }
    }

    /**
     * Notify platform admin that storage is critically full (95%).
     */
    public function alertStorageCritical(Site $site, float $percent): void
    {
        $message = "سایت {$site->title} — فضای ذخیره‌سازی به {$percent}٪ رسیده است! اقدام فوری لازم است.";

        $this->nc->notify([
            'type'          => 'resource_critical',
            'priority'      => 'critical',
            'title'         => "🚨 بحران فضا: {$site->title}",
            'message'       => $message,
            'audience_type' => 'platform_admin',
            'channel'       => 'both',
            'site_id'       => $site->id,
            'link'          => route('admin.resource-governance.site', $site->id),
        ]);

        try {
            $this->slack->critical("بحران فضای ذخیره‌سازی: {$site->title}", $message);
        } catch (\Throwable $e) {
            Log::debug('ResourceAlertService: Slack critical failed: ' . $e->getMessage());
        }
    }

    /**
     * Alert when an archive is growing unexpectedly.
     */
    public function alertAbnormalArchiveGrowth(Site $site, int $bytes): void
    {
        $formatted = SiteResourceUsage::formatBytes($bytes);
        $message   = "سایت {$site->title} — رشد غیرعادی آرشیو: {$formatted} طی ۲۴ ساعت.";

        $this->nc->notify([
            'type'          => 'archive_anomaly',
            'priority'      => 'high',
            'title'         => "آرشیو غیرعادی: {$site->title}",
            'message'       => $message,
            'audience_type' => 'platform_admin',
            'channel'       => 'dashboard',
            'site_id'       => $site->id,
            'link'          => route('admin.resource-governance.site', $site->id),
        ]);
    }

    /**
     * GAP-M08 — Alert when resource usage push to customer-site fails.
     */
    public function alertPushFailed(Site $site, string $error): void
    {
        $message = "سایت {$site->title} — انتقال اطلاعات مصرف منابع به سایت مشتری ناموفق بود.";

        $this->nc->notify([
            'type'          => 'resource_push_failed',
            'priority'      => 'medium',
            'title'         => "خطا در ارسال منابع: {$site->title}",
            'message'       => $message . " خطا: {$error}",
            'audience_type' => 'platform_admin',
            'channel'       => 'dashboard',
            'site_id'       => $site->id,
            'link'          => route('admin.resource-governance.site', $site->id),
        ]);

        Log::warning("GAP-M08: resource push failed for site {$site->id}", [
            'site'  => $site->title,
            'error' => $error,
        ]);
    }

    /**
     * Alert when a site has no recent backup.
     */
    public function alertBackupMissing(Site $site, int $daysSinceBackup): void
    {
        $message = "سایت {$site->title} — آخرین پشتیبان {$daysSinceBackup} روز پیش بود.";

        $this->nc->notify([
            'type'          => 'backup_missing',
            'priority'      => 'high',
            'title'         => "پشتیبان گم‌شده: {$site->title}",
            'message'       => $message,
            'audience_type' => 'platform_admin',
            'channel'       => 'dashboard',
            'site_id'       => $site->id,
        ]);
    }
}
