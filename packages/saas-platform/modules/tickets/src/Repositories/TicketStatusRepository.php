<?php

namespace SaaSPlatform\Modules\Tickets\Repositories;

use SaaSPlatform\Modules\Tickets\Contracts\Repositories\TicketStatusRepositoryInterface;
use SaaSPlatform\Modules\Tickets\Models\Ticket;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * PHASE 7.1 — TASK 4: Ticket Status Repository
 *
 * Tracks status transitions and provides aggregated status data.
 * Uses the tickets table directly (no separate history table yet —
 * history table can be added in a future schema version upgrade).
 */
class TicketStatusRepository implements TicketStatusRepositoryInterface
{
    public function recordTransition(Ticket $ticket, string $from, string $to, ?int $userId): void
    {
        // FUTURE: write to ticket_status_transitions table when schema_version >= 2
        // For now, status is directly on the tickets row — transitions are implicit
    }

    public function getHistory(int $ticketId): Collection
    {
        // FUTURE: return from ticket_status_transitions table
        return collect([]);
    }

    public function getCountsByStatus(?int $siteId = null): array
    {
        $query = Ticket::query();

        if ($siteId) {
            $query->where('site_id', $siteId);
        }

        $counts = $query->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        return array_merge([
            Ticket::STATUS_OPEN        => 0,
            Ticket::STATUS_IN_PROGRESS => 0,
            Ticket::STATUS_CLOSED      => 0,
        ], $counts);
    }

    public function getAverageResolutionTime(?int $siteId = null): ?float
    {
        $query = Ticket::whereNotNull('resolved_at');

        if ($siteId) {
            $query->where('site_id', $siteId);
        }

        $result = $query->selectRaw(
            'AVG(TIMESTAMPDIFF(MINUTE, created_at, resolved_at)) as avg_minutes'
        )->value('avg_minutes');

        return $result !== null ? (float) $result : null;
    }
}
