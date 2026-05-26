<?php

namespace SaaSPlatform\Modules\Tickets\Services;

use SaaSPlatform\CoreUtils\Contracts\Services\AuthUserResolverInterface;
use SaaSPlatform\Modules\Tickets\Models\Ticket;

class TicketGateService
{
    public function __construct(
        protected AuthUserResolverInterface $authResolver
    ) {}

    public function canViewAdmin(): bool
    {
        return $this->authResolver->check() && $this->authResolver->isAdmin();
    }

    public function canCreateTicket(?int $siteId = null): bool
    {
        return $this->authResolver->check();
    }

    public function canReply(Ticket $ticket): bool
    {
        return $this->authResolver->check() && $ticket->status !== Ticket::STATUS_CLOSED;
    }

    public function canAssign(): bool
    {
        return $this->authResolver->check() && $this->authResolver->isAdmin();
    }

    public function canChangeStatus(): bool
    {
        return $this->authResolver->check() && $this->authResolver->isAdmin();
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
