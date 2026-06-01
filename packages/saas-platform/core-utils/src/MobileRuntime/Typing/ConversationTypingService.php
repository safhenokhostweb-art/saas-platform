<?php

namespace SaaSPlatform\CoreUtils\MobileRuntime\Typing;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use SaaSPlatform\CoreUtils\MobileRuntime\Realtime\TypingIndicatorRuntime;

/**
 * Conversation-level typing service — wraps TypingIndicatorRuntime with
 * Reverb broadcast, tenant-aware isolation, and automatic timeout cleanup.
 *
 * Realtime only — no polling fallback for typing indicators.
 */
class ConversationTypingService
{
    private const TYPING_TIMEOUT = 5; // seconds — matches TypingIndicatorRuntime::TTL

    public function __construct(
        private readonly TypingIndicatorRuntime $runtime,
    ) {}

    // ── User starts typing ────────────────────────────────────────────────────

    public function startTyping(string $conversationId, string|int $userId, string $tenantId): void
    {
        $this->runtime->startTyping($conversationId, $userId, $tenantId);
        $this->broadcast($conversationId, $userId, true);

        Log::debug('typing.start', [
            'conversation_id' => $conversationId,
            'user_id'         => $userId,
            'tenant_id'       => $tenantId,
        ]);
    }

    // ── User stops typing (explicit or timeout) ───────────────────────────────

    public function stopTyping(string $conversationId, string|int $userId): void
    {
        $this->runtime->stopTyping($conversationId, $userId);
        $this->broadcast($conversationId, $userId, false);

        Log::debug('typing.stop', [
            'conversation_id' => $conversationId,
            'user_id'         => $userId,
        ]);
    }

    // ── Get users currently typing in a conversation ──────────────────────────

    public function getTypingUsers(string $conversationId): array
    {
        return $this->runtime->getTypingUsers($conversationId);
    }

    // ── Check if a specific user is typing ───────────────────────────────────

    public function isTyping(string $conversationId, string|int $userId): bool
    {
        return $this->runtime->isTyping($conversationId, $userId);
    }

    // ── Cleanup expired entries (called on schedule or via job) ──────────────

    public function cleanExpired(string $conversationId): void
    {
        $before = $this->runtime->getTypingUsers($conversationId);
        $this->runtime->cleanExpiredTyping($conversationId);
        $after  = $this->runtime->getTypingUsers($conversationId);

        // Broadcast stop for users that expired
        $beforeIds = array_column($before, 'user_id');
        $afterIds  = array_column($after, 'user_id');
        foreach (array_diff($beforeIds, $afterIds) as $expiredUserId) {
            $this->broadcast($conversationId, $expiredUserId, false);
        }
    }

    // ── Typing timeout constant ───────────────────────────────────────────────

    public function getTypingTimeout(): int
    {
        return self::TYPING_TIMEOUT;
    }

    // ── Private broadcast helper ──────────────────────────────────────────────

    private function broadcast(string $conversationId, string|int $userId, bool $typing): void
    {
        try {
            broadcast(new TypingEvent($conversationId, (string) $userId, $typing));
        } catch (\Throwable $e) {
            // Typing indicator failure is non-critical
            Log::warning('typing.broadcast.failed', ['error' => $e->getMessage()]);
        }
    }
}

// ── Inline broadcast event ────────────────────────────────────────────────────

class TypingEvent implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly string $conversationId,
        public readonly string $userId,
        public readonly bool   $typing,
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel("conversation.{$this->conversationId}")];
    }

    public function broadcastAs(): string
    {
        return 'conversation.typing';
    }

    public function broadcastWith(): array
    {
        return [
            'user_id'         => $this->userId,
            'typing'          => $this->typing,
            'conversation_id' => $this->conversationId,
            'timestamp'       => now()->toIso8601String(),
        ];
    }
}
