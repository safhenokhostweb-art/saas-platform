<?php

namespace SaaSPlatform\Modules\Education\Contracts\Services;

use Illuminate\Support\Collection;
use SaaSPlatform\Modules\Education\DataTransferObjects\FeatureDto;

interface FeatureDiscoveryServiceInterface
{
    /** Run full discovery and persist to edu_features. Returns count of upserted records. */
    public function discover(?int $siteId = null): int;

    /** Scan AdminMenu and return FeatureDtos (does not persist). */
    public function discoverFromMenu(): Collection;

    /** Scan registered admin routes and return FeatureDtos (does not persist). */
    public function discoverFromRoutes(): Collection;

    /** Scan module manifests and return FeatureDtos (does not persist). */
    public function discoverFromManifests(): Collection;
}
