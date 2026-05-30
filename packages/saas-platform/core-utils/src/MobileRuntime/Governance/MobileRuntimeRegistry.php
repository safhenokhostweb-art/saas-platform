<?php

namespace SaaSPlatform\CoreUtils\MobileRuntime\Governance;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class MobileRuntimeRegistry
{
    private const PREFIX = 'mobile:registry:';
    private const INDEX_KEY = 'mobile:registry:index';
    private const TTL = 86400;

    public function register(string $tenantId, array $runtimeConfig): void
    {
        $config = array_merge($runtimeConfig, [
            'tenant_id'    => $tenantId,
            'registered_at' => now()->toIso8601String(),
            'status'       => 'active',
        ]);
        Cache::put(self::PREFIX . $tenantId, $config, self::TTL);
        $this->addToIndex($tenantId);
        Log::channel('stack')->info('mobile.runtime.registered', ['tenant_id' => $tenantId]);
    }

    public function getRuntime(string $tenantId): ?array
    {
        return Cache::get(self::PREFIX . $tenantId);
    }

    public function deregister(string $tenantId): void
    {
        Cache::forget(self::PREFIX . $tenantId);
        $this->removeFromIndex($tenantId);
    }

    public function getAllRuntimes(): array
    {
        $index   = Cache::get(self::INDEX_KEY, []);
        $runtimes = [];
        $alive   = [];
        foreach ($index as $tenantId) {
            $runtime = Cache::get(self::PREFIX . $tenantId);
            if ($runtime !== null) {
                $runtimes[$tenantId] = $runtime;
                $alive[] = $tenantId;
            }
        }
        if (count($alive) !== count($index)) {
            Cache::put(self::INDEX_KEY, $alive, self::TTL);
        }
        return $runtimes;
    }

    public function isRegistered(string $tenantId): bool
    {
        return Cache::has(self::PREFIX . $tenantId);
    }

    public function updateConfig(string $tenantId, array $updates): void
    {
        $existing = $this->getRuntime($tenantId) ?? ['tenant_id' => $tenantId];
        $merged   = array_merge($existing, $updates, ['updated_at' => now()->toIso8601String()]);
        Cache::put(self::PREFIX . $tenantId, $merged, self::TTL);
    }

    public function getStats(): array
    {
        $all = $this->getAllRuntimes();
        return [
            'total'        => count($all),
            'active_tenants' => array_keys($all),
            'generated_at' => now()->toIso8601String(),
        ];
    }

    private function addToIndex(string $tenantId): void
    {
        $index = Cache::get(self::INDEX_KEY, []);
        if (! in_array($tenantId, $index, true)) {
            $index[] = $tenantId;
            Cache::put(self::INDEX_KEY, $index, self::TTL);
        }
    }

    private function removeFromIndex(string $tenantId): void
    {
        $index = Cache::get(self::INDEX_KEY, []);
        Cache::put(self::INDEX_KEY, array_values(array_diff($index, [$tenantId])), self::TTL);
    }
}
