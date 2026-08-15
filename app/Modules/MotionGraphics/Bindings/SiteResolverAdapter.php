<?php

namespace App\Modules\MotionGraphics\Bindings;

use App\Models\Site;
use SaaSPlatform\CoreUtils\Contracts\TenantContextContract;
use SaaSPlatform\CoreUtils\Contracts\SiteContextContract;
use SaaSPlatform\Modules\MotionGraphics\Contracts\SiteResolverContract;

/**
 * Host-app implementation of the standalone module's SiteResolverContract.
 * Bound ahead of MotionGraphicsServiceProvider::bindDefault() so the
 * module resolves real sites once the integration flag is enabled; while
 * disabled this behaves exactly like the module's own NullSiteResolver.
 */
final class SiteResolverAdapter implements SiteResolverContract
{
    public function __construct(
        private readonly TenantContextContract $tenantContext,
        private readonly SiteContextContract $siteContext,
    ) {}

    public function exists(string $siteIdentifier): bool
    {
        if (! $this->tenantContext->hasFeature('motion_graphics_integration')) {
            return false;
        }

        if (! ctype_digit($siteIdentifier)) {
            return false;
        }

        return Site::query()->whereKey((int) $siteIdentifier)->exists();
    }

    public function currentSiteIdentifier(): ?string
    {
        if (! $this->tenantContext->hasFeature('motion_graphics_integration')) {
            return null;
        }

        return $this->siteContext->siteId() !== null ? (string) $this->siteContext->siteId() : null;
    }
}
