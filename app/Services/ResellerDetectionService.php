<?php

namespace App\Services;

use App\Models\Reseller;
use Illuminate\Http\Request;

/**
 * تشخیص Reseller از دامنه یا زیردامنهٔ درخواست (برای White-label).
 */
class ResellerDetectionService
{
    protected static ?Reseller $cached = null;

    public static function fromRequest(Request $request): ?Reseller
    {
        if (self::$cached !== null) {
            return self::$cached;
        }

        $host = strtolower($request->getHost());

        // دامنهٔ اختصاصی: host دقیقاً با custom_domain یکی باشد
        $byDomain = Reseller::where('is_white_label', true)
            ->whereNotNull('custom_domain')
            ->where('custom_domain', $host)
            ->first();

        if ($byDomain) {
            self::$cached = $byDomain;
            return $byDomain;
        }

        // زیردامنه: مثلاً reseller1.platform.com
        $platformHost = self::platformHost();
        if ($platformHost && str_ends_with($host, '.' . $platformHost)) {
            $subdomain = substr($host, 0, -strlen('.' . $platformHost));
            if ($subdomain !== 'www' && $subdomain !== '') {
                $bySubdomain = Reseller::where('is_white_label', true)
                    ->where('subdomain', $subdomain)
                    ->first();
                if ($bySubdomain) {
                    self::$cached = $bySubdomain;
                    return $bySubdomain;
                }
            }
        }

        return null;
    }

    protected static function platformHost(): ?string
    {
        $url = config('app.url');
        if (!$url) {
            return null;
        }
        $parsed = parse_url($url);
        if (empty($parsed['host'])) {
            return null;
        }
        return strtolower($parsed['host']);
    }

    public static function clearCache(): void
    {
        self::$cached = null;
    }
}
