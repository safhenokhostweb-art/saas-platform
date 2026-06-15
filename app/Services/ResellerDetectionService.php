<?php
namespace App\Services;

use App\Models\Reseller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * تشخیص Reseller از دامنه یا زیردامنهٔ درخواست (برای White-label).
 */
class ResellerDetectionService
{
    /** In-process sentinel: null = unchecked, false = checked & not found */
    protected static Reseller|false|null $cached = null;

    private const CACHE_TTL = 300;

    public static function fromRequest(Request $request): ?Reseller
    {
        if (self::$cached !== null) {
            return self::$cached instanceof Reseller ? self::$cached : null;
        }

        $host = strtolower($request->getHost());
        $cacheKey = 'reseller.domain.' . md5($host);

        // Redis cache: false = "no reseller for this host" (avoid repeated DB queries)
        if (Cache::has($cacheKey)) {
            $hit = Cache::get($cacheKey);
            self::$cached = $hit instanceof Reseller ? $hit : false;
            return $hit instanceof Reseller ? $hit : null;
        }

        $reseller = self::resolveFromDb($host);

        Cache::put($cacheKey, $reseller ?? false, self::CACHE_TTL);
        self::$cached = $reseller ?? false;

        return $reseller;
    }

    private static function resolveFromDb(string $host): ?Reseller
    {
        // دامنهٔ اختصاصی: host دقیقاً با custom_domain یکی باشد
        $byDomain = Reseller::where('is_white_label', true)
            ->whereNotNull('custom_domain')
            ->where('custom_domain', $host)
            ->first();

        if ($byDomain) {
            return $byDomain;
        }

        // زیردامنه: مثلاً reseller1.platform.com
        $platformHost = self::platformHost();
        if ($platformHost && str_ends_with($host, '.' . $platformHost)) {
            $subdomain = substr($host, 0, -strlen('.' . $platformHost));
            if ($subdomain !== 'www' && $subdomain !== '') {
                return Reseller::where('is_white_label', true)
                    ->where('subdomain', $subdomain)
                    ->first();
            }
        }

        return null;
    }

    public static function forgetCacheForHost(string $host): void
    {
        Cache::forget('reseller.domain.' . md5(strtolower($host)));
        self::$cached = null;
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
