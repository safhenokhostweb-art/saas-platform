<?php

namespace SaaSPlatform\CoreUtils\MobileRuntime\Inbox;

use Illuminate\Support\Facades\DB;
use SaaSPlatform\CoreUtils\MobileRuntime\Communication\Conversation;
use SaaSPlatform\CoreUtils\MobileRuntime\Communication\ConversationMessage;
use SaaSPlatform\CoreUtils\MobileRuntime\Communication\ConversationParticipant;

/**
 * Unified conversation hub — single inbox for platform admins, support agents,
 * and customer administrators.
 *
 * Provides search, filter, unread, priority, status, assignment views.
 * All mutations broadcast via Reverb so clients stay synchronized in realtime.
 */
class ConversationHubService
{
    // Filter constants
    public const FILTER_ALL        = 'all';
    public const FILTER_UNREAD     = 'unread';
    public const FILTER_ASSIGNED   = 'assigned';
    public const FILTER_UNASSIGNED = 'unassigned';
    public const FILTER_ESCALATED  = 'escalated';

    // Priority levels
    public const PRIORITY_LOW    = 'low';
    public const PRIORITY_NORMAL = 'normal';
    public const PRIORITY_HIGH   = 'high';
    public const PRIORITY_URGENT = 'urgent';

    // ── Inbox retrieval ───────────────────────────────────────────────────────

    public function getHub(
        string $agentId,
        string $agentRole,
        array $tenantIds,
        array $options = []
    ): array {
        $filter   = $options['filter']   ?? self::FILTER_ALL;
        $search   = $options['search']   ?? null;
        $status   = $options['status']   ?? null;
        $priority = $options['priority'] ?? null;
        $limit    = (int) ($options['limit'] ?? 50);
        $cursor   = $options['cursor']   ?? null;

        $query = Conversation::query()
            ->whereIn('tenant_id', $tenantIds)
            ->with(['participants', 'messages' => fn ($q) => $q->latest()->limit(1)])
            ->orderBy('updated_at', 'desc')
            ->limit($limit);

        // Status filter
        if ($status) {
            $query->where('status', $status);
        } else {
            $query->active();
        }

        // Priority filter via metadata JSON
        if ($priority) {
            $query->whereJsonContains('metadata->priority', $priority);
        }

        // Search across title and last message content
        if ($search) {
            $query->where(fn ($q) => $q
                ->where('title', 'like', "%{$search}%")
                ->orWhereHas('messages', fn ($mq) =>
                    $mq->where('content', 'like', "%{$search}%")
                )
            );
        }

        // Unread filter — conversations with unread for this agent
        if ($filter === self::FILTER_UNREAD) {
            $query->whereHas('messages', fn ($mq) =>
                $mq->whereJsonDoesntContain('read_by', $agentId)
                   ->where('sender_id', '!=', $agentId)
                   ->where('is_deleted', false)
            );
        }

        // Assignment filters — uses metadata.assigned_to
        if ($filter === self::FILTER_ASSIGNED) {
            $query->whereJsonContains('metadata->assigned_to', $agentId);
        } elseif ($filter === self::FILTER_UNASSIGNED) {
            $query->where(fn ($q) => $q
                ->whereNull(DB::raw("JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.assigned_to'))"))
                ->orWhereJsonLength('metadata->assigned_to', 0)
            );
        }

        // Escalated filter
        if ($filter === self::FILTER_ESCALATED) {
            $query->whereJsonContains('metadata->escalated', true);
        }

        // Agent scope — non-platform-admins only see their tenants/assignments
        if ($agentRole !== 'platform_admin') {
            $query->whereHas('participants', fn ($q) =>
                $q->where('user_id', $agentId)->whereNull('left_at')
            );
        }

        if ($cursor) {
            $query->where('updated_at', '<', $cursor);
        }

        $conversations = $query->get();
        $nextCursor    = $conversations->count() === $limit
            ? $conversations->last()?->updated_at?->toIso8601String()
            : null;

        return [
            'data'        => $conversations->map(fn ($c) => $this->buildConversationItem($c, $agentId))->values()->all(),
            'next_cursor' => $nextCursor,
            'has_more'    => $nextCursor !== null,
            'filter'      => $filter,
            'fetched_at'  => now()->toIso8601String(),
        ];
    }

    // ── Per-conversation detail ───────────────────────────────────────────────

    public function getConversationDetail(string $conversationId, string $agentId): ?array
    {
        $conv = Conversation::with(['participants', 'messages' => fn ($q) => $q->latest()->limit(20)])
            ->find($conversationId);

        if (! $conv) {
            return null;
        }

        return $this->buildConversationItem($conv, $agentId, detailed: true);
    }

    // ── Bulk operations ───────────────────────────────────────────────────────

    public function bulkMarkRead(array $conversationIds, string $agentId): int
    {
        $updated = 0;
        foreach ($conversationIds as $convId) {
            $messages = ConversationMessage::where('conversation_id', $convId)
                ->whereJsonDoesntContain('read_by', $agentId)
                ->get();
            foreach ($messages as $msg) {
                $readBy   = $msg->read_by ?? [];
                $readBy[] = $agentId;
                $msg->update(['read_by' => array_unique($readBy)]);
                $updated++;
            }
            ConversationParticipant::where('conversation_id', $convId)
                ->where('user_id', $agentId)
                ->update(['last_read_at' => now()]);
        }
        return $updated;
    }

    public function setPriority(string $conversationId, string $priority): void
    {
        Conversation::where('id', $conversationId)->update([
            'metadata' => DB::raw("JSON_SET(COALESCE(metadata, '{}'), '$.priority', '{$priority}')"),
        ]);
    }

    // ── Unread aggregate ──────────────────────────────────────────────────────

    public function getTotalUnread(string $agentId, array $tenantIds): int
    {
        return (int) ConversationMessage::whereHas('conversation', fn ($q) =>
                $q->whereIn('tenant_id', $tenantIds)->active()
            )
            ->whereJsonDoesntContain('read_by', $agentId)
            ->where('sender_id', '!=', $agentId)
            ->where('is_deleted', false)
            ->count();
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    private function buildConversationItem(Conversation $conv, string $agentId, bool $detailed = false): array
    {
        $data = $conv->toArray();

        // Unread count for this agent
        $data['unread_count'] = ConversationMessage::where('conversation_id', $conv->id)
            ->whereJsonDoesntContain('read_by', $agentId)
            ->where('sender_id', '!=', $agentId)
            ->where('is_deleted', false)
            ->count();

        // Priority from metadata (default normal)
        $data['priority']    = $conv->metadata['priority']    ?? self::PRIORITY_NORMAL;
        $data['assigned_to'] = $conv->metadata['assigned_to'] ?? null;
        $data['escalated']   = $conv->metadata['escalated']   ?? false;

        // Last message preview
        $lastMsg             = $conv->messages->first();
        $data['last_message'] = $lastMsg ? [
            'id'         => $lastMsg->id,
            'content'    => mb_substr($lastMsg->content, 0, 120),
            'sender_id'  => $lastMsg->sender_id,
            'created_at' => $lastMsg->created_at?->toIso8601String(),
        ] : null;

        if ($detailed) {
            $data['messages'] = $conv->messages->reverse()->values()->toArray();
        } else {
            unset($data['messages']);
        }

        return $data;
    }
}
