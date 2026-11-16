<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Site;
use App\Services\AssetProvisioning\SiteAssetEntitlementService;
use Illuminate\Http\JsonResponse;
use SaaSPlatform\CoreUtils\Contracts\SiteContextContract;

/**
 * ADR-087: GET /api/asset-entitlements, the provisioning endpoint a tenant
 * syncs its asset entitlements from.
 *
 * Auth: license_key + domain (tenant.api), which also binds the site
 * context, so a tenant can only ever read its own site's answer. Nothing
 * in the request influences the answer: it is computed from the
 * platform's own licence projection, vertical, grants and overrides
 * (SiteAssetEntitlementService::forSite). Read-only, never cached
 * platform-side, so a grant or revoke is visible on the tenant's next sync.
 */
class SiteAssetEntitlementApiController extends Controller
{
    public function __construct(
        protected SiteContextContract $siteContext,
        protected SiteAssetEntitlementService $service,
    ) {}

    public function show(): JsonResponse
    {
        $site = Site::query()->findOrFail($this->siteContext->requireSiteId());

        return response()
            ->json(['available' => true] + $this->service->forSite($site)->toPayload())
            ->header('Cache-Control', 'no-store');
    }
}
