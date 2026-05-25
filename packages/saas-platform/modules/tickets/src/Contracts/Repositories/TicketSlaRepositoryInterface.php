<?php

namespace SaaSPlatform\Modules\Tickets\Contracts\Repositories;

use SaaSPlatform\Modules\Tickets\Models\Ticket;
use Illuminate\Support\Collection;

/**
 * PHASE 7.1 — TASK 4: Ticket SLA Repository Interface
 */
interface TicketSlaRepositoryInterface
{
    public function getBreachedTickets(?int $siteId = null): Collection;
    public function getTicketsApproachingBreach(?int $siteId = null, int $withinMinutes = 30): Collection;
    public function getSlaMetrics(?int $siteId = null): array;
    public function isBreached(Ticket $ticket): bool;
    public function getFirstResponseDeadline(Ticket $ticket): ?\DateTimeInterface;
    public function getResolutionDeadline(Ticket $ticket): ?\DateTimeInterface;
}
