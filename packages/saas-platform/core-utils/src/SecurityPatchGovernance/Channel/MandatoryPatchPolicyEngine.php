<?php

namespace SaaSPlatform\CoreUtils\SecurityPatchGovernance\Channel;

use SaaSPlatform\CoreUtils\SecurityPatchGovernance\Delivery\PatchEligibilityResolver;

class MandatoryPatchPolicyEngine
{
    private array $policies = [
        'critical_always_mandatory'  => true,
        'emergency_bypasses_license' => true,
        'security_channel_independent_of_license' => true,
        'max_deferral_days_critical' => 0,
        'max_deferral_days_high'     => 7,
        'max_deferral_days_medium'   => 30,
        'max_deferral_days_low'      => 90,
    ];

    public function __construct(
        private SecurityReleaseRegistry $registry,
        private PatchEligibilityResolver $eligibilityResolver
    ) {}

    public function evaluate(string $patchId, int $siteId): array
    {
        $patch = $this->registry->find($patchId);
        if (!$patch) {
            return ['mandatory' => false, 'reason' => 'patch_not_found'];
        }

        $mandatory = $this->isMandatory((array) $patch);
        $bypassLicense = $this->shouldBypassLicense((array) $patch);

        return [
            'patch_id'       => $patchId,
            'site_id'        => $siteId,
            'mandatory'      => $mandatory,
            'bypass_license' => $bypassLicense,
            'severity'       => $patch->severity,
            'channel'        => $patch->channel,
            'max_deferral'   => $this->maxDeferralDays($patch->severity),
            'reason'         => $this->reason((array) $patch, $mandatory),
            'evaluated_at'   => now()->toIso8601String(),
        ];
    }

    public function isMandatory(array $patch): bool
    {
        if ($patch['mandatory'] ?? false) {
            return true;
        }
        if (($patch['severity'] ?? '') === 'critical' && $this->policies['critical_always_mandatory']) {
            return true;
        }
        if (($patch['channel'] ?? '') === 'emergency') {
            return true;
        }
        return false;
    }

    public function shouldBypassLicense(array $patch): bool
    {
        if ($patch['license_independent'] ?? true) {
            return true;
        }
        if (in_array($patch['channel'] ?? '', ['security', 'emergency'], true)) {
            return $this->policies['security_channel_independent_of_license'];
        }
        return false;
    }

    public function maxDeferralDays(string $severity): int
    {
        return match($severity) {
            'critical' => $this->policies['max_deferral_days_critical'],
            'high'     => $this->policies['max_deferral_days_high'],
            'medium'   => $this->policies['max_deferral_days_medium'],
            default    => $this->policies['max_deferral_days_low'],
        };
    }

    public function allPolicies(): array
    {
        return $this->policies;
    }

    public function overridePolicy(string $key, mixed $value): void
    {
        $this->policies[$key] = $value;
    }

    private function reason(array $patch, bool $mandatory): string
    {
        if ($patch['mandatory'] ?? false) {
            return 'explicitly_marked_mandatory';
        }
        if (($patch['severity'] ?? '') === 'critical') {
            return 'critical_severity_mandatory_policy';
        }
        if (($patch['channel'] ?? '') === 'emergency') {
            return 'emergency_channel_mandatory_policy';
        }
        return $mandatory ? 'policy_mandatory' : 'optional';
    }
}
