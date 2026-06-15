<?php

namespace App\Console\Commands;

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
