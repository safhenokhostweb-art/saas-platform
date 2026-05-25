<?php

namespace SaaSPlatform\Modules\Tickets\Http\Controllers\Admin;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use SaaSPlatform\Modules\Tickets\Contracts\Services\TicketServiceInterface;
use SaaSPlatform\Modules\Tickets\Services\TicketGateService;
use SaaSPlatform\Modules\Tickets\Services\TicketSlaService;
use SaaSPlatform\Modules\Tickets\Http\Requests\Admin\StoreTicketRequest;

/**
 * PHASE 7.1 — TASK 3: Admin Ticket Controller (canonical)
 *
 * Handles index, create, store, show for admin context.
 * Status/assignment/SLA are split into dedicated controllers.
 */
class TicketController extends Controller
{
    public function __construct(
        protected TicketServiceInterface $ticketService,
        protected TicketGateService      $gateService,
        protected TicketSlaService       $slaService,
    ) {}

    public function index(Request $request)
    {
        $filters = $request->only(['status', 'priority', 'site_id', 'category', 'q', 'sort_by', 'sort_dir', 'per_page']);
        $tickets = $this->ticketService->getAll($filters);
        $stats   = $this->ticketService->getStats();

        return view('tickets::admin.tickets.index', [
            'tickets'    => $tickets,
            'stats'      => $stats,
            'filters'    => $filters,
            'slaEnabled' => $this->gateService->isSlaEnabled(),
        ]);
    }

    public function create()
    {
        $sites = $this->getActiveSites();

        return view('tickets::admin.tickets.create', [
            'sites'               => $sites,
            'attachmentsEnabled'  => $this->gateService->canUploadAttachments(),
            'autoAssignEnabled'   => $this->gateService->isAutoAssignEnabled(),
        ]);
    }

    public function store(StoreTicketRequest $request)
    {
        $files = $this->gateService->canUploadAttachments()
            ? $request->file('attachments')
            : null;

        $this->ticketService->create($request->validated(), $files);

        return redirect()
            ->route('tickets.admin.index')
            ->with('success', 'تیکت با موفقیت ایجاد شد.');
    }

    public function show(int $id)
    {
        $ticket = $this->ticketService->getById($id);

        if (!$ticket) {
            abort(404);
        }

        return view('tickets::admin.tickets.show', [
            'ticket'     => $ticket,
            'slaEnabled' => $this->gateService->isSlaEnabled(),
            'slaMetrics' => $this->gateService->isSlaEnabled()
                ? $this->slaService->getMetrics($ticket->site_id)
                : [],
            'attachmentsEnabled' => $this->gateService->canUploadAttachments(),
        ]);
    }

    protected function getActiveSites()
    {
        if (class_exists(\App\Models\Site::class)) {
            return \App\Models\Site::where('is_active', true)->orderBy('name')->get();
        }
        return collect([]);
    }
}
