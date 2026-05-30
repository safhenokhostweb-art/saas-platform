<?php

namespace SaaSPlatform\CoreUtils\MobileRuntime\DeviceManagement;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class DeviceTrustManager
{
    public const TRUSTED     = 'trusted';
    public const PROVISIONAL = 'provisional';
    public const SUSPICIOUS  = 'suspicious';
    public const REVOKED     = 'revoked';

    private const PREFIX           = 'mobile:trust:';
    private const TTL              = 86400;
    private const FAIL_WINDOW       = 600;  // 10 minutes
    public const MAX_FAIL_ATTEMPTS  = 5;

    public function getTrustLevel(string $deviceId): string
    {
        return Cache::get(self::PREFIX . "level:{$deviceId}", self::PROVISIONAL);
    }

    public function setTrustLevel(string $deviceId, string $level, string $reason = ''): void
    {
        Cache::put(self::PREFIX . "level:{$deviceId}", $level, self::TTL);
        Log::info('mobile.trust.level_changed', [
            'device_id' => $deviceId,
            'level'     => $level,
            'reason'    => $reason,
        ]);
    }

    public function trustDevice(string $deviceId): void
    {
        $this->setTrustLevel($deviceId, self::TRUSTED, 'manual_trust');
        Cache::forget(self::PREFIX . "fails:{$deviceId}");
    }

    public function revokeDevice(string $deviceId, string $reason): void
    {
        $this->setTrustLevel($deviceId, self::REVOKED, $reason);
        Log::warning('mobile.trust.device_revoked', ['device_id' => $deviceId, 'reason' => $reason]);
    }

    public function flagAsSuspicious(string $deviceId, string $reason): void
    {
        $this->setTrustLevel($deviceId, self::SUSPICIOUS, $reason);
        Log::warning('mobile.trust.suspicious_device', ['device_id' => $deviceId, 'reason' => $reason]);
    }

    public function isTrusted(string $deviceId): bool
    {
        return $this->getTrustLevel($deviceId) === self::TRUSTED;
    }

    public function isRevoked(string $deviceId): bool
    {
        return $this->getTrustLevel($deviceId) === self::REVOKED;
    }

    public function recordLoginAttempt(string $deviceId, bool $success): void
    {
        if ($success) {
            Cache::forget(self::PREFIX . "fails:{$deviceId}");
            if ($this->getTrustLevel($deviceId) === self::PROVISIONAL) {
                $this->setTrustLevel($deviceId, self::TRUSTED, 'successful_login');
            }
            return;
        }

        $failKey = self::PREFIX . "fails:{$deviceId}";
        $fails   = Cache::get($failKey, []);
        $now     = time();
        $fails[] = $now;
        // Prune old failures outside window
        $fails   = array_filter($fails, fn ($t) => $t > $now - self::FAIL_WINDOW);
        Cache::put($failKey, array_values($fails), self::FAIL_WINDOW);

        if (count($fails) >= self::MAX_FAIL_ATTEMPTS) {
            $this->flagAsSuspicious($deviceId, 'too_many_failed_logins');
        }
    }

    public function getDeviceTrustReport(string $tenantId): array
    {
        // Returns aggregate from cache index
        $index    = Cache::get(self::PREFIX . "tenant_idx:{$tenantId}", []);
        $counts   = [self::TRUSTED => 0, self::PROVISIONAL => 0, self::SUSPICIOUS => 0, self::REVOKED => 0];
        foreach ($index as $deviceId) {
            $level = $this->getTrustLevel($deviceId);
            $counts[$level] = ($counts[$level] ?? 0) + 1;
        }
        return [
            'tenant_id' => $tenantId,
            'counts'    => $counts,
            'total'     => array_sum($counts),
        ];
    }
}
