<?php

namespace App\Modules\MotionGraphics\DTO;

/**
 * Phase 7 (docs/motion-admin-management/): the Motion Graphics Admin
 * Dashboard's counts. Deliberately counts only — no asset ids, no site
 * identities, no source/AI data.
 *
 * Phase 9 (docs/motion-commercialization/ADMIN_MANAGEMENT.md) adds
 * `licensedSitesCount` and `moduleStatus` — the lightweight commercial
 * usage-summary the task asks for, following the same "counts only, no
 * new analytics infrastructure" convention the four Phase 7 fields
 * already established (the audit found no existing module-usage-tracking
 * pattern anywhere in the codebase to follow instead).
 * `activatedInternalSites` (Phase 7, unchanged) counts sites with the
 * runtime flag on; `licensedSitesCount` counts sites with a real, valid
 * `ModuleLicense` — deliberately a *different* number now that
 * entitlement and technical activation are separate steps (a site can be
 * licensed but not yet activated).
 */
final class MotionAdminDashboardSummary
{
    public function __construct(
        public readonly int $totalAssets,
        public readonly int $totalTemplates,
        public readonly int $assignedPackages,
        public readonly int $activatedInternalSites,
        public readonly int $licensedSitesCount = 0,
        public readonly string $moduleStatus = 'inactive',
    ) {}
}
