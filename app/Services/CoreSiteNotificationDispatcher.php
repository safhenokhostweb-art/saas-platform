<?php

namespace App\Services;

use App\Contracts\SmsGatewayInterface;
use App\Models\PlatformSiteNotification;
use App\Models\User;
use SaaSPlatform\CoreUtils\Contracts\Services\SiteNotificationDispatcherInterface;

/**
 * Phase 20 — Part 6/7: the real implementation of the generic
 * SiteNotificationDispatcherInterface — bound in AppServiceProvider.
 * Reuses the SAME in-app notification table (PlatformSiteNotification)
 * and generic SMS gateway (App\Contracts\SmsGatewayInterface) that
 * ExpiryReminderService already uses (app/Services/ExpiryReminderService.php)
 * — deliberately not a new/parallel notification system, per Phase 20's
 * own "no parallel system" rule.
 */
class CoreSiteNotificationDispatcher implements SiteNotificationDispatcherInterface
{
    public function __construct(
        private readonly ?SmsGatewayInterface $smsGateway = null,
    ) {}

    public function notifySiteAdmin(int $siteId, string $title, string $message): void
    {
        $order = PlatformSiteNotification::where('site_id', $siteId)->max('order') + 1;

        PlatformSiteNotification::create([
            'site_id' => $siteId,
            'title' => $title,
            'body' => $message,
            'type' => 'info',
            'position' => 'top',
            'image_position' => 'right',
            'audience' => 'admin',
            'priority' => 'normal',
            'is_active' => true,
            'order' => $order,
        ]);

        $phone = User::where('site_id', $siteId)->where('is_site_admin', true)->where('is_active', true)->value('mobile');

        if ($phone && $this->smsGateway) {
            $this->smsGateway->send($phone, "{$title}: {$message}");
        }
    }
}
