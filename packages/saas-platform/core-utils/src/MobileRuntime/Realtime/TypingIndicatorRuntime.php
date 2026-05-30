<?php

namespace SaaSPlatform\CoreUtils\MobileRuntime\Realtime;

use Illuminate\Support\Facades\Cache;

class TypingIndicatorRuntime
{
    private const PREFIX = 'mobile:typing:';
    private const TTL    = 5;

    public function startTyping(string $conversationId, int|string $userId, string $tenantId): void
    {
        $key  = $this->typingKey($conversationId, $userId);
        Cache::put($key, [
            'user_id'        => $userId,
            'tenant_id'      => $tenantId,
            'conversation_id' => $conversationId,
            'started_at'     => now()->toIso8601String(),
        ], self::TTL);
        $this->addToConversationIndex($conversationId, $userId);
    }

    public function stopTyping(string $conversationId, int|string $userId): void
    {
        Cache::forget($this->typingKey($conversationId, $userId));
        $this->removeFromConversationIndex($conversationId, $userId);
    }

    public function getTypingUsers(string $conversationId): array
    {
        $index  = Cache::get(self::PREFIX . "conv_idx:{$conversationId}", []);
        $typing = [];
        $alive  = [];
        foreach ($index as $userId) {
            $data = Cache::get($this->typingKey($conversationId, $userId));
            if ($data) {
                $typing[] = $data;
                $alive[]  = $userId;
            }
        }
        if (count($alive) !== count($index)) {
            Cache::put(self::PREFIX . "conv_idx:{$conversationId}", $alive, 60);
        }
        return $typing;
    }

    public function isTyping(string $conversationId, int|string $userId): bool
    {
        return Cache::has($this->typingKey($conversationId, $userId));
    }

    public function cleanExpiredTyping(string $conversationId): void
    {
        $index = Cache::get(self::PREFIX . "conv_idx:{$conversationId}", []);
        $alive = array_filter($index, fn ($uid) => Cache::has($this->typingKey($conversationId, $uid)));
        Cache::put(self::PREFIX . "conv_idx:{$conversationId}", array_values($alive), 60);
    }

    public function broadcast(string $conversationId, int|string $userId, bool $typing): void
    {
        // Fires event that reverb will broadcast to conversation.{id} channel
        try {
            event(new class ($conversationId, $userId, $typing) {
                public function __construct(
                    public readonly string $conversationId,
                    public readonly int|string $userId,
                    public readonly bool $typing,
                ) {}
            });
        } catch (\Throwable) {
            // Non-critical — typing indicator failure should never break the app
        }
    }

    private function typingKey(string $conversationId, int|string $userId): string
    {
        return self::PREFIX . "{$conversationId}:{$userId}";
    }

    private function addToConversationIndex(string $conversationId, int|string $userId): void
    {
        $index = Cache::get(self::PREFIX . "conv_idx:{$conversationId}", []);
        if (! in_array($userId, $index, true)) {
            $index[] = $userId;
            Cache::put(self::PREFIX . "conv_idx:{$conversationId}", $index, 60);
        }
    }

    private function removeFromConversationIndex(string $conversationId, int|string $userId): void
    {
        $index = Cache::get(self::PREFIX . "conv_idx:{$conversationId}", []);
        Cache::put(self::PREFIX . "conv_idx:{$conversationId}", array_values(array_diff($index, [$userId])), 60);
    }
}
