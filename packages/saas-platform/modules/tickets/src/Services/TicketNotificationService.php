<?php

namespace SaaSPlatform\Modules\Tickets\Services;

use SaaSPlatform\Modules\Tickets\Models\Ticket;

/**
 * PHASE 7.1 — TASK 5: Ticket Notification Service
 *
 * Decoupled notification dispatch. Fires Laravel events/notifications.
 * Concrete notification classes live in the package's Notifications/ directory.
 */
class TicketNotificationService
{
    public function onCreated(Ticket $ticket): void
    {
        // FUTURE: dispatch TicketCreated event → queue notification to assignee + admin
    }

    public function onStatusChanged(Ticket $ticket, string $from, string $to): void
    {
        // FUTURE: notify customer on close, notify staff on reopen
    }

    public function onAssigned(Ticket $ticket, int $assigneeId): void
    {
        // FUTURE: notify the newly assigned user
    }

    public function onReplyAdded(Ticket $ticket, int $replyId, string $authorType): void
    {
        // FUTURE: notify customer on staff reply, notify staff on customer reply
    }

    public function onSlaBreached(Ticket $ticket): void
    {
        // FUTURE: alert monitoring + assigned user
    }
}
