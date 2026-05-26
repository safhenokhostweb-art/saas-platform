<?php

namespace SaaSPlatform\Modules\Forms\Services;

use Illuminate\Support\Facades\Log;
use SaaSPlatform\CoreUtils\Contracts\Platform\SiteModelInterface;
use SaaSPlatform\CoreUtils\Contracts\Services\ModuleLicenseServiceInterface;

class FormsLicensingService
{
    public const MODULE_SLUG = 'forms';

    public function __construct(
        protected ModuleLicenseServiceInterface $licenseService
    ) {}

    /** @return array{allowed: bool, reason: ?string} */
    public function checkEntitlement(SiteModelInterface $site): array
    {
        return $this->licenseService->checkEntitlement($site, self::MODULE_SLUG);
    }

    public function isAllowed(SiteModelInterface $site): bool
    {
        return $this->checkEntitlement($site)['allowed'];
    }

    public function logDenial(SiteModelInterface $site, string $context, string $reason): void
    {
        $this->licenseService->logDenial($site, self::MODULE_SLUG, $context, $reason);
    }
}
