<?php
namespace App\Http\Controllers\Api;

use App\Exceptions\SupportPortalException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\IndexSupportTicketsRequest;
use App\Http\Requests\Api\StoreSupportTicketApiRequest;
use App\Http\Requests\Api\StoreSupportTicketReplyApiRequest;
use App\Models\SupportTicket;
use App\Models\SupportTicketReply;
use App\Services\SupportPortalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * DEPRECATED — use /api/tenant/tickets instead.
 *
 * PHASE 8.0: All requests now require a valid tenant credential (tenant.api middleware).
 * Responses include X-Deprecated-Endpoint: true and X-Migrate-To headers to signal
 * that callers should migrate to the canonical endpoint.
 */
class SupportTicketController extends Controller
{
    public function __construct(
        private readonly SupportPortalService $supportPortalService
    ) {}

    public function index(IndexSupportTicketsRequest $request): JsonResponse
    {
        $this->auditLegacyAccess($request, 'index');

        try {
            $validated = $request->validated();
            $tickets = $this->supportPortalService->listTickets(
                (string) $validated['license_key'],
                (string) $validated['domain']
            );

            return $this->deprecatedResponse([
                'success' => true,
                'tickets' => $tickets->map(fn (SupportTicket $ticket) => $this->ticketPayload($ticket))->values(),
            ]);
        } catch (SupportPortalException $exception) {
            return $this->errorResponse($exception);
        }
    }

    public function store(StoreSupportTicketApiRequest $request): JsonResponse
    {
        $this->auditLegacyAccess($request, 'store');

        try {
            $ticket = $this->supportPortalService->createTicket($request->validated());

            return $this->deprecatedResponse([
                'success' => true,
                'message' => 'تیکت با موفقیت ثبت شد.',
                'ticket_number' => $ticket->ticket_number,
            ]);
        } catch (SupportPortalException $exception) {
            return $this->errorResponse($exception);
        }
    }

    public function reply(StoreSupportTicketReplyApiRequest $request, int $id): JsonResponse
    {
        $this->auditLegacyAccess($request, 'reply', ['ticket_id' => $id]);

        try {
            $ticket = $this->supportPortalService->addCustomerReply($id, $request->validated());

            return $this->deprecatedResponse([
                'success' => true,
                'message' => 'پاسخ شما با موفقیت ثبت شد.',
                'ticket' => [
                    'id' => $ticket->id,
                    'ticket_number' => $ticket->ticket_number,
                    'status' => $ticket->status,
                    'replies' => $ticket->replies->map(fn (SupportTicketReply $reply) => $this->replyPayload($reply))->values(),
                ],
            ]);
        } catch (SupportPortalException $exception) {
            return $this->errorResponse($exception);
        }
    }

    public function unreadCount(IndexSupportTicketsRequest $request): JsonResponse
    {
        $this->auditLegacyAccess($request, 'unread-count');

        try {
            $validated = $request->validated();
            $count = $this->supportPortalService->unreadCount(
                (string) $validated['license_key'],
                (string) $validated['domain']
            );

            return $this->deprecatedResponse([
                'success' => true,
                'unread_count' => $count,
            ]);
        } catch (SupportPortalException $exception) {
            return $this->errorResponse($exception);
        }
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    private function deprecatedResponse(array $data, int $status = 200): JsonResponse
    {
        return response()->json($data, $status)->withHeaders([
            'X-Deprecated-Endpoint' => 'true',
            'X-Migrate-To'          => '/api/tenant/tickets',
            'X-Deprecation-Phase'   => 'phase-8.0',
        ]);
    }

    /**
     * Audit every access to this deprecated endpoint.
     * Credentials are NEVER logged — only the site identity resolved by middleware.
     */
    private function auditLegacyAccess(Request $request, string $action, array $extra = []): void
    {
        $site = $request->attributes->get('tenant_site');

        Log::channel('stack')->warning('DEPRECATED_API_ACCESS', [
            'request_id' => (string) Str::uuid(),
            'endpoint'   => '/api/support-tickets',
            'action'     => $action,
            'site_id'    => $site?->id,
            'site_domain'=> $site?->domain ?? null,
            'ip'         => $request->ip(),
            'user_agent' => $request->userAgent(),
        ] + $extra);
    }

    private function ticketPayload(SupportTicket $ticket): array
    {
        return [
            'id'                => $ticket->id,
            'ticket_number'     => $ticket->ticket_number,
            'subject'           => $ticket->subject,
            'message'           => $ticket->message,
            'status'            => $ticket->status,
            'priority'          => $ticket->priority,
            'support_level'     => $ticket->support_level,
            'assigned_to'       => $ticket->assigned_to,
            'customer_email'    => $ticket->customer_email,
            'category'          => $ticket->category,
            'tags'              => $ticket->tags,
            'attachments'       => $ticket->attachments,
            'closed_at'         => $ticket->closed_at?->toISOString(),
            'first_response_at' => $ticket->first_response_at?->toISOString(),
            'resolved_at'       => $ticket->resolved_at?->toISOString(),
            'created_at'        => $ticket->created_at?->toISOString(),
            'replies'           => $ticket->replies->map(fn (SupportTicketReply $r) => $this->replyPayload($r))->values(),
        ];
    }

    private function replyPayload(SupportTicketReply $reply): array
    {
        return [
            'id'            => $reply->id,
            'message'       => $reply->message,
            'author_type'   => $reply->author_type,
            'customer_name' => $reply->customer_name,
            'attachments'   => $reply->attachments,
            'created_at'    => $reply->created_at?->toISOString(),
        ];
    }

    private function errorResponse(SupportPortalException $exception): JsonResponse
    {
        return response()->json([
            'success' => false,
            'error'   => $exception->publicError(),
            'message' => $exception->publicMessage(),
        ], $exception->httpStatus());
    }
}
