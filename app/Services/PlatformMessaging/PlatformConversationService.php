<?php

namespace App\Services\PlatformMessaging;

use App\Models\PlatformConversation;
use App\Models\PlatformConversationParticipant;
use Illuminate\Support\Str;

/**
 * DOMAIN B — Platform Communication Runtime service.
 *
 * All conversations created here are between platform-level principals only:
 * Main Admin, Platform Staff, Customer Admin.
 *
 * End-user accounts are structurally prevented by model boot() guards.
 */
class PlatformConversationService
{
    public function __construct(
        private PlatformParticipantService $participants
    ) {}

    public function create(
        string $subject,
        int    $createdById,
        string $createdByType,
        string $type = PlatformConversation::TYPE_DIRECT,
        array  $metadata = []
    ): PlatformConversation {
        $this->assertAllowedParticipantType($createdByType);

        $conversation = PlatformConversation::create([
            'id'              => Str::uuid()->toString(),
            'type'            => $type,
            'subject'         => $subject,
            'created_by'      => $createdById,
            'created_by_type' => $createdByType,
            'metadata'        => $metadata,
            'status'          => PlatformConversation::STATUS_ACTIVE,
        ]);

        $this->participants->add(
            $conversation->id,
            $createdById,
            $createdByType,
            PlatformConversationParticipant::ROLE_OWNER
        );

        return $conversation;
    }

    public function getForParticipant(int $participantId, string $participantType): \Illuminate\Database\Eloquent\Collection
    {
        $this->assertAllowedParticipantType($participantType);

        return PlatformConversation::active()
            ->forParticipant($participantId, $participantType)
            ->with(['participants', 'messages' => fn ($q) => $q->latest()->limit(1)])
            ->orderByDesc('last_message_at')
            ->get();
    }

    public function archive(string $id): void
    {
        PlatformConversation::where('id', $id)
            ->update(['status' => PlatformConversation::STATUS_ARCHIVED]);
    }

    public function close(string $id): void
    {
        PlatformConversation::where('id', $id)
            ->update(['status' => PlatformConversation::STATUS_CLOSED]);
    }

    private function assertAllowedParticipantType(string $type): void
    {
        if (!in_array($type, PlatformConversation::ALLOWED_PARTICIPANT_TYPES, true)) {
            throw new \InvalidArgumentException(
                "Participant type '{$type}' is not allowed in platform conversations. Allowed: "
                . implode(', ', PlatformConversation::ALLOWED_PARTICIPANT_TYPES)
            );
        }
    }
}
