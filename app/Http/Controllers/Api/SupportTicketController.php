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

class SupportTicketController extends Controller
{
    public function __construct(
        private readonly SupportPortalService $supportPortalService
    ) {}

    public function index(IndexSupportTicketsRequest $request): JsonResponse
    {
        try {
            $validated = $request->validated();
            $tickets = $this->supportPortalService->listTickets(
                (string) $validated['license_key'],
                (string) $validated['domain']
            );

            return response()->json([
                'success' => true,
                'tickets' => $tickets->map(fn (SupportTicket $ticket) => $this->ticketPayload($ticket))->values(),
            ]);
        } catch (SupportPortalException $exception) {
            return $this->errorResponse($exception);
        }
    }

    public function store(StoreSupportTicketApiRequest $request): JsonResponse
    {
        try {
            $ticket = $this->supportPortalService->createTicket($request->validated());

            return response()->json([
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
        try {
            $ticket = $this->supportPortalService->addCustomerReply($id, $request->validated());

            return response()->json([
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
        try {
            $validated = $request->validated();
            $count = $this->supportPortalService->unreadCount(
                (string) $validated['license_key'],
                (string) $validated['domain']
            );

            return response()->json([
                'success' => true,
                'unread_count' => $count,
            ]);
        } catch (SupportPortalException $exception) {
            return $this->errorResponse($exception);
        }
    }

    private function ticketPayload(SupportTicket $ticket): array
    {
        return [
            'id' => $ticket->id,
            'ticket_number' => $ticket->ticket_number,
            'subject' => $ticket->subject,
            'message' => $ticket->message,
            'status' => $ticket->status,
            'priority' => $ticket->priority,
            'support_level' => $ticket->support_level,
            'assigned_to' => $ticket->assigned_to,
            'customer_email' => $ticket->customer_email,
            'category' => $ticket->category,
            'tags' => $ticket->tags,
            'attachments' => $ticket->attachments,
            'closed_at' => $ticket->closed_at?->toISOString(),
            'first_response_at' => $ticket->first_response_at?->toISOString(),
            'resolved_at' => $ticket->resolved_at?->toISOString(),
            'created_at' => $ticket->created_at?->toISOString(),
            'replies' => $ticket->replies->map(fn (SupportTicketReply $reply) => $this->replyPayload($reply))->values(),
        ];
    }

    private function replyPayload(SupportTicketReply $reply): array
    {
        return [
            'id' => $reply->id,
            'message' => $reply->message,
            'author_type' => $reply->author_type,
            'customer_name' => $reply->customer_name,
            'attachments' => $reply->attachments,
            'created_at' => $reply->created_at?->toISOString(),
        ];
    }

    private function errorResponse(SupportPortalException $exception): JsonResponse
    {
        return response()->json([
            'success' => false,
            'error' => $exception->publicError(),
            'message' => $exception->publicMessage(),
        ], $exception->httpStatus());
    }
}
