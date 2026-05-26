<?php

namespace SaaSPlatform\CoreUtils\Contracts\Services;

use SaaSPlatform\CoreUtils\Contracts\Platform\SiteModelInterface;

/**
 * Contract for module entitlement checks.
 *
 * Packages call checkEntitlement() to determine if a site has access to
 * a given module. The host app implements all concrete Module/ModuleEntitlement
 * Eloquent logic inside its adapter — packages never see those models.
 */
interface ModuleLicenseServiceInterface
{
    /**
     * Check whether a site is entitled to use a module.
     *
     * @return array{allowed: bool, reason: ?string}
     */
    public function checkEntitlement(SiteModelInterface $site, string $moduleSlug): array;

    /**
     * Log a denial event. The implementation decides how to record context.
     */
    public function logDenial(
        SiteModelInterface $site,
        string $moduleSlug,
        string $context,
        string $reason,
        array $requestContext = []
    ): void;
}
