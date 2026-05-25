<?php

namespace SaaSPlatform\Modules\Tickets\Services;

use SaaSPlatform\Modules\Tickets\Models\Ticket;
use SaaSPlatform\Modules\Tickets\Contracts\Repositories\TicketRepositoryInterface;

/**
 * PHASE 7.1 — TASK 5: Ticket Priority Service
 */
class TicketPriorityService
{
    public function __construct(
        protected TicketRepositoryInterface $ticketRepository,
        protected TicketActivityService     $activityService,
    ) {}

    public function getAvailablePriorities(): array
    {
        return config('tickets.priorities', [
            Ticket::PRIORITY_LOW,
            Ticket::PRIORITY_NORMAL,
            Ticket::PRIORITY_HIGH,
        ]);
    }

    public function getPriorityLabel(string $priority): string
    {
        return match ($priority) {
            Ticket::PRIORITY_LOW    => 'کم',
            Ticket::PRIORITY_NORMAL => 'معمولی',
            Ticket::PRIORITY_HIGH   => 'فوری',
            default                 => $priority,
        };
    }

    public function getPriorityBadgeClass(string $priority): string
    {
        return match ($priority) {
            Ticket::PRIORITY_HIGH   => 'bg-red-100 text-red-800',
            Ticket::PRIORITY_NORMAL => 'bg-blue-100 text-blue-800',
            Ticket::PRIORITY_LOW    => 'bg-gray-100 text-gray-600',
            default                 => 'bg-gray-100 text-gray-600',
        };
    }

    public function changePriority(Ticket $ticket, string $priority, ?int $actorId = null): Ticket
    {
        $previous = $ticket->priority;
        $ticket   = $this->ticketRepository->update($ticket, ['priority' => $priority]);

        $this->activityService->log($ticket, 'priority_changed', [
            'from'  => $previous,
            'to'    => $priority,
            'actor' => $actorId,
        ]);

        return $ticket;
    }
}
