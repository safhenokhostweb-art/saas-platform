<?php

namespace App\Services\ResourceGovernance;

use App\Models\Site;
use App\Models\SiteResourceUsage;
use App\Models\SiteResourceLimit;
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
     * Check and fire alert if storage > warning threshold (default 80%).
     */
    public function checkAndAlertStorage(Site $site, SiteResourceUsage $usage, SiteResourceLimit $limit): void
    {
        $hardLimit = (int) ($limit->disk_hard_limit_bytes ?: $limit->storage_limit_bytes ?: 5368709120);
        $pct     = $usage->storagePercent($hardLimit);
        $warning = config('resource_governance.alert_warning_percent', 80);
        $critical = config('resource_governance.alert_critical_percent', 95);

        if ($pct >= $critical && !$usage->alerted_95) {
            $this->alertStorageCritical($site, $pct);
            $usage->update(['alerted_95' => true, 'alerted_80' => true]);
        } elseif ($pct >= $warning && !$usage->alerted_80) {
            $this->alertStorageWarning($site, $pct);
            $usage->update(['alerted_80' => true]);
        }

        $this->checkAndAlertSoftDisk($site, $usage, $limit);
    }

    /**
     * Check soft disk limit (default 70% of hard disk) with 7-day Grace Period policy.
     */
    public function checkAndAlertSoftDisk(Site $site, SiteResourceUsage $usage, SiteResourceLimit $limit): void
    {
        $hardLimit = (int) ($limit->disk_hard_limit_bytes ?: $limit->storage_limit_bytes ?: 5368709120);
        $softLimit = (int) ($limit->disk_soft_limit_bytes ?: ($hardLimit * 0.70));
        $usedBytes = (int) ($usage->disk_usage_bytes ?: $usage->storage_bytes ?: 0);

        if ($softLimit > 0 && $usedBytes >= $softLimit && !$usage->alerted_80) {
            $softGb = round($softLimit / (1024 ** 3), 1);
            $usedGb = round($usedBytes / (1024 ** 3), 1);
            $message = "سایت {$site->title} — مصرف دیسک از سقف نرم ({$softGb} گیگابایت) فراتر رفت (مصرف: {$usedGb} گیگابایت). مهلت ۷ روزه برای ارتقا یا پاکسازی فعال است.";

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
