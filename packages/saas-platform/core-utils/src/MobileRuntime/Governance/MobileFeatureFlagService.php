<?php

namespace SaaSPlatform\CoreUtils\MobileRuntime\Governance;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;

class MobileFeatureFlagService
{
    public const FLAG_CHAT          = 'chat';
    public const FLAG_TICKETS       = 'tickets';
    public const FLAG_NOTIFICATIONS = 'notifications';
    public const FLAG_PRESENCE      = 'presence';
    public const FLAG_REALTIME      = 'realtime';
    public const FLAG_OTA           = 'ota';
    public const FLAG_VOICE         = 'voice';
    public const FLAG_FILE_UPLOAD   = 'file_upload';
    public const FLAG_AI_ASSISTANT  = 'ai_assistant';

    private const PREFIX = 'mobile:flags:';
    private const TTL    = 3600;

    private array $defaults = [
        self::FLAG_CHAT          => true,
        self::FLAG_TICKETS       => true,
        self::FLAG_NOTIFICATIONS => true,
        self::FLAG_PRESENCE      => true,
        self::FLAG_REALTIME      => true,
        self::FLAG_OTA           => true,
        self::FLAG_VOICE         => false,
        self::FLAG_FILE_UPLOAD   => true,
        self::FLAG_AI_ASSISTANT  => false,
    ];

    public function isEnabled(string $flag, string $tenantId, string $role = 'any'): bool
    {
        $flags = $this->getFlags($tenantId);
        $key   = $role !== 'any' ? "{$flag}:{$role}" : $flag;
        return (bool) ($flags[$key] ?? $flags[$flag] ?? $this->defaults[$flag] ?? false);
    }

    public function enable(string $flag, string $tenantId, ?string $role = null): void
    {
        $flags = $this->getFlags($tenantId);
        $key   = $role !== null ? "{$flag}:{$role}" : $flag;
        $flags[$key] = true;
        Cache::put(self::PREFIX . $tenantId, $flags, self::TTL);
    }

    public function disable(string $flag, string $tenantId, ?string $role = null): void
    {
        $flags = $this->getFlags($tenantId);
        $key   = $role !== null ? "{$flag}:{$role}" : $flag;
        $flags[$key] = false;
        Cache::put(self::PREFIX . $tenantId, $flags, self::TTL);
    }

    public function getFlags(string $tenantId): array
    {
        $stored  = Cache::get(self::PREFIX . $tenantId, []);
        $global  = $this->getGlobalFlags();
        return array_merge($this->defaults, $global, $stored);
    }

    public function setFlags(string $tenantId, array $flags): void
    {
        Cache::put(self::PREFIX . $tenantId, $flags, self::TTL);
    }

    public function getGlobalFlags(): array
    {
        return Config::get('mobile.feature_flags', []);
    }

    public function resetToDefaults(string $tenantId): void
    {
        Cache::forget(self::PREFIX . $tenantId);
    }

    public function getAllDefaults(): array
    {
        return $this->defaults;
    }
}
