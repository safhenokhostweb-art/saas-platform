<?php

namespace SaaSPlatform\CoreUtils\CacheGovernance;

class TenantCacheNamespaceManager
{
    private const NAMESPACE_SEP = ':';

    public function namespace(int $tenantId, string $prefix = ''): string
    {
        $ns = 'tenant.' . $tenantId;
        return $prefix ? $ns . self::NAMESPACE_SEP . $prefix : $ns;
    }

    public function key(int $tenantId, string $key): string
    {
        return $this->namespace($tenantId) . self::NAMESPACE_SEP . $key;
    }

    public function isOwnedBy(string $cacheKey, int $tenantId): bool
    {
        return str_starts_with($cacheKey, $this->namespace($tenantId) . self::NAMESPACE_SEP);
    }

    public function extractTenantId(string $cacheKey): ?int
    {
        if (!str_starts_with($cacheKey, 'tenant.')) {
            return null;
        }
        $parts = explode('.', $cacheKey, 3);
        return isset($parts[1]) && is_numeric($parts[1]) ? (int) $parts[1] : null;
    }

    public function globPattern(int $tenantId): string
    {
        return $this->namespace($tenantId) . self::NAMESPACE_SEP . '*';
    }

    public function systemKey(string $key): string
    {
        return 'system' . self::NAMESPACE_SEP . $key;
    }

    public function governanceKey(string $key): string
    {
        return 'governance' . self::NAMESPACE_SEP . $key;
    }
}
