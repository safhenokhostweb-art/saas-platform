<?php

namespace SaaSPlatform\Modules\Tickets\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use SaaSPlatform\Modules\Tickets\Contracts\Services\TicketServiceInterface;
use SaaSPlatform\Modules\Tickets\Contracts\Repositories\TicketRepositoryInterface;
use SaaSPlatform\Modules\Tickets\Services\TicketGateService;
use SaaSPlatform\Modules\Tickets\Models\TicketReply;

/**
 * PHASE 7.1 — TASK 3: Ticket API Controller (canonical)
 *
 * Stateless JSON API for ticket operations.
 * All responses are JSON. All requests require sanctum auth.
 * Site scoping is enforced on every endpoint.
 */
class TicketApiController extends Controller
{
    public function __construct(
        protected TicketServiceInterface    $ticketService,
        protected TicketRepositoryInterface $ticketRepository,
        protected TicketGateService         $gateService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $filters = $request->only(['status', 'priority', 'site_id', 'q', 'per_page']);
        $tickets = $this->ticketService->getAll($filters);

        return response()->json([
            'data'  => $tickets->items(),
            'meta'  => [
                'total'        => $tickets->total(),
                'per_page'     => $tickets->perPage(),
                'current_page' => $tickets->currentPage(),
                'last_page'    => $tickets->lastPage(),
            ],
        ]);
    }

    public function show(int $id): JsonResponse
    {
        $ticket = $this->ticketService->getById($id);

        if (!$ticket) {
            return response()->json(['error' => 'Ticket not found.'], 404);
        }

        return response()->json(['data' => $ticket]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'subject'       => ['required', 'string', 'max:255'],
            'description'   => ['required', 'string'],
            'priority'      => ['nullable', 'in:low,normal,high'],
            'site_id'       => ['nullable', 'integer'],
            'category'      => ['nullable', 'string'],
            'customer_email' => ['nullable', 'email'],
            'customer_name'  => ['nullable', 'string'],
        ]);

        $ticket = $this->ticketService->create($validated);

        return response()->json(['data' => $ticket], 201);
    }

    public function addReply(Request $request, int $id): JsonResponse
    {
        $ticket = $this->ticketRepository->findById($id);

        if (!$ticket) {
            return response()->json(['error' => 'Ticket not found.'], 404);
        }

        $validated = $request->validate([
            'message'     => ['required', 'string'],
            'author_type' => ['nullable', 'in:staff,customer'],
        ]);

        $validated['author_type'] = $validated['author_type'] ?? TicketReply::AUTHOR_STAFF;

        $ticket = $this->ticketService->addReply($ticket, $validated);

        return response()->json(['data' => $ticket]);
    }

    public function stats(): JsonResponse
    {
        return response()->json(['data' => $this->ticketService->getStats()]);
    }
}
