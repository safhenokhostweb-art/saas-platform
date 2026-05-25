<?php

namespace SaaSPlatform\Modules\Tickets\Services;

use SaaSPlatform\Modules\Tickets\Models\Ticket;
use SaaSPlatform\Modules\Tickets\Contracts\Repositories\TicketRepositoryInterface;

/**
 * PHASE 7.1 — TASK 5: Ticket Assignment Service
 */
class TicketAssignmentService
{
    public function __construct(
        protected TicketRepositoryInterface $ticketRepository,
        protected TicketActivityService     $activityService,
        protected TicketNotificationService $notificationService,
    ) {}

    public function assign(Ticket $ticket, ?int $userId, ?int $actorId = null): Ticket
    {
        $previous = $ticket->assigned_to;
        $ticket   = $this->ticketRepository->update($ticket, ['assigned_to' => $userId]);

        $this->activityService->log($ticket, 'assigned', [
            'previous_assignee' => $previous,
            'new_assignee'      => $userId,
            'actor'             => $actorId,
        ]);

        if ($userId && $userId !== $previous) {
            $this->notificationService->onAssigned($ticket, $userId);
        }

        return $ticket;
    }

    public function unassign(Ticket $ticket, ?int $actorId = null): Ticket
    {
        return $this->assign($ticket, null, $actorId);
    }

    public function autoAssign(Ticket $ticket): Ticket
    {
        // FUTURE: implement round-robin or load-based auto-assign
        return $ticket;
    }
}
