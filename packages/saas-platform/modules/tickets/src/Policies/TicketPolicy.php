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
        // Platform admin: همه تیکت‌های سیستم (super admin)
        if ($user->is_admin && !$user->is_site_admin) {
            return true;
        }
        // Site admin: فقط تیکت‌های site خودش
        if ($user->is_site_admin) {
            return $ticket->site_id == $user->site_id;
        }
        // Customer: فقط تیکت‌های سایت خودش
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

    public function delete($user, Ticket $ticket): bool
    {
        // Platform admin: می‌تواند هر تیکتی را حذف کند
        if ($user->is_admin && !$user->is_site_admin) {
            return true;
        }
        // Site admin: فقط تیکت‌های سایت خودش
        if ($user->is_site_admin) {
            return $ticket->site_id == $user->site_id;
        }
        return false;
    }
}
