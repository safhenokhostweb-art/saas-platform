<?php

namespace SaaSPlatform\Modules\MotionGraphics\Http\View\Components;

use Illuminate\View\Component;
use Illuminate\View\View;
use SaaSPlatform\Modules\MotionGraphics\Contracts\SiteResolverContract;
use SaaSPlatform\Modules\MotionGraphics\Services\MotionDeploymentService;

final class MotionGraphicsComponent extends Component
{
    public readonly ?array $manifest;

    public function __construct(
        public readonly string $name,
        SiteResolverContract $sites,
        MotionDeploymentService $deployments,
        public readonly string $page = 'homepage',
        public readonly ?string $placement = null,
    ) {
        $siteIdentifier = $sites->currentSiteIdentifier();
        $this->manifest = $siteIdentifier === null
            ? null
            : $deployments->manifestsFor($siteIdentifier, $page, $placement)->firstWhere('slug', $name);
    }

    public function shouldRender(): bool
    {
        return $this->manifest !== null;
    }

    public function render(): View
    {
        return view('motion-graphics::components.graphics');
    }
}
