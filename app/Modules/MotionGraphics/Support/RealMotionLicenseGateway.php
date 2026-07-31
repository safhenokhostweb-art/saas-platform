<?php

namespace App\Modules\MotionGraphics\Support;

use App\Models\Site;
use App\Modules\MotionGraphics\Contracts\MotionLicenseGatewayContract;
use App\Modules\MotionGraphics\DTO\LicenseGatewayDecision;
use SaaSPlatform\CoreUtils\Contracts\Services\ModuleLicenseServiceInterface;

/**
 * Phase 6 — real MotionLicenseGatewayContract implementation, replacing
 * FakeMotionLicenseGateway (docs/motion-integration-phase6-audit.md §4).
 * Wraps the platform's canonical ModuleLicenseServiceInterface
 * (App\Adapters\ModuleLicenseAdapter) — the same entitlement gate every
 * other module goes through. No new licensing logic is introduced here.
 *
 * Motion Graphics has no real per-asset licensing system of its own on
 * the platform (the audit's "must not duplicate" list), so both gateway
 * methods resolve to the same site-level "is this site entitled to the
 * motion-graphics module at all" check via checkEntitlement(). The
 * motion-graphics module row is registered as Module::TYPE_CORE
 * (see database/seeders/ModuleSeeder.php), which makes checkEntitlement()
 * allow any active, non-retired site unconditionally — the *fine-grained*
 * "only this specific internal test site" restriction is deliberately
 * enforced one layer up, by the `motion_graphics_integration` feature
 * flag (per-site via sites.features), not by this gateway. This mirrors
 * how core modules like blog/shop already work on this platform:
 * entitlement is a coarse platform-wide gate (with a real admin kill
 * switch via Module::is_active/retired_at); fine per-site enablement is a
 * separate concern.
 */
final class RealMotionLicenseGateway implements MotionLicenseGatewayContract
{
    public const MODULE_SLUG = 'motion-graphics';

    public function __construct(
        private readonly ModuleLicenseServiceInterface $moduleLicenseService,
    ) {}

    public function checkAvailability(string $assetId, string $siteReference): LicenseGatewayDecision
    {
        return $this->checkSiteEntitlement($siteReference);
    }

    public function validateEntitlement(string $siteReference, string $packageReference): LicenseGatewayDecision
    {
        return $this->checkSiteEntitlement($siteReference);
    }

    private function checkSiteEntitlement(string $siteReference): LicenseGatewayDecision
    {
        if (! ctype_digit($siteReference)) {
            return LicenseGatewayDecision::deny(
                'invalid_site_reference',
                'The site reference is not a valid site identifier.'
            );
        }

        $site = Site::query()->find((int) $siteReference);
        if ($site === null) {
            return LicenseGatewayDecision::deny('site_not_found', 'The site could not be resolved.');
        }

        $result = $this->moduleLicenseService->checkEntitlement($site, self::MODULE_SLUG);

        return $result['allowed']
            ? LicenseGatewayDecision::allow()
            : LicenseGatewayDecision::deny(
                'license_denied',
                $result['reason'] ?? 'Motion Graphics is not licensed for this site.'
            );
    }
}
