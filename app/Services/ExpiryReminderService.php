<?php

namespace App\Services;

use App\Helpers\Jalali;
use App\Helpers\PlatformSettings;
use App\Models\ExpiryReminderLog;
use App\Models\PlatformSiteNotification;
use App\Models\Site;
use Carbon\Carbon;
use DateTimeInterface;
use Illuminate\Support\Facades\Schema;

/**
 * ارسال خودکار اعلان هشدار انقضا: پشتیبانی، هاست، دامنه.
 * یک ماه قبل از انقضا و هر ۱۰ روز یک‌بار تا انقضا.
 */
class ExpiryReminderService
{
    public const DAYS_WINDOW = 30;
    public const MIN_DAYS_BETWEEN_REMINDERS = 10;

    /**
     * برای همهٔ سایت‌های دارای تاریخ انقضا، در صورت نیاز اعلان ارسال و لاگ ثبت می‌کند.
     */
    public function run(): array
    {
        $sent = ['support' => 0, 'host' => 0, 'domain' => 0];
        if (! Schema::hasTable('expiry_reminder_logs')) {
            return $sent;
        }

        $sites = Site::where('is_active', true)->get();
        foreach ($sites as $site) {
            if (PlatformSettings::isAutoNotifySupportExpiryEnabled() && $site->support_expires_at) {
                if ($this->shouldSend($site, ExpiryReminderLog::TYPE_SUPPORT, $site->support_expires_at)) {
                    $this->sendReminder($site, ExpiryReminderLog::TYPE_SUPPORT, $site->support_expires_at, 'پشتیبانی');
                    $sent['support']++;
                }
            }
            if (PlatformSettings::isAutoNotifyHostExpiryEnabled() && $site->host_expires_at) {
                if ($this->shouldSend($site, ExpiryReminderLog::TYPE_HOST, $site->host_expires_at)) {
                    $this->sendReminder($site, ExpiryReminderLog::TYPE_HOST, $site->host_expires_at, 'هاست');
                    $sent['host']++;
                }
            }
            if (PlatformSettings::isAutoNotifyDomainExpiryEnabled() && $site->domain_expires_at) {
                if ($this->shouldSend($site, ExpiryReminderLog::TYPE_DOMAIN, $site->domain_expires_at)) {
                    $this->sendReminder($site, ExpiryReminderLog::TYPE_DOMAIN, $site->domain_expires_at, 'دامنه');
                    $sent['domain']++;
                }
            }
        }

        return $sent;
    }

    /**
     * آیا الان باید برای این سایت و این نوع انقضا اعلان فرستاد؟
     * شرط: تاریخ انقضا در آینده، فاصله تا انقضا <= ۳۰ روز، و یا آخرین ارسال بیش از ۱۰ روز قبل بوده یا هرگز ارسال نشده.
     */
    public function shouldSend(Site $site, string $type, DateTimeInterface $expiresAt): bool
    {
        $expiresAt = Carbon::instance($expiresAt);
        if ($expiresAt->isPast()) {
            return false;
        }
        $daysLeft = (int) now()->diffInDays($expiresAt, false);
        if ($daysLeft > self::DAYS_WINDOW) {
            return false;
        }

        $last = ExpiryReminderLog::where('site_id', $site->id)
            ->where('type', $type)
            ->orderByDesc('sent_at')
            ->first();
        if (! $last) {
            return true;
        }
        return $last->sent_at->diffInDays(now(), false) >= self::MIN_DAYS_BETWEEN_REMINDERS;
    }

    /**
     * اعلان هشدار انقضا را ثبت می‌کند:
     * - در ادمین اصلی پلتفرم (expiry_reminder_logs → داشبورد پلتفرم)
     * - در پنل ادمین سایت مشتری (PlatformSiteNotification → زنگوله و پیشخوان)
     */
    public function sendReminder(Site $site, string $type, DateTimeInterface $expiresAt, string $labelFa): void
    {
        $expiresAt = Carbon::instance($expiresAt);
        $dateFa = Jalali::format($expiresAt);
        $daysLeft = (int) now()->diffInDays($expiresAt, false);
        $title = "هشدار انقضای {$labelFa}";
        $body = "تاریخ انقضای {$labelFa}: {$dateFa} (حدود {$daysLeft} روز مانده). لطفاً برای تمدید اقدام کنید.";

        ExpiryReminderLog::create([
            'site_id' => $site->id,
            'type' => $type,
            'title' => $title . ' — ' . $site->title,
            'body' => $body,
            'sent_at' => now(),
        ]);

        if (Schema::hasTable('platform_site_notifications')) {
            $order = PlatformSiteNotification::where('site_id', $site->id)->max('order') + 1;
            PlatformSiteNotification::create([
                'site_id' => $site->id,
                'title' => $title,
                'body' => $body,
                'type' => 'warning',
                'link' => null,
                'link_text' => null,
                'image_path' => null,
                'position' => 'top',
                'image_position' => 'right',
                'audience' => 'admin',
                'priority' => 'high',
                'starts_at' => null,
                'expires_at' => null,
                'is_active' => true,
                'order' => $order,
            ]);
        }

        app(ExpirySmsService::class)->sendForReminder($site, $labelFa, $expiresAt, $title . ' — ' . $site->title);
    }
}
