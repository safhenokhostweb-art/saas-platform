<?php

namespace App\Services\Infrastructure;

use App\Models\Infrastructure\InfraBackupRun;

/**
 * Real-environment operational checks (disk usage, backup freshness,
 * scheduler heartbeat) that RuntimeAnomalyEngine does not cover.
 *
 * Extracted from ScanRuntimeAnomaliesCommand into its own injectable
 * service so it can be substituted in tests the same way
 * RuntimeAnomalyEngine already is — the command's exit code and Slack
 * calls must be deterministic and governed by injected collaborators
 * only, never by the real disk/DB state of the machine running the test.
 */
class OperationalAnomalyChecker
{
    public function check(): array
    {
        $anomalies = [];

        // 1. Disk usage
        try {
            $storagePath  = storage_path();
            $freeBytes    = disk_free_space($storagePath);
            $totalBytes   = disk_total_space($storagePath);
            if ($totalBytes > 0) {
                $usedPct = round((1 - $freeBytes / $totalBytes) * 100, 1);
                if ($usedPct >= 90) {
                    $anomalies[] = $this->opAnomaly('DISK_CRITICAL', 'critical',
                        "Disk usage at {$usedPct}% on production VPS — writes may fail soon",
                        ['used_pct' => $usedPct, 'free_gb' => round($freeBytes / 1024 ** 3, 2)]);
                } elseif ($usedPct >= 80) {
                    $anomalies[] = $this->opAnomaly('DISK_WARNING', 'high',
                        "Disk usage at {$usedPct}% — plan cleanup or expansion",
                        ['used_pct' => $usedPct, 'free_gb' => round($freeBytes / 1024 ** 3, 2)]);
                }
            }
        } catch (\Throwable) {
        }

        // 2. Backup overdue (> 26 hours since last successful backup)
        try {
            $lastBackup = InfraBackupRun::where('status', 'success')->latest()->first();
            if (!$lastBackup || $lastBackup->created_at->lt(now()->subHours(26))) {
                $ago = $lastBackup ? $lastBackup->created_at->diffForHumans() : 'never';
                $anomalies[] = $this->opAnomaly('BACKUP_OVERDUE', 'critical',
                    "No successful database backup in the last 26 hours (last: {$ago})",
                    ['last_backup_at' => $lastBackup?->created_at?->toIso8601String()]);
            }
        } catch (\Throwable) {
        }

        // 3. Scheduler dead — check heartbeat cache key (written every minute by Kernel)
        try {
            $heartbeat = cache('scheduler_last_run');
            if ($heartbeat === null) {
                // No heartbeat ever written — either first boot or cron not configured
                // Don't alert until we have positive evidence of a working then broken scheduler
            } elseif ($heartbeat->lt(now()->subMinutes(3))) {
                $anomalies[] = $this->opAnomaly('SCHEDULER_DEAD', 'critical',
                    'Laravel scheduler has not run in the last 3 minutes — cron may be down',
                    ['last_heartbeat_at' => $heartbeat->toIso8601String()]);
            }
        } catch (\Throwable) {
        }

        return $anomalies;
    }

    private function opAnomaly(string $code, string $severity, string $message, array $context = []): array
    {
        return [
            'code'         => $code,
            'severity'     => $severity,
            'message'      => $message,
            'context'      => $context,
            'context_key'  => $code,
            'detected_at'  => now()->toIso8601String(),
            '_operational' => true,
        ];
    }
}
