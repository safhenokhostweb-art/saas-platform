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

        $userModel = config('tickets.user_model', \App\Models\User::class);
        $users     = collect(); // default: empty (site admins pass real users via their own controller)

        return view('tickets::site.tickets.create', [
            'attachmentsEnabled'  => $this->gateService->canUploadAttachments(),
            'users'               => $users,
            'isSiteAdminContext'  => (bool) (auth()->user()?->is_site_admin ?? false),
        ]);
    }

    public function store(StorePortalTicketRequest $request)
    {
        $siteId = $this->resolveSiteId();

        if (!$siteId) {
            abort(403);
        }

        $validated = $request->validated();

        // customer-site form uses 'message'; canonical model needs 'description'
        if (empty($validated['description']) && !empty($validated['message'])) {
            $validated['description'] = $validated['message'];
        }
        unset($validated['message']);

        $data = array_merge($validated, [
            'customer_mobile' => auth()->user()->mobile,
            'customer_name'   => auth()->user()->name,
        ]);

        $files = $this->gateService->canUploadAttachments()
            ? array_filter((array) $request->file('attachments'))
            : null;

        $this->portalService->createForSite($siteId, $data, auth()->id(), $files ?: null);

        return redirect()
            ->route('tickets.site.index')
            ->with('success', 'تیکت شما با موفقیت ارسال شد.');
    }

    public function storeReply(StorePortalReplyRequest $request, int $ticketId)
    {
        $siteId = $this->resolveSiteId();
        $ticket = $this->portalService->findForSite($ticketId, $siteId);

        $files = $this->gateService->canUploadAttachments()
            ? array_filter((array) $request->file('attachments'))
            : null;

        $updatedTicket = $this->portalService->addCustomerReply($ticket, $siteId, array_merge(
            $request->validated(),
            ['author_type' => TicketReply::AUTHOR_CUSTOMER]
        ), $files);

        if ($request->ajax() || $request->wantsJson()) {
            $lastReply = $updatedTicket->replies->last();
            return response()->json([
                'success' => true,
                'reply'   => $lastReply ? [
                    'id'          => $lastReply->id,
                    'message'     => $lastReply->message,
                    'author_type' => $lastReply->author_type,
                    'author_name' => auth()->user()?->name ?? 'شما',
                    'created_at'  => $lastReply->created_at->toIso8601String(),
                    'attachments' => $lastReply->attachments ?? [],
                ] : null,
            ]);
        }

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
