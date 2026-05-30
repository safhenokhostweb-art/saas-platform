<?php

namespace SaaSPlatform\CoreUtils\MobileRuntime\Presence;

use Illuminate\Support\Facades\Cache;

class PresenceRuntimeManager
{
    public const ONLINE  = 'online';
    public const AWAY    = 'away';
    public const BUSY    = 'busy';
    public const OFFLINE = 'offline';

    private const PREFIX = 'mobile:presence:';
    private const TTL    = 120;
    private const INDEX_TTL = 7200;

    public function setPresence(
        string $tenantId,
        int|string $userId,
        string $deviceId,
        string $status,
        array $meta = []
    ): void {
        $key  = $this->presenceKey($tenantId, $userId, $deviceId);
        $data = array_merge([
            'user_id'    => $userId,
            'tenant_id'  => $tenantId,
            'device_id'  => $deviceId,
            'status'     => $status,
            'last_seen'  => now()->toIso8601String(),
        ], $meta);
        Cache::put($key, $data, self::TTL);
        $this->registerInIndex($tenantId, $userId, $deviceId);
    }

    public function getPresence(string $tenantId, int|string $userId): ?array
    {
        $devices = $this->getDevicePresences($tenantId, $userId);
        if (empty($devices)) return null;
        // Return the most active device presence
        usort($devices, fn ($a, $b) => strcmp($b['last_seen'], $a['last_seen']));
        return $devices[0];
    }

    public function markOnline(string $tenantId, int|string $userId, string $deviceId, array $deviceInfo = []): void
    {
        $this->setPresence($tenantId, $userId, $deviceId, self::ONLINE, $deviceInfo);
    }

    public function markOffline(string $tenantId, int|string $userId, string $deviceId): void
    {
        Cache::forget($this->presenceKey($tenantId, $userId, $deviceId));
        $this->removeFromIndex($tenantId, $userId, $deviceId);
    }

    public function markAway(string $tenantId, int|string $userId): void
    {
        $devices = $this->getUserDevices($tenantId, $userId);
        foreach ($devices as $deviceId) {
            $existing = Cache::get($this->presenceKey($tenantId, $userId, $deviceId));
            if ($existing) {
                $existing['status'] = self::AWAY;
                Cache::put($this->presenceKey($tenantId, $userId, $deviceId), $existing, self::TTL);
            }
        }
    }

    public function getOnlineUsers(string $tenantId): array
    {
        $index   = Cache::get(self::PREFIX . "tenant_index:{$tenantId}", []);
        $online  = [];
        $pruned  = [];
        foreach ($index as $userId => $deviceIds) {
            foreach ((array) $deviceIds as $deviceId) {
                $data = Cache::get($this->presenceKey($tenantId, $userId, $deviceId));
                if ($data && ($data['status'] ?? '') === self::ONLINE) {
                    $online[$userId] = $data;
                    $pruned[$userId][] = $deviceId;
                    break;
                }
            }
        }
        return array_values($online);
    }

    public function getPresenceSummary(string $tenantId): array
    {
        $index  = Cache::get(self::PREFIX . "tenant_index:{$tenantId}", []);
        $counts = ['online' => 0, 'away' => 0, 'busy' => 0, 'offline' => 0];
        foreach ($index as $userId => $deviceIds) {
            $presence = $this->getPresence($tenantId, $userId);
            $status   = $presence['status'] ?? 'offline';
            $counts[$status] = ($counts[$status] ?? 0) + 1;
        }
        return $counts;
    }

    public function heartbeat(string $tenantId, int|string $userId, string $deviceId): void
    {
        $key  = $this->presenceKey($tenantId, $userId, $deviceId);
        $data = Cache::get($key);
        if ($data) {
            $data['last_seen'] = now()->toIso8601String();
            $data['status']    = self::ONLINE;
            Cache::put($key, $data, self::TTL);
        }
    }

    public function cleanExpired(string $tenantId): int
    {
        $index   = Cache::get(self::PREFIX . "tenant_index:{$tenantId}", []);
        $cleaned = 0;
        $alive   = [];
        foreach ($index as $userId => $deviceIds) {
            $liveDevices = [];
            foreach ((array) $deviceIds as $deviceId) {
                if (Cache::has($this->presenceKey($tenantId, $userId, $deviceId))) {
                    $liveDevices[] = $deviceId;
                } else {
                    $cleaned++;
                }
            }
            if (! empty($liveDevices)) {
                $alive[$userId] = $liveDevices;
            }
        }
        Cache::put(self::PREFIX . "tenant_index:{$tenantId}", $alive, self::INDEX_TTL);
        return $cleaned;
    }

    private function presenceKey(string $tenantId, int|string $userId, string $deviceId): string
    {
        return self::PREFIX . "t:{$tenantId}:u:{$userId}:d:{$deviceId}";
    }

    private function getDevicePresences(string $tenantId, int|string $userId): array
    {
        $deviceIds = $this->getUserDevices($tenantId, $userId);
        $presences = [];
        foreach ($deviceIds as $deviceId) {
            $data = Cache::get($this->presenceKey($tenantId, $userId, $deviceId));
            if ($data) {
                $presences[] = $data;
            }
        }
        return $presences;
    }

    private function getUserDevices(string $tenantId, int|string $userId): array
    {
        $index = Cache::get(self::PREFIX . "tenant_index:{$tenantId}", []);
        return (array) ($index[$userId] ?? []);
    }

    private function registerInIndex(string $tenantId, int|string $userId, string $deviceId): void
    {
        $index = Cache::get(self::PREFIX . "tenant_index:{$tenantId}", []);
        if (! isset($index[$userId])) {
            $index[$userId] = [];
        }
        if (! in_array($deviceId, $index[$userId], true)) {
            $index[$userId][] = $deviceId;
        }
        Cache::put(self::PREFIX . "tenant_index:{$tenantId}", $index, self::INDEX_TTL);
    }

    private function removeFromIndex(string $tenantId, int|string $userId, string $deviceId): void
    {
        $index = Cache::get(self::PREFIX . "tenant_index:{$tenantId}", []);
        if (isset($index[$userId])) {
            $index[$userId] = array_values(array_diff($index[$userId], [$deviceId]));
            if (empty($index[$userId])) unset($index[$userId]);
        }
        Cache::put(self::PREFIX . "tenant_index:{$tenantId}", $index, self::INDEX_TTL);
    }
}
