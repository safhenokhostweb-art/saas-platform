<?php

namespace SaaSPlatform\CoreUtils\MobileRuntime\Inbox;

use Illuminate\Support\Facades\Cache;
use SaaSPlatform\CoreUtils\MobileRuntime\Communication\Conversation;
use SaaSPlatform\CoreUtils\MobileRuntime\Communication\ConversationMessage;
use SaaSPlatform\CoreUtils\MobileRuntime\Communication\ConversationParticipant;
use SaaSPlatform\CoreUtils\MobileRuntime\Presence\PresenceRuntimeManager;

/**
 * Multi-tenant support inbox.
 *
 * Hierarchy: Platform Admin → Customer Sites → Customer Admins → End Users
 * All counters are realtime-capable: call refresh() after Reverb events.
 */
class TenantInboxService
{
    private const CACHE_TTL   = 60;
    private const SLA_WARNING = 3600;  // 1 hour in seconds
    private const SLA_BREACH  = 86400; // 24 hours in seconds

    public function __construct(
        private readonly PresenceRuntimeManager $presence,
    ) {}

    // ── Assigned tenants (platform admin view) ────────────────────────────────

    public function listAssignedTenants(string $agentId, string $agentRole): array
    {
        $cacheKey = "inbox:agent:{$agentId}:tenants";

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($agentId, $agentRole) {
            $query = Conversation::query()
                ->select('tenant_id')
                ->distinct()
                ->where('status', Conversation::STATUS_ACTIVE);

            if ($agentRole !== 'platform_admin') {
                $query->whereHas('participants', fn ($q) =>
                    $q->where('user_id', $agentId)->whereNull('left_at')
                );
            }

            return $query->pluck('tenant_id')
                ->map(fn ($tenantId) => [
                    'tenant_id'          => $tenantId,
                    'open_count'         => $this->countOpenConversations($tenantId),
                    'unread_count'       => $this->countUnreadForAgent($tenantId, $agentId),
                    'sla_warning_count'  => $this->countSlaWarnings($tenantId),
                    'online_user_count'  => count($this->presence->getOnlineUsers($tenantId)),
                ])
                ->values()
                ->all();
        });
    }

    // ── Assigned conversations ────────────────────────────────────────────────

    public function listAssignedConversations(
        string $tenantId,
        string $agentId,
        string $agentRole,
        int $limit = 50,
        ?string $cursor = null
    ): array {
        $query = Conversation::forTenant($tenantId)
            ->active()
            ->with(['participants', 'messages' => fn ($q) => $q->latest()->limit(1)])
            ->orderBy('updated_at', 'desc')
            ->limit($limit);

        if ($agentRole !== 'platform_admin' && $agentRole !== 'customer_admin') {
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
            'data'        => $conversations->map(fn ($c) => $this->enrichConversation($c, $agentId))->values()->all(),
            'next_cursor' => $nextCursor,
            'has_more'    => $nextCursor !== null,
        ];
    }

    // ── Unread counters ───────────────────────────────────────────────────────

    public function getUnreadCount(string $tenantId, string $userId): int
    {
        return $this->countUnreadForAgent($tenantId, $userId);
    }

    public function getTenantUnreadSummary(string $tenantId): array
    {
        $conversations = Conversation::forTenant($tenantId)->active()->get();

        $total     = 0;
        $byConv    = [];

        foreach ($conversations as $conv) {
            $unread = $this->countUnreadInConversation($conv->id);
            if ($unread > 0) {
                $total += $unread;
                $byConv[$conv->id] = $unread;
            }
        }

        return [
            'tenant_id'    => $tenantId,
            'total_unread' => $total,
            'by_conversation' => $byConv,
        ];
    }

    // ── SLA counters ──────────────────────────────────────────────────────────

    public function getSlaCounters(string $tenantId): array
    {
        $conversations = Conversation::forTenant($tenantId)->active()->get();
        $warning = 0;
        $breach  = 0;
        $ok      = 0;

        foreach ($conversations as $conv) {
            $age = now()->diffInSeconds($conv->created_at);
            if ($age >= self::SLA_BREACH) {
                $breach++;
            } elseif ($age >= self::SLA_WARNING) {
                $warning++;
            } else {
                $ok++;
            }
        }

        return [
            'ok'      => $ok,
            'warning' => $warning,
            'breach'  => $breach,
            'total'   => $ok + $warning + $breach,
        ];
    }

    // ── Online user counters ──────────────────────────────────────────────────

    public function getOnlineUserCount(string $tenantId): int
    {
        return count($this->presence->getOnlineUsers($tenantId));
    }

    public function getOnlineUsers(string $tenantId): array
    {
        return $this->presence->getOnlineUsers($tenantId);
    }

    // ── Full inbox summary (for dashboard widget) ─────────────────────────────

    public function getInboxSummary(string $tenantId, string $agentId, string $agentRole): array
    {
        return [
            'tenant_id'         => $tenantId,
            'open_conversations' => $this->countOpenConversations($tenantId),
            'my_unread'         => $this->countUnreadForAgent($tenantId, $agentId),
            'sla'               => $this->getSlaCounters($tenantId),
            'online_users'      => $this->getOnlineUserCount($tenantId),
            'presence_summary'  => $this->presence->getPresenceSummary($tenantId),
            'fetched_at'        => now()->toIso8601String(),
        ];
    }

    // ── Invalidate cache (call after Reverb realtime updates) ─────────────────

    public function invalidateAgentCache(string $agentId): void
    {
        Cache::forget("inbox:agent:{$agentId}:tenants");
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    private function countOpenConversations(string $tenantId): int
    {
        return Conversation::forTenant($tenantId)->active()->count();
    }

    private function countUnreadForAgent(string $tenantId, string $agentId): int
    {
        return (int) ConversationMessage::whereHas('conversation', fn ($q) =>
                $q->forTenant($tenantId)->active()
            )
            ->whereJsonDoesntContain('read_by', $agentId)
            ->where('sender_id', '!=', $agentId)
            ->count();
    }

    private function countUnreadInConversation(string $conversationId): int
    {
        return (int) ConversationMessage::where('conversation_id', $conversationId)
            ->where('is_deleted', false)
            ->count();
    }

    private function countSlaWarnings(string $tenantId): int
    {
        return $this->getSlaCounters($tenantId)['warning'];
    }

    private function enrichConversation(Conversation $conv, string $agentId): array
    {
        $data             = $conv->toArray();
        $data['unread']   = $this->countUnreadForAgent($conv->tenant_id, $agentId);
        $data['sla_age']  = now()->diffInSeconds($conv->created_at);
        $data['sla_status'] = match (true) {
            $data['sla_age'] >= self::SLA_BREACH  => 'breach',
            $data['sla_age'] >= self::SLA_WARNING => 'warning',
            default                               => 'ok',
        };
        return $data;
    }
}
