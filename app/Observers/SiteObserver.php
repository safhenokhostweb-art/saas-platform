<?php

namespace App\Observers;

use App\Models\Site;
use App\Services\AdminNotificationService;

class SiteObserver
{
    public function created(Site $site): void
    {
        AdminNotificationService::send(
            type: 'info',
            title: 'سایت جدید ثبت شد',
            body: "سایت «{$site->title}» با دامنه {$site->domain} اضافه شد.",
            link: "/admin/sites/{$site->id}/edit"
        );
    }
}
