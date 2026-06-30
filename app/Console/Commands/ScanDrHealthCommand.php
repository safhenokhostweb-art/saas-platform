<?php
namespace App\Console\Commands;

use App\Models\Infrastructure\InfraBackupRun;
use App\Services\Infrastructure\ArvanBackupService;
use App\Services\Infrastructure\RestoreService;
use App\Services\Infrastructure\SlackAlertService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Schema;

class ScanDrHealthCommand extends Command
{
    protected $signature   = 'dr:scan-health';
    protected $description = 'Scan disaster recovery health — fires Slack alerts for backup staleness, Arvan gaps, and stuck restore locks';

    private const DEDUP_TTL = 6 * 3600; // 6h silence window per alert type

    public function handle(
        ArvanBackupService $arvan,
        SlackAlertService  $slack,
        RestoreService     $restore,
    ): int {
        $alerts = [];

        // ── ALERT-001: DB backup stale ─────────────────────────────────────────
        $lastDb = InfraBackupRun::whereNull('site_id')
            ->where('backup_type', 'database')
            ->where('status', 'success')
            ->orderByDesc('started_at')
            ->first();

        if (!$lastDb || $lastDb->completed_at->lt(now()->subHours(26))) {
            $hoursAgoRaw   = $lastDb ? now()->diffInHours($lastDb->completed_at) : null;
            $hoursAgoLabel = $hoursAgoRaw !== null ? "{$hoursAgoRaw} ساعت" : 'هرگز';
            $this->fireAlert('ALERT-001', 'critical', "آخرین موفقیت: {$hoursAgoLabel} پیش",
                $slack, 'DB Backup Stale', "Platform database backup has not completed successfully in over 26 hours. Last success: " . ($hoursAgoRaw !== null ? "{$hoursAgoRaw}h" : 'never') . " ago.");
            $alerts[] = ['type' => 'ALERT-001', 'severity' => 'critical', 'message' => "بکاپ DB پلتفرم بیش از ۲۶ ساعت است بروز نشده. آخرین موفقیت: {$hoursAgoLabel} پیش"];
        } else {
            Cache::forget('dr_alert_sent_ALERT-001');
        }

        // ── ALERT-003: Arvan sync gap ──────────────────────────────────────────
        if ($lastDb && $lastDb->completed_at->gte(now()->subHours(26))) {
            $filename = basename($lastDb->file_path ?? '');
            if ($filename && !$arvan->exists("saas-platform/database/{$filename}")) {
                $this->fireAlert('ALERT-003', 'high', "فایل {$filename} در Arvan یافت نشد",
                    $slack, 'Arvan Sync Gap Detected', "Backup file {$filename} is recorded as success but not found on Arvan object storage.");
                $alerts[] = ['type' => 'ALERT-003', 'severity' => 'high', 'message' => "فایل {$filename} در Arvan یافت نشد — sync احتمالاً ناموفق بوده"];
            } else {
                Cache::forget('dr_alert_sent_ALERT-003');
            }
        }

        // ── ALERT-004: Storage backup stale ───────────────────────────────────
        $lastStorage = InfraBackupRun::whereNull('site_id')
            ->where('backup_type', 'storage')
            ->where('status', 'success')
            ->orderByDesc('started_at')
            ->first();

        if (!$lastStorage || $lastStorage->completed_at->lt(now()->subDays(8))) {
            $daysAgoRaw   = $lastStorage ? now()->diffInDays($lastStorage->completed_at) : null;
            $daysAgoLabel = $daysAgoRaw !== null ? "{$daysAgoRaw} روز" : 'هرگز';
            $this->fireAlert('ALERT-004', 'high', "آخرین موفقیت: {$daysAgoLabel} پیش",
                $slack, 'Storage Backup Stale', "Platform storage backup has not completed successfully in over 8 days. Last success: " . ($daysAgoRaw !== null ? "{$daysAgoRaw}d" : 'never') . " ago.");
            $alerts[] = ['type' => 'ALERT-004', 'severity' => 'high', 'message' => "بکاپ Storage پلتفرم بیش از ۸ روز است بروز نشده. آخرین موفقیت: {$daysAgoLabel} پیش"];
        } else {
            Cache::forget('dr_alert_sent_ALERT-004');
        }

        // ── ALERT-007: Restore lock stuck ─────────────────────────────────────
        if ($restore->isLocked()) {
            try {
                $remaining = Redis::ttl('restore_in_progress');
                $age       = 3600 - max(0, $remaining); // original TTL was 3600s
                if ($age > 7200) { // stuck for over 2h
                    $lockValue = $restore->getLockValue();
                    $this->fireAlert('ALERT-007', 'high', "قفل {$lockValue} برای {$age}s نگه داشته شده",
                        $slack, 'Restore Lock Stuck', "Redis restore_in_progress lock has been held for {$age}s (> 2h). Value: {$lockValue}");
                    $alerts[] = ['type' => 'ALERT-007', 'severity' => 'high', 'message' => "قفل restore_in_progress برای {$age} ثانیه قفل شده (> ۲ ساعت)"];
                }
            } catch (\Throwable $e) {
                $this->warn("Could not check Redis lock TTL: " . $e->getMessage());
            }
        } else {
            Cache::forget('dr_alert_sent_ALERT-007');
        }

        // ── Persist to dr_alert_log (optional, graceful degradation) ──────────
        $this->persistAlerts($alerts);

        $count = count($alerts);
        $this->info("DR health scan complete. {$count} alert(s) detected.");
        return self::SUCCESS;
    }

    private function fireAlert(
        string            $alertType,
        string            $severity,
        string            $detail,
        SlackAlertService $slack,
        string            $title,
        string            $body,
    ): void {
        $cacheKey = "dr_alert_sent_{$alertType}";

        if (Cache::has($cacheKey)) {
            $this->line("[{$alertType}] Slack deduped (within 6h silence window)");
            return;
        }

        try {
            match($severity) {
                'critical' => $slack->critical($title, $body . "\nAction: Check /admin/disaster-recovery/center"),
                'high'     => $slack->high($title, $body . "\nAction: Check /admin/disaster-recovery/center"),
                default    => $slack->warning($title, $body),
            };
            Cache::put($cacheKey, true, self::DEDUP_TTL);
            $this->warn("[{$alertType}] {$severity}: {$detail}");
        } catch (\Throwable $e) {
            $this->warn("[{$alertType}] Slack send failed: " . $e->getMessage());
        }
    }

    private function persistAlerts(array $alerts): void
    {
        if (empty($alerts) || !Schema::hasTable('dr_alert_log')) {
            return;
        }

        try {
            foreach ($alerts as $alert) {
                DB::table('dr_alert_log')->insert([
                    'alert_type'  => $alert['type'],
                    'severity'    => $alert['severity'],
                    'message'     => $alert['message'],
                    'context'     => json_encode([]),
                    'resolved'    => false,
                    'created_at'  => now(),
                    'updated_at'  => now(),
                ]);
            }
        } catch (\Throwable $e) {
            // Table may not exist — degrade gracefully
        }
    }
}
