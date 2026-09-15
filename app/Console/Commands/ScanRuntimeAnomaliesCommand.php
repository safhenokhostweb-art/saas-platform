<?php

namespace App\Console\Commands;

use App\Services\Infrastructure\OperationalAnomalyChecker;
use App\Services\Infrastructure\SlackAlertService;
use Illuminate\Console\Command;
use SaaSPlatform\CoreUtils\Telemetry\Anomaly\RuntimeAnomalyEngine;

class ScanRuntimeAnomaliesCommand extends Command
{
    protected $signature = 'runtime:scan-anomalies {--quiet-slack : Suppress Slack for non-critical}';

    protected $description = 'اسکن runtime anomalies (queue stalls, tenant isolation, security, deployment)';

    public function handle(RuntimeAnomalyEngine $engine, OperationalAnomalyChecker $operationalChecker, SlackAlertService $slack): int
    {
        $anomalies = $engine->scan();

        // ── Operational checks (not covered by RuntimeAnomalyEngine) ──────────

        $operationalAnomalies = $operationalChecker->check();
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
}
