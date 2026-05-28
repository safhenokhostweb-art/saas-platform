<?php

namespace SaaSPlatform\CoreUtils\SecurityCenter\UpdateChannels;

use Illuminate\Support\Facades\Cache;

class SecurityUpdateChannelPolicy
{
    public function getChannelSummary(): array
    {
        return [
            'security'  => $this->getSecurityChannelSummary(),
            'stability' => $this->getStabilityChannelSummary(),
            'feature'   => $this->getFeatureChannelSummary(),
        ];
    }

    public function getPolicyMatrix(): array
    {
        return [
            'security' => [
                'name'               => 'Security Channel',
                'always_installable' => true,
                'license_required'   => false,
                'mandatory_visibility'=> true,
                'can_be_disabled'    => false,
                'auto_install'       => true,
                'bypass_freeze'      => false,
                'prefixes'           => ['security/', 'critical/', 'runtime/', 'hotfix/', 'tenant-isolation/'],
                'color'              => 'red',
                'icon'               => 'shield-check',
            ],
            'stability' => [
                'name'               => 'Stability Channel',
                'always_installable' => true,
                'license_required'   => false,
                'mandatory_visibility'=> false,
                'can_be_disabled'    => true,
                'auto_install'       => false,
                'bypass_freeze'      => false,
                'prefixes'           => ['fix/', 'bugfix/', 'perf/', 'compat/', 'stability/'],
                'color'              => 'green',
                'icon'               => 'check-circle',
            ],
            'feature' => [
                'name'               => 'Feature Channel',
                'always_installable' => false,
                'license_required'   => true,
                'mandatory_visibility'=> false,
                'can_be_disabled'    => true,
                'auto_install'       => false,
                'bypass_freeze'      => false,
                'prefixes'           => ['feature/', 'premium/', 'enterprise/', 'ai/', 'module/'],
                'color'              => 'purple',
                'icon'               => 'sparkles',
            ],
        ];
    }

    public function isChannelEnabled(string $channel): bool
    {
        if ($channel === 'security') {
            return true; // ALWAYS enabled
        }
        return (bool) Cache::get("security:channel_enabled:{$channel}", true);
    }

    public function setChannelEnabled(string $channel, bool $enabled): void
    {
        if ($channel === 'security') {
            return; // Cannot disable security channel
        }
        Cache::put("security:channel_enabled:{$channel}", $enabled, 86400 * 365);
    }

    public function isLicenseRequiredForChannel(string $channel): bool
    {
        return $channel === 'feature';
    }

    private function getSecurityChannelSummary(): array
    {
        return [
            'enabled'        => true,
            'locked'         => true,
            'patches_pending'=> Cache::get('security:channel_pending_count:security', 0),
            'description'    => 'Mandatory security patches, hotfixes, and tenant isolation fixes',
        ];
    }

    private function getStabilityChannelSummary(): array
    {
        return [
            'enabled'        => $this->isChannelEnabled('stability'),
            'locked'         => false,
            'patches_pending'=> Cache::get('security:channel_pending_count:stability', 0),
            'description'    => 'Bug fixes, performance improvements, and compatibility updates',
        ];
    }

    private function getFeatureChannelSummary(): array
    {
        return [
            'enabled'        => $this->isChannelEnabled('feature'),
            'locked'         => false,
            'patches_pending'=> Cache::get('security:channel_pending_count:feature', 0),
            'license_required'=> true,
            'description'    => 'Premium features, AI upgrades, and enterprise capabilities',
        ];
    }
}
