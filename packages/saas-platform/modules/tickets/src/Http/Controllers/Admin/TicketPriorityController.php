<?php

namespace SaaSPlatform\Modules\Tickets\Http\Controllers\Admin;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use SaaSPlatform\Modules\Tickets\Contracts\Repositories\TicketRepositoryInterface;
use SaaSPlatform\Modules\Tickets\Services\TicketPriorityService;

/**
 * PHASE 7.1 — TASK 3: Admin Ticket Priority Controller (canonical)
 */
class TicketPriorityController extends Controller
{
    public function __construct(
        protected TicketRepositoryInterface $ticketRepository,
        protected TicketPriorityService     $priorityService,
    ) {}

    public function update(Request $request, int $ticketId)
    {
        $request->validate([
            'priority' => ['required', 'string', 'in:' . implode(',', $this->priorityService->getAvailablePriorities())],
        ]);

        $ticket = $this->ticketRepository->findById($ticketId);

        if (!$ticket) {
            abort(404);
        }

        $this->priorityService->changePriority($ticket, $request->priority, auth()->id());

        return back()->with('success', 'اولویت تیکت تغییر کرد.');
    }
}
