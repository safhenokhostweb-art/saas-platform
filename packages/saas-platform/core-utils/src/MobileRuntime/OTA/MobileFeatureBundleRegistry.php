<?php

namespace SaaSPlatform\CoreUtils\MobileRuntime\OTA;

use Illuminate\Support\Facades\Cache;

class MobileFeatureBundleRegistry
{
    private const PREFIX = 'mobile:bundles:';
    private const TTL    = 3600;

    /** @var array<string, array> */
    private array $bundles = [];

    public function register(string $bundleId, array $definition): void
    {
        $this->bundles[$bundleId] = array_merge($definition, ['id' => $bundleId]);
        $global = Cache::get(self::PREFIX . 'global', []);
        $global[$bundleId] = $this->bundles[$bundleId];
        Cache::put(self::PREFIX . 'global', $global, self::TTL);
    }

    public function getBundle(string $bundleId): ?array
    {
        return $this->bundles[$bundleId]
            ?? Cache::get(self::PREFIX . 'global', [])[$bundleId]
            ?? null;
    }

    public function getBundlesForTenant(string $tenantId): array
    {
        $global  = Cache::get(self::PREFIX . 'global', []);
        $granted = Cache::get(self::PREFIX . "tenant:{$tenantId}", []);
        $result  = [];
        foreach ($global as $bundleId => $bundle) {
            $isActive = in_array($bundleId, $granted, true);
            if ($isActive) {
                $result[] = $bundle;
            }
        }
        return $result;
    }

    public function getBundlesForChannel(string $channel): array
    {
        $global = Cache::get(self::PREFIX . 'global', []);
        return array_values(array_filter($global, fn ($b) => ($b['channel'] ?? 'stable') === $channel));
    }

    public function activateBundle(string $bundleId, string $tenantId): void
    {
        $granted = Cache::get(self::PREFIX . "tenant:{$tenantId}", []);
        if (! in_array($bundleId, $granted, true)) {
            $granted[] = $bundleId;
            Cache::put(self::PREFIX . "tenant:{$tenantId}", $granted, self::TTL);
        }
    }

    public function deactivateBundle(string $bundleId, string $tenantId): void
    {
        $granted = Cache::get(self::PREFIX . "tenant:{$tenantId}", []);
        Cache::put(self::PREFIX . "tenant:{$tenantId}", array_values(array_diff($granted, [$bundleId])), self::TTL);
    }

    public function isActive(string $bundleId, string $tenantId): bool
    {
        $granted = Cache::get(self::PREFIX . "tenant:{$tenantId}", []);
        return in_array($bundleId, $granted, true);
    }

    public function getAllBundles(): array
    {
        return Cache::get(self::PREFIX . 'global', []);
    }
}
