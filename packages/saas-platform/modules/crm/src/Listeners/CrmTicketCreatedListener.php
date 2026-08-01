<?php

namespace SaaSPlatform\Modules\Crm\Listeners;

use SaaSPlatform\Modules\Crm\Models\CrmActivity;
use SaaSPlatform\Modules\Crm\Models\CrmContact;

/**
 * Phase 20 — Part 7: reacts to Tickets' TicketCreated event (registered
 * by CrmServiceProvider::boot(), guarded by class_exists — never imports
 * SaaSPlatform\Modules\Tickets\* classes, only duck-types $event).
 *
 * crm_activities.subject_type/subject_id are NOT NULL (an Activity must
 * attach to a real CRM entity) — so a high-priority ticket only produces
 * an Activity when its customer_mobile matches an EXISTING CrmContact.
 * No Contact is auto-created here; that would risk creating noise for
 * every anonymous/one-off ticket.
 */
class CrmTicketCreatedListener
{
    public function handle(object $event): void
    {
        $ticket = $event->ticket ?? null;
        if (!$ticket || empty($ticket->site_id) || ($ticket->priority ?? null) !== 'high') {
            return;
        }

        if (empty($ticket->customer_mobile)) {
            return;
        }

        $contact = CrmContact::where('site_id', $ticket->site_id)
            ->where('phone', $ticket->customer_mobile)
            ->first();

        if (!$contact) {
            return;
        }

        $contact->activities()->create([
            'site_id' => $ticket->site_id,
            'type' => CrmActivity::TYPE_SYSTEM,
            'subject' => 'تیکت مهم ثبت شد',
            'body' => 'تیکت «' . ($ticket->subject ?? '') . '» با اولویت بالا برای این مخاطب ثبت شد.',
            'occurred_at' => now(),
        ]);
    }
}
