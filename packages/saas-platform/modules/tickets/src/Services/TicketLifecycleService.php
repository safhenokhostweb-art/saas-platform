<?php

namespace SaaSPlatform\Modules\Tickets\Services;

use SaaSPlatform\Modules\Tickets\Models\Ticket;
use SaaSPlatform\Modules\Tickets\Contracts\Repositories\TicketRepositoryInterface;
use SaaSPlatform\Modules\Tickets\Contracts\Repositories\TicketStatusRepositoryInterface;

/**
 * PHASE 7.1 — TASK 5: Ticket Lifecycle Service
 *
 * Owns the ticket state machine.
 * All status transitions MUST go through this service.
 *
 * State machine:
 *   open → in_progress → closed
 *   closed → open (reopen)
 */
class TicketLifecycleService
{
    protected array $allowedTransitions = [
        Ticket::STATUS_OPEN        => [Ticket::STATUS_IN_PROGRESS, Ticket::STATUS_CLOSED],
        Ticket::STATUS_IN_PROGRESS => [Ticket::STATUS_CLOSED, Ticket::STATUS_OPEN],
        Ticket::STATUS_CLOSED      => [Ticket::STATUS_OPEN],
    ];

    public function __construct(
        protected TicketRepositoryInterface       $ticketRepository,
        protected TicketStatusRepositoryInterface $statusRepository,
        protected TicketActivityService           $activityService,
        protected TicketNotificationService       $notificationService,
    ) {}

    public function canTransition(Ticket $ticket, string $newStatus): bool
    {
        return in_array($newStatus, $this->allowedTransitions[$ticket->status] ?? [], true);
    }

    public function transition(Ticket $ticket, string $newStatus, ?int $actorId = null): Ticket
    {
        if (!$this->canTransition($ticket, $newStatus)) {
            throw new \LogicException(
                "Invalid transition from [{$ticket->status}] to [{$newStatus}]."
            );
        }

        $oldStatus = $ticket->status;
        $updates   = ['status' => $newStatus];

        if ($newStatus === Ticket::STATUS_CLOSED) {
            $updates['closed_at']   = now();
            $updates['resolved_at'] = now();
        } elseif ($oldStatus === Ticket::STATUS_CLOSED) {
            $updates['closed_at']   = null;
            $updates['resolved_at'] = null;
        }

        $ticket = $this->ticketRepository->update($ticket, $updates);

        $this->statusRepository->recordTransition($ticket, $oldStatus, $newStatus, $actorId);
        $this->activityService->log($ticket, 'status_changed', [
            'from'  => $oldStatus,
            'to'    => $newStatus,
            'actor' => $actorId,
        ]);
        $this->notificationService->onStatusChanged($ticket, $oldStatus, $newStatus);

        return $ticket;
    }

    public function autoPromoteOnReply(Ticket $ticket): Ticket
    {
        if ($ticket->status === Ticket::STATUS_OPEN) {
            return $this->transition($ticket, Ticket::STATUS_IN_PROGRESS);
        }
        return $ticket;
    }

    public function allowedNextStatuses(Ticket $ticket): array
    {
        return $this->allowedTransitions[$ticket->status] ?? [];
    }
}
