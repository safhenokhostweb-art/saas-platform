<?php

namespace SaaSPlatform\CoreUtils\Services;

use Illuminate\Support\Facades\File;

/**
 * سرویس مدیریت Versioning (Cache Busting) برای Assetهای ماژول.
 *
 * این سرویس یک Hash بر اساس آخرین زمان تغییر فایل (mtime) یا Git Hash
 * تولید می‌کند و آن را به عنوان Query String به URL asset اضافه می‌کند.
 */
class AssetVersionService
{
    /**
     * Cache زمانی برای mtimeها (ثانیه).
     */
    protected int $cacheTtl = 3600;

    /**
     * مسیر پایه assets ماژول‌ها در vendor.
     */
    protected string $vendorBasePath;

    public function __construct()
    {
        $this->vendorBasePath = base_path('vendor/saas-platform');
    }

    /**
     * تولید نسخه (hash) برای یک فایل asset.
     *
     @param string $moduleName نام ماژول (مثلاً 'tickets')
     * @param string $assetPath مسیر نسبی فایل asset (مثلاً 'css/tickets.css')
     * @return string
     */
    public function version(string $moduleName, string $assetPath): string
    {
        // 1. تلاش برای یافتن فایل در vendor
        $filePath = $this->resolveAssetPath($moduleName, $assetPath);

        if ($filePath && File::exists($filePath)) {
            // استفاده از mtime برای تولید hash
            $mtime = File::lastModified($filePath);
            return md5($moduleName . $assetPath . $mtime);
        }

        // 2. اگر فایل در vendor نبود، از public/vendor بررسی کن
        $publicPath = public_path("vendor/{$moduleName}/{$assetPath}");
        if (File::exists($publicPath)) {
            $mtime = File::lastModified($publicPath);
            return md5($moduleName . $assetPath . $mtime);
        }

        // 3. fallback: hash بر اساس نام ماژول و مسیر
        return md5($moduleName . $assetPath);
    }

    /**
     * تولید URL کامل برای یک asset ماژول با version hash.
     *
     * @param string $moduleName
     * @param string $assetPath
     * @return string
     */
    public function url(string $moduleName, string $assetPath): string
    {
        $hash = $this->version($moduleName, $assetPath);
        $base = asset("vendor/{$moduleName}/{$assetPath}");
        return "{$base}?v={$hash}";
    }

    /**
     * مسیر واقعی فایل asset را در vendor پیدا کن.
     *
     * ساختار پکیج‌ها:
     * vendor/saas-platform/modules-{name}/Resources/assets/{assetPath}
     * vendor/saas-platform/{name}/Resources/assets/{assetPath}
     */
    protected function resolveAssetPath(string $moduleName, string $assetPath): ?string
    {
        $possiblePaths = [
            // ساختار استاندارد: vendor/saas-platform/modules-{name}/Resources/assets/
            $this->vendorBasePath . "/modules-{$moduleName}/Resources/assets/{$assetPath}",
            // ساختار جایگزین: vendor/saas-platform/{name}/Resources/assets/
            $this->vendorBasePath . "/{$moduleName}/Resources/assets/{$assetPath}",
            // ساختار در پروژه اصلی (توسعه): packages/saas-platform/modules/{name}/Resources/assets/
            base_path("packages/saas-platform/modules/{$moduleName}/Resources/assets/{$assetPath}"),
            // ساختار در پروژه اصلی (توسعه): packages/saas-platform/{name}/Resources/assets/
            base_path("packages/saas-platform/{$moduleName}/Resources/assets/{$assetPath}"),
        ];

        foreach ($possiblePaths as $path) {
            if (File::exists($path)) {
                return $path;
            }
        }

        return null;
    }
}
