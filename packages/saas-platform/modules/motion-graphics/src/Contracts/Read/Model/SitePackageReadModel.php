<?php

namespace SaaSPlatform\Modules\MotionGraphics\Contracts\Read\Model;

/**
 * Runtime-safe snapshot of one site's installed Motion Graphics
 * deployments. Never carries other-tenant data — every entry in
 * `activeAssets` has already passed the same ownership isolation check
 * as `MotionLibraryService::runtimeManifest()`. The module does not own
 * "template" identity (that belongs to the SaaS Platform — see
 * docs/motion-saas-integration/INTEGRATION_BOUNDARY.md), so
 * `templateReference` is always null in this phase; the Laravel adapter
 * supplies its own platform-side template identity separately.
 */
final class SitePackageReadModel
{
    /**
     * @param list<AssetReadModel> $activeAssets
     */
    public function __construct(
        public readonly string $packageId,
        public readonly ?string $templateReference,
        public readonly string $siteIdentifier,
        public readonly array $activeAssets,
    ) {}
}
