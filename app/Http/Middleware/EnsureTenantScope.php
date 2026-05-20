<?php
namespace App\Http\Middleware;

use App\Services\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * اطمینان از وجود tenant برای مسیرهایی که به tenant نیاز دارند.
 * برای مسیرهای reseller، EnsureUserIsReseller کافی است؛ این middleware برای موارد خاص است.
 */
class EnsureTenantScope
{
    public function __construct(
        protected TenantContext $tenantContext
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->tenantContext->hasTenant()) {
            abort(403, 'دسترسی به این بخش نیاز به شناسایی tenant دارد.');
        }

        return $next($request);
    }
}
