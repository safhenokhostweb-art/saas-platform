<?php

namespace SaaSPlatform\Modules\Tickets\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use SaaSPlatform\Modules\Tickets\Contracts\Services\TicketServiceInterface;
use SaaSPlatform\Modules\Tickets\Contracts\Repositories\TicketRepositoryInterface;
use SaaSPlatform\Modules\Tickets\Services\TicketGateService;
use SaaSPlatform\Modules\Tickets\Models\Ticket;
use SaaSPlatform\Modules\Tickets\Models\TicketReply;
use SaaSPlatform\Modules\Tickets\Models\TicketVote;

/**
 * PHASE 7.1 — TASK 3: Ticket API Controller (canonical)
 *
 * Stateless JSON API for ticket operations.
 * All responses are JSON. All requests require sanctum auth.
 *
 * TICKET-API-TENANT-01 (fixed): every ID-based action below now runs the
 * request through the pre-existing, already-registered `TicketPolicy`
 * (bound in TicketsServiceProvider via Gate::policy(Ticket::class, ...))
 * before returning or mutating data. Repository lookups here intentionally
 * stay "admin-context" (Ticket::withoutTenantIsolation()-backed, via
 * TicketRepository::findById()/TicketService::getById()) because a platform
 * admin legitimately needs to resolve any ticket by raw ID — TicketPolicy's
 * own `view()`/`reply()`/`updateStatus()` methods are what draw the tenant
 * boundary (platform admin: any ticket; site admin/customer: only their own
 * site's tickets), exactly as the policy was already written to do. No
 * second authorization system is introduced.
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

        // Only a platform admin may list across sites or choose an arbitrary
        // site_id filter. Anyone else is force-scoped to their own site_id,
        // regardless of what (if anything) they passed — client-supplied
        // site_id must never widen visibility beyond the caller's own site.
        if (!$this->gateService->canViewAdmin()) {
            $user = $request->user();
            $filters['site_id'] = $user?->site_id ?? 0;
        }

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

        Gate::authorize('view', $ticket);

        return response()->json(['data' => $ticket]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'subject'        => ['required', 'string', 'max:255'],
            'description'    => ['required', 'string'],
            'priority'       => ['nullable', 'in:low,normal,high'],
            'site_id'        => ['nullable', 'integer'],
            'category'       => ['nullable', 'string'],
            'type'           => ['nullable', 'in:support,feature_request'],
            'customer_mobile'=> ['nullable', 'string', 'regex:/^09[0-9]{9}$/'],
            'customer_name'  => ['nullable', 'string'],
        ]);

        $ticket = $this->ticketService->create($validated);

        return response()->json(['data' => $ticket], 201);
    }

    public function updateStatus(Request $request, int $id): JsonResponse
    {
        $ticket = $this->ticketRepository->findById($id);
        if (!$ticket) {
            return response()->json(['error' => 'Ticket not found.'], 404);
        }
        Gate::authorize('updateStatus', $ticket);
        $validated = $request->validate(['status' => ['required', 'in:open,in_progress,closed']]);
        $ticket    = $this->ticketService->updateStatus($ticket, $validated['status']);
        return response()->json(['data' => $ticket]);
    }

    public function vote(Request $request, int $id): JsonResponse
    {
        $ticket = $this->ticketRepository->findById($id);
        if (!$ticket) {
            return response()->json(['error' => 'Ticket not found.'], 404);
        }
        Gate::authorize('view', $ticket);
        if (!$ticket->isFeatureRequest()) {
            return response()->json(['error' => 'Only feature requests can be voted.'], 422);
        }
        $userId = auth()->id();
        $mobile = $request->user()?->mobile;
        $existing = TicketVote::where('ticket_id', $ticket->id)
            ->where(fn($q) => $q->where('voter_id', $userId)->when($mobile, fn($q2) => $q2->orWhere('voter_mobile', $mobile)))
            ->first();
        if ($existing) {
            $existing->delete();
            return response()->json(['voted' => false, 'vote_count' => $ticket->votes()->count()]);
        }
        TicketVote::create(['ticket_id' => $ticket->id, 'voter_id' => $userId, 'voter_mobile' => $mobile]);
        return response()->json(['voted' => true, 'vote_count' => $ticket->votes()->count()]);
    }

    public function addReply(Request $request, int $id): JsonResponse
    {
        $ticket = $this->ticketRepository->findById($id);

        if (!$ticket) {
            return response()->json(['error' => 'Ticket not found.'], 404);
        }

        Gate::authorize('reply', $ticket);

        $validated = $request->validate([
            'message'     => ['required', 'string'],
            'author_type' => ['nullable', 'in:staff,customer'],
        ]);

        $validated['author_type'] = $validated['author_type'] ?? TicketReply::AUTHOR_STAFF;

        $ticket = $this->ticketService->addReply($ticket, $validated);

        return response()->json(['data' => $ticket]);
    }

    public function stats(Request $request): JsonResponse
    {
        if (!$this->gateService->canViewAdmin()) {
            $user = $request->user();

            return response()->json(['data' => $this->ticketService->getStats($user?->site_id ?? 0)]);
        }

        return response()->json(['data' => $this->ticketService->getStats()]);
    }
}
