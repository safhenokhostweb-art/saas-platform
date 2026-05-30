<?php

namespace SaaSPlatform\CoreUtils\MobileRuntime\Versioning;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;

class MobileVersionPolicyManager
{
    public const CHANNEL_STABLE = 'stable';
    public const CHANNEL_BETA   = 'beta';
    public const CHANNEL_CANARY = 'canary';

    private const PREFIX = 'mobile:versions:';
    private const TTL    = 3600;

    private array $channelDefaults = [
        self::CHANNEL_STABLE => [
            'min_version'         => '1.0.0',
            'latest_version'      => '1.0.0',
            'mandatory_after'     => null,
            'deprecated_versions' => [],
            'force_update_after'  => null,
            'delay_days'          => 7,
        ],
        self::CHANNEL_BETA => [
            'min_version'         => '1.0.0',
            'latest_version'      => '1.0.0',
            'mandatory_after'     => null,
            'deprecated_versions' => [],
            'force_update_after'  => null,
            'delay_days'          => 1,
        ],
        self::CHANNEL_CANARY => [
            'min_version'         => '1.0.0',
            'latest_version'      => '1.0.0',
            'mandatory_after'     => null,
            'deprecated_versions' => [],
            'force_update_after'  => null,
            'delay_days'          => 0,
        ],
    ];

    public function setPolicy(string $tenantId, string $channel, array $policy): void
    {
        $defaults = $this->getDefaultPolicy($channel);
        Cache::put(self::PREFIX . "policy:{$tenantId}:{$channel}", array_merge($defaults, $policy), self::TTL);
    }

    public function getPolicy(string $tenantId, string $channel): array
    {
        return Cache::get(self::PREFIX . "policy:{$tenantId}:{$channel}")
            ?? $this->getDefaultPolicy($channel);
    }

    public function getDefaultPolicy(string $channel): array
    {
        return $this->channelDefaults[$channel] ?? $this->channelDefaults[self::CHANNEL_STABLE];
    }

    public function requiresUpdate(string $currentVersion, string $tenantId, string $channel): bool
    {
        $policy = $this->getPolicy($tenantId, $channel);
        return $this->compareVersions($currentVersion, $policy['latest_version']) < 0;
    }

    public function isMandatory(string $currentVersion, string $tenantId): bool
    {
        $policy     = $this->getPolicy($tenantId, self::CHANNEL_STABLE);
        $minVersion = $policy['min_version'] ?? '1.0.0';
        return $this->compareVersions($currentVersion, $minVersion) < 0;
    }

    public function getLatestVersion(string $channel): string
    {
        return Config::get("mobile.versions.{$channel}", '1.0.0');
    }

    public function getAllPolicies(string $tenantId): array
    {
        return [
            self::CHANNEL_STABLE => $this->getPolicy($tenantId, self::CHANNEL_STABLE),
            self::CHANNEL_BETA   => $this->getPolicy($tenantId, self::CHANNEL_BETA),
            self::CHANNEL_CANARY => $this->getPolicy($tenantId, self::CHANNEL_CANARY),
        ];
    }

    private function compareVersions(string $a, string $b): int
    {
        $va = array_map('intval', explode('.', ltrim($a, 'v')));
        $vb = array_map('intval', explode('.', ltrim($b, 'v')));
        for ($i = 0; $i < 3; $i++) {
            $diff = ($va[$i] ?? 0) - ($vb[$i] ?? 0);
            if ($diff !== 0) return $diff > 0 ? 1 : -1;
        }
        return 0;
    }
}
