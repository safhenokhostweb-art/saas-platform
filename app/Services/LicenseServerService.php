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
     * تولید کلید لایسنس و ذخیره در دیتابیس.
     * کلید کامل فقط همین یک‌بار برگردانده می‌شود.
     */
    public function issue(Site $site, ?string $domain = null, ?\DateTimeInterface $expiresAt = null, ?string $productSku = null): string
    {
        $key = $this->generateKey();
        $prefix = substr(preg_replace('/[^A-Z0-9]/', '', strtoupper($key)), 0, 8);
        $hash = hash('sha256', $key);

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

            $key = preg_replace('/\s+/', '', $licenseKey);
            if (strlen($key) < 12) {
                return ['valid' => false, 'error' => 'invalid_key', 'message' => 'کلید لایسنس نامعتبر است.'];
            }

            $prefix = substr(preg_replace('/[^A-Z0-9]/', '', strtoupper($key)), 0, 8);
            $hash = hash('sha256', $key);

            $license = PlatformLicense::where('license_key_prefix', $prefix)
                ->where('license_key_hash', $hash)
                ->with('site')
                ->first();

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
