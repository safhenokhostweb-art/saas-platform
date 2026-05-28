<?php

namespace SaaSPlatform\CoreUtils\SecurityCenter\UpdateChannels;

use Illuminate\Support\Facades\Cache;

class FeatureEntitlementUpdateGate
{
    public function canInstall(string $tenantId, array $patch): bool
    {
        $channel = $patch['channel'] ?? 'stability';

        // Security and stability channels never require license
        if (in_array($channel, ['security', 'stability', 'critical', 'hotfix', 'runtime', 'tenant-isolation'])) {
            return true;
        }

        // Feature channel requires valid license
        if ($channel === 'feature') {
            return $this->hasFeaturesLicense($tenantId);
        }

        // Individual license override per patch
        if ($patch['requires_license'] ?? false) {
            return $this->hasActiveLicense($tenantId);
        }

        return true;
    }

    public function getBlockedReason(string $tenantId, array $patch): ?string
    {
        if ($this->canInstall($tenantId, $patch)) {
            return null;
        }

        $channel = $patch['channel'] ?? 'stability';

        if ($channel === 'feature') {
            return 'Active license or subscription required to install feature updates';
        }

        return 'License required for this update';
    }

    public function hasActiveLicense(string $tenantId): bool
    {
        return (bool) Cache::get("license:active:{$tenantId}", false);
    }

    public function hasFeaturesLicense(string $tenantId): bool
    {
        return (bool) Cache::get("license:features:{$tenantId}", false);
    }

    public function setLicense(string $tenantId, bool $active, array $features = []): void
    {
        Cache::put("license:active:{$tenantId}", $active, 86400 * 365);
        Cache::put("license:features:{$tenantId}", $active && in_array('features', $features), 86400 * 365);
    }

    public function getEntitlementSummary(string $tenantId): array
    {
        return [
            'tenant_id'         => $tenantId,
            'active_license'    => $this->hasActiveLicense($tenantId),
            'features_license'  => $this->hasFeaturesLicense($tenantId),
            'security_updates'  => true, // always
            'stability_updates' => true, // always
            'feature_updates'   => $this->hasFeaturesLicense($tenantId),
        ];
    }
}
