<?php

namespace App\Services\PlatformMessaging;

use App\Models\PlatformConversation;
use App\Models\PlatformConversationParticipant;
use App\Models\PlatformMessage;
use Illuminate\Support\Str;

class PlatformMessageService
{
    public function send(
        string $conversationId,
        int    $senderId,
        string $senderType,
        string $content,
        string $type = PlatformMessage::TYPE_TEXT,
        ?string $replyToId = null,
        array   $metadata = []
    ): PlatformMessage {
        // Guard: only platform actors can send
        if (in_array($senderType, PlatformConversationParticipant::FORBIDDEN_TYPES, true)) {
            throw new \InvalidArgumentException(
                "Sender type '{$senderType}' cannot send platform messages."
            );
        }

        $message = PlatformMessage::create([
            'id'                  => Str::uuid()->toString(),
            'conversation_id'     => $conversationId,
            'sender_id'           => $senderId,
            'sender_type'         => $senderType,
            'message_type'        => $type,
            'content'             => $content,
            'reply_to_message_id' => $replyToId,
            'metadata'            => $metadata,
        ]);

        PlatformConversation::where('id', $conversationId)
            ->update(['last_message_at' => now()]);

        return $message;
    }

    public function paginate(string $conversationId, int $limit = 50, ?string $before = null): array
    {
        $query = PlatformMessage::where('conversation_id', $conversationId)
            ->visible()
            ->orderByDesc('created_at')
            ->limit($limit);

        if ($before) {
            $query->where('created_at', '<', $before);
        }

        $messages   = $query->get();
        $nextCursor = $messages->count() === $limit ? $messages->last()?->created_at?->toIso8601String() : null;

        return [
            'data'        => $messages->reverse()->values(),
            'next_cursor' => $nextCursor,
            'has_more'    => $nextCursor !== null,
        ];
    }

    public function markRead(string $conversationId, int $readerId, string $readerType): void
    {
        PlatformConversationParticipant::where('conversation_id', $conversationId)
            ->where('participant_id', $readerId)
            ->where('participant_type', $readerType)
            ->update(['last_read_at' => now(), 'unread_count' => 0]);
    }
}
