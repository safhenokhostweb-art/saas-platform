<?php

namespace App\Services;

use App\Models\Setting;
use App\Models\Site;
use App\Models\SiteCaptchaSetting;
use App\Models\Module;
use Illuminate\Support\Facades\Session;

class CaptchaService
{
    public const TYPE_PERSIAN_NUMBER = 'persian_number';
    public const TYPE_NORMAL = 'normal';
    public const TYPE_HCAPTCHA = 'hcaptcha';

    /** آیا ماژول کپچا در پلتفرم فعال است؟ */
    public static function isModuleEnabled(): bool
    {
        try {
            $module = Module::where('slug', 'captcha')->first();
            return $module && $module->is_active;
        } catch (\Throwable $e) {
            // در صورت خطای اتصال به دیتابیس یا هر خطای دیگر، کپچا غیرفعال در نظر گرفته می‌شود
            return false;
        }
    }

    /** آیا کپچا برای فرم‌های ورود و ثبت‌نام فعال است؟ (تنظیم جداگانه در پنل) */
    public static function isAuthCaptchaEnabled(): bool
    {
        if (!self::isModuleEnabled()) {
            return false;
        }
        return (string) Setting::get('auth_captcha_enabled', '0') === '0';
    }

    /** تنظیمات کپچا برای فرم ورود و ثبت‌نام (فقط وقتی auth_captcha_enabled روشن باشد) */
    public static function getAuthCaptchaConfig(): array
    {
        if (!self::isAuthCaptchaEnabled()) {
            return ['enabled' => false, 'type' => null, 'hcaptcha_site_key' => '', 'hcaptcha_secret_key' => ''];
        }
        $cfg = self::getForSite(null);
        $cfg['enabled'] = true;
        $cfg['type'] = (string) Setting::get('captcha_type', $cfg['type'] ?: self::TYPE_PERSIAN_NUMBER);
        $cfg['hcaptcha_site_key'] = (string) Setting::get('hcaptcha_site_key', $cfg['hcaptcha_site_key'] ?? '');
        $cfg['hcaptcha_secret_key'] = (string) Setting::get('hcaptcha_secret_key', $cfg['hcaptcha_secret_key'] ?? '');
        return $cfg;
    }

    /** تنظیمات کپچا برای یک سایت (با fallback به پیش‌فرض پلتفرم) */
    public static function getForSite(?int $siteId = null): array
    {
        $enabled = false;
        $type = self::TYPE_PERSIAN_NUMBER;
        $siteKey = '';
        $secretKey = '';

        if ($siteId) {
            $siteSetting = SiteCaptchaSetting::where('site_id', $siteId)->first();
            if ($siteSetting) {
                // سایت تنظیمات دارد: فعال/غیرفعال و نوع و کلیدها (خالی = استفاده از پیش‌فرض)
                $enabled = (bool) $siteSetting->captcha_enabled;
                $type = $siteSetting->captcha_type ?: self::TYPE_PERSIAN_NUMBER;
                $siteKey = (string) $siteSetting->hcaptcha_site_key;
                $secretKey = (string) $siteSetting->hcaptcha_secret_key;
            }
        }

        // اگر سایت مشخص نشده یا سایت تنظیم نداشت، از پلتفرم استفاده کن
        $hasSiteSetting = $siteId && SiteCaptchaSetting::where('site_id', $siteId)->exists();
        if (!$hasSiteSetting) {
            if (self::isModuleEnabled() && (string) Setting::get('captcha_enabled', '0') === '1') {
                $enabled = true;
                $type = (string) Setting::get('captcha_type', self::TYPE_PERSIAN_NUMBER);
                $siteKey = (string) Setting::get('hcaptcha_site_key', '');
                $secretKey = (string) Setting::get('hcaptcha_secret_key', '');
            }
        } elseif ($enabled && ($siteKey === '' || $secretKey === '') && $type === self::TYPE_HCAPTCHA) {
            $siteKey = $siteKey ?: (string) Setting::get('hcaptcha_site_key', '');
            $secretKey = $secretKey ?: (string) Setting::get('hcaptcha_secret_key', '');
        }

        return [
            'enabled' => $enabled,
            'type' => $type,
            'hcaptcha_site_key' => $siteKey,
            'hcaptcha_secret_key' => $secretKey,
        ];
    }

    /** تولید کپچای عدد فارسی و ذخیره در سشن */
    public static function generatePersianNumber(int $length = 4): string
    {
        $persianDigits = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
        $code = '';
        for ($i = 0; $i < $length; $i++) {
            $code .= $persianDigits[random_int(0, 9)];
        }
        Session::put('captcha_persian_answer', $code);
        return $code;
    }

    /** تبدیل عدد فارسی به انگلیسی برای مقایسه */
    public static function persianToEnglish(string $input): string
    {
        $persian = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
        $english = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];
        return str_replace($persian, $english, $input);
    }

    /** اعتبارسنجی کپچای عدد فارسی */
    public static function validatePersianNumber(string $userInput): bool
    {
        $expected = Session::pull('captcha_persian_answer');
        if (!$expected) {
            return false;
        }
        $normalized = self::persianToEnglish(trim($userInput));
        $expectedNormalized = self::persianToEnglish($expected);
        return $normalized === $expectedNormalized;
    }

    /** تبدیل عدد به رشتهٔ اعداد فارسی */
    public static function numberToPersian(int $n): string
    {
        $persianDigits = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
        $s = (string) $n;
        $out = '';
        for ($i = 0; $i < strlen($s); $i++) {
            $out .= $persianDigits[(int) $s[$i]];
        }
        return $out;
    }

    /** کپچای معمولی: جمع دو عدد فارسی و ذخیره جواب در سشن */
    public static function generateNormal(): array
    {
        $a = random_int(1, 12);
        $b = random_int(1, 12);
        $sum = $a + $b;
        Session::put('captcha_normal_answer', (string) $sum);
        return [
            'text' => self::numberToPersian($a) . ' + ' . self::numberToPersian($b) . ' = ?',
            'answer' => (string) $sum,
        ];
    }

    /** اعتبارسنجی کپچای معمولی */
    public static function validateNormal(string $userInput): bool
    {
        $expected = Session::pull('captcha_normal_answer');
        if ($expected === null || $expected === '') {
            return false;
        }
        $normalized = trim(self::persianToEnglish($userInput));
        return $normalized === $expected;
    }

    /** اعتبارسنجی hCaptcha با سرور */
    public static function verifyHcaptcha(string $token, string $secretKey): bool
    {
        if (!$secretKey) {
            if (config('app.debug')) {
                \Illuminate\Support\Facades\Log::warning('CaptchaService: hCaptcha secret key is empty.');
            }
            return false;
        }
        if (trim($token) === '') {
            return false;
        }
        // در محیط لوکال می‌توان با AUTH_CAPTCHA_SKIP_IN_LOCAL=true موقتاً تأیید را رد نکن (فقط برای توسعه)
        if (config('app.env') === 'local' && config('services.hcaptcha.skip_in_local', false)) {
            return true;
        }
        $response = \Illuminate\Support\Facades\Http::timeout(10)->asForm()->post('https://hcaptcha.com/siteverify', [
            'secret' => $secretKey,
            'response' => $token,
        ]);
        $data = $response->json();
        $success = isset($data['success']) && $data['success'] === true;
        if (!$success && config('app.debug') && is_array($data)) {
            \Illuminate\Support\Facades\Log::debug('hCaptcha verify failed', [
                'error_codes' => $data['error-codes'] ?? [],
                'body' => $data,
            ]);
        }
        return $success;
    }
}
