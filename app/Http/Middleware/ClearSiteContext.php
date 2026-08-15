<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use SaaSPlatform\CoreUtils\Contracts\SiteContextContract;
use Symfony\Component\HttpFoundation\Response;

/** Resets site-only state around non-tenant web requests and long-lived workers. */
final class ClearSiteContext
{
    public function __construct(private readonly SiteContextContract $siteContext) {}

    public function handle(Request $request, Closure $next): Response
    {
        $this->siteContext->clear();
        try { return $next($request); }
        finally { $this->siteContext->clear(); }
    }
}
