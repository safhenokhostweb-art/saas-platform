<?php

namespace SaaSPlatform\Modules\Tickets\Http\Controllers\Admin;

use Illuminate\Routing\Controller;
use SaaSPlatform\CoreUtils\Contracts\Services\AuthUserResolverInterface;
use SaaSPlatform\Modules\Tickets\Contracts\Repositories\TicketRepositoryInterface;
use SaaSPlatform\Modules\Tickets\Http\Requests\Admin\UpdateTicketStatusRequest;
use SaaSPlatform\Modules\Tickets\Services\TicketLifecycleService;

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
        protected AuthUserResolverInterface $authResolver,
    ) {}

    public function update(UpdateTicketStatusRequest $request, int $ticketId)
    {
        $ticket = $this->ticketRepository->findById($ticketId);

        if (!$ticket) {
            abort(404);
        }

        $this->authorize('updateStatus', $ticket);

        $this->lifecycleService->transition(
            $ticket,
            $request->validated()['status'],
            $this->authResolver->id()
        );

        return back()->with('success', 'وضعیت تیکت بروز شد.');
    }
}
