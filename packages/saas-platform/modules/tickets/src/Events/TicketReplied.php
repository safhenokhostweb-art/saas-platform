<?php

namespace SaaSPlatform\Modules\Tickets\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use SaaSPlatform\Modules\Tickets\Models\Ticket;
use SaaSPlatform\Modules\Tickets\Models\TicketReply;

/**
 * PHASE 7.2 — TASK 7: Canonical reply event.
 * Dispatched by TicketService::addReply() after a reply is persisted.
 */
class TicketReplied
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Ticket      $ticket,
        public readonly TicketReply $reply,
    ) {}
}
