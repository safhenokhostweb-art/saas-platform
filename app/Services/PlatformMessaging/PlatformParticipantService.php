<?php

namespace App\Services\PlatformMessaging;

use App\Models\PlatformConversationParticipant;

class PlatformParticipantService
{
    public function add(
        string $conversationId,
        int    $participantId,
        string $participantType,
        string $role = PlatformConversationParticipant::ROLE_PARTICIPANT,
        ?string $displayName = null
    ): void {
        // Model boot() will throw if participant_type is forbidden
        PlatformConversationParticipant::firstOrCreate(
            [
                'conversation_id'  => $conversationId,
                'participant_id'   => $participantId,
                'participant_type' => $participantType,
            ],
            [
                'role'         => $role,
                'display_name' => $displayName,
                'joined_at'    => now(),
            ]
        );
    }

    public function remove(string $conversationId, int $participantId, string $participantType): void
    {
        PlatformConversationParticipant::where('conversation_id', $conversationId)
            ->where('participant_id', $participantId)
            ->where('participant_type', $participantType)
            ->whereNull('left_at')
            ->update(['left_at' => now()]);
    }

    public function isParticipant(string $conversationId, int $participantId, string $participantType): bool
    {
        return PlatformConversationParticipant::where('conversation_id', $conversationId)
            ->where('participant_id', $participantId)
            ->where('participant_type', $participantType)
            ->whereNull('left_at')
            ->exists();
    }
}
