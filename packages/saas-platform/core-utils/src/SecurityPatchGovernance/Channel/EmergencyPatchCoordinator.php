<?php

namespace SaaSPlatform\CoreUtils\SecurityPatchGovernance\Channel;

use SaaSPlatform\CoreUtils\SecurityPatchGovernance\Delivery\MandatorySecurityDeliveryService;
use SaaSPlatform\CoreUtils\SecurityPatchGovernance\Telemetry\SecurityPatchTelemetry;

class EmergencyPatchCoordinator
{
    private bool $emergencyModeActive = false;

    public function __construct(
        private SecurityReleaseRegistry        $registry,
        private SecurityPatchChannelManager    $channelManager,
        private MandatorySecurityDeliveryService $deliveryService,
        private SecurityPatchTelemetry         $telemetry
    ) {}

    public function initiateEmergencyRollout(string $patchId, string $reason): array
    {
        $patch = $this->registry->find($patchId);

        if (!$patch) {
            return ['success' => false, 'reason' => 'patch_not_found'];
        }

        $this->emergencyModeActive = true;
        $this->telemetry->recordEmergencyActivation($patchId, $reason);

        $result = $this->deliveryService->forceDeploy($patchId, [
            'channel'              => 'emergency',
            'bypass_license'       => true,
            'bypass_staged_rollout' => true,
            'skip_compatibility_gate' => false,
            'reason'               => $reason,
        ]);

        if ($result['success']) {
            $this->registry->markDeployed($patchId);
        }

        $this->emergencyModeActive = false;
        return $result;
    }

    public function isEmergencyModeActive(): bool
    {
        return $this->emergencyModeActive;
    }

    public function emergencyRolloutStatus(): array
    {
        $critical = $this->registry->critical();
        return [
            'active'           => $this->emergencyModeActive,
            'critical_patches' => count($critical),
            'channel_config'   => $this->channelManager->resolve('emergency'),
            'patches'          => $critical,
        ];
    }

    public function broadcastEmergencyAlert(string $patchId, string $severity, string $message): void
    {
        $this->telemetry->recordEmergencyBroadcast($patchId, $severity, $message);
    }
}
