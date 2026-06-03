<?php

namespace SaaSPlatform\Modules\Education\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use SaaSPlatform\Modules\Education\Contracts\Services\FeatureUsageServiceInterface;

class TrackFeatureUsageMiddleware
{
    public function __construct(
        private readonly FeatureUsageServiceInterface $usageService,
    ) {}

    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        // Only track GET requests to named admin routes; skip API/AJAX
        if (
            $request->isMethod('GET')
            && ! $request->expectsJson()
            && auth()->check()
            && auth()->user()->is_admin
        ) {
            $routeName = Route::currentRouteName();
            $userId    = auth()->id();
            $siteId    = null; // platform-level admin has no tenant site_id

            if ($routeName && str_starts_with($routeName, 'admin.')) {
                try {
                    $this->usageService->trackByRoute($routeName, $userId, $siteId);
                } catch (\Throwable) {
                    // Never let tracking break the response
                }
            }
        }

        return $response;
    }
}
