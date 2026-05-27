<?php

namespace SaaSPlatform\Modules\Forms\Services;

use Illuminate\Support\Collection;
use SaaSPlatform\CoreUtils\Contracts\Platform\SiteModelInterface;
use SaaSPlatform\CoreUtils\Contracts\Services\SiteRepositoryInterface;

/**
 * Fallback: used when the host app does not bind SiteRepositoryInterface.
 * Tenant (customer-site) contexts have no multi-site concept.
 */
class NullSiteRepository implements SiteRepositoryInterface
{
    public function allForDisplay(): Collection
    {
        return collect();
    }

    public function allActive(): Collection
    {
        return collect();
    }

    public function allIds(): Collection
    {
        return collect();
    }

    public function find(int $id): ?SiteModelInterface
    {
        return null;
    }

    public function count(): int
    {
        return 0;
    }
}
