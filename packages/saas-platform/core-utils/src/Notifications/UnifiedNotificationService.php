<?php

namespace SaaSPlatform\CoreUtils\Notifications;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class UnifiedNotificationService
{
    public function __construct(
        private readonly NotificationChannelManager    $channelManager,
        private readonly NotificationPreferenceService $preferences,
        private readonly NotificationDeliveryTracker   $tracker,
        private readonly NotificationDigestService     $digest,
    ) {}

    public function send(
        string $tenantId,
        int    $userId,
        string $type,
        string $title,
        string $body,
        array  $channels = ['in_app'],
        array  $meta = [],
        string $severity = 'info'
    ): array {
        $notificationId = Str::uuid()->toString();
        $prefs = $this->preferences->getFor($tenantId, $userId);

        // Quiet hours check
        if ($this->preferences->isInQuietHours($tenantId, $userId) && $severity !== 'critical') {
            $this->digest->queue($tenantId, $userId, $notificationId, compact('type', 'title', 'body', 'meta', 'severity'));
            return ['queued_for_digest' => true, 'notification_id' => $notificationId];
        }

        $delivered = [];
        foreach ($channels as $channel) {
            // Check if user has disabled this channel
            if (! $this->preferences->isChannelEnabled($tenantId, $userId, $channel)) {
                continue;
            }

            $result = $this->channelManager->send($channel, [
                'notification_id' => $notificationId,
                'tenant_id'       => $tenantId,
                'user_id'         => $userId,
                'type'            => $type,
                'title'           => $title,
                'body'            => $body,
                'severity'        => $severity,
                'meta'            => $meta,
            ]);

            $delivered[$channel] = $result;
            $this->tracker->record($notificationId, $channel, $result['success'] ?? false);
        }

        // Store in-app notification
        $this->storeInApp($tenantId, $userId, $notificationId, compact('type', 'title', 'body', 'severity', 'meta'));

        return [
            'notification_id' => $notificationId,
            'delivered'       => $delivered,
        ];
    }

    public function sendTicketEvent(
        string $tenantId,
        int    $userId,
        string $ticketEvent,
        array  $ticketData,
        array  $extraChannels = []
    ): array {
        $templates = [
            'created'    => ['title' => 'New Ticket Created', 'body' => 'Ticket #:number has been created: :subject', 'severity' => 'info'],
            'assigned'   => ['title' => 'Ticket Assigned', 'body' => 'Ticket #:number assigned to you: :subject', 'severity' => 'info'],
            'replied'    => ['title' => 'New Reply on Ticket', 'body' => ':subject — new reply received.', 'severity' => 'info'],
            'escalated'  => ['title' => 'Ticket Escalated', 'body' => 'Ticket #:number has been escalated.', 'severity' => 'warning'],
            'sla_warn'   => ['title' => 'SLA Warning', 'body' => 'Ticket #:number is approaching SLA deadline.', 'severity' => 'warning'],
            'reopened'   => ['title' => 'Ticket Reopened', 'body' => 'Ticket #:number has been reopened.', 'severity' => 'info'],
            'closed'     => ['title' => 'Ticket Closed', 'body' => 'Ticket #:number has been resolved.', 'severity' => 'success'],
        ];

        $tpl = $templates[$ticketEvent] ?? ['title' => 'Ticket Update', 'body' => 'Your ticket has been updated.', 'severity' => 'info'];

        $title = $tpl['title'];
        $body  = strtr($tpl['body'], [
            ':number'  => $ticketData['ticket_number'] ?? '?',
            ':subject' => $ticketData['subject'] ?? 'No subject',
        ]);

        $channels = array_unique(array_merge(['in_app'], $extraChannels));

        return $this->send($tenantId, $userId, "ticket.{$ticketEvent}", $title, $body, $channels, $ticketData, $tpl['severity']);
    }

    public function getUnread(string $tenantId, int $userId, int $limit = 20): array
    {
        $key   = "notifications:in_app:{$tenantId}:{$userId}";
        $all   = Cache::get($key, []);
        $unread = array_filter($all, fn($n) => ! ($n['read'] ?? false));
        return array_values(array_slice(array_reverse($unread), 0, $limit));
    }

    public function markRead(string $tenantId, int $userId, string $notificationId): void
    {
        $key  = "notifications:in_app:{$tenantId}:{$userId}";
        $all  = Cache::get($key, []);
        foreach ($all as &$n) {
            if ($n['id'] === $notificationId) {
                $n['read'] = true;
                $n['read_at'] = now()->toISOString();
            }
        }
        Cache::put($key, $all, now()->addDays(30));
    }

    public function markAllRead(string $tenantId, int $userId): void
    {
        $key = "notifications:in_app:{$tenantId}:{$userId}";
        $all = Cache::get($key, []);
        foreach ($all as &$n) {
            $n['read']    = true;
            $n['read_at'] = now()->toISOString();
        }
        Cache::put($key, $all, now()->addDays(30));
    }

    private function storeInApp(string $tenantId, int $userId, string $id, array $data): void
    {
        $key  = "notifications:in_app:{$tenantId}:{$userId}";
        $all  = Cache::get($key, []);
        $all[] = array_merge($data, [
            'id'         => $id,
            'read'       => false,
            'created_at' => now()->toISOString(),
        ]);
        // Keep last 200
        if (count($all) > 200) {
            $all = array_slice($all, -200);
        }
        Cache::put($key, $all, now()->addDays(30));
    }
}
