<?php

namespace SaaSPlatform\Modules\Tickets\Http\Controllers\Admin;

use Illuminate\Routing\Controller;
use SaaSPlatform\Modules\Tickets\Contracts\Services\TicketServiceInterface;
use SaaSPlatform\Modules\Tickets\Contracts\Repositories\TicketRepositoryInterface;
use SaaSPlatform\Modules\Tickets\Models\TicketReply;
use SaaSPlatform\Modules\Tickets\Services\TicketGateService;
use SaaSPlatform\Modules\Tickets\Http\Requests\Admin\StoreTicketReplyRequest;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

/**
 * PHASE 7.1 — TASK 3: Admin Ticket Reply Controller (canonical)
 */
class TicketReplyController extends Controller
{
    use AuthorizesRequests;
    public function __construct(
        protected TicketServiceInterface    $ticketService,
        protected TicketRepositoryInterface $ticketRepository,
        protected TicketGateService         $gateService,
    ) {}

    public function store(StoreTicketReplyRequest $request, int $ticketId)
    {
        $ticket = $this->ticketRepository->findById($ticketId);

        if (!$ticket) {
            abort(404);
        }

        $this->authorize('reply', $ticket);

        $files = $this->gateService->canUploadAttachments()
            ? array_filter((array) $request->file('attachments'))
            : null;

        $this->ticketService->addReply($ticket, array_merge(
            $request->validated(),
            ['author_type' => TicketReply::AUTHOR_STAFF]
        ), $files);

        return back()->with('success', 'پاسخ با موفقیت ثبت شد.');
    }
}
