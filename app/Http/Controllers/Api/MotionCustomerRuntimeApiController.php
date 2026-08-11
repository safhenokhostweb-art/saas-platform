<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Site;
use App\Modules\MotionGraphics\DTO\CustomerRuntimeAssetEntry;
use App\Modules\MotionGraphics\DTO\MotionAdapterContext;
use App\Modules\MotionGraphics\Exceptions\MotionAdapterFailClosedException;
use App\Modules\MotionGraphics\Services\MotionBespokeCustomerRuntimeService;
use App\Modules\MotionGraphics\Services\MotionCustomerRuntimeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Phase 8 (docs/motion-customer-runtime-phase8/CUSTOMER_RUNTIME_ARCHITECTURE.md):
 * the single customer-facing Motion Graphics endpoint. Auth: `tenant.api`
 * middleware (license_key + domain), the same canonical channel already
 * used by ThemeMarketplaceApiController, ApiLifecycleController, etc. —
 * no new authentication mechanism.
 *
 * Fails closed to a safe, empty `{"available": false}` shape for every
 * gate failure (flag off, no entitlement, no active package) — never a
 * 500, so a disabled/not-yet-enabled site's normal page rendering is
 * never disrupted by this endpoint (task requirement: "preserve existing
 * site behavior when disabled").
 */
class MotionCustomerRuntimeApiController extends Controller
{
    public function __construct(
        private readonly MotionCustomerRuntimeService $runtimeService,
        private readonly MotionBespokeCustomerRuntimeService $bespokeRuntimeService,
    ) {}

    // GET /api/motion-runtime/manifest
    public function manifest(Request $request): JsonResponse
    {
        /** @var Site $site */
        $site = $request->attributes->get('tenant_site');

        $context = new MotionAdapterContext(
            actorId: 'customer-site:'.$site->id,
            siteId: (string) $site->id,
            correlationId: (string) Str::uuid(),
        );

        try {
            $manifest = $this->runtimeService->buildManifestForSite($site, $context);
        } catch (MotionAdapterFailClosedException $e) {
            return response()->json(['available' => false, 'reason' => $e->failureCode]);
        }

        return response()->json(['available' => true, ...$manifest->toArray()]);
    }

    // GET /api/motion-runtime/bespoke-manifest — Phase X, a separate
    // endpoint from manifest() by design (see
    // MotionBespokeCustomerRuntimeService's class docblock).
    public function bespokeManifest(Request $request): JsonResponse
    {
        /** @var Site $site */
        $site = $request->attributes->get('tenant_site');

        $context = new MotionAdapterContext(
            actorId: 'customer-site:'.$site->id,
            siteId: (string) $site->id,
            correlationId: (string) Str::uuid(),
        );

        try {
            $assets = $this->bespokeRuntimeService->bespokeAssetsForSite($site, $context);
        } catch (MotionAdapterFailClosedException $e) {
            return response()->json(['available' => false, 'reason' => $e->failureCode]);
        }

        return response()->json([
            'available' => true,
            'assets' => array_map(fn (CustomerRuntimeAssetEntry $entry): array => $entry->toArray(), $assets),
        ]);
    }

    // GET /api/motion-runtime/bespoke-commissions — customer-panel status
    // list (purchased flag, business status, currently assigned version).
    public function bespokeCommissions(Request $request): JsonResponse
    {
        /** @var Site $site */
        $site = $request->attributes->get('tenant_site');

        return response()->json([
            'commissions' => $this->bespokeRuntimeService->commissionsStatusForSite($site),
        ]);
    }
}
