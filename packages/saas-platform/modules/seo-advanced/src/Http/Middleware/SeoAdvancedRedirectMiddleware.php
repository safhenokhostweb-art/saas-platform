<?php

namespace SaaSPlatform\Modules\SeoAdvanced\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use SaaSPlatform\Modules\SeoAdvanced\Models\SeoAdvancedRedirect;

/**
 * Phase 18-C — Part 2: turns the Redirect Manager from CRUD-only into a
 * real runtime effect. Registered globally (see
 * SeoAdvancedServiceProvider::boot() → Kernel::pushMiddleware()) rather
 * than on the 'web' route-group, specifically so it also catches paths
 * that no longer match ANY route (the whole point of a redirect: an old
 * URL that was deleted/renamed) — group middleware never runs for a
 * request that doesn't match a route in that group.
 *
 * Priority: an explicit redirect rule always wins over whatever would
 * otherwise happen at that path (including a coincidentally-matching real
 * route) — an admin who created a redirect did so deliberately, and this
 * module has no way to know "is this path a real route" without coupling
 * to Core's router. Tenant isolation: scoped to the single-tenant site_id
 * convention already established in Phase 18-B's SeoOverrideProviderAdapter.
 */
class SeoAdvancedRedirectMiddleware
{
    private const SINGLE_TENANT_SITE_ID = 1;

    /** Never redirect the module's own routes, or non-GET/HEAD requests. */
    private const SKIP_PREFIXES = ['seo-advanced/', 'admin/seo-advanced/'];

    public function handle(Request $request, Closure $next)
    {
        if (!$request->isMethod('GET') && !$request->isMethod('HEAD')) {
            return $next($request);
        }

        $path = ltrim($request->path(), '/');

        foreach (self::SKIP_PREFIXES as $prefix) {
            if (str_starts_with($path, $prefix)) {
                return $next($request);
            }
        }

        try {
            $redirect = SeoAdvancedRedirect::where('site_id', self::SINGLE_TENANT_SITE_ID)
                ->where('from_path', '/' . $path)
                ->where('is_active', true)
                ->first();
        } catch (\Throwable $e) {
            // Table not migrated yet, DB unreachable, etc. — never break
            // the site over a redirect lookup failure.
            return $next($request);
        }

        if ($redirect === null) {
            return $next($request);
        }

        return redirect($redirect->to_path, $redirect->status_code);
    }
}
