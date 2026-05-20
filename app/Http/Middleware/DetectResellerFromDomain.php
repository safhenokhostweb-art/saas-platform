<?php
namespace App\Http\Middleware;

use App\Services\ResellerDetectionService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class DetectResellerFromDomain
{
    public function handle(Request $request, Closure $next): Response
    {
        $reseller = ResellerDetectionService::fromRequest($request);
        if ($reseller) {
            $request->attributes->set('current_reseller_from_domain', $reseller);
        }

        return $next($request);
    }
}
