<?php
namespace App\Services;

use App\Models\PlatformNotificationTemplate;
use App\Models\PlatformSiteNotification;
use App\Models\Site;

/**
 * ارسال خودکار اعلان‌های خوش‌آمدگویی به سایت‌های جدید.
 */
class WelcomeNotificationService
{
    /**
     * ارسال قالب‌های فعال به سایت مشخص.
     */
    public function sendToSite(Site $site): int
    {
        $templates = PlatformNotificationTemplate::active()->orderBy('order')->get();
        $created = 0;

        foreach ($templates as $t) {
            $maxOrder = PlatformSiteNotification::where('site_id', $site->id)->max('order') ?? 0;
            PlatformSiteNotification::create([
                'site_id' => $site->id,
                'title' => $t->title,
                'body' => $t->body,
                'type' => $t->type,
                'link' => $t->link,
                'link_text' => $t->link_text,
                'image_path' => $t->image_path,
                'position' => $t->position,
                'image_position' => $t->image_position,
                'audience' => $t->audience,
                'starts_at' => null,
                'expires_at' => null,
                'is_active' => true,
                'order' => $maxOrder + 1,
            ]);
            $created++;
        }

        return $created;
    }
}
