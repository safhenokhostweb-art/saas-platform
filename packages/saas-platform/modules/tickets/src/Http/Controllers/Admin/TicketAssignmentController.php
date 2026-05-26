<?php

namespace SaaSPlatform\Modules\Tickets\Http\Controllers\Admin;

use Illuminate\Routing\Controller;
use SaaSPlatform\CoreUtils\Contracts\Services\AuthUserResolverInterface;
use SaaSPlatform\Modules\Tickets\Contracts\Repositories\TicketRepositoryInterface;
use SaaSPlatform\Modules\Tickets\Http\Requests\Admin\AssignTicketRequest;
use SaaSPlatform\Modules\Tickets\Services\TicketAssignmentService;

/**
 * PHASE 7.1 — TASK 3: Admin Ticket Assignment Controller (canonical)
 */
class TicketAssignmentController extends Controller
{
    public function __construct(
        protected TicketRepositoryInterface $ticketRepository,
        protected TicketAssignmentService   $assignmentService,
        protected AuthUserResolverInterface $authResolver,
    ) {}

    public function update(AssignTicketRequest $request, int $ticketId)
    {
        $ticket = $this->ticketRepository->findById($ticketId);

        if (!$ticket) {
            abort(404);
        }

        $this->assignmentService->assign(
            $ticket,
            $request->validated()['assigned_to'],
            $this->authResolver->id()
        );

        return back()->with('success', 'تیکت به کاربر تخصیص داده شد.');
    }
}
