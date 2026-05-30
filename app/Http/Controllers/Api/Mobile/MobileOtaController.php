<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use SaaSPlatform\CoreUtils\MobileRuntime\OTA\MobileUpdateManifestBuilder;
use SaaSPlatform\CoreUtils\MobileRuntime\OTA\MobileVersionEnforcementService;
use SaaSPlatform\CoreUtils\MobileRuntime\OTA\MobileFeatureBundleRegistry;
use SaaSPlatform\CoreUtils\MobileRuntime\Governance\MobileRuntimeManifestBuilder;

class MobileOtaController extends Controller
{
    public function __construct(
        private readonly MobileUpdateManifestBuilder $manifestBuilder,
        private readonly MobileVersionEnforcementService $enforcement,
        private readonly MobileFeatureBundleRegistry $bundleRegistry,
        private readonly MobileRuntimeManifestBuilder $runtimeManifestBuilder,
    ) {}

    public function manifest(Request $request): JsonResponse
    {
        $tenantId  = $this->tenantId($request);
        $appVersion = $request->header('X-App-Version', '1.0.0');
        $channel   = $request->query('channel', 'stable');
        $manifest  = $this->manifestBuilder->build($tenantId, $appVersion, $channel);
        $signed    = $this->manifestBuilder->sign($manifest);
        return response()->json(['data' => $signed]);
    }

    public function checkUpdate(Request $request): JsonResponse
    {
        $request->validate([
            'app_version' => 'required|string',
            'device_id'   => 'required|string',
            'channel'     => 'in:stable,beta,canary',
        ]);

        $tenantId   = $this->tenantId($request);
        $result     = $this->enforcement->enforce($request->app_version, $tenantId);
        $manifest   = $this->manifestBuilder->build($tenantId, $request->app_version, $request->channel ?? 'stable');

        return response()->json([
            'data' => [
                'enforcement' => $result,
                'manifest'    => $manifest,
                'update_available' => $result['action'] !== 'none',
                'mandatory'        => $result['action'] === 'hard_update',
            ],
        ]);
    }

    public function getBundle(Request $request, string $bundleId): JsonResponse
    {
        $tenantId = $this->tenantId($request);
        $bundle   = $this->bundleRegistry->getBundle($bundleId);

        if (! $bundle) {
            return response()->json(['message' => 'Bundle not found'], 404);
        }

        if (! $this->bundleRegistry->isActive($bundleId, $tenantId)) {
            return response()->json(['message' => 'Bundle not available for your tenant'], 403);
        }

        return response()->json(['data' => $bundle]);
    }

    private function tenantId(Request $request): string
    {
        return (string) ($request->attributes->get('tenant_site')?->id ?? $request->user()?->site_id ?? 'platform');
    }
}
