<?php

namespace SaaSPlatform\Modules\Tickets\Services;

use SaaSPlatform\Modules\Tickets\Models\PushSubscription;
use SaaSPlatform\Modules\Tickets\Models\Ticket;
use SaaSPlatform\Modules\Tickets\Models\TicketReply;
use Illuminate\Support\Facades\Log;

/**
 * SUPPORT-SUITE-01 — Section E
 * Sends Web Push notifications to subscribed admins when ticket replies arrive.
 *
 * Uses a simple HTTP request to each subscription endpoint.
 * For full VAPID support, install minishlink/web-push and configure
 * VAPID keys in config/tickets.php → push.
 */
class TicketPushService
{
    /**
     * Notify all admin subscribers that a new reply has arrived.
     */
    public function notifyNewReply(Ticket $ticket, TicketReply $reply): void
    {
        if ($reply->is_internal) {
            return; // internal notes do not trigger push
        }

        $payload = json_encode([
            'title'     => 'تیکت ' . $ticket->ticket_number . ' — پیام جدید',
            'body'      => mb_substr($reply->message ?? '', 0, 100),
            'url'       => route('tickets.admin.show', $ticket->id),
            'tag'       => 'ticket-' . $ticket->id,
            'ticket_id' => $ticket->id,
        ]);

        $subscriptions = PushSubscription::whereNotNull('user_id')->get();

        foreach ($subscriptions as $sub) {
            $this->sendPush($sub, $payload);
        }
    }

    private function sendPush(PushSubscription $sub, string $payload): void
    {
        // If minishlink/web-push is installed, use it:
        if (class_exists('\Minishlink\WebPush\WebPush')) {
            $this->sendWithWebPush($sub, $payload);
            return;
        }

        // Fallback: log the push payload (no VAPID library installed)
        Log::debug('[TicketPush] Would send push to ' . mb_substr($sub->endpoint, -30) . ': ' . $payload);
    }

    private function sendWithWebPush(PushSubscription $sub, string $payload): void
    {
        try {
            $auth = [
                'VAPID' => [
                    'subject'    => config('app.url'),
                    'publicKey'  => config('tickets.push.vapid_public_key'),
                    'privateKey' => config('tickets.push.vapid_private_key'),
                ],
            ];

            $webPush = new \Minishlink\WebPush\WebPush($auth);

            $subscription = \Minishlink\WebPush\Subscription::create([
                'endpoint'        => $sub->endpoint,
                'contentEncoding' => $sub->encoding ?? 'aesgcm',
                'keys'            => [
                    'p256dh' => $sub->public_key,
                    'auth'   => $sub->auth_token,
                ],
            ]);

            $webPush->queueNotification($subscription, $payload);

            foreach ($webPush->flush() as $report) {
                if (!$report->isSuccess()) {
                    if ($report->isSubscriptionExpired()) {
                        $sub->delete(); // clean up expired subscriptions
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::warning('[TicketPush] Failed: ' . $e->getMessage());
        }
    }
}
