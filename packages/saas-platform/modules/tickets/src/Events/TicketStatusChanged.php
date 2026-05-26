<?php

namespace SaaSPlatform\Modules\Tickets\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use SaaSPlatform\Modules\Tickets\Models\Ticket;

/**
 * PHASE 7.2 — TASK 7: Canonical status-change event.
 * Dispatched by TicketService::updateStatus() after the status is persisted.
 */
class TicketStatusChanged
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Ticket $ticket,
        public readonly string $previousStatus,
        public readonly string $newStatus,
    ) {}
}
