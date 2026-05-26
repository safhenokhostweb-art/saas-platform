<?php

namespace SaaSPlatform\CoreUtils\RuntimeIsolation\NoisyNeighbor;

use Illuminate\Support\Facades\Cache;
use SaaSPlatform\CoreUtils\Telemetry\TelemetryManager;

class NoisyNeighborDetector
{
    private const DETECTED_PREFIX = 'isolation.noisy.tenant.';
    private const DETECTION_TTL   = 300;

    public function __construct(
        private TenantPressureAnalyzer $analyzer,
        private TelemetryManager       $telemetry
    ) {}

    public function detect(array $tenantIds): array
    {
        $noisyTenants = [];

        foreach ($tenantIds as $id) {
            if ($this->analyzer->isNoisy($id)) {
                $score = $this->analyzer->pressureScore($id);
                $noisyTenants[$id] = [
                    'tenant_id'       => $id,
                    'pressure_score'  => $score,
                    'pressure_level'  => 'noisy',
                    'detected_at'     => now()->toIso8601String(),
                ];

                $this->markDetected($id, $score);

                $this->telemetry->warning('isolation', 'isolation.noisy_neighbor.detected', [
                    'tenant_id'     => $id,
                    'pressure_score' => $score,
                ]);
            }
        }

        return $noisyTenants;
    }

    public function isCurrentlyNoisy(int $tenantId): bool
    {
        return (bool) Cache::get(self::DETECTED_PREFIX . $tenantId);
    }

    public function currentNoisyTenants(): array
    {
        // Returns any tenants we've recently marked as noisy
        // The cache key pattern isn't searchable, so we rely on callers passing tenant IDs
        return [];
    }

    public function clearDetection(int $tenantId): void
    {
        Cache::forget(self::DETECTED_PREFIX . $tenantId);
    }

    private function markDetected(int $tenantId, float $score): void
    {
        Cache::put(self::DETECTED_PREFIX . $tenantId, [
            'score'       => $score,
            'detected_at' => now()->toIso8601String(),
        ], self::DETECTION_TTL);
    }
}
