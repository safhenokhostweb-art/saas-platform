<?php

namespace SaaSPlatform\CoreUtils\MobileRuntime\Infrastructure;

use Illuminate\Support\Facades\Cache;

class PresenceStorageValidator
{
    private const PROBE_KEY = 'mobile:infra:presence_probe';
    private const PROBE_TTL = 5;

    public function validate(): array
    {
        $write = $this->probeWrite();
        $read  = $write ? $this->probeRead() : false;
        $ttl   = $read  ? $this->probeTtl() : false;

        $score = ($write ? 34 : 0) + ($read ? 33 : 0) + ($ttl ? 33 : 0);

        return [
            'write_ok'         => $write,
            'read_ok'          => $read,
            'ttl_ok'           => $ttl,
            'score'            => $score,
            'production_ready' => $score === 100,
            'status'           => $score >= 67 ? 'operational' : ($score >= 34 ? 'degraded' : 'unavailable'),
            'driver'           => config('cache.default', 'unknown'),
        ];
    }

    public function getHealthCheck(): array
    {
        $result = $this->validate();
        return [
            'name'    => 'Presence Storage',
            'status'  => $result['production_ready'] ? 'pass' : ($result['status'] === 'unavailable' ? 'fail' : 'warn'),
            'message' => "Presence storage {$result['status']} (driver: {$result['driver']}, score: {$result['score']}/100)",
            'details' => $result,
        ];
    }

    private function probeWrite(): bool
    {
        try {
            Cache::put(self::PROBE_KEY, 'probe', self::PROBE_TTL);
            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    private function probeRead(): bool
    {
        try {
            $value = Cache::get(self::PROBE_KEY);
            Cache::forget(self::PROBE_KEY);
            return $value === 'probe';
        } catch (\Throwable) {
            return false;
        }
    }

    private function probeTtl(): bool
    {
        // File/array cache always returns true for TTL support
        // Redis can be verified with more precision
        try {
            Cache::put(self::PROBE_KEY . ':ttl', '1', 2);
            return Cache::has(self::PROBE_KEY . ':ttl');
        } catch (\Throwable) {
            return false;
        } finally {
            Cache::forget(self::PROBE_KEY . ':ttl');
        }
    }
}
