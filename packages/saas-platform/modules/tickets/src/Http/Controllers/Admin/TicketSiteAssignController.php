<?php

namespace SaaSPlatform\Modules\Tickets\Http\Controllers\Admin;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use SaaSPlatform\Modules\Tickets\Contracts\Repositories\TicketRepositoryInterface;

/**
 * Reassign a ticket to a different site — Phase 8.3
 */
class TicketSiteAssignController extends Controller
{
    public function __construct(
        protected TicketRepositoryInterface $ticketRepository,
    ) {}

    public function update(Request $request, int $ticketId)
    {
        $ticket = $this->ticketRepository->findById($ticketId);
        if (!$ticket) abort(404);

        $request->validate([
            'site_id' => 'nullable|integer|exists:sites,id',
        ]);

        $this->ticketRepository->update($ticket, [
            'site_id' => $request->input('site_id') ?: null,
        ]);

        return back()->with('success', 'سایت تیکت به‌روزرسانی شد.');
    }
}
