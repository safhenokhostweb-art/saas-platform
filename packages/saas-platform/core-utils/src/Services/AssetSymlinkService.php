<?php

namespace SaaSPlatform\CoreUtils\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

/**
 * سرویس مدیریت Symlink برای Assetهای ماژول‌ها.
 *
 * این سرویس در محیط توسعه (Local/Staging) به‌صورت خودکار
 * پوشه assets ماژول را به public/vendor/{module} لینک می‌کند
 * تا دیگر نیاز به اجرای دستی vendor:publish نباشد.
 */
class AssetSymlinkService
{
    /**
     * مسیر public/vendor در پروژه مصرف‌کننده.
     */
    protected string $publicVendorPath;

    public function __construct()
    {
        $this->publicVendorPath = public_path('vendor');
    }

    /**
     * بررسی و ایجاد Symlink برای یک ماژول.
     *
     * @param string $moduleName نام ماژول (مثلاً 'tickets')
     * @param string $sourcePath مسیر مطلق پوشه assets در پکیج
     * @return bool
     */
    public function ensure(string $moduleName, string $sourcePath): bool
    {
        // فقط در محیط غیر production اجرا کن
        if ($this->isProduction()) {
            return false;
        }

        $targetPath = $this->publicVendorPath . '/' . $moduleName;

        // اگر target از قبل وجود دارد و معتبر است، کاری نکن
        if (is_dir($targetPath) && is_link($targetPath)) {
            return true;
        }

        // اگر target وجود دارد ولی Symlink نیست (publish شده)، کاری نکن
        if (is_dir($targetPath) && ! is_link($targetPath)) {
            return true;
        }

        // اگر target یک فایل یا لینک خراب است، حذفش کن
        if (file_exists($targetPath) || is_link($targetPath)) {
            File::delete($targetPath);
        }

        // ایجاد دایرکتوری vendor اگر وجود ندارد
        if (! is_dir($this->publicVendorPath)) {
            File::makeDirectory($this->publicVendorPath, 0755, true);
        }

        try {
            // ایجاد Symlink (Windows: directory junction)
            $this->createSymlink($sourcePath, $targetPath);
            Log::info("AssetSymlink: Symlink created for module '{$moduleName}'", [
                'source' => $sourcePath,
                'target' => $targetPath,
            ]);
            return true;
        } catch (\Throwable $e) {
            Log::error("AssetSymlink: Failed to create symlink for module '{$moduleName}'", [
                'error' => $e->getMessage(),
                'source' => $sourcePath,
                'target' => $targetPath,
            ]);
            return false;
        }
    }

    /**
     * ایجاد Symlink متناسب با سیستم‌عامل.
     */
    protected function createSymlink(string $source, string $target): void
    {
        if (PHP_OS_FAMILY === 'Windows') {
            // Windows: از دستور mklink /J (Junction) استفاده کن
            $source = rtrim($source, '\\/');
            $target = rtrim($target, '\\/');
            $command = sprintf('mklink /J "%s" "%s"', $target, $source);
            exec($command, $output, $exitCode);

            if ($exitCode !== 0) {
                throw new \RuntimeException("Failed to create junction: " . implode("\n", $output));
            }
        } else {
            // Linux/Mac: از symlink استاندارد استفاده کن
            symlink($source, $target);
        }
    }

    /**
     * حذف Symlink یک ماژول.
     */
    public function remove(string $moduleName): bool
    {
        $targetPath = $this->publicVendorPath . '/' . $moduleName;

        if (is_link($targetPath) || (PHP_OS_FAMILY === 'Windows' && is_dir($targetPath))) {
            if (PHP_OS_FAMILY === 'Windows') {
                rmdir($targetPath); // Windows junction را با rmdir حذف کن
            } else {
                File::delete($targetPath);
            }
            Log::info("AssetSymlink: Symlink removed for module '{$moduleName}'");
            return true;
        }

        return false;
    }

    /**
     * بررسی کن که آیا Symlink برای یک ماژول فعال است.
     */
    public function exists(string $moduleName): bool
    {
        $targetPath = $this->publicVendorPath . '/' . $moduleName;
        return is_dir($targetPath) && is_link($targetPath);
    }

    /**
     * بررسی محیط Production.
     */
    protected function isProduction(): bool
    {
        return app()->environment('production');
    }
}
