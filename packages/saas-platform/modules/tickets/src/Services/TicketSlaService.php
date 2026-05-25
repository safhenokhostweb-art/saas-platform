<?php

namespace SaaSPlatform\Modules\Tickets\Services;

use SaaSPlatform\Modules\Tickets\Contracts\Repositories\TicketSlaRepositoryInterface;
use SaaSPlatform\Modules\Tickets\Models\Ticket;

/**
 * PHASE 7.1 — TASK 5: Ticket SLA Service
 *
 * Feature-flagged: only active when config('feature-flags.tickets.sla') is true.
 * Provides SLA computation and breach detection.
 */
class TicketSlaService
{
    public function __construct(
        protected TicketSlaRepositoryInterface $slaRepository,
    ) {}

    public function isEnabled(): bool
    {
        return (bool) config('feature-flags.tickets.sla', false);
    }

    public function isBreached(Ticket $ticket): bool
    {
        if (!$this->isEnabled()) {
            return false;
        }
        return $this->slaRepository->isBreached($ticket);
    }

    public function getMetrics(?int $siteId = null): array
    {
        if (!$this->isEnabled()) {
            return ['enabled' => false];
        }
        return array_merge(['enabled' => true], $this->slaRepository->getSlaMetrics($siteId));
    }

    public function getFirstResponseDeadline(Ticket $ticket): ?\DateTimeInterface
    {
        if (!$this->isEnabled()) {
            return null;
        }
        return $this->slaRepository->getFirstResponseDeadline($ticket);
    }

    public function getResolutionDeadline(Ticket $ticket): ?\DateTimeInterface
    {
        if (!$this->isEnabled()) {
            return null;
        }
        return $this->slaRepository->getResolutionDeadline($ticket);
    }

    public function getBreachedTickets(?int $siteId = null)
    {
        if (!$this->isEnabled()) {
            return collect([]);
        }
        return $this->slaRepository->getBreachedTickets($siteId);
    }

    public function getRemainingMinutes(Ticket $ticket): ?int
    {
        $deadline = $this->getResolutionDeadline($ticket);
        if (!$deadline) {
            return null;
        }
        return max(0, (int) now()->diffInMinutes($deadline, false));
    }
}
