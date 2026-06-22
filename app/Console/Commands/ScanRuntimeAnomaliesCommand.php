<?php

namespace App\Console\Commands;

use App\Models\Infrastructure\InfraBackupRun;
use App\Models\Infrastructure\InfraSchedulerRun;
use App\Services\Infrastructure\SlackAlertService;
use Illuminate\Console\Command;
use SaaSPlatform\CoreUtils\Telemetry\Anomaly\RuntimeAnomalyEngine;

class ScanRuntimeAnomaliesCommand extends Command
{
    protected $signature = 'runtime:scan-anomalies {--quiet-slack : Suppress Slack for non-critical}';

    protected $description = 'اسکن runtime anomalies (queue stalls, tenant isolation, security, deployment)';

    public function handle(RuntimeAnomalyEngine $engine, SlackAlertService $slack): int
    {
        $anomalies = $engine->scan();

        // ── Operational checks (not covered by RuntimeAnomalyEngine) ──────────

        $operationalAnomalies = $this->checkOperational();
        $anomalies = array_merge($anomalies, $operationalAnomalies);

        foreach ($operationalAnomalies as $a) {
            $sev  = strtolower($a['severity']);
            $body = $a['message'];
            match ($sev) {
                'critical' => $slack->critical('Operational Alert: ' . $a['code'], $body),
                'high'     => $slack->high('Operational Alert: ' . $a['code'], $body),
                default    => null,
            };
        }

        // ─────────────────────────────────────────────────────────────────────

        if (empty($anomalies)) {
            $this->line('[' . now()->toTimeString() . '] هیچ anomaly یافت نشد.');
            return self::SUCCESS;
        }

        $criticalOrHigh = [];

        foreach ($anomalies as $anomaly) {
            $severity = strtoupper($anomaly['severity'] ?? 'unknown');
            $this->line(sprintf(
                '[%s] %s — %s | %s',
                now()->toTimeString(),
                $severity,
                $anomaly['code'],
                $anomaly['message']
            ));

            if (in_array(strtolower($anomaly['severity'] ?? ''), ['critical', 'high'], true)) {
                $criticalOrHigh[] = $anomaly;
            }
        }

        foreach ($criticalOrHigh as $anomaly) {
            // Operational anomalies already alerted above — skip duplicates
            if (isset($anomaly['_operational'])) {
                continue;
            }

            $severity = strtolower($anomaly['severity']);
            $body = $anomaly['message'];
            if (!empty($anomaly['context'])) {
                $body .= "\n```" . json_encode($anomaly['context'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . '```';
            }

            match ($severity) {
                'critical' => $slack->critical('Runtime Anomaly: ' . $anomaly['code'], $body),
                'high'     => $slack->high('Runtime Anomaly: ' . $anomaly['code'], $body),
                default    => null,
            };
        }

        return count($criticalOrHigh) > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function checkOperational(): array
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

        // 3. Scheduler dead (no InfraSchedulerRun record in last 5 minutes)
        try {
            $lastRun = InfraSchedulerRun::latest()->first();
            if ($lastRun && $lastRun->created_at->lt(now()->subMinutes(5))) {
                $anomalies[] = $this->opAnomaly('SCHEDULER_DEAD', 'critical',
                    'Laravel scheduler has not run in the last 5 minutes — cron may be down',
                    ['last_run_at' => $lastRun->created_at->toIso8601String(),
                     'last_command' => $lastRun->command]);
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
