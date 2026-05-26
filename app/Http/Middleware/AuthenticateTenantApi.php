<?php

namespace App\Http\Middleware;

use App\Models\Site;
use App\Services\LicenseServerService;
use App\Support\SecuritySanitizer;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * PHASE 7.25 — TASK 8: Tenant API authentication middleware.
 * PHASE 8.0 — SECURITY: License key now read from Authorization: Bearer header.
 *             Body fallback retained for backward compatibility with older customer-site
 *             releases. When body is used a deprecation warning is logged.
 *             Credentials are NEVER echoed back in error responses or logs.
 */
class AuthenticateTenantApi
{
    public function __construct(
        private readonly LicenseServerService $licenseService
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        [$licenseKey, $credentialSource] = $this->resolveCredentials($request);
        $domain = $this->resolveDomain($request);

        if ($licenseKey === '' || $domain === '') {
            return response()->json([
                'success' => false,
                'error'   => 'missing_credentials',
                'message' => 'احراز هویت الزامی است. از Authorization: Bearer <license_key> استفاده کنید.',
            ], 401);
        }

        // Warn when legacy body-based credentials are still being used
        if ($credentialSource === 'body') {
            Log::warning('DEPRECATED_CREDENTIAL_TRANSPORT', [
                'message'    => 'license_key sent in request body. Migrate to Authorization: Bearer header.',
                'domain'     => $domain,
                'ip'         => $request->ip(),
                'user_agent' => $request->userAgent(),
                // license_key is intentionally NOT logged
            ]);
        }

        $result = $this->licenseService->validate($licenseKey, $domain);

        if (!($result['valid'] ?? false)) {
            return response()->json([
                'success' => false,
                'error'   => $result['error'] ?? 'invalid_license',
                'message' => $result['message'] ?? 'لایسنس نامعتبر است.',
                // credential value is never returned to caller
            ], 403);
        }

        $siteId = (int) ($result['site_id'] ?? 0);
        if ($siteId < 1) {
            return response()->json([
                'success' => false,
                'error'   => 'site_not_found',
                'message' => 'سایت مورد نظر یافت نشد.',
            ], 403);
        }

        $site = Site::query()->find($siteId);
        if (!$site) {
            return response()->json([
                'success' => false,
                'error'   => 'site_not_found',
                'message' => 'سایت مورد نظر یافت نشد.',
            ], 403);
        }

        $request->attributes->set('tenant_site', $site);

        // Remove credential from request inputs so it cannot appear in dumps / Telescope
        SecuritySanitizer::scrubRequest($request);

        return $next($request);
    }

    /**
     * Resolve the license key.
     * Priority 1: Authorization: Bearer <token>
     * Priority 2: request body / query (deprecated, logged)
     *
     * @return array{string, string} [$licenseKey, $source]
     */
    private function resolveCredentials(Request $request): array
    {
        $bearer = $request->bearerToken();
        if ($bearer !== null && $bearer !== '') {
            return [$bearer, 'header'];
        }

        $body = (string) $request->input('license_key', '');
        return [$body, 'body'];
    }

    /**
     * Domain can still travel as a query/body param — it is not a secret.
     */
    private function resolveDomain(Request $request): string
    {
        return (string) $request->input('domain', '');
    }
}
