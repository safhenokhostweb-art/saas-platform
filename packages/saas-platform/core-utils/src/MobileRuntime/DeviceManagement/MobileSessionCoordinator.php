<?php

namespace SaaSPlatform\CoreUtils\MobileRuntime\DeviceManagement;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class MobileSessionCoordinator
{
    private const PREFIX   = 'mobile:sessions:';
    private const TTL      = 86400;
    private const MAX_PER_USER = 10;

    public function createSession(string $userId, string $tenantId, string $deviceId, array $meta = []): array
    {
        $sessionId = Str::uuid()->toString();
        $token     = bin2hex(random_bytes(32));
        $expiresAt = now()->addSeconds(self::TTL);

        $session = [
            'session_id' => $sessionId,
            'user_id'    => $userId,
            'tenant_id'  => $tenantId,
            'device_id'  => $deviceId,
            'token'      => $token,
            'expires_at' => $expiresAt->toIso8601String(),
            'created_at' => now()->toIso8601String(),
            'meta'       => $meta,
        ];

        Cache::put(self::PREFIX . $sessionId, $session, self::TTL);
        $this->addToUserIndex($userId, $tenantId, $sessionId);

        return [
            'session_id' => $sessionId,
            'token'      => $token,
            'expires_at' => $expiresAt->toIso8601String(),
        ];
    }

    public function validateSession(string $sessionId, string $deviceId): bool
    {
        $session = Cache::get(self::PREFIX . $sessionId);
        if (! $session) return false;
        if ($session['device_id'] !== $deviceId) return false;
        if (now()->isAfter($session['expires_at'])) {
            $this->revokeSession($sessionId);
            return false;
        }
        return true;
    }

    public function revokeSession(string $sessionId): void
    {
        $session = Cache::get(self::PREFIX . $sessionId);
        if ($session) {
            $this->removeFromUserIndex($session['user_id'], $session['tenant_id'], $sessionId);
        }
        Cache::forget(self::PREFIX . $sessionId);
    }

    public function revokeAllSessions(string $userId, string $tenantId): int
    {
        $sessions = $this->getSessions($userId, $tenantId);
        $count    = 0;
        foreach ($sessions as $session) {
            $this->revokeSession($session['session_id']);
            $count++;
        }
        Cache::forget(self::PREFIX . "user_idx:{$userId}:{$tenantId}");
        return $count;
    }

    public function getSessions(string $userId, string $tenantId): array
    {
        $ids     = Cache::get(self::PREFIX . "user_idx:{$userId}:{$tenantId}", []);
        $sessions = [];
        $alive   = [];
        foreach ($ids as $sessionId) {
            $session = Cache::get(self::PREFIX . $sessionId);
            if ($session) {
                $sessions[] = $session;
                $alive[]    = $sessionId;
            }
        }
        if (count($alive) !== count($ids)) {
            Cache::put(self::PREFIX . "user_idx:{$userId}:{$tenantId}", $alive, self::TTL);
        }
        return $sessions;
    }

    public function refreshSession(string $sessionId): array
    {
        $session = Cache::get(self::PREFIX . $sessionId);
        if (! $session) throw new \RuntimeException("Session {$sessionId} not found");

        $newToken  = bin2hex(random_bytes(32));
        $expiresAt = now()->addSeconds(self::TTL);
        $session   = array_merge($session, [
            'token'      => $newToken,
            'expires_at' => $expiresAt->toIso8601String(),
        ]);
        Cache::put(self::PREFIX . $sessionId, $session, self::TTL);

        return ['session_id' => $sessionId, 'token' => $newToken, 'expires_at' => $expiresAt->toIso8601String()];
    }

    public function getActiveSessionCount(string $userId): int
    {
        return count(Cache::get(self::PREFIX . "user_idx_all:{$userId}", []));
    }

    public function purgeExpiredSessions(): int
    {
        // With Cache-based sessions, expired sessions auto-purge when TTL lapses
        return 0;
    }

    private function addToUserIndex(string $userId, string $tenantId, string $sessionId): void
    {
        $key     = self::PREFIX . "user_idx:{$userId}:{$tenantId}";
        $index   = Cache::get($key, []);
        $index[] = $sessionId;
        if (count($index) > self::MAX_PER_USER) {
            // Revoke oldest session when limit exceeded
            $oldest = array_shift($index);
            Cache::forget(self::PREFIX . $oldest);
        }
        Cache::put($key, $index, self::TTL);
    }

    private function removeFromUserIndex(string $userId, string $tenantId, string $sessionId): void
    {
        $key   = self::PREFIX . "user_idx:{$userId}:{$tenantId}";
        $index = Cache::get($key, []);
        Cache::put($key, array_values(array_diff($index, [$sessionId])), self::TTL);
    }
}
