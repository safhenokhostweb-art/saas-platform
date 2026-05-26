<?php

namespace SaaSPlatform\CoreUtils\PluginGovernance\Lifecycle;

use Illuminate\Support\Facades\Cache;
use SaaSPlatform\CoreUtils\Telemetry\TelemetryManager;

class PluginRollbackCoordinator
{
    private const SNAPSHOT_PREFIX = 'plugin.rollback.snapshot';
    private const SNAPSHOT_TTL   = 86400;

    public function __construct(
        private PluginActivationCoordinator $activationCoordinator,
        private TelemetryManager            $telemetry
    ) {}

    public function saveSnapshot(string $moduleId, string $version, array $metadata = []): void
    {
        Cache::put(self::SNAPSHOT_PREFIX . '.' . $moduleId, [
            'module_id'   => $moduleId,
            'version'     => $version,
            'metadata'    => $metadata,
            'captured_at' => now()->toIso8601String(),
        ], self::SNAPSHOT_TTL);
    }

    public function rollback(string $moduleId, string $reason = 'manual_rollback'): array
    {
        $snapshot = Cache::get(self::SNAPSHOT_PREFIX . '.' . $moduleId);

        if (!$snapshot) {
            return ['rolled_back' => false, 'reason' => 'no_snapshot_available'];
        }

        $this->activationCoordinator->deactivate($moduleId, 'rollback_in_progress');

        $this->telemetry->warning('plugin_governance', 'plugin.rollback.initiated', [
            'module_id'        => $moduleId,
            'rollback_to'      => $snapshot['version'],
            'reason'           => $reason,
        ]);

        return [
            'rolled_back'    => true,
            'restored_version' => $snapshot['version'],
            'reason'         => $reason,
        ];
    }

    public function hasSnapshot(string $moduleId): bool
    {
        return Cache::has(self::SNAPSHOT_PREFIX . '.' . $moduleId);
    }

    public function getSnapshot(string $moduleId): ?array
    {
        return Cache::get(self::SNAPSHOT_PREFIX . '.' . $moduleId);
    }
}
