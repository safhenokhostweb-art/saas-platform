<?php

namespace SaaSPlatform\Modules\Tickets\Services;

use SaaSPlatform\Modules\Tickets\Contracts\Repositories\TicketRepositoryInterface;
use SaaSPlatform\Modules\Tickets\Contracts\Repositories\TicketSlaRepositoryInterface;

/**
 * PHASE 7.1 — TASK 5: Ticket Monitoring Service
 *
 * Provides platform-level monitoring of ticket health across all sites.
 * Used by platform admin only — NEVER exposed in tenant/customer views.
 */
class TicketMonitoringService
{
    public function __construct(
        protected TicketRepositoryInterface    $ticketRepository,
        protected TicketSlaRepositoryInterface $slaRepository,
        protected TicketSlaService             $slaService,
    ) {}

    public function getGlobalStats(): array
    {
        $stats = $this->ticketRepository->getStats();
        $sla   = $this->slaService->getMetrics();

        return array_merge($stats, [
            'sla'              => $sla,
            'generated_at'     => now()->toIso8601String(),
        ]);
    }

    public function getHighPriorityOpen(): int
    {
        return \SaaSPlatform\Modules\Tickets\Models\Ticket::query()
            ->where('priority', \SaaSPlatform\Modules\Tickets\Models\Ticket::PRIORITY_HIGH)
            ->whereIn('status', [
                \SaaSPlatform\Modules\Tickets\Models\Ticket::STATUS_OPEN,
                \SaaSPlatform\Modules\Tickets\Models\Ticket::STATUS_IN_PROGRESS,
            ])
            ->count();
    }

    public function getUnassignedCount(): int
    {
        return \SaaSPlatform\Modules\Tickets\Models\Ticket::query()
            ->whereNull('assigned_to')
            ->whereIn('status', [
                \SaaSPlatform\Modules\Tickets\Models\Ticket::STATUS_OPEN,
                \SaaSPlatform\Modules\Tickets\Models\Ticket::STATUS_IN_PROGRESS,
            ])
            ->count();
    }

    public function healthCheck(): array
    {
        return [
            'status'            => 'ok',
            'global_stats'      => $this->getGlobalStats(),
            'high_priority_open' => $this->getHighPriorityOpen(),
            'unassigned'        => $this->getUnassignedCount(),
            'sla_breached'      => $this->slaService->isEnabled()
                ? $this->slaRepository->getBreachedTickets()->count()
                : null,
        ];
    }
}
