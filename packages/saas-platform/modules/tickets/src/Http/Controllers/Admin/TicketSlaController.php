<?php

namespace SaaSPlatform\Modules\Tickets\Http\Controllers\Admin;

use Illuminate\Routing\Controller;
use SaaSPlatform\Modules\Tickets\Services\TicketSlaService;
use SaaSPlatform\Modules\Tickets\Services\TicketGateService;

/**
 * PHASE 7.1 — TASK 3: Admin Ticket SLA Controller (canonical)
 *
 * Platform-admin-only SLA dashboard endpoint.
 * Feature-flagged via tickets.sla config.
 */
class TicketSlaController extends Controller
{
    public function __construct(
        protected TicketSlaService   $slaService,
        protected TicketGateService  $gateService,
    ) {}

    public function index()
    {
        if (!$this->gateService->canViewSlaMetrics()) {
            abort(403);
        }

        return view('tickets::admin.tickets.sla', [
            'metrics'         => $this->slaService->getMetrics(),
            'breachedTickets' => $this->slaService->getBreachedTickets(),
        ]);
    }
}
