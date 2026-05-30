<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use SaaSPlatform\Modules\Tickets\Contracts\Services\TicketServiceInterface;
use SaaSPlatform\Modules\Tickets\Models\Ticket;
use SaaSPlatform\Modules\Tickets\Models\TicketReply;

/**
 * PHASE 7.25 — TASK 8: Canonical Tenant Ticket API.
 *
 * Replaces /api/support-tickets (legacy) with /api/tenant/tickets.
 * Auth: license_key + domain validated by AuthenticateTenantApi middleware.
 * Data: reads from canonical `tickets` table via TicketServiceInterface.
 *
 * Response shape is backward-compatible with SupportTicketController:
 *   - 'message' key is used (mapped from canonical 'description')
 *   - All reply fields match the legacy format
 *
 * @see App\Http\Middleware\AuthenticateTenantApi
 */
class TenantTicketController extends Controller
{
    public function __construct(
        private readonly TicketServiceInterface $ticketService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $site = $request->attributes->get('tenant_site');
        $tickets = $this->ticketService->getBySiteId($site->id);

        return response()->json([
            'success' => true,
            'tickets' => $tickets->map(fn(Ticket $t) => $this->ticketPayload($t))->values(),
        ]);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $site = $request->attributes->get('tenant_site');
        $ticket = $this->ticketService->getByIdForSite($id, $site->id);

        if (!$ticket) {
            return response()->json([
                'success' => false,
                'message' => 'تیکت مورد نظر یافت نشد.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'ticket' => $this->ticketPayload($ticket),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $site = $request->attributes->get('tenant_site');

        $validated = $request->validate([
            'subject'        => ['required', 'string', 'max:255'],
            'message'        => ['required', 'string'],
            'customer_email' => ['required', 'email', 'max:255'],
            'customer_name'  => ['nullable', 'string', 'max:255'],
            'priority'       => ['nullable', 'in:low,normal,high'],
            'category'       => ['nullable', 'string', 'max:100'],
            'tags'           => ['nullable', 'array'],
            'attachments'    => ['nullable', 'array'],
        ]);

        $ticket = $this->ticketService->create([
            'site_id'        => $site->id,
            'subject'        => $validated['subject'],
            'description'    => $validated['message'],
            'customer_email' => $validated['customer_email'],
            'customer_name'  => $validated['customer_name'] ?? null,
            'priority'       => $validated['priority'] ?? Ticket::PRIORITY_NORMAL,
            'category'       => $validated['category'] ?? null,
            'tags'           => $validated['tags'] ?? null,
            'attachments'    => $validated['attachments'] ?? [],
        ]);

        return response()->json([
            'success'       => true,
            'message'       => 'تیکت با موفقیت ثبت شد.',
            'ticket_number' => $ticket->ticket_number,
            'ticket_id'     => $ticket->id,
        ], 201);
    }

    public function reply(Request $request, int $id): JsonResponse
    {
        $site = $request->attributes->get('tenant_site');
        $ticket = $this->ticketService->getByIdForSite($id, $site->id);

        if (!$ticket) {
            return response()->json([
                'success' => false,
                'message' => 'تیکت مورد نظر یافت نشد.',
            ], 404);
        }

        $validated = $request->validate([
            'message'       => ['required', 'string'],
            'customer_name' => ['nullable', 'string', 'max:255'],
            'attachments'   => ['nullable', 'array'],
        ]);

        $ticket = $this->ticketService->addReply($ticket, [
            'message'       => $validated['message'],
            'author_type'   => TicketReply::AUTHOR_CUSTOMER,
            'customer_name' => $validated['customer_name'] ?? 'مشتری',
            'attachments'   => $validated['attachments'] ?? [],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'پاسخ شما با موفقیت ثبت شد.',
            'ticket'  => [
                'id'            => $ticket->id,
                'ticket_number' => $ticket->ticket_number,
                'status'        => $ticket->status,
                'replies'       => $ticket->replies->map(fn(TicketReply $r) => $this->replyPayload($r))->values(),
            ],
        ]);
    }

    public function poll(Request $request, int $id): JsonResponse
    {
        $site   = $request->attributes->get('tenant_site');
        $ticket = $this->ticketService->getByIdForSite($id, $site->id);

        if (! $ticket) {
            return response()->json(['replies' => [], 'last_reply_at' => null]);
        }

        $lastId = (int) $request->query('last_id', 0);
        $query  = $ticket->replies()
            ->where(fn($q) => $q->where('is_internal', false)->orWhereNull('is_internal'))
            ->orderBy('id');

        if ($lastId > 0) {
            $query->where('id', '>', $lastId);
        }

        $replies = $query->get();

        return response()->json([
            'replies' => $replies->map(fn(TicketReply $r) => [
                'id'          => $r->id,
                'message'     => $r->message,
                'author_type' => $r->author_type,
                'author_name' => $r->customer_name ?? ($r->author_type === 'staff' ? 'پشتیبان' : 'مشتری'),
                'created_at'  => $r->created_at->toIso8601String(),
                'attachments' => $r->attachments ?? [],
            ])->values(),
            'last_id' => $replies->last()?->id ?? $lastId,
        ]);
    }

    private function ticketPayload(Ticket $ticket): array
    {
        return [
            'id'               => $ticket->id,
            'ticket_number'    => $ticket->ticket_number,
            'subject'          => $ticket->subject,
            'message'          => $ticket->description,
            'status'           => $ticket->status,
            'priority'         => $ticket->priority,
            'assigned_to'      => $ticket->assigned_to,
            'assignee_name'    => $ticket->assignee?->name,
            'customer_email'   => $ticket->customer_email,
            'category'         => $ticket->category,
            'tags'             => $ticket->tags,
            'attachments'      => $ticket->attachments,
            'closed_at'        => $ticket->closed_at?->toISOString(),
            'first_response_at' => $ticket->first_response_at?->toISOString(),
            'resolved_at'      => $ticket->resolved_at?->toISOString(),
            'created_at'       => $ticket->created_at?->toISOString(),
            'replies'          => $ticket->relationLoaded('replies')
                ? $ticket->replies->map(fn(TicketReply $r) => $this->replyPayload($r))->values()
                : [],
        ];
    }

    private function replyPayload(TicketReply $reply): array
    {
        return [
            'id'            => $reply->id,
            'message'       => $reply->message,
            'author_type'   => $reply->author_type,
            'author_name'   => $reply->customer_name ?? ($reply->author_type === 'staff' ? 'پشتیبان' : 'مشتری'),
            'customer_name' => $reply->customer_name,
            'attachments'   => $reply->attachments,
            'created_at'    => $reply->created_at?->toISOString(),
        ];
    }
}
