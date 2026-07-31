<?php

namespace App\Console\Commands;

use App\Models\Site;
use Illuminate\Console\Command;

/**
 * Phase 6 (docs/motion-integration-phase6-audit.md §6): the only lever
 * that turns `motion_graphics_integration` on for a specific site.
 *
 * The global config value defaults to null (see
 * packages/saas-platform/core-utils/config/feature-flags.php), so every
 * site is disabled unless its own `features` JSON explicitly opts in.
 * This command is the deliberate, explicit, reversible way an operator
 * does that for exactly one internal test site — never globally, never
 * implicitly.
 */
class MotionGraphicsInternalTestSiteCommand extends Command
{
    protected $signature = 'motion:internal-test-site {site : Site ID to toggle} {--disable : Disable instead of enable}';

    protected $description = 'Enable (or --disable) the motion_graphics_integration feature flag for one specific site';

    public function handle(): int
    {
        $site = Site::find($this->argument('site'));
        if (! $site) {
            $this->error("Site [{$this->argument('site')}] not found.");
            return self::FAILURE;
        }

        $features = $site->features ?? [];
        $enable = ! $this->option('disable');
        $features['motion_graphics_integration'] = $enable;
        $site->features = $features;
        $site->save();

        $this->info(sprintf(
            'motion_graphics_integration is now %s for site #%d (%s).',
            $enable ? 'ENABLED' : 'disabled',
            $site->id,
            $site->domain ?? $site->title ?? 'unnamed'
        ));

        return self::SUCCESS;
    }
}
