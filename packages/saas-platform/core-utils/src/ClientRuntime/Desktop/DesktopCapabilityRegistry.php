<?php

namespace SaaSPlatform\CoreUtils\ClientRuntime\Desktop;

use Illuminate\Support\Facades\Cache;
use SaaSPlatform\CoreUtils\ClientRuntime\Enums\ClientPlatform;

class DesktopCapabilityRegistry
{
    private const PREFIX = 'client:desktop:caps:';
    private const TTL    = 3600;

    /** @var array<string, array> */
    private array $overrides = [];

    private array $matrix = [
        'windows' => [
            'websocket'            => true,
            'local_notifications'  => true,
            'file_attachments'     => true,
            'presence'             => true,
            'realtime'             => true,
            'firebase_push'        => false,
            'ota'                  => true,
            'sync_recovery'        => true,
        ],
        'linux' => [
            'websocket'            => true,
            'local_notifications'  => true,
            'file_attachments'     => true,
            'presence'             => true,
            'realtime'             => true,
            'firebase_push'        => false,
            'ota'                  => true,
            'sync_recovery'        => true,
        ],
        'macos' => [
            'websocket'            => true,
            'local_notifications'  => true,
            'file_attachments'     => true,
            'presence'             => true,
            'realtime'             => true,
            'firebase_push'        => false,
            'ota'                  => true,
            'sync_recovery'        => true,
        ],
    ];

    public function getCapabilities(string $platform): array
    {
        $platform = strtolower($platform);
        $cacheKey = self::PREFIX . $platform;

        return Cache::remember($cacheKey, self::TTL, function () use ($platform) {
            $base      = $this->matrix[$platform] ?? [];
            $overrides = $this->overrides[$platform] ?? [];
            return array_merge($base, $overrides);
        });
    }

    public function hasCapability(string $platform, string $capability): bool
    {
        return (bool) ($this->getCapabilities($platform)[$capability] ?? false);
    }

    public function isDesktopPlatform(string $platform): bool
    {
        try {
            return ClientPlatform::fromString($platform)->isDesktop();
        } catch (\Throwable) {
            return false;
        }
    }

    public function override(string $platform, string $capability, bool $value): void
    {
        $platform = strtolower($platform);
        $this->overrides[$platform][$capability] = $value;
        Cache::forget(self::PREFIX . $platform);
    }

    public function getSupportedDesktopPlatforms(): array
    {
        return array_keys($this->matrix);
    }

    public function getCapabilityMatrix(): array
    {
        $result = [];
        foreach (array_keys($this->matrix) as $platform) {
            $result[$platform] = $this->getCapabilities($platform);
        }
        return $result;
    }
}
