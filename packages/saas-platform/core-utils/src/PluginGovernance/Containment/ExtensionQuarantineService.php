<?php

namespace SaaSPlatform\CoreUtils\PluginGovernance\Containment;

use Illuminate\Support\Facades\Cache;
use SaaSPlatform\CoreUtils\Telemetry\TelemetryManager;

class ExtensionQuarantineService
{
    private const QUARANTINE_PREFIX = 'plugin.quarantine';
    private const QUARANTINE_TTL   = 86400;

    public function __construct(
        private TelemetryManager $telemetry
    ) {}

    public function quarantine(string $moduleId, string $reason, string $quarantinedBy = 'runtime'): void
    {
        Cache::put(self::QUARANTINE_PREFIX . '.' . $moduleId, [
            'module_id'       => $moduleId,
            'reason'          => $reason,
            'quarantined_by'  => $quarantinedBy,
            'quarantined_at'  => now()->toIso8601String(),
        ], self::QUARANTINE_TTL);

        $this->telemetry->critical('plugin_governance', 'plugin.quarantine.activated', [
            'module_id' => $moduleId,
            'reason'    => $reason,
        ]);
    }

    public function release(string $moduleId, string $releasedBy = 'admin'): void
    {
        Cache::forget(self::QUARANTINE_PREFIX . '.' . $moduleId);

        $this->telemetry->info('plugin_governance', 'plugin.quarantine.released', [
            'module_id'   => $moduleId,
            'released_by' => $releasedBy,
        ]);
    }

    public function isQuarantined(string $moduleId): bool
    {
        return Cache::has(self::QUARANTINE_PREFIX . '.' . $moduleId);
    }

    public function getQuarantineRecord(string $moduleId): ?array
    {
        return Cache::get(self::QUARANTINE_PREFIX . '.' . $moduleId);
    }
}
