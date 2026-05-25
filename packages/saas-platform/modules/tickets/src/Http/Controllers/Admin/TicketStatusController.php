<?php

namespace SaaSPlatform\Modules\Tickets\Http\Controllers\Admin;

use Illuminate\Routing\Controller;
use SaaSPlatform\Modules\Tickets\Contracts\Repositories\TicketRepositoryInterface;
use SaaSPlatform\Modules\Tickets\Services\TicketLifecycleService;
use SaaSPlatform\Modules\Tickets\Http\Requests\Admin\UpdateTicketStatusRequest;

/**
 * PHASE 7.1 — TASK 3: Admin Ticket Status Controller (canonical)
 *
 * Delegates all state transitions to TicketLifecycleService.
 */
class TicketStatusController extends Controller
{
    public function __construct(
        protected TicketRepositoryInterface $ticketRepository,
        protected TicketLifecycleService    $lifecycleService,
    ) {}

    public function update(UpdateTicketStatusRequest $request, int $ticketId)
    {
        $ticket = $this->ticketRepository->findById($ticketId);

        if (!$ticket) {
            abort(404);
        }

        $this->lifecycleService->transition(
            $ticket,
            $request->validated()['status'],
            auth()->id()
        );

        return back()->with('success', 'وضعیت تیکت بروز شد.');
    }
}
