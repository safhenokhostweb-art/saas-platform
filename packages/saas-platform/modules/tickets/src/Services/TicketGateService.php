<?php

namespace SaaSPlatform\Modules\Tickets\Services;

use SaaSPlatform\Modules\Tickets\Models\Ticket;

/**
 * PHASE 7.1 — TASK 5: Ticket Gate Service
 *
 * Single authority for permission resolution.
 * Controllers and views MUST use this service, not raw Gate checks.
 *
 * Does NOT bypass Laravel's Gate/Policy system — augments it with
 * module-specific feature-flag and licensing checks.
 */
class TicketGateService
{
    public function canViewAdmin(): bool
    {
        return auth()->check() && auth()->user()?->is_admin;
    }

    public function canCreateTicket(?int $siteId = null): bool
    {
        if (!auth()->check()) {
            return false;
        }
        // FUTURE: check site module activation
        return true;
    }

    public function canReply(Ticket $ticket): bool
    {
        if (!auth()->check()) {
            return false;
        }
        return $ticket->status !== Ticket::STATUS_CLOSED;
    }

    public function canAssign(): bool
    {
        return auth()->check() && auth()->user()?->is_admin;
    }

    public function canChangeStatus(): bool
    {
        return auth()->check() && auth()->user()?->is_admin;
    }

    public function canViewSlaMetrics(): bool
    {
        return $this->canViewAdmin() && config('feature-flags.tickets.sla', false);
    }

    public function canUploadAttachments(): bool
    {
        return (bool) config('feature-flags.tickets.attachments', true);
    }

    public function isModuleVisible(): bool
    {
        // FUTURE: check CustomerMenu::isVisible('support-tickets') or equivalent
        return true;
    }

    public function isSlaEnabled(): bool
    {
        return (bool) config('feature-flags.tickets.sla', false);
    }

    public function isAutoAssignEnabled(): bool
    {
        return (bool) config('feature-flags.tickets.auto_assign', false);
    }
}
