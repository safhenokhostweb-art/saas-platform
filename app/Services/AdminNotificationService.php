<?php

namespace App\Services;

use App\Models\SystemNotification;
use Illuminate\Support\Facades\Schema;

class AdminNotificationService
{
    public static function send(
        string $type,
        string $title,
        string $body = '',
        string $link = ''
    ): void {
        if (! Schema::hasTable('system_notifications')) {
            return;
        }

        SystemNotification::create([
            'type'  => $type,
            'title' => $title,
            'body'  => $body ?: null,
            'data'  => $link ? ['link' => $link] : null,
            // platform-level: no site_id, no reseller_id, no notifiable
        ]);

        // Auto-prune: نگه‌دارندن ۵۰۰ اعلان آخر
        $count = SystemNotification::whereNull('site_id')->whereNull('reseller_id')->count();
        if ($count > 500) {
            SystemNotification::whereNull('site_id')
                ->whereNull('reseller_id')
                ->orderBy('id')
                ->limit($count - 500)
                ->delete();
        }
    }

    public static function unreadCount(): int
    {
        if (! Schema::hasTable('system_notifications')) {
            return 0;
        }
        return SystemNotification::whereNull('site_id')
            ->whereNull('reseller_id')
            ->whereNull('read_at')
            ->count();
    }

    public static function recentUnread(int $limit = 10): \Illuminate\Support\Collection
    {
        if (! Schema::hasTable('system_notifications')) {
            return collect();
        }
        return SystemNotification::whereNull('site_id')
            ->whereNull('reseller_id')
            ->whereNull('read_at')
            ->latest()
            ->take($limit)
            ->get();
    }
}
