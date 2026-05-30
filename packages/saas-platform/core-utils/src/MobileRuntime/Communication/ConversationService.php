<?php

namespace SaaSPlatform\CoreUtils\MobileRuntime\Communication;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ConversationService
{
    public function createConversation(
        string $tenantId,
        string $createdBy,
        array $participantIds,
        string $type = Conversation::TYPE_DIRECT,
        array $metadata = []
    ): array {
        $conversation = Conversation::create([
            'tenant_id'  => $tenantId,
            'title'      => $metadata['title'] ?? $this->generateTitle($type, count($participantIds)),
            'type'       => $type,
            'status'     => Conversation::STATUS_ACTIVE,
            'metadata'   => $metadata,
            'created_by' => $createdBy,
        ]);

        foreach ($participantIds as $index => $userId) {
            $role = ($index === 0) ? ConversationParticipant::ROLE_OWNER : ConversationParticipant::ROLE_PARTICIPANT;
            $this->addParticipant($conversation->id, (string) $userId, ConversationParticipant::USER_TYPE_END_USER, $role);
        }

        Log::info('mobile.conversation.created', ['id' => $conversation->id, 'tenant_id' => $tenantId, 'type' => $type]);
        return $conversation->toArray();
    }

    public function addParticipant(
        string $conversationId,
        string $userId,
        string $userType = ConversationParticipant::USER_TYPE_END_USER,
        string $role = ConversationParticipant::ROLE_PARTICIPANT
    ): void {
        $existing = ConversationParticipant::where('conversation_id', $conversationId)
            ->where('user_id', $userId)
            ->whereNull('left_at')
            ->first();

        if (! $existing) {
            ConversationParticipant::create([
                'conversation_id' => $conversationId,
                'user_id'         => $userId,
                'user_type'       => $userType,
                'role'            => $role,
                'joined_at'       => now(),
            ]);
        }
    }

    public function removeParticipant(string $conversationId, string $userId): void
    {
        ConversationParticipant::where('conversation_id', $conversationId)
            ->where('user_id', $userId)
            ->whereNull('left_at')
            ->update(['left_at' => now()]);
    }

    public function sendMessage(
        string $conversationId,
        string $senderId,
        string $senderType,
        string $content,
        string $type = ConversationMessage::TYPE_TEXT,
        array $metadata = []
    ): array {
        $message = ConversationMessage::create([
            'conversation_id' => $conversationId,
            'sender_id'       => $senderId,
            'sender_type'     => $senderType,
            'message_type'    => $type,
            'content'         => $content,
            'metadata'        => $metadata,
            'is_deleted'      => false,
            'read_by'         => [$senderId],
        ]);

        Log::debug('mobile.message.sent', ['conversation_id' => $conversationId, 'message_id' => $message->id]);
        return $message->toArray();
    }

    public function getMessages(
        string $conversationId,
        string $tenantId,
        int $limit = 50,
        ?string $cursor = null
    ): array {
        $query = ConversationMessage::where('conversation_id', $conversationId)
            ->visible()
            ->orderBy('created_at', 'desc')
            ->limit($limit);

        if ($cursor) {
            $query->where('created_at', '<', $cursor);
        }

        $messages  = $query->get();
        $nextCursor = $messages->count() === $limit ? $messages->last()?->created_at?->toIso8601String() : null;

        return [
            'data'        => $messages->reverse()->values()->toArray(),
            'next_cursor' => $nextCursor,
            'has_more'    => $nextCursor !== null,
        ];
    }

    public function markRead(string $conversationId, string $userId, string $messageId): void
    {
        $message = ConversationMessage::find($messageId);
        if (! $message) return;

        $readBy = $message->read_by ?? [];
        if (! in_array($userId, $readBy, true)) {
            $readBy[] = $userId;
            $message->update(['read_by' => $readBy]);
        }

        ConversationParticipant::where('conversation_id', $conversationId)
            ->where('user_id', $userId)
            ->update(['last_read_at' => now()]);
    }

    public function archiveConversation(string $conversationId, string $tenantId): void
    {
        Conversation::forTenant($tenantId)->where('id', $conversationId)
            ->update(['status' => Conversation::STATUS_ARCHIVED]);
    }

    public function getConversationsForUser(string $userId, string $tenantId): array
    {
        return Conversation::forTenant($tenantId)
            ->forUser($userId)
            ->active()
            ->with(['participants', 'messages' => fn ($q) => $q->latest()->limit(1)])
            ->orderBy('updated_at', 'desc')
            ->get()
            ->toArray();
    }

    public function linkToTicket(string $conversationId, string $ticketId): void
    {
        Conversation::where('id', $conversationId)->update([
            'type'     => Conversation::TYPE_TICKET_LINKED,
            'metadata' => DB::raw("JSON_SET(COALESCE(metadata, '{}'), '$.ticket_id', '{$ticketId}')"),
        ]);
    }

    private function generateTitle(string $type, int $participantCount): string
    {
        return match ($type) {
            Conversation::TYPE_DIRECT  => 'Direct Message',
            Conversation::TYPE_SUPPORT => 'Support Chat',
            Conversation::TYPE_GROUP   => "Group ({$participantCount})",
            default                    => 'Conversation',
        };
    }
}
