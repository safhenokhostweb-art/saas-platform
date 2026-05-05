<?php

namespace App\Services;

use App\Contracts\SmsGatewayInterface;
use App\Helpers\Jalali;
use App\Helpers\PlatformSettings;
use App\Models\Site;
use App\Models\User;
use App\Services\Sms\SmsGatewayResolver;
use Carbon\Carbon;
use DateTimeInterface;
use Illuminate\Support\Facades\Log;

/**
 * ارسال پیامک هشدار انقضا به ادمین سایت مشتری و (اختیاری) ادمین پلتفرم.
 * قالب پیامک مطابق سرویس‌های ایرانی (ملی‌پیامک و ...) و کاربرپسند.
 */
class ExpirySmsService
{
    protected ?SmsGatewayInterface $gateway = null;

    public function __construct()
    {
        $this->gateway = SmsGatewayResolver::resolve();
    }

    /**
     * ارسال پیامک برای یک هشدار انقضا: به مشتری (صاحب سایت) و در صورت تنظیم به ادمین‌های پلتفرم.
     */
    public function sendForReminder(Site $site, string $typeFa, DateTimeInterface $expiresAt, string $titleForLog): void
    {
        if (! PlatformSettings::isExpirySmsEnabled()) {
            return;
        }
        if (! $this->gateway) {
            return;
        }

        $expiresAt = Carbon::instance($expiresAt);
        $dateFa = Jalali::format($expiresAt);
        $daysLeft = (int) now()->diffInDays($expiresAt, false);
        $platformName = PlatformSettings::platformName();
        $message = $this->buildMessage($platformName, $site->title, $typeFa, $dateFa, $daysLeft);

        $sent = [];

        $customerMobile = $this->getSiteAdminMobile($site);
        if ($customerMobile !== '') {
            if ($this->gateway->send($customerMobile, $message)) {
                $sent[] = 'site_admin:' . $customerMobile;
            }
        }

        if (PlatformSettings::isExpirySmsToPlatformAdminEnabled()) {
            $adminMobiles = $this->getPlatformAdminMobiles();
            foreach ($adminMobiles as $mobile) {
                if ($mobile !== '' && $this->gateway->send($mobile, $message)) {
                    $sent[] = 'platform_admin:' . $mobile;
                }
            }
        }

        if (! empty($sent)) {
            Log::channel('single')->info('Expiry SMS sent', ['reminder' => $titleForLog, 'sent_to' => $sent]);
        }
    }

    /**
     * ساخت متن پیامک از قالب (کاربرپسند، رعایت محدودیت طول).
     */
    public function buildMessage(string $platformName, string $siteTitle, string $typeFa, string $dateFa, int $daysLeft): string
    {
        $template = config('sms.templates.expiry_reminder', '[{platform_name}] هشدار انقضای {type_fa} برای سایت {site_title}. تاریخ: {date_fa} ({days_left} روز مانده). لطفاً تمدید کنید.');
        $message = str_replace(
            ['{platform_name}', '{site_title}', '{type_fa}', '{date_fa}', '{days_left}'],
            [$platformName, $siteTitle, $typeFa, $dateFa, (string) $daysLeft],
            $template
        );
        return $this->trimForSms($message);
    }

    /**
     * شماره موبایل ادمین سایت مشتری (مشتری صاحب سایت یا کاربر مرتبط).
     */
    protected function getSiteAdminMobile(Site $site): string
    {
        $site->loadMissing('customer');
        $customer = $site->customer;
        if (! $customer) {
            return '';
        }
        $mobile = $customer->mobile ?? '';
        if ($mobile !== '') {
            return $this->normalizeMobile($mobile);
        }
        if ($customer->user_id) {
            $user = User::find($customer->user_id);
            if ($user && ! empty($user->mobile)) {
                return $this->normalizeMobile($user->mobile);
            }
        }
        return '';
    }

    /**
     * شماره موبایل ادمین‌های پلتفرم (کاربران با is_admin یا نقش سوپرادمین).
     */
    protected function getPlatformAdminMobiles(): array
    {
        $query = User::where('is_active', true)
            ->where(function ($q) {
                $q->where('is_admin', true);
                if (class_exists(\App\Models\Role::class)) {
                    $q->orWhereHas('roles', fn ($r) => $r->where('slug', \App\Models\Role::SUPER_ADMIN));
                }
            })
            ->whereNotNull('mobile')
            ->where('mobile', '!=', '');

        return $query->pluck('mobile')->map(fn ($m) => $this->normalizeMobile($m))->unique()->filter()->values()->all();
    }

    protected function normalizeMobile(string $mobile): string
    {
        $mobile = preg_replace('/\D/', '', $mobile);
        if (str_starts_with($mobile, '98')) {
            return $mobile;
        }
        if (str_starts_with($mobile, '0')) {
            return '98' . substr($mobile, 1);
        }
        if (strlen($mobile) === 10) {
            return '98' . $mobile;
        }
        return $mobile;
    }

    /**
     * کوتاه‌سازی متن برای رعایت محدودیت پیامک (۱ بخش فارسی ≈ ۷۰ کاراکتر).
     */
    protected function trimForSms(string $message, int $maxLength = 300): string
    {
        $message = trim($message);
        if (mb_strlen($message) <= $maxLength) {
            return $message;
        }
        return mb_substr($message, 0, $maxLength - 3) . '…';
    }
}
