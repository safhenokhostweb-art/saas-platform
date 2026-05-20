<?php
namespace App\Http\Middleware;

use App\Services\ResellerDetectionService;
use App\Services\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * تشخیص tenant از دامنه/زیردامنه و تنظیم TenantContext.
 * بعد از DetectResellerFromDomain اجرا می‌شود.
 */
class SetTenantContext
{
    public function __construct(
        protected TenantContext $tenantContext
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $this->tenantContext->clear();

        $reseller = ResellerDetectionService::fromRequest($request);
        if ($reseller) {
            $this->tenantContext->set($reseller);
        }

        $response = $next($request);

        $this->tenantContext->clear();

        return $response;
    }
}
