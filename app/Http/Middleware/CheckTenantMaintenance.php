<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use SaaSPlatform\CoreUtils\Services\Maintenance\TenantMaintenanceManager;
use SaaSPlatform\CoreUtils\Contracts\TenantContextContract;

class CheckTenantMaintenance
{
    public function __construct(
        private TenantMaintenanceManager $maintenance,
        private TenantContextContract    $tenantContext
    ) {}

    public function handle(Request $request, Closure $next): mixed
    {
        // Health check endpoints always bypass
        if (str_starts_with($request->path(), 'health/')) {
            return $next($request);
        }

        // Only check when a tenant site is active
        if (!$this->tenantContext->hasTenant() || !$this->tenantContext->isSite()) {
            return $next($request);
        }

        $siteId = $this->tenantContext->getSiteId();
        if (!$siteId) {
            return $next($request);
        }

        if (!$this->maintenance->isUnderMaintenance($siteId)) {
            return $next($request);
        }

        $record = $this->maintenance->getMaintenanceRecord($siteId);

        // Admin bypass
        if ($record?->allow_admin_bypass && auth()->check() && auth()->user()?->is_admin) {
            return $next($request);
        }

        $message = $record?->message ?? 'سیستم در حال نگهداری است.';

        if ($request->expectsJson()) {
            return response()->json([
                'error'   => 'maintenance',
                'message' => $message,
            ], 503);
        }

        return response($message, 503)->header('Retry-After', '300');
    }
}
