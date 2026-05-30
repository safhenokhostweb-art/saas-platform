<?php

namespace SaaSPlatform\Modules\Tickets\Services;

use SaaSPlatform\Modules\Tickets\Models\Ticket;
use SaaSPlatform\Modules\Tickets\Models\TicketReply;

/**
 * Ticket Notification Service — Phase 8.3
 *
 * Automatic SMS dispatch on ticket lifecycle events.
 * Each method checks the relevant feature flag in settings before sending.
 */
class TicketNotificationService
{
    public function __construct(
        protected TicketSmsService $smsService,
    ) {}

    /**
     * Fired when a new ticket is created.
     * → SMS to admin phone
     */
    public function onCreated(Ticket $ticket): void
    {
        if (!$this->smsService->isEnabled()) return;
        if (!$this->setting('tickets.sms_on_created', false)) return;

        $adminPhone = $this->smsService->getAdminPhone();
        if ($adminPhone) {
            $message = $this->smsService->renderTemplate(
                'tickets.sms_template_created',
                [
                    'ticket_number' => $ticket->ticket_number,
                    'subject'       => $ticket->subject,
                    'priority'      => $this->priorityLabel($ticket->priority),
                    'customer'      => $ticket->customer_name ?? $ticket->customer_mobile ?? '—',
                ],
                'تیکت جدید {ticket_number} با موضوع «{subject}» از طرف {customer} ثبت شد.'
            );
            $this->smsService->send($adminPhone, $message);
        }
    }

    /**
     * Fired on status change.
     * → SMS to admin (on reopen) or customer (on close)
     */
    public function onStatusChanged(Ticket $ticket, string $from, string $to): void
    {
        if (!$this->smsService->isEnabled()) return;
        if (!$this->setting('tickets.sms_on_status', false)) return;

        $adminPhone = $this->smsService->getAdminPhone();
        if ($adminPhone) {
            $message = $this->smsService->renderTemplate(
                'tickets.sms_template_status',
                [
                    'ticket_number' => $ticket->ticket_number,
                    'from_status'   => $this->statusLabel($from),
                    'to_status'     => $this->statusLabel($to),
                ],
                'وضعیت تیکت {ticket_number} از {from_status} به {to_status} تغییر کرد.'
            );
            $this->smsService->send($adminPhone, $message);
        }
    }

    /**
     * Fired when ticket is assigned to someone.
     */
    public function onAssigned(Ticket $ticket, int $assigneeId): void
    {
        if (!$this->smsService->isEnabled()) return;
        if (!$this->setting('tickets.sms_on_assigned', false)) return;

        // Resolve assignee phone if User model has phone field
        try {
            $userModel = config('tickets.user_model');
            $assignee  = $userModel::find($assigneeId);
            $phone     = $assignee?->mobile ?? $assignee?->phone ?? null;
            if ($phone) {
                $message = $this->smsService->renderTemplate(
                    'tickets.sms_template_assigned',
                    [
                        'ticket_number' => $ticket->ticket_number,
                        'subject'       => $ticket->subject,
                    ],
                    'تیکت {ticket_number} با موضوع «{subject}» به شما تخصیص یافت.'
                );
                $this->smsService->send($phone, $message);
            }
        } catch (\Throwable) {
            // Assignee phone lookup is non-critical — silently skip
        }
    }

    /**
     * Fired when a reply is added.
     * → Staff reply: SMS to admin (for awareness) if configured
     * → Customer reply: SMS to admin phone
     */
    public function onReplyAdded(Ticket $ticket, TicketReply $reply): void
    {
        if (!$this->smsService->isEnabled()) return;
        if (!$this->setting('tickets.sms_on_reply', false)) return;

        $isCustomer = $reply->author_type === TicketReply::AUTHOR_CUSTOMER;

        if ($isCustomer) {
            // Customer replied → notify admin
            $adminPhone = $this->smsService->getAdminPhone();
            if ($adminPhone) {
                $message = $this->smsService->renderTemplate(
                    'tickets.sms_template_customer_reply',
                    [
                        'ticket_number' => $ticket->ticket_number,
                        'customer'      => $reply->customer_name ?? $ticket->customer_name ?? 'مشتری',
                    ],
                    'پاسخ جدید از مشتری برای تیکت {ticket_number} — {customer}'
                );
                $this->smsService->send($adminPhone, $message);
            }
        }
        // Staff reply → customer SMS is sent manually via TicketSmsController
    }

    /**
     * Fired on SLA breach.
     */
    public function onSlaBreached(Ticket $ticket): void
    {
        if (!$this->smsService->isEnabled()) return;

        $adminPhone = $this->smsService->getAdminPhone();
        if ($adminPhone) {
            $message = "هشدار SLA: تیکت {$ticket->ticket_number} از زمان مجاز پاسخ‌دهی تجاوز کرده است.";
            $this->smsService->send($adminPhone, $message);
        }
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    private function setting(string $key, mixed $default = null): mixed
    {
        $model = config('tickets.settings_model');
        return ($model && class_exists($model)) ? $model::get($key, $default) : $default;
    }

    private function priorityLabel(string $priority): string
    {
        return match($priority) {
            'high'   => 'بالا',
            'low'    => 'کم',
            default  => 'عادی',
        };
    }

    private function statusLabel(string $status): string
    {
        return match($status) {
            'open'        => 'باز',
            'in_progress' => 'در حال بررسی',
            'closed'      => 'بسته',
            default       => $status,
        };
    }
}
