<?php

namespace SaaSPlatform\CoreUtils\RuntimeGovernance\Degradation;

use Illuminate\Support\Facades\Cache;
use SaaSPlatform\CoreUtils\Telemetry\TelemetryManager;

class RuntimeFeatureDegrader
{
    private const DISABLED_FEATURES_KEY = 'governance.degraded_features';

    public function __construct(private TelemetryManager $telemetry) {}

    public function disableFeature(string $feature, string $reason = 'load_management', int $ttl = 3600): void
    {
        $features          = $this->disabledFeatures();
        $features[$feature] = [
            'disabled_at' => now()->toIso8601String(),
            'reason'      => $reason,
            'ttl'         => $ttl,
        ];

        Cache::put(self::DISABLED_FEATURES_KEY, $features, $ttl);

        $this->telemetry->warning('governance', 'governance.feature.disabled', [
            'feature' => $feature,
            'reason'  => $reason,
        ]);
    }

    public function enableFeature(string $feature): void
    {
        $features = $this->disabledFeatures();
        unset($features[$feature]);

        Cache::forever(self::DISABLED_FEATURES_KEY, $features);

        $this->telemetry->info('governance', 'governance.feature.enabled', ['feature' => $feature]);
    }

    public function isDisabled(string $feature): bool
    {
        return isset($this->disabledFeatures()[$feature]);
    }

    public function disabledFeatures(): array
    {
        return Cache::get(self::DISABLED_FEATURES_KEY, []);
    }

    public function restoreAll(): void
    {
        Cache::forget(self::DISABLED_FEATURES_KEY);
        $this->telemetry->info('governance', 'governance.features.all_restored', []);
    }
}
