<?php

namespace SaaSPlatform\Modules\Tickets\Http\Controllers\Admin;

use Illuminate\Routing\Controller;
use SaaSPlatform\Modules\Tickets\Contracts\Services\TicketServiceInterface;
use SaaSPlatform\Modules\Tickets\Contracts\Repositories\TicketRepositoryInterface;
use SaaSPlatform\Modules\Tickets\Models\TicketReply;
use SaaSPlatform\Modules\Tickets\Services\TicketGateService;
use SaaSPlatform\Modules\Tickets\Services\TicketNotificationService;
use SaaSPlatform\Modules\Tickets\Services\TicketPushService;
use SaaSPlatform\Modules\Tickets\Http\Requests\Admin\StoreTicketReplyRequest;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

/**
 * PHASE 7.1 — TASK 3: Admin Ticket Reply Controller (canonical)
 */
class TicketReplyController extends Controller
{
    use AuthorizesRequests;
    public function __construct(
        protected TicketServiceInterface    $ticketService,
        protected TicketRepositoryInterface $ticketRepository,
        protected TicketGateService         $gateService,
        protected TicketNotificationService $notificationService,
        protected TicketPushService         $pushService,
    ) {}

    public function store(StoreTicketReplyRequest $request, int $ticketId)
    {
        $ticket = $this->ticketRepository->findById($ticketId);

        if (!$ticket) {
            abort(404);
        }

        $this->authorize('reply', $ticket);

        $files = $this->gateService->canUploadAttachments()
            ? array_filter((array) $request->file('attachments'))
            : null;

        $updatedTicket = $this->ticketService->addReply($ticket, array_merge(
            $request->validated(),
            ['author_type' => TicketReply::AUTHOR_STAFF]
        ), $files);

        $lastReply = $updatedTicket->replies->last();
        if ($lastReply) {
            $this->notificationService->onReplyAdded($updatedTicket, $lastReply);
            // Section E: Web Push to all subscribed admins
            $this->pushService->notifyNewReply($updatedTicket, $lastReply);
        }

        if ($request->ajax() || $request->wantsJson()) {
            $attachments = collect($lastReply?->attachments ?? [])->map(function ($att) {
                if (empty($att['url']) && !empty($att['path'])) {
                    $att['url'] = route('tickets.admin.attachment.download', ['path' => $att['path']]);
                }
                return $att;
            })->values()->all();

            return response()->json([
                'success' => true,
                'reply'   => $lastReply ? [
                    'id'          => $lastReply->id,
                    'message'     => $lastReply->message,
                    'author_type' => $lastReply->author_type,
                    'author_name' => auth()->user()?->name ?? 'پشتیبان',
                    'is_internal' => (bool) ($lastReply->is_internal ?? false),
                    'created_at'  => $lastReply->created_at->toIso8601String(),
                    'attachments' => $attachments,
                ] : null,
            ]);
        }

        return back()->with('success', 'پاسخ با موفقیت ثبت شد.');
    }
}
