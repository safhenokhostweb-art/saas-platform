<?php

namespace SaaSPlatform\Modules\Tickets\Services;

use SaaSPlatform\Modules\Tickets\Models\Ticket;
use SaaSPlatform\Modules\Tickets\Models\TicketReply;
use SaaSPlatform\Modules\Tickets\Contracts\Repositories\TicketRepositoryInterface;
use SaaSPlatform\Modules\Tickets\Contracts\Repositories\TicketReplyRepositoryInterface;
use SaaSPlatform\Modules\Tickets\Exceptions\TicketPortalException;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * PHASE 7.1 — TASK 5: Ticket Portal Service
 *
 * Handles all customer-facing (tenant site) ticket operations.
 * Every method enforces site_id scoping to prevent cross-tenant access.
 */
class TicketPortalService
{
    public function __construct(
        protected TicketRepositoryInterface      $ticketRepository,
        protected TicketReplyRepositoryInterface $replyRepository,
        protected TicketLifecycleService         $lifecycleService,
        protected TicketNotificationService      $notificationService,
        protected TicketActivityService          $activityService,
    ) {}

    public function listForSite(int $siteId, array $filters = []): LengthAwarePaginator
    {
        $filters['site_id'] = $siteId;
        return $this->ticketRepository->search(
            $filters,
            $filters['sort_by']  ?? 'created_at',
            $filters['sort_dir'] ?? 'desc',
            $filters['per_page'] ?? config('tickets.pagination', 20)
        );
    }

    public function findForSite(int $ticketId, int $siteId): Ticket
    {
        $ticket = $this->ticketRepository->findByIdForSite(
            $ticketId, $siteId, ['site', 'assignee', 'publicReplies.user']
        );

        if (!$ticket) {
            throw new TicketPortalException(
                'ticket.not_found',
                'تیکت مورد نظر یافت نشد.',
                404
            );
        }

        return $ticket;
    }

    public function createForSite(int $siteId, array $data, ?int $userId = null, ?array $files = null): Ticket
    {
        return DB::transaction(function () use ($siteId, $data, $userId, $files) {
            $data['site_id']    = $siteId;
            $data['customer_id'] = $userId;
            $data['status']     = Ticket::STATUS_OPEN;
            $data['priority']   = $data['priority'] ?? Ticket::PRIORITY_NORMAL;

            $ticket = app(TicketService::class)->create($data, $files);

            $this->activityService->log($ticket, 'created_by_customer', ['site_id' => $siteId]);
            $this->notificationService->onCreated($ticket);

            return $ticket;
        });
    }

    public function addCustomerReply(Ticket $ticket, int $siteId, array $data, ?array $files = null): Ticket
    {
        // Enforce site ownership
        if ((int) $ticket->site_id !== $siteId) {
            throw new TicketPortalException('ticket.forbidden', 'دسترسی مجاز نیست.', 403);
        }

        if ($ticket->status === Ticket::STATUS_CLOSED) {
            throw new TicketPortalException('ticket.closed', 'این تیکت بسته شده است.', 422);
        }

        $data['author_type'] = TicketReply::AUTHOR_CUSTOMER;

        return app(TicketService::class)->addReply($ticket, $data, $files);
    }

    public function getSiteStats(int $siteId): array
    {
        $base = Ticket::withoutTenantIsolation()->where('site_id', $siteId);

        return [
            'total'       => (clone $base)->count(),
            'open'        => (clone $base)->where('status', Ticket::STATUS_OPEN)->count(),
            'in_progress' => (clone $base)->where('status', Ticket::STATUS_IN_PROGRESS)->count(),
            'closed'      => (clone $base)->where('status', Ticket::STATUS_CLOSED)->count(),
        ];
    }

    public function countUnread(int $siteId): int
    {
        return $this->ticketRepository->countUnreadBySiteId($siteId);
    }
}
