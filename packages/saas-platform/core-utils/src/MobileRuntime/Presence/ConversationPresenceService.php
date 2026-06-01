<?php

namespace SaaSPlatform\CoreUtils\MobileRuntime\Presence;

use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Conversation-level presence service.
 *
 * Wraps PresenceRuntimeManager with conversation-scoped visibility.
 * Does NOT create a parallel presence system — delegates all storage to
 * PresenceRuntimeManager and adds conversation-scoped broadcasting.
 */
class ConversationPresenceService
{
    public const STATUS_ONLINE  = PresenceRuntimeManager::ONLINE;
    public const STATUS_AWAY    = PresenceRuntimeManager::AWAY;
    public const STATUS_OFFLINE = PresenceRuntimeManager::OFFLINE;

    private const CONV_PRESENCE_PREFIX = 'mobile:conv_presence:';
    private const CONV_PRESENCE_TTL    = 300; // 5 minutes

    public function __construct(
        private readonly PresenceRuntimeManager $presenceManager,
    ) {}

    // ── Join conversation presence ────────────────────────────────────────────

    public function joinConversation(
        string $conversationId,
        string $userId,
        string $tenantId,
        string $deviceId,
        string $currentSession = '',
        string $activeDevice   = ''
    ): void {
        // Mark the user online globally via PresenceRuntimeManager
        $this->presenceManager->markOnline($tenantId, $userId, $deviceId, [
            'current_session' => $currentSession,
            'active_device'   => $activeDevice ?: $deviceId,
        ]);

        // Track conversation-level presence separately
        $key  = self::CONV_PRESENCE_PREFIX . $conversationId;
        $list = Cache::get($key, []);

        $list[$userId] = [
            'user_id'         => $userId,
            'device_id'       => $deviceId,
            'status'          => self::STATUS_ONLINE,
            'joined_at'       => now()->toIso8601String(),
            'last_seen'       => now()->toIso8601String(),
            'current_session' => $currentSession,
            'active_device'   => $activeDevice ?: $deviceId,
        ];

        Cache::put($key, $list, self::CONV_PRESENCE_TTL);

        $this->broadcastPresenceChange($conversationId, $userId, self::STATUS_ONLINE, $list);

        Log::debug('conversation.presence.join', [
            'conversation_id' => $conversationId,
            'user_id'         => $userId,
        ]);
    }

    // ── Leave conversation presence ───────────────────────────────────────────

    public function leaveConversation(
        string $conversationId,
        string $userId,
        string $tenantId,
        string $deviceId
    ): void {
        $key  = self::CONV_PRESENCE_PREFIX . $conversationId;
        $list = Cache::get($key, []);

        if (isset($list[$userId])) {
            $list[$userId]['status']    = self::STATUS_OFFLINE;
            $list[$userId]['last_seen'] = now()->toIso8601String();
        }

        Cache::put($key, $list, self::CONV_PRESENCE_TTL);

        $this->broadcastPresenceChange($conversationId, $userId, self::STATUS_OFFLINE, $list);

        Log::debug('conversation.presence.leave', [
            'conversation_id' => $conversationId,
            'user_id'         => $userId,
        ]);
    }

    // ── Update status within conversation ────────────────────────────────────

    public function updateStatus(
        string $conversationId,
        string $userId,
        string $status,
        string $tenantId,
        string $deviceId
    ): void {
        $key  = self::CONV_PRESENCE_PREFIX . $conversationId;
        $list = Cache::get($key, []);

        if (isset($list[$userId])) {
            $list[$userId]['status']    = $status;
            $list[$userId]['last_seen'] = now()->toIso8601String();
            Cache::put($key, $list, self::CONV_PRESENCE_TTL);
        }

        if ($status === self::STATUS_AWAY) {
            $this->presenceManager->markAway($tenantId, $userId);
        }

        $this->broadcastPresenceChange($conversationId, $userId, $status, $list);
    }

    // ── Get active users in conversation ─────────────────────────────────────

    public function getConversationPresence(string $conversationId): array
    {
        $key  = self::CONV_PRESENCE_PREFIX . $conversationId;
        $list = Cache::get($key, []);

        // Prune expired entries (no heartbeat for > 5 min)
        $now    = now();
        $active = [];

        foreach ($list as $userId => $data) {
            $lastSeen = isset($data['last_seen'])
                ? \Carbon\Carbon::parse($data['last_seen'])
                : null;

            if ($lastSeen && $now->diffInSeconds($lastSeen) < self::CONV_PRESENCE_TTL) {
                $active[$userId] = $data;
            }
        }

        if (count($active) !== count($list)) {
            Cache::put($key, $active, self::CONV_PRESENCE_TTL);
        }

        return array_values($active);
    }

    // ── Get global presence for a user (delegates to PresenceRuntimeManager) ─

    public function getUserPresence(string $tenantId, string $userId): ?array
    {
        return $this->presenceManager->getPresence($tenantId, $userId);
    }

    // ── Get last_seen for a user ──────────────────────────────────────────────

    public function getLastSeen(string $tenantId, string $userId): ?string
    {
        $presence = $this->presenceManager->getPresence($tenantId, $userId);
        return $presence['last_seen'] ?? null;
    }

    // ── Heartbeat (keeps conversation presence alive) ─────────────────────────

    public function heartbeat(
        string $conversationId,
        string $userId,
        string $tenantId,
        string $deviceId
    ): void {
        $this->presenceManager->heartbeat($tenantId, $userId, $deviceId);

        $key  = self::CONV_PRESENCE_PREFIX . $conversationId;
        $list = Cache::get($key, []);

        if (isset($list[$userId])) {
            $list[$userId]['last_seen'] = now()->toIso8601String();
            $list[$userId]['status']    = self::STATUS_ONLINE;
            Cache::put($key, $list, self::CONV_PRESENCE_TTL);
        }
    }

    // ── Online user count in tenant ───────────────────────────────────────────

    public function getOnlineCount(string $tenantId): int
    {
        return count($this->presenceManager->getOnlineUsers($tenantId));
    }

    // ── Private broadcast ─────────────────────────────────────────────────────

    private function broadcastPresenceChange(
        string $conversationId,
        string $userId,
        string $status,
        array  $allPresence
    ): void {
        try {
            broadcast(new ConversationPresenceEvent(
                $conversationId,
                $userId,
                $status,
                array_values($allPresence)
            ));
        } catch (\Throwable $e) {
            Log::warning('presence.broadcast.failed', ['error' => $e->getMessage()]);
        }
    }
}

// ── Inline broadcast event ────────────────────────────────────────────────────

class ConversationPresenceEvent implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly string $conversationId,
        public readonly string $userId,
        public readonly string $status,
        public readonly array  $allPresence,
    ) {}

    public function broadcastOn(): array
    {
        return [new PresenceChannel("conversation.{$this->conversationId}")];
    }

    public function broadcastAs(): string
    {
        return 'conversation.presence.updated';
    }

    public function broadcastWith(): array
    {
        return [
            'user_id'     => $this->userId,
            'status'      => $this->status,
            'all'         => $this->allPresence,
            'timestamp'   => now()->toIso8601String(),
        ];
    }
}
