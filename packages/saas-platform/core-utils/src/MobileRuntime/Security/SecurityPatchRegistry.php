<?php

namespace SaaSPlatform\CoreUtils\MobileRuntime\Security;

use Illuminate\Support\Facades\Cache;

class SecurityPatchRegistry
{
    private const CACHE_KEY = 'mobile:security:platform_patch_registry';
    private const TTL       = 86400;

    /**
     * Bootstrap patches representing M1.0 platform security policies.
     * These document real, already-implemented security controls.
     */
    private const BOOTSTRAP_PATCHES = [
        'PLAT-M1-001' => [
            'id'          => 'PLAT-M1-001',
            'title'       => 'HMAC-SHA256 OTA Manifest Signing',
            'severity'    => 'high',
            'type'        => 'security',
            'description' => 'Enforces HMAC-SHA256 signing on all OTA manifests. Prevents unauthorized manifest injection.',
            'implemented' => true,
            'applies_to'  => 'all',
        ],
        'PLAT-M1-002' => [
            'id'          => 'PLAT-M1-002',
            'title'       => 'Sanctum Mobile API Authentication',
            'severity'    => 'high',
            'type'        => 'security',
            'description' => 'Enforces token-based authentication on all mobile API endpoints via Laravel Sanctum.',
            'implemented' => true,
            'applies_to'  => 'all',
        ],
        'PLAT-M1-003' => [
            'id'          => 'PLAT-M1-003',
            'title'       => 'Device Security Baseline Validation',
            'severity'    => 'normal',
            'type'        => 'security',
            'description' => 'Enforces minimum OS version (iOS 15, Android 10) and jailbreak/root detection on device registration.',
            'implemented' => true,
            'applies_to'  => 'all',
        ],
        'PLAT-M1-004' => [
            'id'          => 'PLAT-M1-004',
            'title'       => 'WebSocket Channel HMAC Authorization',
            'severity'    => 'normal',
            'type'        => 'security',
            'description' => 'Enforces HMAC token authorization (300s TTL) on all Reverb WebSocket channel connections.',
            'implemented' => true,
            'applies_to'  => 'all',
        ],
        'PLAT-M1-005' => [
            'id'          => 'PLAT-M1-005',
            'title'       => 'FCM Transport Isolation Policy',
            'severity'    => 'normal',
            'type'        => 'governance',
            'description' => 'Enforces that FCM is used as transport carrier only. All push routing, priority, and policy decisions remain on platform.',
            'implemented' => true,
            'applies_to'  => 'all',
        ],
    ];

    public function boot(): void
    {
        if (Cache::has(self::CACHE_KEY)) {
            return;
        }
        $this->seed();
    }

    public function seed(): void
    {
        $existing = Cache::get(self::CACHE_KEY, []);
        foreach (self::BOOTSTRAP_PATCHES as $id => $patch) {
            $existing[$id] = array_merge($patch, [
                'registered_at' => now()->toIso8601String(),
                'source'        => 'platform_bootstrap',
            ]);
        }
        Cache::put(self::CACHE_KEY, $existing, self::TTL);
    }

    public function register(string $id, array $patch): void
    {
        $registry       = Cache::get(self::CACHE_KEY, []);
        $registry[$id]  = array_merge($patch, [
            'id'            => $id,
            'registered_at' => now()->toIso8601String(),
            'source'        => 'runtime',
        ]);
        Cache::put(self::CACHE_KEY, $registry, self::TTL);
    }

    public function get(string $id): ?array
    {
        return Cache::get(self::CACHE_KEY, [])[$id] ?? null;
    }

    public function all(): array
    {
        return Cache::get(self::CACHE_KEY, []);
    }

    public function getCritical(): array
    {
        return array_filter($this->all(), fn ($p) => ($p['severity'] ?? 'normal') === 'critical');
    }

    public function getByType(string $type): array
    {
        return array_values(array_filter($this->all(), fn ($p) => ($p['type'] ?? 'security') === $type));
    }

    public function count(): int
    {
        return count($this->all());
    }

    public function isBootstrapped(): bool
    {
        return $this->count() >= count(self::BOOTSTRAP_PATCHES);
    }
}
