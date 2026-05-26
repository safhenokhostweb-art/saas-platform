<?php

namespace SaaSPlatform\CoreUtils\PluginGovernance\Lifecycle;

use Illuminate\Support\Facades\Cache;
use SaaSPlatform\CoreUtils\PluginGovernance\DependencyScanning\PluginDependencyScanner;
use SaaSPlatform\CoreUtils\PluginGovernance\DependencyScanning\DependencyRiskClassifier;
use SaaSPlatform\CoreUtils\Telemetry\TelemetryManager;

class PluginActivationCoordinator
{
    private const STATE_PREFIX = 'plugin.lifecycle.state';
    private const STATE_TTL    = 86400;

    public function __construct(
        private PluginDependencyScanner $scanner,
        private TelemetryManager        $telemetry
    ) {}

    public function activate(string $moduleId, string $modulePath = ''): array
    {
        if ($modulePath && !$this->scanner->isModuleSafe($moduleId, $modulePath)) {
            $this->setState($moduleId, PluginLifecycleManager::STATE_BLOCKED, 'dependency_scan_failed');

            return ['activated' => false, 'reason' => 'dependency_scan_failed'];
        }

        $this->setState($moduleId, PluginLifecycleManager::STATE_ACTIVE);

        $this->telemetry->info('plugin_governance', 'plugin.lifecycle.activated', ['module_id' => $moduleId]);

        return ['activated' => true];
    }

    public function deactivate(string $moduleId, string $reason = 'manual'): void
    {
        $this->setState($moduleId, PluginLifecycleManager::STATE_INACTIVE, $reason);
        $this->telemetry->info('plugin_governance', 'plugin.lifecycle.deactivated', [
            'module_id' => $moduleId,
            'reason'    => $reason,
        ]);
    }

    public function block(string $moduleId, string $reason): void
    {
        $this->setState($moduleId, PluginLifecycleManager::STATE_BLOCKED, $reason);
        $this->telemetry->critical('plugin_governance', 'plugin.lifecycle.blocked', [
            'module_id' => $moduleId,
            'reason'    => $reason,
        ]);
    }

    public function getState(string $moduleId): array
    {
        return Cache::get(self::STATE_PREFIX . '.' . $moduleId, [
            'state'  => PluginLifecycleManager::STATE_UNREGISTERED,
            'reason' => null,
        ]);
    }

    private function setState(string $moduleId, string $state, string $reason = ''): void
    {
        Cache::put(self::STATE_PREFIX . '.' . $moduleId, [
            'state'      => $state,
            'reason'     => $reason,
            'updated_at' => now()->toIso8601String(),
        ], self::STATE_TTL);
    }
}
