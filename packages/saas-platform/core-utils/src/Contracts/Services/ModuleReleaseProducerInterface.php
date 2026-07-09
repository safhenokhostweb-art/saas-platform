<?php

namespace SaaSPlatform\CoreUtils\Contracts\Services;

use SaaSPlatform\CoreUtils\Contracts\Services\Dto\ModuleReleaseRequest;
use SaaSPlatform\CoreUtils\Contracts\Services\Dto\ModuleReleaseResult;

/**
 * Contract for recording a module release against the platform's version
 * authority (module_releases — see docs/architecture/module-version-authority-adr.md).
 *
 * Packages call record() instead of writing to App\Models\ModuleRelease
 * directly. The host app implements all concrete Eloquent logic inside
 * its adapter — packages never see that model.
 */
interface ModuleReleaseProducerInterface
{
    public function record(ModuleReleaseRequest $request): ModuleReleaseResult;
}
