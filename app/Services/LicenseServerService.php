<?php

namespace App\Services;

use App\Models\PlatformLicense;
use App\Models\Site;
use App\Services\ModuleAssignmentService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class LicenseServerService
{
    /**
     * Phase 5 / P0-5:
     * هش کلید لایسنس با HMAC-SHA256 (به‌جای SHA256 ساده) محاسبه می‌شود تا
     * rainbow tables عمومی و brute-force بدون دسترسی به APP_KEY عملاً بی‌اثر شوند.
     * طول خروجی (64 hex char) دقیقاً برابر SHA256 است، بنابراین به مایگریشن
     * دیتابیس نیاز نیست.
     *
     * برای حفظ سازگاری با لایسنس‌های قبلی که با hash('sha256', $key) ذخیره شده‌اند،
     * متد validate() ابتدا با HMAC جستجو می‌کند؛ در صورت عدم تطابق، با SHA256 legacy
     * جستجو می‌کند و در صورت یافتن، آن رکورد را در همان لحظه به HMAC ارتقا می‌دهد
     * (lazy rehash بدون migration و backfill).
     */
    private function hashKey(string $key): string
    {
        $secret = (string) config('app.key');
        return hash_hmac('sha256', $key, $secret);
    }

    private function legacyHashKey(string $key): string
    {
        return hash('sha256', $key);
    }

    /**
     * منبع واحد جستجوی لایسنس بر اساس کلید کامل.
     * این متد dual lookup (HMAC اول، سپس SHA256 legacy) و lazy rehash را در یکجا
     * encapsulate می‌کند تا هر controller و service که نیاز به یافتن لایسنس از کلید
     * دارد، رفتار یکسانی داشته باشد.
     *
     * بازگشت: مدل PlatformLicense یا null در صورت عدم تطابق.
     */
    public function findByKey(string $licenseKey): ?PlatformLicense
    {
        $key = (string) preg_replace('/\s+/', '', $licenseKey);
        if (strlen($key) < 12) {
            return null;
        }

        $prefix = substr(preg_replace('/[^A-Z0-9]/', '', strtoupper($key)), 0, 8);
        $hmacHash = $this->hashKey($key);

        $license = PlatformLicense::where('license_key_prefix', $prefix)
            ->where('license_key_hash', $hmacHash)
            ->with('site')
            ->first();

        if ($license) {
            return $license;
        }

        // مسیر سازگاری: لایسنس قدیمی با SHA256 legacy.
        $legacyHash = $this->legacyHashKey($key);
        $license = PlatformLicense::where('license_key_prefix', $prefix)
            ->where('license_key_hash', $legacyHash)
            ->with('site')
            ->first();

        if ($license) {
            try {
                $license->license_key_hash = $hmacHash;
                $license->save();
                Log::info('license.hash.upgraded_to_hmac', [
                    'license_id' => $license->id,
                    'site_id' => $license->site_id,
                ]);
            } catch (\Throwable $rehashEx) {
                Log::warning('license.hash.upgrade_failed', [
                    'license_id' => $license->id,
                    'message' => $rehashEx->getMessage(),
                ]);
                // در صورت شکست، همچنان لایسنس معتبر است؛ بار بعد دوباره fallback می‌خورد.
            }
        }

        return $license;
    }

    /**
     * تولید کلید لایسنس و ذخیره در دیتابیس.
     * کلید کامل فقط همین یک‌بار برگردانده می‌شود.
     */
    public function issue(Site $site, ?string $domain = null, ?\DateTimeInterface $expiresAt = null, ?string $productSku = null): string
    {
        $key = $this->generateKey();
        $prefix = substr(preg_replace('/[^A-Z0-9]/', '', strtoupper($key)), 0, 8);
        $hash = $this->hashKey($key);

        PlatformLicense::create([
            'site_id' => $site->id,
            'license_key_prefix' => $prefix,
            'license_key_hash' => $hash,
            'domain_allowed' => $domain,
            'expires_at' => $expiresAt,
            'product_sku' => $productSku,
            'issued_at' => now(),
        ]);

        return $key;
    }

    /**
     * اعتبارسنجی لایسنس با کلید و دامنه.
     * آرایهٔ خروجی برای API (ماژول‌ها و support_expires_at از سایت گرفته می‌شود).
     * هیچ استثنایی پرتاب نمی‌کند؛ در صورت خطا آرایهٔ valid=>false برمی‌گردد.
     */
    public function validate(string $licenseKey, string $domain): array
    {
        try {
            if (!Schema::hasTable('platform_licenses')) {
                return $this->serverError('جدول platform_licenses وجود ندارد. مایگریشن را اجرا کنید: php artisan migrate');
            }

            $key = (string) preg_replace('/\s+/', '', $licenseKey);
            if (strlen($key) < 12) {
                return ['valid' => false, 'error' => 'invalid_key', 'message' => 'کلید لایسنس نامعتبر است.'];
            }

            $license = $this->findByKey($key);

            if (!$license) {
                return ['valid' => false, 'error' => 'invalid_key', 'message' => 'کلید لایسنس نامعتبر است.'];
            }

            if ($license->isRevoked()) {
                return ['valid' => false, 'error' => 'revoked', 'message' => 'این لایسنس لغو شده است.'];
            }

            if ($license->isExpired()) {
                return ['valid' => false, 'error' => 'expired', 'message' => 'تاریخ انقضای لایسنس گذشته است.'];
            }

            if (!$license->isDomainAllowed($domain)) {
                return ['valid' => false, 'error' => 'domain_mismatch', 'message' => 'دامنهٔ این سایت با لایسنس مطابقت ندارد.'];
            }

            $site = $license->site;
            if (!$site || !$site->is_active) {
                return ['valid' => false, 'error' => 'site_inactive', 'message' => 'سایت در پلتفرم غیرفعال است.'];
            }

            $modules = [];
            if (Schema::hasTable('site_modules') && Schema::hasTable('modules')) {
                $modules = app(ModuleAssignmentService::class)->getEnabledSlugsForSite($site);
            }

            $expiresAt = null;
            if ($license->expires_at) {
                $expiresAt = $license->expires_at->format(\DateTimeInterface::ATOM);
            }
            $supportExpiresAt = null;
            if ($site->support_expires_at) {
                $supportExpiresAt = $site->support_expires_at->format(\DateTimeInterface::ATOM);
            }
            $supportStartsAt = null;
            if (isset($site->support_starts_at) && $site->support_starts_at) {
                $supportStartsAt = $site->support_starts_at->format(\DateTimeInterface::ATOM);
            }
            $supportLevel = $site->support_level ?? 'basic';

            return [
                'valid' => true,
                'site_id' => $site->id,
                'expires_at' => $expiresAt,
                'support_expires_at' => $supportExpiresAt,
                'support_starts_at' => $supportStartsAt,
                'support_level' => $supportLevel,
                'modules' => $modules,
                'site_title' => $site->title ?? '',
            ];
        } catch (\Throwable $e) {
            Log::error('LicenseServerService::validate exception', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            return $this->serverError($e->getMessage());
        }
    }

    private function serverError(string $detail): array
    {
        $message = config('app.debug')
            ? 'خطای سرور لایسنس: ' . $detail
            : 'خطای سرور لایسنس. بعداً تلاش کنید.';
        return [
            'valid' => false,
            'error' => 'server_error',
            'message' => $message,
        ];
    }

    protected function generateKey(): string
    {
        $segments = [];
        for ($i = 0; $i < 4; $i++) {
            $segments[] = strtoupper(Str::random(4));
        }
        return implode('-', $segments);
    }
}
