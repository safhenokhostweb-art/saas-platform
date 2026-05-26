<?php

namespace SaaSPlatform\Modules\Tickets\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use SaaSPlatform\Modules\Tickets\Models\Ticket;

/**
 * PHASE 7.2 — TASK 7: Canonical ticket creation event.
 * Dispatched by TicketService::create() after a ticket is persisted.
 */
class TicketCreated
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Ticket $ticket,
    ) {}
}
