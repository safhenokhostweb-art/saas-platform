<?php

namespace App\Modules\MotionGraphics\DTO;

/**
 * Phase 7 (docs/motion-admin-management/): the Motion Graphics Admin
 * Dashboard's four counts. Deliberately counts only — no asset ids, no
 * site identities, no source/AI data.
 */
final class MotionAdminDashboardSummary
{
    public function __construct(
        public readonly int $totalAssets,
        public readonly int $totalTemplates,
        public readonly int $assignedPackages,
        public readonly int $activatedInternalSites,
    ) {}
}
