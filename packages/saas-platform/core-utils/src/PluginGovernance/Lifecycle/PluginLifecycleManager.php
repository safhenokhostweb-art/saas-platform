<?php

namespace SaaSPlatform\CoreUtils\PluginGovernance\Lifecycle;

use SaaSPlatform\CoreUtils\Telemetry\TelemetryManager;

class PluginLifecycleManager
{
    public const STATE_UNREGISTERED = 'unregistered';
    public const STATE_INACTIVE     = 'inactive';
    public const STATE_ACTIVE       = 'active';
    public const STATE_SUSPENDED    = 'suspended';
    public const STATE_BLOCKED      = 'blocked';

    public function __construct(
        private PluginActivationCoordinator $activationCoordinator,
        private PluginHealthMonitor         $healthMonitor,
        private PluginUpgradeValidator      $upgradeValidator,
        private PluginRollbackCoordinator   $rollbackCoordinator,
        private TelemetryManager            $telemetry
    ) {}

    public function register(string $moduleId, string $version, string $modulePath = ''): array
    {
        $result = $this->activationCoordinator->activate($moduleId, $modulePath);

        if ($result['activated']) {
            $this->rollbackCoordinator->saveSnapshot($moduleId, $version);
            $this->healthMonitor->recordSuccess($moduleId);
        }

        return $result;
    }

    public function upgrade(string $moduleId, string $fromVersion, string $toVersion, string $newPath = ''): array
    {
        $validation = $this->upgradeValidator->validateUpgrade($moduleId, $fromVersion, $toVersion, $newPath);

        if (!$validation['allowed']) {
            return ['upgraded' => false, 'reasons' => $validation['reasons']];
        }

        $this->rollbackCoordinator->saveSnapshot($moduleId, $fromVersion);
        $this->activationCoordinator->activate($moduleId, $newPath);

        return ['upgraded' => true, 'to_version' => $toVersion];
    }

    public function deactivate(string $moduleId, string $reason = 'manual'): void
    {
        $this->activationCoordinator->deactivate($moduleId, $reason);
    }

    public function block(string $moduleId, string $reason): void
    {
        $this->activationCoordinator->block($moduleId, $reason);
    }

    public function rollback(string $moduleId, string $reason = 'manual'): array
    {
        return $this->rollbackCoordinator->rollback($moduleId, $reason);
    }

    public function recordSuccess(string $moduleId): void
    {
        $this->healthMonitor->recordSuccess($moduleId);
    }

    public function recordFailure(string $moduleId, string $error): void
    {
        $this->healthMonitor->recordFailure($moduleId, $error);

        if ($this->healthMonitor->isCritical($moduleId)) {
            $this->activationCoordinator->deactivate($moduleId, 'health_critical');
        }
    }

    public function status(string $moduleId): array
    {
        return array_merge(
            $this->activationCoordinator->getState($moduleId),
            ['health' => $this->healthMonitor->getHealth($moduleId)]
        );
    }

    public function isActive(string $moduleId): bool
    {
        $state = $this->activationCoordinator->getState($moduleId);
        return ($state['state'] ?? '') === self::STATE_ACTIVE;
    }
}
