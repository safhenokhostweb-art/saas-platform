<?php

namespace SaaSPlatform\Modules\Tickets\Policies;

/**
 * PHASE 7.1 — TASK 10: Ticket Admin Policy
 *
 * Platform-level admin capabilities.
 * Only accessible in central/platform admin context.
 */
class TicketAdminPolicy
{
    public function viewGlobalDashboard($user): bool
    {
        return $user->is_admin && $user->is_platform_admin ?? $user->is_admin;
    }

    public function viewSlaMetrics($user): bool
    {
        return $user->is_admin && (bool) config('feature-flags.tickets.sla', false);
    }

    public function manageAllSiteTickets($user): bool
    {
        return $user->is_admin;
    }

    public function distributeModule($user): bool
    {
        return $user->is_admin;
    }
}
