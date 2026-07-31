<?php

namespace App\Modules\MotionGraphics\DTO;

/**
 * Brand Variant preparation (task scope item 5) — no AI generation in
 * this phase. Structurally expresses:
 *
 * ```text
 * Master Asset -> Brand Variant -> Site Runtime Asset
 * ```
 *
 * - The Master never changes: this DTO only ever *references* a master
 *   asset/version; nothing in this phase writes to the module.
 * - The variant belongs to one site: `siteId` is required and is always
 *   the platform-trusted tenant, never a raw caller-supplied value.
 * - The variant requires approval: `approvalStatus` must be `approved`
 *   before `MotionRuntimeInstallationService` will bind it into a
 *   package — see docs/motion-runtime-installation/BRAND_VARIANT_FLOW.md.
 * - The variant has a lineage reference: `lineageParentReference` points
 *   at the variant (or master) it was derived from, mirroring the
 *   module's own version-lineage pattern.
 */
final class BrandVariantReference
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';

    public function __construct(
        public readonly string $variantId,
        public readonly string $masterAssetId,
        public readonly int $masterVersion,
        public readonly string $siteId,
        public readonly string $approvalStatus,
        public readonly ?string $lineageParentReference = null,
    ) {}

    public function isApproved(): bool
    {
        return $this->approvalStatus === self::STATUS_APPROVED;
    }
}
