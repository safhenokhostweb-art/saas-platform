<?php

namespace Database\Seeders;

use App\Modules\MotionGraphics\Services\MotionLibraryCatalogService;
use Illuminate\Database\Seeder;

/**
 * ADR-078: publishes the curated motion library (hero-motion,
 * business-growth-motion) into the Motion Graphics catalog. Safe in every
 * environment and idempotent: it writes no license, package or site flag,
 * and an unchanged library file never creates a new version.
 *
 * Replaces the 2026-11-04 seeder that published `hero-vector-showcase` with
 * an empty compiled_assets list (and re-wrote that immutable version on
 * every run); MotionLibraryCatalogService archives that record.
 */
final class MotionGraphicsDefaultSeeder extends Seeder
{
    public function run(MotionLibraryCatalogService $catalog): void
    {
        foreach ($catalog->sync() as $slug => $state) {
            $this->command?->info(sprintf(
                'motion %s → %s v%d (%s)',
                $slug, $state['uuid'], $state['version'], $state['created'] ? 'published' : 'unchanged'
            ));
        }
    }
}
