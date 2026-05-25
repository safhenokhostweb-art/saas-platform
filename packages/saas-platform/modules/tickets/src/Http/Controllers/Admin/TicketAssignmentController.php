<?php

namespace SaaSPlatform\Modules\Tickets\Http\Controllers\Admin;

use Illuminate\Routing\Controller;
use SaaSPlatform\Modules\Tickets\Contracts\Repositories\TicketRepositoryInterface;
use SaaSPlatform\Modules\Tickets\Services\TicketAssignmentService;
use SaaSPlatform\Modules\Tickets\Http\Requests\Admin\AssignTicketRequest;

/**
 * PHASE 7.1 — TASK 3: Admin Ticket Assignment Controller (canonical)
 */
class TicketAssignmentController extends Controller
{
    public function __construct(
        protected TicketRepositoryInterface $ticketRepository,
        protected TicketAssignmentService   $assignmentService,
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
            auth()->id()
        );

        return back()->with('success', 'تیکت به کاربر تخصیص داده شد.');
    }
}
