<?php
namespace App\Helpers;

use App\Models\Reseller;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Storage;

/**
 * برندینگ White-label برای Reseller تشخیص‌داده‌شده از دامنه.
 * وقتی کاربر از دامنهٔ Reseller وارد شده، نام و لوگوی Reseller برگردانده می‌شود.
 */
class ResellerBranding
{
    protected static ?Reseller $reseller = null;

    public static function currentReseller(): ?Reseller
    {
        if (self::$reseller !== null) {
            return self::$reseller;
        }
        $req = request();
        if ($req && $req->attributes->has('current_reseller_from_domain')) {
            self::$reseller = $req->attributes->get('current_reseller_from_domain');
        }
        return self::$reseller;
    }

    public static function platformName(): string
    {
        $r = self::currentReseller();
        if ($r && $r->is_white_label && $r->brand_name) {
            return trim($r->brand_name);
        }
        return PlatformSettings::platformName();
    }

    public static function logoUrl(): ?string
    {
        $r = self::currentReseller();
        if ($r && $r->is_white_label && $r->logo_path) {
            $path = trim($r->logo_path);
            if (preg_match('#^https?://#i', $path) || str_starts_with($path, '/')) {
                return $path;
            }
            $path = PlatformSettings::normalizeStoragePath($path);
            if ($path !== '' && Storage::disk(config('filesystems.upload_disk', 'public'))->exists($path)) {
                return '/storage/' . $path;
            }
        }
        return PlatformSettings::logoUrl();
    }

    public static function clearCache(): void
    {
        self::$reseller = null;
    }
}
