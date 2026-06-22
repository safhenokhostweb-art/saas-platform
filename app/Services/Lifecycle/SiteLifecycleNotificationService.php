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

    public function gracePeriodMidReminder(Site $site): void
    {
        $daysLeft = $site->gracePeriodDaysRemaining() ?? 0;
        $message  = "یادآوری میانه: سایت {$site->title} هنوز تمدید نشده است. {$daysLeft} روز تا تعلیق باقی است. همین حالا اقدام کنید.";

        $this->sendSmsToCustomer($site, $message);
        $this->notifyPlatformAdmin($site, 'grace_period_mid_reminder', "یادآوری میانه دوره اعتبار: {$site->title}", $message, 'medium');
    }

    public function gracePeriodReminder(Site $site): void
    {
        $daysLeft = $site->gracePeriodDaysRemaining() ?? 0;
        $message  = "یادآوری نهایی: سایت {$site->title} {$daysLeft} روز دیگر معلق می‌شود. همین حالا تمدید کنید.";

        $this->sendSmsToCustomer($site, $message);
        $this->notifyPlatformAdmin($site, 'grace_period_reminder', "یادآوری نهایی دوره اعتبار: {$site->title}", $message, 'medium');
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

    /**
     * C01 — Send restore payment link SMS to customer.
     * Link points to the customer-site direct payment page.
     */
    public function sendRestoreInvoiceSms(Site $site, string $invoiceNumber, int $amount): void
    {
        $paymentUrl = 'https://' . $site->domain . '/lifecycle-pay/' . $invoiceNumber;
        $amountFmt  = number_format($amount);

        $message = $amount > 0
            ? "برای فعال‌سازی مجدد سایت {$site->title}، مبلغ {$amountFmt} تومان را پرداخت کنید:\n{$paymentUrl}"
            : "برای فعال‌سازی رایگان سایت {$site->title} کلیک کنید:\n{$paymentUrl}";

        $this->sendSmsToCustomer($site, $message);
        $this->notifyPlatformAdmin($site, 'restore_invoice_sent', "لینک پرداخت ارسال شد: {$site->title}",
            "لینک پرداخت برای بازگردانی سایت به مشتری ارسال شد. مبلغ: {$amountFmt} تومان", 'medium');
    }

    /**
     * GAP-H04 — Send overage invoice SMS and platform admin notification.
     */
    public function sendOverageInvoiceSms(Site $site, string $invoiceNumber, int $amount): void
    {
        $paymentUrl = 'https://' . $site->domain . '/lifecycle-pay/' . $invoiceNumber;
        $amountFmt  = number_format($amount);

        $message = "سایت {$site->title}: هزینه مازاد فضای ذخیره‌سازی ماه جاری {$amountFmt} ریال است.\n"
                 . "شماره فاکتور: {$invoiceNumber}\n"
                 . "پرداخت: {$paymentUrl}";

        $this->sendSmsToCustomer($site, $message);
        $this->notifyPlatformAdmin($site, 'overage_invoice_sent', "فاکتور مازاد فضا: {$site->title}",
            "هزینه مازاد {$amountFmt} ریال برای سایت {$site->title} صادر شد. شماره: {$invoiceNumber}", 'medium');
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
