<?php

namespace SaaSPlatform\CoreUtils\PluginGovernance\Trust;

use Illuminate\Support\Facades\Cache;

class ModuleTrustRegistry
{
    public const LEVEL_UNTRUSTED  = 'untrusted';
    public const LEVEL_SANDBOXED  = 'sandboxed';
    public const LEVEL_TRUSTED    = 'trusted';
    public const LEVEL_CORE       = 'core';

    private const TRUST_PREFIX = 'plugin.trust.module';
    private const TTL          = 86400;

    public function setTrustLevel(string $moduleId, string $level, string $reason = ''): void
    {
        Cache::put(self::TRUST_PREFIX . '.' . $moduleId, [
            'module_id'  => $moduleId,
            'level'      => $level,
            'reason'     => $reason,
            'updated_at' => now()->toIso8601String(),
        ], self::TTL);
    }

    public function getTrustLevel(string $moduleId): string
    {
        $record = Cache::get(self::TRUST_PREFIX . '.' . $moduleId);
        return $record['level'] ?? self::LEVEL_UNTRUSTED;
    }

    public function isTrusted(string $moduleId): bool
    {
        return in_array($this->getTrustLevel($moduleId), [self::LEVEL_TRUSTED, self::LEVEL_CORE], true);
    }

    public function isSandboxed(string $moduleId): bool
    {
        return $this->getTrustLevel($moduleId) === self::LEVEL_SANDBOXED;
    }

    public function isCore(string $moduleId): bool
    {
        return $this->getTrustLevel($moduleId) === self::LEVEL_CORE;
    }

    public function canExecute(string $moduleId): bool
    {
        return $this->getTrustLevel($moduleId) !== self::LEVEL_UNTRUSTED;
    }

    public function getRecord(string $moduleId): ?array
    {
        return Cache::get(self::TRUST_PREFIX . '.' . $moduleId);
    }
}
