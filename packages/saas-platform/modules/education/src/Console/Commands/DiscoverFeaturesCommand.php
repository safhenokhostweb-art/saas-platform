<?php

namespace SaaSPlatform\Modules\Education\Console\Commands;

use Illuminate\Console\Command;
use SaaSPlatform\Modules\Education\Contracts\Services\FeatureDiscoveryServiceInterface;

class DiscoverFeaturesCommand extends Command
{
    protected $signature   = 'education:discover-features {--site-id= : Scope discovery to a specific site}';
    protected $description = 'Scan platform (AdminMenu, routes, module manifests) and persist discovered features to edu_features.';

    public function handle(FeatureDiscoveryServiceInterface $discovery): int
    {
        $siteId = $this->option('site-id') ? (int) $this->option('site-id') : null;

        $this->info('Discovering features from AdminMenu…');
        $menuDtos = $discovery->discoverFromMenu();
        $this->line("  → {$menuDtos->count()} entries from AdminMenu");

        $this->info('Discovering features from module manifests…');
        $manifestDtos = $discovery->discoverFromManifests();
        $this->line("  → {$manifestDtos->count()} entries from manifests");

        $this->info('Persisting to edu_features…');
        $total = $discovery->discover($siteId);

        $this->info("Done. {$total} features upserted.");

        return self::SUCCESS;
    }
}
