<?php

namespace SaaSPlatform\Modules\Tickets\Http\Controllers\Site;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use SaaSPlatform\Modules\Tickets\Services\TicketPortalService;
use SaaSPlatform\Modules\Tickets\Services\TicketGateService;
use SaaSPlatform\Modules\Tickets\Models\TicketActivity;

/**
 * PHASE 7.1 — TASK 3: Site Ticket Portal Controller (canonical)
 *
 * Customer-facing ticket listing and viewing.
 * ALL operations are scoped to the authenticated user's site.
 * Platform controls (assign, SLA, monitoring) are NEVER available here.
 */
class TicketPortalController extends Controller
{
    // Activity types visible to customers (internal ops excluded)
    private const CUSTOMER_VISIBLE_ACTIONS = [
        TicketActivity::ACTION_CREATED,
        TicketActivity::ACTION_STATUS_CHANGED,
        TicketActivity::ACTION_REPLIED,
        TicketActivity::ACTION_PRIORITY_CHANGED,
        TicketActivity::ACTION_CLOSED,
        TicketActivity::ACTION_REOPENED,
    ];

    public function __construct(
        protected TicketPortalService $portalService,
        protected TicketGateService   $gateService,
    ) {}

    public function index(Request $request)
    {
        $siteId = $this->resolveSiteId();

        if (!$siteId) {
            abort(403, 'سایتی برای نمایش تیکت‌ها یافت نشد.');
        }

        if (!$this->gateService->isModuleVisible()) {
            abort(403);
        }

        $filters = $request->only(['status', 'priority', 'q']);
        $tickets = $this->portalService->listForSite($siteId, $filters);
        $stats   = $this->portalService->getSiteStats($siteId);

        return view('tickets::site.tickets.index', [
            'tickets' => $tickets,
            'stats'   => $stats,
            'filters' => $filters,
        ]);
    }

    public function show(int $ticketId)
    {
        $siteId = $this->resolveSiteId();

        if (!$siteId) {
            abort(403);
        }

        $ticket = $this->portalService->findForSite($ticketId, $siteId);

        $activities = TicketActivity::where('ticket_id', $ticket->id)
            ->whereIn('action_type', self::CUSTOMER_VISIBLE_ACTIONS)
            ->orderBy('created_at', 'asc')
            ->get();

        return view('tickets::site.tickets.show', [
            'ticket'             => $ticket,
            'activities'         => $activities,
            'attachmentsEnabled' => $this->gateService->canUploadAttachments(),
        ]);
    }

    protected function resolveSiteId(): ?int
    {
        // Try current site from app context, then from authenticated user
        if (function_exists('current_site') && current_site()) {
            return (int) current_site()->id;
        }

        if (auth()->check() && method_exists(auth()->user(), 'sites')) {
            $site = auth()->user()->sites()->where('is_active', true)->first();
            return $site?->id;
        }

        return null;
    }
}
