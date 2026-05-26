<?php

namespace SaaSPlatform\CoreUtils\Telemetry\Anomaly;

use Illuminate\Support\Facades\Cache;
use SaaSPlatform\CoreUtils\Telemetry\TelemetryManager;

class RuntimeAnomalyEngine
{
    private const COOLDOWN_SECONDS = 300;

    public function __construct(
        private TelemetryManager              $telemetry,
        private QueueAnomalyDetector          $queueDetector,
        private TenantIsolationAnomalyDetector $tenantDetector,
        private DeploymentFailureDetector     $deploymentDetector,
        private SecurityAnomalyDetector       $securityDetector
    ) {}

    public function scan(): array
    {
        $anomalies = [];

        $anomalies = array_merge($anomalies, $this->queueDetector->detect());
        $anomalies = array_merge($anomalies, $this->tenantDetector->detect());
        $anomalies = array_merge($anomalies, $this->deploymentDetector->detect());
        $anomalies = array_merge($anomalies, $this->securityDetector->detect());

        $anomalies = $this->deduplicate($anomalies);

        foreach ($anomalies as $anomaly) {
            if (!$this->isOnCooldown($anomaly['code'])) {
                $this->emit($anomaly);
                $this->setCooldown($anomaly['code']);
            }
        }

        return $anomalies;
    }

    public function scanMemory(): ?array
    {
        $memoryMb = memory_get_usage(true) / 1024 / 1024;
        $limitMb  = $this->parseMemoryLimit();

        if ($limitMb > 0 && ($memoryMb / $limitMb) > 0.85) {
            return $this->buildAnomaly(
                'RUNTIME_MEMORY_HIGH',
                'warning',
                'Memory usage exceeds 85% of limit',
                ['memory_mb' => round($memoryMb, 2), 'limit_mb' => $limitMb]
            );
        }

        return null;
    }

    private function emit(array $anomaly): void
    {
        $this->telemetry->emit(
            'anomaly',
            $anomaly['severity'],
            'anomaly.' . strtolower($anomaly['code']),
            $anomaly
        );
    }

    private function deduplicate(array $anomalies): array
    {
        $seen   = [];
        $result = [];

        foreach ($anomalies as $anomaly) {
            $key = $anomaly['code'] . '|' . ($anomaly['context_key'] ?? '');
            if (!isset($seen[$key])) {
                $seen[$key] = true;
                $result[]   = $anomaly;
            }
        }

        return $result;
    }

    private function isOnCooldown(string $code): bool
    {
        return Cache::has('anomaly.cooldown.' . md5($code));
    }

    private function setCooldown(string $code): void
    {
        Cache::put('anomaly.cooldown.' . md5($code), 1, self::COOLDOWN_SECONDS);
    }

    private function parseMemoryLimit(): float
    {
        $limit = ini_get('memory_limit');
        if ($limit === '-1') {
            return 0;
        }

        $unit  = strtolower(substr($limit, -1));
        $value = (float) $limit;

        return match ($unit) {
            'g'     => $value * 1024,
            'm'     => $value,
            'k'     => $value / 1024,
            default => $value / 1024 / 1024,
        };
    }

    public static function buildAnomaly(
        string $code,
        string $severity,
        string $message,
        array  $context = [],
        string $contextKey = ''
    ): array {
        return [
            'code'        => $code,
            'severity'    => $severity,
            'message'     => $message,
            'context'     => $context,
            'context_key' => $contextKey,
            'detected_at' => now()->toIso8601String(),
        ];
    }
}
