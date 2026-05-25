<?php

namespace SaaSPlatform\Modules\Tickets\Contracts\Repositories;

use SaaSPlatform\Modules\Tickets\Models\Ticket;
use Illuminate\Support\Collection;

/**
 * PHASE 7.1 — TASK 4: Ticket Status Repository Interface
 */
interface TicketStatusRepositoryInterface
{
    public function recordTransition(Ticket $ticket, string $from, string $to, ?int $userId): void;
    public function getHistory(int $ticketId): Collection;
    public function getCountsByStatus(?int $siteId = null): array;
    public function getAverageResolutionTime(?int $siteId = null): ?float;
}
