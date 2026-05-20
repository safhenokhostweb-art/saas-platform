<?php
namespace App\Http\Middleware;

use App\Services\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsReseller
{
    public function __construct(
        protected TenantContext $tenantContext
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()) {
            return redirect()->route('login.show');
        }

        $reseller = $request->user()->reseller;
        if (! $reseller) {
            abort(403, 'شما دسترسی به پنل نمایندگی ندارید.');
        }

        $request->attributes->set('current_reseller', $reseller);
        $this->tenantContext->set($reseller);

        return $next($request);
    }
}
