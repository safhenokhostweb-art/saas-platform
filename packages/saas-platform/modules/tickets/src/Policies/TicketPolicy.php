<?php

namespace SaaSPlatform\Modules\Tickets\Policies;

use SaaSPlatform\Modules\Tickets\Models\Ticket;

/**
 * PHASE 7.1 — TASK 10: Ticket Policy
 *
 * Gate checks for ticket access.
 * Consumed by TicketGateService and @can directives.
 */
class TicketPolicy
{
    public function viewAny($user): bool
    {
        return $user !== null;
    }

    public function view($user, Ticket $ticket): bool
    {
        if ($user->is_admin) {
            return true;
        }
        // Customer can only view tickets belonging to their site
        if (method_exists($user, 'sites')) {
            $siteIds = $user->sites()->pluck('sites.id')->toArray();
            return in_array($ticket->site_id, $siteIds, true);
        }
        return false;
    }

    public function create($user): bool
    {
        return $user !== null;
    }

    public function reply($user, Ticket $ticket): bool
    {
        if ($ticket->status === Ticket::STATUS_CLOSED) {
            return false;
        }
        return $this->view($user, $ticket);
    }

    public function updateStatus($user, Ticket $ticket): bool
    {
        return $user->is_admin;
    }

    public function assign($user, Ticket $ticket): bool
    {
        return $user->is_admin;
    }

    public function changePriority($user, Ticket $ticket): bool
    {
        return $user->is_admin;
    }
}
