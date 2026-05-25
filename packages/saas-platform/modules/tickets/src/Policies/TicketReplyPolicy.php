<?php

namespace SaaSPlatform\Modules\Tickets\Policies;

use SaaSPlatform\Modules\Tickets\Models\Ticket;
use SaaSPlatform\Modules\Tickets\Models\TicketReply;

/**
 * PHASE 7.1 — TASK 10: Ticket Reply Policy
 */
class TicketReplyPolicy
{
    public function create($user, Ticket $ticket): bool
    {
        if ($ticket->status === Ticket::STATUS_CLOSED) {
            return false;
        }

        if ($user->is_admin) {
            return true;
        }

        if (method_exists($user, 'sites')) {
            $siteIds = $user->sites()->pluck('sites.id')->toArray();
            return in_array($ticket->site_id, $siteIds, true);
        }

        return false;
    }

    public function delete($user, TicketReply $reply): bool
    {
        return $user->is_admin;
    }
}
