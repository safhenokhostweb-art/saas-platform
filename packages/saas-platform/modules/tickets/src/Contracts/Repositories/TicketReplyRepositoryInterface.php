<?php

namespace SaaSPlatform\Modules\Tickets\Contracts\Repositories;

use SaaSPlatform\Modules\Tickets\Models\TicketReply;
use Illuminate\Support\Collection;

interface TicketReplyRepositoryInterface
{
    /**
     * Get all replies for a ticket.
     */
    public function getByTicketId(int $ticketId): Collection;

    /**
     * Create a new reply.
     */
    public function create(array $data): TicketReply;
}
