<?php

namespace SaaSPlatform\CoreUtils\MobileRuntime\Communication;

use Carbon\Carbon;

class ConversationTypingState
{
    public function __construct(
        public readonly string $conversationId,
        public readonly string $userId,
        public readonly string $tenantId,
        public readonly bool $isTyping = true,
        public readonly ?Carbon $startedAt = null,
    ) {}

    public static function create(string $conversationId, string $userId, string $tenantId): static
    {
        return new static($conversationId, $userId, $tenantId, true, now());
    }

    public static function stop(string $conversationId, string $userId, string $tenantId): static
    {
        return new static($conversationId, $userId, $tenantId, false, null);
    }

    public function toArray(): array
    {
        return [
            'conversation_id' => $this->conversationId,
            'user_id'         => $this->userId,
            'tenant_id'       => $this->tenantId,
            'is_typing'       => $this->isTyping,
            'started_at'      => $this->startedAt?->toIso8601String(),
        ];
    }

    public function isExpired(int $ttlSeconds = 5): bool
    {
        if (! $this->isTyping || $this->startedAt === null) return true;
        return $this->startedAt->diffInSeconds(now()) > $ttlSeconds;
    }
}
