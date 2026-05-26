<?php

namespace SaaSPlatform\CoreUtils\PluginGovernance\Trust;

use Illuminate\Support\Facades\Cache;

class TrustedPublisherRegistry
{
    private const PUBLISHERS_KEY = 'plugin.trust.publishers';
    private const TTL            = 86400;

    public function trust(string $publisherId, string $name, string $addedBy = 'system'): void
    {
        $publishers = $this->all();
        $publishers[$publisherId] = [
            'id'       => $publisherId,
            'name'     => $name,
            'added_by' => $addedBy,
            'added_at' => now()->toIso8601String(),
        ];
        Cache::put(self::PUBLISHERS_KEY, $publishers, self::TTL);
    }

    public function revoke(string $publisherId): void
    {
        $publishers = $this->all();
        unset($publishers[$publisherId]);
        Cache::put(self::PUBLISHERS_KEY, $publishers, self::TTL);
    }

    public function isTrusted(string $publisherId): bool
    {
        return isset($this->all()[$publisherId]);
    }

    public function all(): array
    {
        return Cache::get(self::PUBLISHERS_KEY, []);
    }

    public function count(): int
    {
        return count($this->all());
    }
}
