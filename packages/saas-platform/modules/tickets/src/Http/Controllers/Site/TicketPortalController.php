<?php

namespace SaaSPlatform\Modules\Tickets\Http\Controllers\Site;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use SaaSPlatform\Modules\Tickets\Services\TicketPortalService;
use SaaSPlatform\Modules\Tickets\Services\TicketGateService;

/**
 * PHASE 7.1 — TASK 3: Site Ticket Portal Controller (canonical)
 *
 * Customer-facing ticket listing and viewing.
 * ALL operations are scoped to the authenticated user's site.
 * Platform controls (assign, SLA, monitoring) are NEVER available here.
 */
class TicketPortalController extends Controller
{
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

        return view('tickets::site.tickets.show', [
            'ticket' => $ticket,
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
