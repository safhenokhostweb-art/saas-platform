<?php

namespace Tests\Unit\Modules\Crm;

use Illuminate\Foundation\Testing\RefreshDatabase;
use SaaSPlatform\Modules\Crm\Models\CrmLead;
use SaaSPlatform\Modules\Crm\Models\CrmOpportunity;
use SaaSPlatform\Modules\Crm\Models\CrmPipeline;
use SaaSPlatform\Modules\Crm\Models\CrmStage;
use SaaSPlatform\Modules\Crm\Services\CrmSalesReportService;
use Tests\Concerns\MakesCrmTestSites;
use Tests\TestCase;

class CrmSalesReportServiceTest extends TestCase
{
    use MakesCrmTestSites;
    use RefreshDatabase;

    public function test_win_lost_and_conversion_rates_are_calculated_correctly(): void
    {
        $site = $this->makeCrmTestSite();
        $pipeline = CrmPipeline::create(['site_id' => $site->id, 'name' => 'Default']);
        $stage = CrmStage::create(['pipeline_id' => $pipeline->id, 'name' => 'Open']);

        // 3 won, 1 lost => win rate 75%, lost rate 25%
        for ($i = 0; $i < 3; $i++) {
            CrmOpportunity::create(['site_id' => $site->id, 'name' => "Won{$i}", 'pipeline_id' => $pipeline->id, 'stage_id' => $stage->id, 'status' => 'won', 'amount' => 100000, 'closed_at' => now()]);
        }
        CrmOpportunity::create(['site_id' => $site->id, 'name' => 'Lost', 'pipeline_id' => $pipeline->id, 'stage_id' => $stage->id, 'status' => 'lost', 'closed_at' => now()]);

        // 1 of 2 leads converted => conversion rate 50%
        CrmLead::create(['site_id' => $site->id, 'first_name' => 'A', 'status' => CrmLead::STATUS_CONVERTED]);
        CrmLead::create(['site_id' => $site->id, 'first_name' => 'B']);

        $report = (new CrmSalesReportService())->build($site->id);

        $this->assertSame(75.0, $report['win_rate']);
        $this->assertSame(25.0, $report['lost_rate']);
        $this->assertSame(50.0, $report['conversion_rate']);
        $this->assertSame(300000, $report['won_amount']);
    }

    public function test_rates_are_zero_when_no_data_exists(): void
    {
        $site = $this->makeCrmTestSite();

        $report = (new CrmSalesReportService())->build($site->id);

        $this->assertSame(0.0, $report['win_rate']);
        $this->assertSame(0.0, $report['conversion_rate']);
    }

    public function test_pipeline_value_and_revenue_forecast_only_include_open_deals(): void
    {
        $site = $this->makeCrmTestSite();
        $pipeline = CrmPipeline::create(['site_id' => $site->id, 'name' => 'Default']);
        $stage = CrmStage::create(['pipeline_id' => $pipeline->id, 'name' => 'Open', 'probability' => 50]);

        CrmOpportunity::create(['site_id' => $site->id, 'name' => 'Open Deal', 'pipeline_id' => $pipeline->id, 'stage_id' => $stage->id, 'amount' => 200000]);
        CrmOpportunity::create(['site_id' => $site->id, 'name' => 'Won Deal', 'pipeline_id' => $pipeline->id, 'stage_id' => $stage->id, 'amount' => 500000, 'status' => 'won']);

        $report = (new CrmSalesReportService())->build($site->id);

        $this->assertSame(200000, $report['pipeline_value']);
        $this->assertSame(100000, $report['revenue_forecast']); // 200000 * 50%
    }

    public function test_reports_never_mix_data_across_sites(): void
    {
        $siteOne = $this->makeCrmTestSite();
        $siteTwo = $this->makeCrmTestSite();

        $pipelineOne = CrmPipeline::create(['site_id' => $siteOne->id, 'name' => 'P1']);
        $stageOne = CrmStage::create(['pipeline_id' => $pipelineOne->id, 'name' => 'Open']);
        CrmOpportunity::create(['site_id' => $siteOne->id, 'name' => 'D1', 'pipeline_id' => $pipelineOne->id, 'stage_id' => $stageOne->id, 'status' => 'won', 'amount' => 999999]);

        $reportSiteTwo = (new CrmSalesReportService())->build($siteTwo->id);

        $this->assertSame(0, $reportSiteTwo['won_amount']);
    }
}
