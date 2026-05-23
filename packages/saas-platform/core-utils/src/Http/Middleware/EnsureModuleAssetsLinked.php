<?php

namespace SaaSPlatform\CoreUtils\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use SaaSPlatform\CoreUtils\Services\AssetSymlinkService;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware برای اطمینان از وجود Symlink Assetهای ماژول.
 *
 * این Middleware در اولین درخواست به اپلیکیشن، Symlinkهای
 * مورد نیاز برای assetهای ماژول‌ها را ایجاد می‌کند.
 *
 * نحوه استفاده:
 * در app/Http/Kernel.php:
 *   protected $middleware = [
 *       \SaaSPlatform\CoreUtils\Http\Middleware\EnsureModuleAssetsLinked::class,
 *       ...
 *   ];
 *
 * یا به صورت گروهی:
 *   protected $middlewareGroups = [
 *       'web' => [
 *           \SaaSPlatform\CoreUtils\Http\Middleware\EnsureModuleAssetsLinked::class,
 *           ...
 *       ],
 *   ];
 */
class EnsureModuleAssetsLinked
{
    /**
     * لیست ماژول‌هایی که باید assetهایشان لینک شود.
     * هر آیتم: ['name' => 'module_name', 'source' => 'absolute_source_path']
     *
     * @var array<int, array{name: string, source: string}>
     */
    protected array $modules = [];

    /**
     * نشان‌دهنده اینکه آیا در این ریکوئست چک شده یا نه.
     */
    protected static bool $checked = false;

    public function __construct()
    {
        $this->modules = config('module-assets.modules', []);
    }

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // فقط یکبار در طول عمر درخواست چک کن
        if (! static::$checked) {
            static::$checked = true;
            $this->ensureSymlinks();
        }

        return $next($request);
    }

    /**
     * ایجاد Symlink برای تمام ماژول‌های ثبت شده.
     */
    protected function ensureSymlinks(): void
    {
        // فقط در محیط غیر production
        if (app()->environment('production')) {
            return;
        }

        $service = app(AssetSymlinkService::class);

        foreach ($this->modules as $module) {
            $service->ensure($module['name'], $module['source']);
        }
    }
}
