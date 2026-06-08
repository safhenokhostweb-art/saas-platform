<?php

namespace App\Services\Infrastructure;

use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Cache;

/**
 * Central cache abstraction for the SaaS platform (CACHE-1).
 *
 * Provides:
 *  - Named store access (Cache::store('users'), 'customers', 'themes', 'tickets', 'modules', 'rate_limit')
 *  - Tenant-isolated key construction via key($name, $siteId)
 *  - Per-store flush — never flushes the entire Redis instance
 *
 * Redis DB map (see config/database.php):
 *   DB 0 — default (queues, Reverb)
 *   DB 1 — legacy 'cache' connection (backward-compat)
 *   DB 2 — sessions (future)
 *   DB 3 — rate_limit
 *   DB 4 — users
 *   DB 5 — customers
 *   DB 6 — themes
 *   DB 7 — tickets
 *   DB 8 — modules
 */
class CacheManager
{
    private const STORES = ['users', 'customers', 'themes', 'tickets', 'modules', 'rate_limit'];

    /**
     * Build a tenant-scoped cache key.
     *
     * Usage:
     *   $cm->key('stats')           → "stats"           (platform-wide)
     *   $cm->key('stats', $siteId)  → "site_42:stats"   (tenant-scoped)
     */
    public function key(string $name, ?int $siteId = null): string
    {
        return $siteId !== null ? "site_{$siteId}:{$name}" : $name;
    }

    /**
     * Resolve a named cache store.
     * Returns the default store if $storeName is not a known named store.
     */
    public function store(string $storeName): Repository
    {
        return Cache::store($storeName);
    }

    /**
     * Cache a value in the given named store.
     */
    public function remember(string $storeName, string $key, int $ttl, \Closure $callback): mixed
    {
        return $this->store($storeName)->remember($key, $ttl, $callback);
    }

    /**
     * Forget a single key in the given named store.
     */
    public function forget(string $storeName, string $key): void
    {
        $this->store($storeName)->forget($key);
    }

    /**
     * Put a value in the given named store.
     */
    public function put(string $storeName, string $key, mixed $value, int $ttl): void
    {
        $this->store($storeName)->put($key, $value, $ttl);
    }

    /**
     * Get a value from the given named store.
     */
    public function get(string $storeName, string $key, mixed $default = null): mixed
    {
        return $this->store($storeName)->get($key, $default);
    }

    /**
     * Flush ALL keys in the given named store only.
     *
     * Since each named store maps to its own Redis DB, this is safe:
     * it flushes DB4 (users) without touching DB5 (customers), DB6 (themes), etc.
     *
     * DO NOT call Cache::flush() directly — that would wipe the entire cache DB.
     */
    public function flushStore(string $storeName): void
    {
        $this->store($storeName)->flush();
    }

    /**
     * Returns true if $storeName is one of the platform-managed named stores.
     */
    public function isKnownStore(string $storeName): bool
    {
        return in_array($storeName, self::STORES, true);
    }

    /**
     * List all known named store names.
     *
     * @return string[]
     */
    public function storeNames(): array
    {
        return self::STORES;
    }
}
