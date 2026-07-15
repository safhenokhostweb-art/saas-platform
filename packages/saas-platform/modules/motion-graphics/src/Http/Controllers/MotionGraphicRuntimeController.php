<?php

namespace SaaSPlatform\Modules\MotionGraphics\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use SaaSPlatform\Modules\MotionGraphics\Contracts\SiteResolverContract;
use SaaSPlatform\Modules\MotionGraphics\Services\MotionDeploymentService;

final class MotionGraphicRuntimeController extends Controller
{
    public function __construct(
        private readonly SiteResolverContract $sites,
        private readonly MotionDeploymentService $deployments,
    ) {}

    public function show(Request $request, string $slug): JsonResponse
    {
        $siteIdentifier = $this->sites->currentSiteIdentifier();
        abort_if($siteIdentifier === null, 404);
        $manifests = $this->deployments->manifestsFor(
            $siteIdentifier,
            (string) $request->query('page', 'homepage'),
            $request->query('placement'),
        );
        $manifest = $manifests->firstWhere('slug', $slug);
        abort_if($manifest === null, 404);

        return response()->json($manifest)
            ->header('Cache-Control', 'private, max-age=300')
            ->header('X-Content-Type-Options', 'nosniff');
    }
}
