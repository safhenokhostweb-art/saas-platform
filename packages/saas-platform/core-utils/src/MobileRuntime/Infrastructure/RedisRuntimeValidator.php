<?php

namespace SaaSPlatform\CoreUtils\MobileRuntime\Infrastructure;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;

class RedisRuntimeValidator
{
    public const STATUS_CONNECTED        = 'connected';
    public const STATUS_FALLBACK         = 'fallback';
    public const STATUS_UNAVAILABLE      = 'unavailable';

    public function validate(): array
    {
        $driver  = Config::get('cache.default', 'file');
        $status  = $this->probe();
        $isRedis = $driver === 'redis';

        return [
            'status'           => $status,
            'driver'           => $driver,
            'redis_driver'     => $isRedis,
            'production_ready' => $status === self::STATUS_CONNECTED && $isRedis,
            'message'          => $this->statusMessage($status, $driver),
        ];
    }

    public function probe(): string
    {
        try {
            Cache::store('redis')->put('mobile:infra:redis_probe', '1', 5);
            Cache::store('redis')->forget('mobile:infra:redis_probe');
            return self::STATUS_CONNECTED;
        } catch (\Throwable) {
            try {
                Cache::put('mobile:infra:cache_probe', '1', 5);
                Cache::forget('mobile:infra:cache_probe');
                return self::STATUS_FALLBACK;
            } catch (\Throwable) {
                return self::STATUS_UNAVAILABLE;
            }
        }
    }

    public function isConnected(): bool
    {
        return $this->probe() === self::STATUS_CONNECTED;
    }

    public function isProductionReady(): bool
    {
        return $this->validate()['production_ready'];
    }

    public function getHealthCheck(): array
    {
        $result = $this->validate();
        return [
            'name'    => 'Redis Connectivity',
            'status'  => $result['production_ready'] ? 'pass' : ($result['status'] === self::STATUS_UNAVAILABLE ? 'fail' : 'warn'),
            'message' => $result['message'],
            'details' => $result,
        ];
    }

    private function statusMessage(string $status, string $driver): string
    {
        return match ($status) {
            self::STATUS_CONNECTED => "Redis connected (driver: {$driver})",
            self::STATUS_FALLBACK  => "Redis unavailable — using {$driver} cache fallback (not production-ready)",
            default                => 'Cache unavailable — runtime degraded',
        };
    }
}
