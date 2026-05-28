<?php

namespace SaaSPlatform\CoreUtils\SecurityCenter;

class SecurityUpdateChannelResolver
{
    private const SECURITY_PREFIXES  = ['security/', 'critical/', 'runtime/', 'hotfix/', 'tenant-isolation/'];
    private const STABILITY_PREFIXES = ['fix/', 'bugfix/', 'perf/', 'compat/', 'stability/'];
    private const FEATURE_PREFIXES   = ['feature/', 'premium/', 'enterprise/', 'ai/', 'module/'];

    public const CHANNEL_SECURITY  = 'security';
    public const CHANNEL_STABILITY = 'stability';
    public const CHANNEL_FEATURE   = 'feature';

    public function resolve(string $patchIdOrTag): string
    {
        foreach (self::SECURITY_PREFIXES as $prefix) {
            if (str_starts_with($patchIdOrTag, $prefix)) {
                return self::CHANNEL_SECURITY;
            }
        }

        foreach (self::FEATURE_PREFIXES as $prefix) {
            if (str_starts_with($patchIdOrTag, $prefix)) {
                return self::CHANNEL_FEATURE;
            }
        }

        foreach (self::STABILITY_PREFIXES as $prefix) {
            if (str_starts_with($patchIdOrTag, $prefix)) {
                return self::CHANNEL_STABILITY;
            }
        }

        return self::CHANNEL_STABILITY;
    }

    public function resolveFromPatch(array $patch): string
    {
        if (! empty($patch['channel'])) {
            return $this->normalizeChannel($patch['channel']);
        }

        $tag = $patch['tag'] ?? $patch['id'] ?? '';
        $channel = $this->resolve($tag);

        // Severity override: critical severity always goes to security channel
        if (($patch['severity'] ?? '') === 'critical') {
            return self::CHANNEL_SECURITY;
        }

        // Feature-flagged patches always go to feature channel
        if ($patch['requires_license'] ?? false) {
            return self::CHANNEL_FEATURE;
        }

        return $channel;
    }

    public function getChannelProperties(string $channel): array
    {
        return match($channel) {
            self::CHANNEL_SECURITY => [
                'name'               => 'Security Channel',
                'always_available'   => true,
                'license_required'   => false,
                'can_be_disabled'    => false,
                'auto_install'       => true,
                'notification_level' => 'critical',
                'icon'               => 'shield',
                'color'              => 'red',
            ],
            self::CHANNEL_STABILITY => [
                'name'               => 'Stability Channel',
                'always_available'   => true,
                'license_required'   => false,
                'can_be_disabled'    => true,
                'auto_install'       => false,
                'notification_level' => 'info',
                'icon'               => 'check-circle',
                'color'              => 'green',
            ],
            self::CHANNEL_FEATURE => [
                'name'               => 'Feature Channel',
                'always_available'   => false,
                'license_required'   => true,
                'can_be_disabled'    => true,
                'auto_install'       => false,
                'notification_level' => 'info',
                'icon'               => 'sparkles',
                'color'              => 'purple',
            ],
            default => [
                'name'               => 'Unknown Channel',
                'always_available'   => false,
                'license_required'   => true,
                'can_be_disabled'    => true,
                'auto_install'       => false,
                'notification_level' => 'info',
                'icon'               => 'question',
                'color'              => 'gray',
            ],
        };
    }

    public function isInstallableWithoutLicense(string $channel): bool
    {
        return in_array($channel, [self::CHANNEL_SECURITY, self::CHANNEL_STABILITY], true);
    }

    private function normalizeChannel(string $channel): string
    {
        return match(strtolower($channel)) {
            'security', 'critical', 'hotfix', 'runtime', 'tenant-isolation' => self::CHANNEL_SECURITY,
            'stability', 'bugfix', 'fix', 'perf', 'compat'                  => self::CHANNEL_STABILITY,
            'feature', 'premium', 'enterprise', 'ai'                         => self::CHANNEL_FEATURE,
            default => self::CHANNEL_STABILITY,
        };
    }
}
