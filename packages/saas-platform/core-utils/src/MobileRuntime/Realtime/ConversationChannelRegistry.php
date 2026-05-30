<?php

namespace SaaSPlatform\CoreUtils\MobileRuntime\Realtime;

use Illuminate\Support\Facades\Cache;

class ConversationChannelRegistry
{
    private const PREFIX = 'mobile:channels:conv:';
    private const TTL    = 7200;

    public function register(string $conversationId, string $tenantId, array $participants): void
    {
        Cache::put(self::PREFIX . $conversationId, [
            'conversation_id' => $conversationId,
            'tenant_id'       => $tenantId,
            'channel_name'    => $this->getChannelName($conversationId),
            'participants'    => $participants,
            'registered_at'   => now()->toIso8601String(),
        ], self::TTL);
        $this->addToTenantIndex($tenantId, $conversationId);
    }

    public function unregister(string $conversationId): void
    {
        $channel = $this->getChannel($conversationId);
        if ($channel) {
            $this->removeFromTenantIndex($channel['tenant_id'], $conversationId);
        }
        Cache::forget(self::PREFIX . $conversationId);
    }

    public function getChannel(string $conversationId): ?array
    {
        return Cache::get(self::PREFIX . $conversationId);
    }

    public function getChannelName(string $conversationId): string
    {
        return "conversation.{$conversationId}";
    }

    public function addParticipant(string $conversationId, int|string $userId): void
    {
        $channel = $this->getChannel($conversationId);
        if (! $channel) return;
        if (! in_array($userId, $channel['participants'], true)) {
            $channel['participants'][] = $userId;
            Cache::put(self::PREFIX . $conversationId, $channel, self::TTL);
        }
    }

    public function removeParticipant(string $conversationId, int|string $userId): void
    {
        $channel = $this->getChannel($conversationId);
        if (! $channel) return;
        $channel['participants'] = array_values(array_diff($channel['participants'], [$userId]));
        Cache::put(self::PREFIX . $conversationId, $channel, self::TTL);
    }

    public function getActiveChannels(string $tenantId): array
    {
        $index  = Cache::get(self::PREFIX . "tenant_idx:{$tenantId}", []);
        $active = [];
        $alive  = [];
        foreach ($index as $conversationId) {
            $channel = $this->getChannel($conversationId);
            if ($channel) {
                $active[] = $channel;
                $alive[]  = $conversationId;
            }
        }
        if (count($alive) !== count($index)) {
            Cache::put(self::PREFIX . "tenant_idx:{$tenantId}", $alive, self::TTL);
        }
        return $active;
    }

    public function isActive(string $conversationId): bool
    {
        return Cache::has(self::PREFIX . $conversationId);
    }

    public function getParticipants(string $conversationId): array
    {
        return $this->getChannel($conversationId)['participants'] ?? [];
    }

    private function addToTenantIndex(string $tenantId, string $conversationId): void
    {
        $index = Cache::get(self::PREFIX . "tenant_idx:{$tenantId}", []);
        if (! in_array($conversationId, $index, true)) {
            $index[] = $conversationId;
            Cache::put(self::PREFIX . "tenant_idx:{$tenantId}", $index, self::TTL);
        }
    }

    private function removeFromTenantIndex(string $tenantId, string $conversationId): void
    {
        $index = Cache::get(self::PREFIX . "tenant_idx:{$tenantId}", []);
        Cache::put(self::PREFIX . "tenant_idx:{$tenantId}", array_values(array_diff($index, [$conversationId])), self::TTL);
    }
}
