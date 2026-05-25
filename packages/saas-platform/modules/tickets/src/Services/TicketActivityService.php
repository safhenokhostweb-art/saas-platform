<?php

namespace SaaSPlatform\Modules\Tickets\Services;

use SaaSPlatform\Modules\Tickets\Models\Ticket;

/**
 * PHASE 7.1 — TASK 5: Ticket Activity Service
 *
 * Decoupled activity logging. Integrates with the platform's
 * activity log system if available, otherwise logs to app log.
 */
class TicketActivityService
{
    public function log(Ticket $ticket, string $event, array $context = []): void
    {
        $payload = array_merge($context, [
            'ticket_id'     => $ticket->id,
            'ticket_number' => $ticket->ticket_number,
            'event'         => $event,
            'logged_at'     => now()->toIso8601String(),
        ]);

        // Try to use platform ActivityLog if available
        if (class_exists(\App\Models\ActivityLog::class)) {
            try {
                \App\Models\ActivityLog::create([
                    'loggable_type' => get_class($ticket),
                    'loggable_id'   => $ticket->id,
                    'event'         => 'ticket.' . $event,
                    'properties'    => $payload,
                    'user_id'       => $context['actor'] ?? null,
                ]);
                return;
            } catch (\Throwable) {
                // Fall through to log
            }
        }

        logger()->info("ticket.{$event}", $payload);
    }

    public function logReply(Ticket $ticket, int $replyId, string $authorType, ?int $actorId = null): void
    {
        $this->log($ticket, 'reply_added', [
            'reply_id'    => $replyId,
            'author_type' => $authorType,
            'actor'       => $actorId,
        ]);
    }
}
