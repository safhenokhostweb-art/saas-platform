<?php
namespace App\Services\ThemeBuilder\QualityGate;

use App\Models\Theme;

/**
 * One independently-testable category of the Commercial Quality Gate
 * (Phase 2a). Adding a future check (Icon Packs, Block Packs, Design
 * Systems, SEO, visual regression, performance budgets, ...) means writing
 * one new class implementing this interface and registering it with
 * ThemeCommercialQualityGate — never editing an existing check or the gate's
 * own orchestration logic.
 */
interface ThemeQualityCheck
{
    /**
     * @param array<int, array{type: string, ref: string, version_constraint?: ?string, is_required: bool, metadata?: ?array}>|null $dependencies
     *   The dependency set the release would carry — not necessarily
     *   persisted yet, since the gate must be able to run before a release
     *   exists.
     * @return ThemeQualityCheckResult[] Never a single boolean — every
     *   finding is a structured result, even a check that only ever
     *   produces one.
     */
    public function run(Theme $theme, string $version, ?array $dependencies): array;
}
