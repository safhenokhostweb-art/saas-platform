<?php

namespace SaaSPlatform\Modules\Education\Console\Commands;

use Illuminate\Console\Command;
use SaaSPlatform\Modules\Education\Contracts\Repositories\EducationFeatureUsageRepositoryInterface;
use SaaSPlatform\Modules\Education\Models\EducationArticle;
use SaaSPlatform\Modules\Education\Models\EducationFeature;
use SaaSPlatform\Modules\Education\Services\EducationCoverageTracker;

class AdoptionReportCommand extends Command
{
    protected $signature = 'education:adoption-report
                            {--site-id= : Scope to a specific site}
                            {--json     : Output as JSON}';

    protected $description = 'Print feature adoption, documentation coverage, and usage statistics.';

    public function handle(
        EducationFeatureUsageRepositoryInterface $usage,
        EducationCoverageTracker                 $coverage,
    ): int {
        $siteId = $this->option('site-id') ? (int) $this->option('site-id') : null;

        $adoption  = $usage->adoptionStats($siteId);
        $topFeat   = $usage->topFeaturesByUsage($siteId, 10);
        $unused    = $usage->unusedFeatures($siteId);
        $coverageR = $coverage->report();

        $report = [
            'generated_at'      => now()->toDateTimeString(),
            'feature_adoption'  => $adoption,
            'documentation'     => [
                'total_articles'     => EducationArticle::count(),
                'published'          => EducationArticle::published()->count(),
                'auto_generated'     => EducationArticle::where('auto_generated', true)->count(),
                'manual'             => EducationArticle::where('auto_generated', false)->count(),
                'module_coverage'    => $coverageR,
            ],
            'top_features'      => $topFeat->map(fn($f) => [
                'module'      => $f->module_slug,
                'feature'     => $f->feature_slug,
                'total_usage' => $f->total_usage,
            ])->toArray(),
            'unused_features'   => $unused->map(fn($f) => [
                'module'  => $f->module_slug,
                'feature' => $f->feature_slug,
                'name'    => $f->feature_name,
            ])->toArray(),
        ];

        if ($this->option('json')) {
            $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            return self::SUCCESS;
        }

        $this->info('=== Education Adoption Report ===');
        $this->newLine();

        $this->info('Feature Adoption:');
        $this->table(['Metric', 'Value'], [
            ['Total Features Discovered', $adoption['total']],
            ['Adopted (at least 1 visit)', $adoption['adopted']],
            ['Adoption %', $adoption['adoption_pct'] . '%'],
        ]);

        $this->info('Documentation Coverage:');
        $this->table(['Metric', 'Value'], [
            ['Published Articles', $report['documentation']['published']],
            ['Auto-generated (drafts)', $report['documentation']['auto_generated']],
            ['Manual', $report['documentation']['manual']],
            ['Module Coverage %', $coverageR['coverage_pct'] . '%'],
        ]);

        if ($topFeat->isNotEmpty()) {
            $this->info('Top 10 Features by Usage:');
            $this->table(['Module', 'Feature', 'Total Visits'], $topFeat->map(fn($f) => [
                $f->module_slug, $f->feature_slug, $f->total_usage,
            ])->toArray());
        }

        if ($unused->isNotEmpty()) {
            $this->warn("⚠  {$unused->count()} features have never been visited:");
            foreach ($unused->take(10) as $f) {
                $this->line("   - {$f->module_slug}.{$f->feature_slug} ({$f->feature_name})");
            }
        }

        return self::SUCCESS;
    }
}
