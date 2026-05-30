<?php

namespace SaaSPlatform\CoreUtils\MobileRuntime\Versioning;

use Illuminate\Support\Facades\Cache;

class MobileUpdateChannelManager
{
    private const PREFIX = 'mobile:channels:';
    private const TTL    = 86400;

    private array $channelConfigs = [
        'stable' => [
            'name'          => 'Stable',
            'description'   => 'Production-ready releases with 7-day delay',
            'delay_days'    => 7,
            'rollout_pct'   => 100,
            'auto_promote'  => false,
        ],
        'beta' => [
            'name'          => 'Beta',
            'description'   => 'Pre-release testing with 1-day delay',
            'delay_days'    => 1,
            'rollout_pct'   => 20,
            'auto_promote'  => true,
        ],
        'canary' => [
            'name'          => 'Canary',
            'description'   => 'Bleeding edge — immediate release',
            'delay_days'    => 0,
            'rollout_pct'   => 5,
            'auto_promote'  => true,
        ],
    ];

    public function assignChannel(string $tenantId, string $channel): void
    {
        $this->validateChannel($channel);
        Cache::put(self::PREFIX . "tenant:{$tenantId}", $channel, self::TTL);
        $this->addToChannelIndex($channel, $tenantId);
    }

    public function getChannel(string $tenantId): string
    {
        return Cache::get(self::PREFIX . "tenant:{$tenantId}", 'stable');
    }

    public function getAllChannels(): array
    {
        return array_keys($this->channelConfigs);
    }

    public function getTenantsOnChannel(string $channel): array
    {
        return Cache::get(self::PREFIX . "index:{$channel}", []);
    }

    public function setChannelConfig(string $channel, array $config): void
    {
        $this->channelConfigs[$channel] = array_merge(
            $this->channelConfigs[$channel] ?? [],
            $config
        );
        Cache::put(self::PREFIX . "config:{$channel}", $this->channelConfigs[$channel], self::TTL);
    }

    public function getChannelConfig(string $channel): array
    {
        return Cache::get(self::PREFIX . "config:{$channel}")
            ?? $this->channelConfigs[$channel]
            ?? $this->channelConfigs['stable'];
    }

    public function migrateChannel(string $tenantId, string $from, string $to): void
    {
        $this->validateChannel($to);
        Cache::put(self::PREFIX . "tenant:{$tenantId}", $to, self::TTL);
        $this->removeFromChannelIndex($from, $tenantId);
        $this->addToChannelIndex($to, $tenantId);
    }

    public function getChannelStats(): array
    {
        $stats = [];
        foreach ($this->getAllChannels() as $channel) {
            $tenants      = $this->getTenantsOnChannel($channel);
            $stats[$channel] = [
                'tenant_count' => count($tenants),
                'config'       => $this->getChannelConfig($channel),
            ];
        }
        return $stats;
    }

    private function validateChannel(string $channel): void
    {
        if (! array_key_exists($channel, $this->channelConfigs)) {
            throw new \InvalidArgumentException("Unknown channel: {$channel}. Valid: " . implode(', ', array_keys($this->channelConfigs)));
        }
    }

    private function addToChannelIndex(string $channel, string $tenantId): void
    {
        $index = Cache::get(self::PREFIX . "index:{$channel}", []);
        if (! in_array($tenantId, $index, true)) {
            $index[] = $tenantId;
            Cache::put(self::PREFIX . "index:{$channel}", $index, self::TTL);
        }
    }

    private function removeFromChannelIndex(string $channel, string $tenantId): void
    {
        $index = Cache::get(self::PREFIX . "index:{$channel}", []);
        Cache::put(self::PREFIX . "index:{$channel}", array_values(array_diff($index, [$tenantId])), self::TTL);
    }
}
