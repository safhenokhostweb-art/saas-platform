<?php

namespace SaaSPlatform\Modules\Tickets\Services;

use SaaSPlatform\Modules\Tickets\Models\Ticket;

/**
 * PHASE 7.1 — TASK 5: Ticket Status Service
 *
 * Delegates to TicketLifecycleService for transitions.
 * Provides status metadata for UI rendering.
 */
class TicketStatusService
{
    public function __construct(
        protected TicketLifecycleService $lifecycleService,
    ) {}

    public function getAvailableStatuses(): array
    {
        return config('tickets.statuses', [
            Ticket::STATUS_OPEN,
            Ticket::STATUS_IN_PROGRESS,
            Ticket::STATUS_CLOSED,
        ]);
    }

    public function getStatusLabel(string $status): string
    {
        return match ($status) {
            Ticket::STATUS_OPEN        => 'باز',
            Ticket::STATUS_IN_PROGRESS => 'در حال بررسی',
            Ticket::STATUS_CLOSED      => 'بسته شده',
            default                    => $status,
        };
    }

    public function getStatusColor(string $status): string
    {
        return match ($status) {
            Ticket::STATUS_OPEN        => 'yellow',
            Ticket::STATUS_IN_PROGRESS => 'blue',
            Ticket::STATUS_CLOSED      => 'gray',
            default                    => 'gray',
        };
    }

    public function transition(Ticket $ticket, string $status, ?int $actorId = null): Ticket
    {
        return $this->lifecycleService->transition($ticket, $status, $actorId);
    }

    public function getStatusBadgeClass(string $status): string
    {
        return match ($status) {
            Ticket::STATUS_OPEN        => 'bg-yellow-100 text-yellow-800',
            Ticket::STATUS_IN_PROGRESS => 'bg-blue-100 text-blue-800',
            Ticket::STATUS_CLOSED      => 'bg-gray-100 text-gray-700',
            default                    => 'bg-gray-100 text-gray-700',
        };
    }
}
