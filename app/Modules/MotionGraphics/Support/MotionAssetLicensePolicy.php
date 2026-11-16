<?php

namespace App\Modules\MotionGraphics\Support;

use App\Models\Site;
use App\Modules\MotionGraphics\DTO\LicenseCompatibilityDecision;
use App\Modules\MotionGraphics\Models\MotionSiteAssetGrant;
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
 * ADR-086 adds visibility, checked before the tier:
 *
 * - `private`: delivered only to a site holding an ACTIVE
 *   MotionSiteAssetGrant for exactly this asset (purchase, licence or admin
 *   assignment made in the central panel). The grant is the entitlement, so
 *   the tier is not consulted, and the `motion-graphics-premium` module never
 *   unlocks a private motion on its own.
 * - `public` (or a read model that predates the key): the tier rules above,
 *   unchanged.
 *
 * A missing or unknown tier is premium; an unknown visibility is private.
 * Every failure denies; nothing here throws, so callers decide whether a
 * denial skips an item or fails a whole operation.
 */
final class MotionAssetLicensePolicy
{
    public const PREMIUM_MODULE_SLUG = 'motion-graphics-premium';

    /** Denial code for a private motion the site holds no grant for. */
    public const GRANT_REQUIRED = 'asset_grant_required';

    public function __construct(
        private readonly ModuleLicenseServiceInterface $licenses,
    ) {}

    /**
     * @param array<string, mixed> $compatibility the asset's read-model compatibility map
     * @param string|null $assetId the asset's canonical id; required to admit a private asset
     */
    public function decide(Site $site, array $compatibility, ?string $assetId = null): LicenseCompatibilityDecision
    {
        if (self::visibilityOf($compatibility) === MotionGraphic::VISIBILITY_PRIVATE) {
            return $assetId !== null && MotionSiteAssetGrant::isGranted((int) $site->id, $assetId)
                ? LicenseCompatibilityDecision::allow('license_asset_granted', 'The site holds a grant for this private motion asset.')
                : LicenseCompatibilityDecision::deny(self::GRANT_REQUIRED, 'This private motion asset has not been granted to this site.');
        }

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

    /**
     * ADR-086. A read model without the key predates visibility and is
     * public, exactly as every asset behaved before; an explicit unknown
     * value is private.
     *
     * @param array<string, mixed> $compatibility
     */
    public static function visibilityOf(array $compatibility): string
    {
        return array_key_exists('visibility', $compatibility)
            ? MotionGraphic::normalizeVisibility($compatibility['visibility'])
            : MotionGraphic::VISIBILITY_PUBLIC;
    }

    /**
     * ADR-086: false only when the read model says an admin switched the
     * motion off. A read model without the key is enabled.
     *
     * @param array<string, mixed> $compatibility
     */
    public static function isRuntimeEnabled(array $compatibility): bool
    {
        return ($compatibility['runtime_enabled'] ?? true) !== false;
    }
}
