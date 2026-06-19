<?php
namespace App\Services\Lifecycle;

use App\Models\Site;
use App\Services\Sms\SmsGatewayResolver;
use App\Services\Notification\NotificationCenterService;
use Illuminate\Support\Facades\Log;

/**
 * Dispatches SMS + dashboard notifications for all lifecycle events.
 */
class SiteLifecycleNotificationService
{
    public function gracePeriodStarted(Site $site): void
    {
        $daysLeft = (int) config('lifecycle.grace_period_days', 15);
        $message  = "سایت {$site->title}: دوره اعتبار آغاز شد. {$daysLeft} روز فرصت دارید برای تمدید اشتراک. پس از آن سایت معلق می‌شود.";

        $this->sendSmsToCustomer($site, $message);
        $this->notifyPlatformAdmin($site, 'grace_period_started', "دوره اعتبار سایت: {$site->title}", $message);
    }

    public function gracePeriodReminder(Site $site): void
    {
        $daysLeft = $site->gracePeriodDaysRemaining() ?? 0;
        $message  = "یادآوری: سایت {$site->title} {$daysLeft} روز دیگر معلق می‌شود. همین حالا تمدید کنید.";

        $this->sendSmsToCustomer($site, $message);
        $this->notifyPlatformAdmin($site, 'grace_period_reminder', "یادآوری دوره اعتبار: {$site->title}", $message, 'medium');
    }

    public function siteSuspended(Site $site): void
    {
        $message = "سایت {$site->title} به دلیل عدم تمدید اشتراک موقتاً معلق شده است. برای بازگردانی به پنل کاربری مراجعه کنید.";

        $this->sendSmsToCustomer($site, $message);
        $this->notifyPlatformAdmin($site, 'site_suspended', "سایت معلق شد: {$site->title}", $message, 'high');
    }

    public function siteArchived(Site $site): void
    {
        $message = "سایت {$site->title} آرشیو شد. برای بازگردانی با پشتیبانی تماس بگیرید.";

        $this->sendSmsToCustomer($site, $message);
        $this->notifyPlatformAdmin($site, 'site_archived', "سایت آرشیو شد: {$site->title}", $message, 'medium');
    }

    public function siteRestored(Site $site): void
    {
        $message = "سایت {$site->title} با موفقیت بازگردانی و فعال شد.";

        $this->sendSmsToCustomer($site, $message);
        $this->notifyPlatformAdmin($site, 'site_restored', "سایت بازگردانی شد: {$site->title}", $message, 'low');
    }

    // ── Internals ─────────────────────────────────────────────────────────────

    private function sendSmsToCustomer(Site $site, string $message): void
    {
        try {
            $customer = $site->customer;
            if (! $customer || ! $customer->mobile) {
                return;
            }

            $gateway = SmsGatewayResolver::resolve();
            $gateway->send($customer->mobile, $message);

            Log::info("SiteLifecycleNotification: SMS sent to customer {$customer->id} for site {$site->id}");
        } catch (\Throwable $e) {
            Log::warning("SiteLifecycleNotification: SMS failed for site {$site->id}", [
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function notifyPlatformAdmin(
        Site   $site,
        string $type,
        string $title,
        string $message,
        string $priority = 'high'
    ): void {
        try {
            app(NotificationCenterService::class)->notify([
                'type'          => $type,
                'priority'      => $priority,
                'title'         => $title,
                'message'       => $message,
                'audience_type' => 'platform_admin',
                'channel'       => 'dashboard',
                'site_id'       => $site->id,
                'link'          => "/admin/sites/{$site->id}/lifecycle",
            ]);
        } catch (\Throwable $e) {
            Log::warning("SiteLifecycleNotification: platform notify failed for site {$site->id}", [
                'error' => $e->getMessage(),
            ]);
        }
    }
}
