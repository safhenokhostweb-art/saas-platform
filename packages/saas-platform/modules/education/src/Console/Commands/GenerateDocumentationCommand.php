<?php

namespace SaaSPlatform\Modules\Education\Console\Commands;

use Illuminate\Console\Command;
use SaaSPlatform\Modules\Education\Contracts\Services\DocumentationGeneratorServiceInterface;
use SaaSPlatform\Modules\Education\Contracts\Services\FeatureDiscoveryServiceInterface;
use SaaSPlatform\Modules\Education\Models\EducationFeature;

class GenerateDocumentationCommand extends Command
{
    protected $signature = 'education:generate-docs
                            {--discover : Run feature discovery first}
                            {--site-id= : Scope to a specific site}
                            {--dry-run  : Show what would be created without persisting}';

    protected $description = 'Generate draft documentation articles for undocumented features (rule-based, no AI).';

    public function handle(
        DocumentationGeneratorServiceInterface $docGen,
        FeatureDiscoveryServiceInterface       $discovery,
    ): int {
        $siteId = $this->option('site-id') ? (int) $this->option('site-id') : null;
        $dryRun = $this->option('dry-run');

        if ($this->option('discover')) {
            $this->info('Running feature discovery first…');
            $discovered = $discovery->discover($siteId);
            $this->line("  → {$discovered} features upserted");
        }

        if ($dryRun) {
            $this->warn('[DRY RUN] No articles will be written.');
        }

        $features = EducationFeature::forSite($siteId)->get();
        $this->info("Checking {$features->count()} features for missing documentation…");

        $created = 0;

        foreach ($features as $feature) {
            $hasDoc = \SaaSPlatform\Modules\Education\Models\EducationArticle::where(function ($q) use ($feature) {
                $q->where('module_slug', $feature->module_slug)
                  ->orWhere('route_pattern', $feature->route_name);
            })->where('auto_generated', false)->exists();

            if ($hasDoc) {
                $this->line("  [SKIP] {$feature->module_slug}.{$feature->feature_slug} — manual doc exists");
                continue;
            }

            $this->line("  [GEN]  {$feature->module_slug}.{$feature->feature_slug}");

            if (! $dryRun) {
                $docGen->generateForFeature($feature);
                $created++;
            }
        }

        $this->info($dryRun ? "Dry run complete." : "Done. {$created} draft articles created.");

        return self::SUCCESS;
    }
}
