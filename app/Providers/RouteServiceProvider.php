<?php
namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * The path to your application's "home" route.
     *
     * Typically, users are redirected here after authentication.
     *
     * @var string
     */
    public const HOME = '/dashboard';

    /**
     * Define your route model bindings, pattern filters, and other route configuration.
     */
    public function boot(): void
    {
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('mobile-api', function (Request $request) {
            return Limit::perMinute(120)->by($request->user()?->id ?: $request->ip());
        });

        $this->routes(function () {
            Route::middleware('api')
                ->prefix('api')
                ->group(base_path('routes/api.php'));

            // Phase M1.0 — Self-Hosted Mobile Runtime API
            Route::middleware('api')
                ->prefix('api/mobile')
                ->group(base_path('packages/saas-platform/core-utils/Routes/mobile-api.php'));

            // ADR-078 — curated motion library files, referenced root-relative by
            // the Motion Graphics runtime manifest. Deliberately outside the
            // `web` group: a static, immutable asset gets no session or cookie.
            Route::get('motion/{slug}/{version}/animation.svg', \App\Http\Controllers\MotionLibraryAssetController::class)
                ->where(['slug' => '[a-z0-9-]+', 'version' => 'v[0-9]+'])
                ->name('motion.library.asset');

            // ADR-081 — exclusive (customer_specific) library motions, only at
            // the site-scoped signed URL the owning site's manifest carries.
            Route::get('motion/private/{slug}/{version}/{site}/{expires}/{signature}/animation.svg', \App\Http\Controllers\MotionPrivateAssetController::class)
                ->where(['slug' => '[a-z0-9-]+', 'version' => 'v[0-9]+', 'site' => '[0-9]+', 'expires' => '[0-9]+', 'signature' => '[a-f0-9]{64}'])
                ->name('motion.library.private-asset');

            Route::middleware('web')
                ->group(base_path('routes/web.php'));
        });
    }
}
