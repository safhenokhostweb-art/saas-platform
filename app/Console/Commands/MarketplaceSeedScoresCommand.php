<?php

namespace App\Console\Commands;

use App\Models\Theme;
use App\Models\ThemeMarketplace;
use App\Services\ThemeBuilder\ThemeMarketplaceQualityService;
use Illuminate\Console\Command;

/**
 * Scores all (or one) marketplace themes using ThemeMarketplaceQualityService.
 * Run: php artisan marketplace:score
 * Run for one theme: php artisan marketplace:score --theme=nexus-pro-corporate
 */
class MarketplaceSeedScoresCommand extends Command
{
    protected $signature = 'marketplace:score {--theme= : Slug of a single theme to score}';
    protected $description = 'Score marketplace themes using the 5-dimension quality engine';

    public function __construct(private ThemeMarketplaceQualityService $quality)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $query = Theme::with('marketplaceMeta')
            ->whereNull('site_id')
            ->where('is_marketplace', true);

        if ($slug = $this->option('theme')) {
            $query->where('slug', $slug);
        }

        $themes = $query->get();

        if ($themes->isEmpty()) {
            $this->warn('هیچ قالب مارکت‌پلیسی یافت نشد.');
            return 1;
        }

        $this->info("=== امتیازدهی {$themes->count()} قالب ===");
        $this->newLine();

        $table = [];
        $passed = 0;
        $failed = 0;

        foreach ($themes as $theme) {
            $result  = $this->quality->scoreAndPersist($theme);
            $verdict = $result['verdict'];
            $meta    = ThemeMarketplace::where('theme_id', $theme->id)->first();

            $overall = $meta->getOverallScore();
            $badge   = $meta->getScoreBadge();

            if ($verdict === 'PASS') {
                $passed++;
            } else {
                $failed++;
            }

            $table[] = [
                $theme->name,
                $result['design_score'],
                $result['commerce_score'],
                $result['performance_score'],
                $result['completeness_score'],
                $result['marketplace_score'],
                $overall,
                $badge,
                $verdict,
            ];
        }

        $this->table(
            ['قالب', 'طراحی', 'تجاری', 'عملکرد', 'کامل', 'بازار', 'کل', 'درجه', 'نتیجه'],
            $table
        );

        $this->newLine();
        $this->info("✅ موفق: {$passed} | ❌ ناموفق: {$failed}");
        $this->info('مسیر داشبورد امتیازات: /admin/theme-marketplace/scoring');

        return 0;
    }
}
