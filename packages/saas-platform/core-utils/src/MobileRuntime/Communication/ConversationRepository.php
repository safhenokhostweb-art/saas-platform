<?php

namespace SaaSPlatform\CoreUtils\MobileRuntime\Communication;

class ConversationRepository
{
    public function findById(string $id): ?Conversation
    {
        return Conversation::find($id);
    }

    public function findByTenant(string $tenantId, int $limit = 50): array
    {
        return Conversation::forTenant($tenantId)
            ->orderBy('updated_at', 'desc')
            ->limit($limit)
            ->get()
            ->toArray();
    }

    public function findForUser(string $userId, string $tenantId): array
    {
        return Conversation::forTenant($tenantId)
            ->forUser($userId)
            ->active()
            ->orderBy('updated_at', 'desc')
            ->get()
            ->toArray();
    }

    public function findByType(string $type, string $tenantId): array
    {
        return Conversation::forTenant($tenantId)
            ->where('type', $type)
            ->orderBy('updated_at', 'desc')
            ->get()
            ->toArray();
    }

    public function countActiveForTenant(string $tenantId): int
    {
        return Conversation::forTenant($tenantId)->active()->count();
    }

    public function updateStatus(string $id, string $status): void
    {
        Conversation::where('id', $id)->update(['status' => $status]);
    }

    public function getRecentMessages(string $conversationId, int $limit = 50): array
    {
        return ConversationMessage::where('conversation_id', $conversationId)
            ->visible()
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get()
            ->reverse()
            ->values()
            ->toArray();
    }

    public function search(string $tenantId, string $query): array
    {
        return Conversation::forTenant($tenantId)
            ->where(fn ($q) => $q
                ->where('title', 'like', "%{$query}%")
                ->orWhereHas('messages', fn ($mq) => $mq->where('content', 'like', "%{$query}%"))
            )
            ->limit(20)
            ->get()
            ->toArray();
    }
}
