<?php

namespace SaaSPlatform\Modules\Tickets\Repositories;

use SaaSPlatform\Modules\Tickets\Contracts\Repositories\TicketReplyRepositoryInterface;
use SaaSPlatform\Modules\Tickets\Models\TicketReply;
use Illuminate\Support\Collection;

class TicketReplyRepository implements TicketReplyRepositoryInterface
{
    /**
     * {@inheritDoc}
     */
    public function getByTicketId(int $ticketId): Collection
    {
        return TicketReply::where('ticket_id', $ticketId)
            ->orderBy('created_at')
            ->get();
    }

    /**
     * {@inheritDoc}
     */
    public function create(array $data): TicketReply
    {
        return TicketReply::create($data);
    }
}
