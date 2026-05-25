<?php

namespace SaaSPlatform\Modules\Tickets\Http\Controllers\Site;

use Illuminate\Routing\Controller;
use SaaSPlatform\Modules\Tickets\Services\TicketPortalService;
use SaaSPlatform\Modules\Tickets\Services\TicketGateService;
use SaaSPlatform\Modules\Tickets\Http\Requests\Site\StorePortalTicketRequest;
use SaaSPlatform\Modules\Tickets\Http\Requests\Site\StorePortalReplyRequest;
use SaaSPlatform\Modules\Tickets\Models\TicketReply;

/**
 * PHASE 7.1 — TASK 3: Site Ticket Submission Controller (canonical)
 *
 * Handles ticket creation and customer reply submission.
 * Enforces site_id scoping on every operation.
 */
class TicketSubmissionController extends Controller
{
    public function __construct(
        protected TicketPortalService $portalService,
        protected TicketGateService   $gateService,
    ) {}

    public function create()
    {
        if (!$this->gateService->canCreateTicket()) {
            abort(403);
        }

        return view('tickets::site.tickets.create', [
            'attachmentsEnabled' => $this->gateService->canUploadAttachments(),
        ]);
    }

    public function store(StorePortalTicketRequest $request)
    {
        $siteId = $this->resolveSiteId();

        if (!$siteId) {
            abort(403);
        }

        $data = array_merge($request->validated(), [
            'customer_email' => auth()->user()->email,
            'customer_name'  => auth()->user()->name,
        ]);

        $files = $this->gateService->canUploadAttachments()
            ? $request->file('attachments')
            : null;

        $this->portalService->createForSite($siteId, $data, auth()->id(), $files);

        return redirect()
            ->route('tickets.site.index')
            ->with('success', 'تیکت شما با موفقیت ارسال شد.');
    }

    public function storeReply(StorePortalReplyRequest $request, int $ticketId)
    {
        $siteId = $this->resolveSiteId();
        $ticket = $this->portalService->findForSite($ticketId, $siteId);

        $files = $this->gateService->canUploadAttachments()
            ? $request->file('attachments')
            : null;

        $this->portalService->addCustomerReply($ticket, $siteId, array_merge(
            $request->validated(),
            ['author_type' => TicketReply::AUTHOR_CUSTOMER]
        ), $files);

        return back()->with('success', 'پیام شما ارسال شد.');
    }

    protected function resolveSiteId(): ?int
    {
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
