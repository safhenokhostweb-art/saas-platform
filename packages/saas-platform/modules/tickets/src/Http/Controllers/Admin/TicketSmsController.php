<?php

namespace SaaSPlatform\Modules\Tickets\Http\Controllers\Admin;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use SaaSPlatform\Modules\Tickets\Contracts\Repositories\TicketRepositoryInterface;
use SaaSPlatform\Modules\Tickets\Services\TicketSmsService;

/**
 * Manual SMS send from ticket chat — Phase 8.3
 *
 * Admin can send a custom or templated SMS to any phone number
 * directly from the ticket conversation panel.
 */
class TicketSmsController extends Controller
{
    public function __construct(
        protected TicketRepositoryInterface $ticketRepository,
        protected TicketSmsService          $smsService,
    ) {}

    public function send(Request $request, int $ticketId)
    {
        $ticket = $this->ticketRepository->findById($ticketId);
        if (!$ticket) abort(404);

        $data = $request->validate([
            'phone'   => 'required|string|max:20',
            'message' => 'required|string|max:500',
        ]);

        $sent = $this->smsService->send($data['phone'], $data['message']);

        if ($sent) {
            return back()->with('sms_success', 'پیامک با موفقیت ارسال شد.');
        }

        return back()->with('sms_error', 'ارسال پیامک ناموفق بود. تنظیمات SMS را بررسی کنید.');
    }

    /**
     * Returns default templates for the given ticket as JSON (for Alpine.js).
     */
    public function templates(Request $request, int $ticketId)
    {
        $ticket = $this->ticketRepository->findById($ticketId);
        if (!$ticket) abort(404);

        $vars = [
            'ticket_number' => $ticket->ticket_number,
            'subject'       => $ticket->subject,
            'customer'      => $ticket->customer_name ?? $ticket->customer_email ?? 'مشتری',
        ];

        return response()->json([
            'templates' => [
                [
                    'label'   => 'پاسخ آماده',
                    'message' => $this->smsService->renderTemplate(
                        'tickets.sms_template_customer_reply',
                        $vars,
                        'پاسخ تیکت {ticket_number} شما ثبت شد. لطفاً سامانه را بررسی کنید.'
                    ),
                ],
                [
                    'label'   => 'اطلاع وضعیت',
                    'message' => "تیکت {$ticket->ticket_number} شما در حال بررسی است.",
                ],
                [
                    'label'   => 'بسته شد',
                    'message' => "تیکت {$ticket->ticket_number} بررسی و بسته شد. در صورت نیاز تیکت جدید ثبت کنید.",
                ],
            ],
            'customer_phone' => '',
        ]);
    }
}
