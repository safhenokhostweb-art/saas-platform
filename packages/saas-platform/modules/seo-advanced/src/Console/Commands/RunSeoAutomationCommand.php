<?php

namespace SaaSPlatform\Modules\SeoAdvanced\Console\Commands;

use Illuminate\Console\Command;
use SaaSPlatform\Modules\SeoAdvanced\Models\SeoAdvancedSetting;
use SaaSPlatform\Modules\SeoAdvanced\Services\SeoAutomationService;

class RunSeoAutomationCommand extends Command
{
    protected $signature = 'seo-advanced:run-automation';

    protected $description = 'بررسی خودکار افت رتبه، خطای Sitemap/Technical و کاهش امتیاز برای همه سایت‌های دارای تنظیمات SEO Advanced';

    public function handle(SeoAutomationService $service): int
    {
        $siteIds = SeoAdvancedSetting::query()->distinct()->pluck('site_id');
        $totals = ['rank_drops' => 0, 'sitemap_errors' => 0, 'technical_errors' => 0, 'score_drops' => 0];

        foreach ($siteIds as $siteId) {
            $counts = $service->runForSite((int) $siteId);
            foreach ($counts as $key => $value) {
                $totals[$key] += $value;
            }
        }

        $this->info("سایت‌های بررسی‌شده: {$siteIds->count()} — افت رتبه: {$totals['rank_drops']} — خطای Sitemap: {$totals['sitemap_errors']} — خطای Technical: {$totals['technical_errors']} — کاهش امتیاز: {$totals['score_drops']}");

        return self::SUCCESS;
    }
}
