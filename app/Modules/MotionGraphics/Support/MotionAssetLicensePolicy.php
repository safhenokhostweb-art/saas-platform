<?php

namespace App\Modules\MotionGraphics\Support;

use App\Models\Site;
use App\Modules\MotionGraphics\DTO\LicenseCompatibilityDecision;
use SaaSPlatform\CoreUtils\Contracts\Services\ModuleLicenseServiceInterface;
use SaaSPlatform\Modules\MotionGraphics\Models\MotionGraphic;

/**
 * ADR-078: per-asset commercial tier check. Replaces the
 * `LicenseCompatibilityDecision::notImplemented()` placeholder that
 * SiteAssetPackageActivationService carried since Phase 3.
 *
 * Two tiers, one existing licensing system:
 *
 * - `free`: needs nothing beyond what the site already needs to receive a
 *   manifest at all, i.e. a valid `motion-graphics` module license. That
 *   gate stays where it has always been (RealMotionLicenseGateway, checked
 *   once per manifest), so this class does not repeat it.
 * - `premium`: additionally needs the `motion-graphics-premium` module,
 *   checked through the same ModuleLicenseServiceInterface every other
 *   module uses (license-only module, granted by an admin or bought).
 *
 * A missing or unknown tier is premium. Every failure denies; nothing here
 * throws, so callers decide whether a denial skips an item or fails a
 * whole operation.
 */
final class MotionAssetLicensePolicy
{
    public const PREMIUM_MODULE_SLUG = 'motion-graphics-premium';

    public function __construct(
        private readonly ModuleLicenseServiceInterface $licenses,
    ) {}

    /**
     * @param array<string, mixed> $compatibility the asset's read-model compatibility map
     */
    public function decide(Site $site, array $compatibility): LicenseCompatibilityDecision
    {
        if (self::tierOf($compatibility) === MotionGraphic::LICENSE_TIER_FREE) {
            return LicenseCompatibilityDecision::allow('license_free_tier', 'Free-tier asset.');
        }

        $result = $this->licenses->checkEntitlement($site, self::PREMIUM_MODULE_SLUG);

        return ($result['allowed'] ?? false) === true
            ? LicenseCompatibilityDecision::allow('license_premium_entitled', 'The site holds a premium motion license.')
            : LicenseCompatibilityDecision::deny('premium_license_required', 'This motion asset requires a premium motion license for this site.');
    }

    /**
     * @param array<string, mixed> $compatibility
     */
    public static function tierOf(array $compatibility): string
    {
        return ($compatibility['license_tier'] ?? null) === MotionGraphic::LICENSE_TIER_FREE
            ? MotionGraphic::LICENSE_TIER_FREE
            : MotionGraphic::LICENSE_TIER_PREMIUM;
    }
}
