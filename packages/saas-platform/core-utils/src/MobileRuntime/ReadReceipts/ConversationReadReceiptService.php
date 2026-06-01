<?php

namespace SaaSPlatform\CoreUtils\MobileRuntime\ReadReceipts;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use SaaSPlatform\CoreUtils\MobileRuntime\Communication\ConversationMessage;
use SaaSPlatform\CoreUtils\MobileRuntime\Communication\ConversationParticipant;

/**
 * Read receipt service — tracks sent / delivered / read states.
 *
 * Propagates via Reverb in realtime. No polling when WebSocket is available.
 */
class ConversationReadReceiptService
{
    public const STATE_SENT      = 'sent';
    public const STATE_DELIVERED = 'delivered';
    public const STATE_READ      = 'read';

    private const CACHE_PREFIX = 'mobile:receipt:';
    private const CACHE_TTL    = 3600;

    // ── Mark delivered (client received over WebSocket / push) ────────────────

    public function markDelivered(string $messageId, string $recipientId): void
    {
        $message = $this->findMessage($messageId);
        if (! $message) return;

        $this->updateReceiptState($message, $recipientId, self::STATE_DELIVERED);
        $this->broadcastReceipt($message, $recipientId, self::STATE_DELIVERED);
        $this->cacheReceipt($messageId, $recipientId, self::STATE_DELIVERED);
    }

    // ── Mark read (user opened and viewed the message) ────────────────────────

    public function markRead(string $messageId, string $recipientId): void
    {
        $message = $this->findMessage($messageId);
        if (! $message) return;

        // Update the message's read_by array
        $readBy = $message->read_by ?? [];
        if (! in_array($recipientId, $readBy, true)) {
            $readBy[] = $recipientId;
            $message->update(['read_by' => $readBy]);
        }

        // Update participant's last_read_at
        ConversationParticipant::where('conversation_id', $message->conversation_id)
            ->where('user_id', $recipientId)
            ->update(['last_read_at' => now()]);

        $this->broadcastReceipt($message, $recipientId, self::STATE_READ);
        $this->cacheReceipt($messageId, $recipientId, self::STATE_READ);

        Log::debug('receipt.read', ['message_id' => $messageId, 'recipient' => $recipientId]);
    }

    // ── Bulk mark conversation read ────────────────────────────────────────────

    public function markConversationRead(string $conversationId, string $userId): int
    {
        $messages = ConversationMessage::where('conversation_id', $conversationId)
            ->where('is_deleted', false)
            ->whereJsonDoesntContain('read_by', $userId)
            ->where('sender_id', '!=', $userId)
            ->get();

        foreach ($messages as $message) {
            $readBy   = $message->read_by ?? [];
            $readBy[] = $userId;
            $message->update(['read_by' => array_unique($readBy)]);
            $this->broadcastReceipt($message, $userId, self::STATE_READ);
            $this->cacheReceipt($message->id, $userId, self::STATE_READ);
        }

        ConversationParticipant::where('conversation_id', $conversationId)
            ->where('user_id', $userId)
            ->update(['last_read_at' => now()]);

        return $messages->count();
    }

    // ── Get receipt state for a message ──────────────────────────────────────

    public function getReceiptState(string $messageId, string $recipientId): string
    {
        // Check cache first (avoids DB hit on repeated polls)
        $cached = Cache::get(self::CACHE_PREFIX . "{$messageId}:{$recipientId}");
        if ($cached) return $cached;

        $message = $this->findMessage($messageId);
        if (! $message) return self::STATE_SENT;

        $readBy = $message->read_by ?? [];
        if (in_array($recipientId, $readBy, true)) {
            return self::STATE_READ;
        }

        return self::STATE_SENT;
    }

    // ── Get all receipts for a conversation ──────────────────────────────────

    public function getConversationReceipts(string $conversationId): array
    {
        $messages      = ConversationMessage::where('conversation_id', $conversationId)
            ->where('is_deleted', false)
            ->orderBy('created_at', 'desc')
            ->limit(100)
            ->get();

        $participants = ConversationParticipant::where('conversation_id', $conversationId)
            ->whereNull('left_at')
            ->get()
            ->keyBy('user_id');

        return $messages->map(function ($msg) use ($participants) {
            $readBy = $msg->read_by ?? [];
            return [
                'message_id'  => $msg->id,
                'sender_id'   => $msg->sender_id,
                'created_at'  => $msg->created_at?->toIso8601String(),
                'read_by'     => $readBy,
                'read_count'  => count($readBy),
                'total_participants' => $participants->count(),
                'fully_read'  => $participants->count() > 0
                    && count($readBy) >= $participants->count(),
            ];
        })->values()->all();
    }

    // ── Unread count per user in a conversation ───────────────────────────────

    public function getUnreadCount(string $conversationId, string $userId): int
    {
        return (int) ConversationMessage::where('conversation_id', $conversationId)
            ->where('is_deleted', false)
            ->whereJsonDoesntContain('read_by', $userId)
            ->where('sender_id', '!=', $userId)
            ->count();
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    private function findMessage(string $messageId): ?ConversationMessage
    {
        return ConversationMessage::find($messageId);
    }

    private function updateReceiptState(ConversationMessage $message, string $recipientId, string $state): void
    {
        $receipts   = $message->metadata['receipts'] ?? [];
        $receipts[$recipientId] = [
            'state'      => $state,
            'updated_at' => now()->toIso8601String(),
        ];
        $metadata            = $message->metadata ?? [];
        $metadata['receipts'] = $receipts;
        $message->update(['metadata' => $metadata]);
    }

    private function broadcastReceipt(ConversationMessage $message, string $recipientId, string $state): void
    {
        try {
            broadcast(new ReadReceiptEvent(
                $message->conversation_id,
                $message->id,
                $recipientId,
                $state
            ));
        } catch (\Throwable $e) {
            Log::warning('receipt.broadcast.failed', ['error' => $e->getMessage()]);
        }
    }

    private function cacheReceipt(string $messageId, string $recipientId, string $state): void
    {
        Cache::put(self::CACHE_PREFIX . "{$messageId}:{$recipientId}", $state, self::CACHE_TTL);
    }
}

// ── Inline broadcast event (ShouldBroadcastNow — no queue delay) ─────────────

class ReadReceiptEvent implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly string $conversationId,
        public readonly string $messageId,
        public readonly string $recipientId,
        public readonly string $state,
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel("conversation.{$this->conversationId}")];
    }

    public function broadcastAs(): string
    {
        return 'message.receipt';
    }

    public function broadcastWith(): array
    {
        return [
            'message_id'   => $this->messageId,
            'recipient_id' => $this->recipientId,
            'state'        => $this->state,
            'timestamp'    => now()->toIso8601String(),
        ];
    }
}
