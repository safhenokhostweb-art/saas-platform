<?php

namespace SaaSPlatform\CoreUtils\SecurityCenter;

use Illuminate\Support\Facades\Cache;

class SecurityPatchPolicyEngine
{
    private const POLICY_CACHE_KEY = 'security:patch_policy';

    public function canInstallWithoutLicense(string $patchId, array $patch): bool
    {
        // Security channel patches NEVER require license
        if ($this->isSecurityChannelPatch($patch)) {
            return true;
        }
        return ! ($patch['requires_license'] ?? false);
    }

    public function isMandatory(array $patch): bool
    {
        if ($patch['mandatory'] ?? false) {
            return true;
        }
        // All security-channel critical patches are mandatory
        return $this->isSecurityChannelPatch($patch) && ($patch['severity'] ?? '') === 'critical';
    }

    public function canRollback(array $patch): bool
    {
        return $patch['rollback_safe'] ?? true;
    }

    public function getInstallPolicy(array $patch): array
    {
        return [
            'can_install_without_license' => $this->canInstallWithoutLicense($patch['id'] ?? '', $patch),
            'is_mandatory'                => $this->isMandatory($patch),
            'can_rollback'                => $this->canRollback($patch),
            'requires_maintenance_window' => $this->requiresMaintenanceWindow($patch),
            'auto_install_eligible'       => $this->isAutoInstallEligible($patch),
            'canary_required'             => $this->isCanaryRequired($patch),
            'pre_validation_required'     => true,
            'post_validation_required'    => true,
        ];
    }

    public function getPolicyMatrix(): array
    {
        return Cache::remember(self::POLICY_CACHE_KEY, 3600, function () {
            return [
                'security_channel' => [
                    'license_required'   => false,
                    'can_disable'        => false,
                    'auto_install'       => true,
                    'mandatory_display'  => true,
                    'bypass_freeze'      => false,
                    'notification_level' => 'critical',
                ],
                'stability_channel' => [
                    'license_required'   => false,
                    'can_disable'        => true,
                    'auto_install'       => false,
                    'mandatory_display'  => false,
                    'bypass_freeze'      => false,
                    'notification_level' => 'info',
                ],
                'feature_channel' => [
                    'license_required'   => true,
                    'can_disable'        => true,
                    'auto_install'       => false,
                    'mandatory_display'  => false,
                    'bypass_freeze'      => false,
                    'notification_level' => 'info',
                ],
            ];
        });
    }

    public function evaluatePatchRisk(array $patch): array
    {
        $risk = 'low';
        $factors = [];

        if ($patch['severity'] === 'critical') {
            $risk = 'high';
            $factors[] = 'Critical severity patch';
        }

        if (! ($patch['rollback_safe'] ?? true)) {
            $risk = 'high';
            $factors[] = 'Rollback not guaranteed safe';
        }

        if (! ($patch['signed'] ?? false)) {
            $risk = 'medium';
            $factors[] = 'Release not cryptographically signed';
        }

        $confidence = $patch['confidence'] ?? 87;
        if ($confidence < 70) {
            $risk = 'high';
            $factors[] = "Low patch confidence ({$confidence}%)";
        }

        return [
            'risk_level'    => $risk,
            'risk_factors'  => $factors,
            'install_safely' => $risk !== 'critical',
        ];
    }

    private function isSecurityChannelPatch(array $patch): bool
    {
        return in_array($patch['channel'] ?? '', ['security', 'critical', 'runtime', 'hotfix', 'tenant-isolation']);
    }

    private function requiresMaintenanceWindow(array $patch): bool
    {
        return in_array($patch['severity'] ?? '', ['critical', 'high']) && ! ($patch['hot_patch'] ?? false);
    }

    private function isAutoInstallEligible(array $patch): bool
    {
        return $this->isSecurityChannelPatch($patch) && ($patch['rollback_safe'] ?? true) && ($patch['confidence'] ?? 0) >= 80;
    }

    private function isCanaryRequired(array $patch): bool
    {
        return ($patch['confidence'] ?? 100) < 90;
    }
}
