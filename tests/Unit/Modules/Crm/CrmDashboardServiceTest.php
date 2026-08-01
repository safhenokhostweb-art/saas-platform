<?php

namespace Tests\Unit\Modules\Crm;

use Illuminate\Foundation\Testing\RefreshDatabase;
use SaaSPlatform\Modules\Crm\Models\CrmContact;
use SaaSPlatform\Modules\Crm\Models\CrmLead;
use SaaSPlatform\Modules\Crm\Models\CrmOpportunity;
use SaaSPlatform\Modules\Crm\Models\CrmPipeline;
use SaaSPlatform\Modules\Crm\Models\CrmStage;
use SaaSPlatform\Modules\Crm\Services\CrmDashboardService;
use Tests\Concerns\MakesCrmTestSites;
use Tests\TestCase;

/**
 * Phase 20 — Part 2/9: dashboard numbers must be correct AND must never
 * mix data across sites (tenant isolation is asserted directly here,
 * not just in a separate isolation test file).
 */
class CrmDashboardServiceTest extends TestCase
{
    use MakesCrmTestSites;
    use RefreshDatabase;

    private function service(): CrmDashboardService
    {
        return new CrmDashboardService();
    }

    public function test_counts_leads_customers_and_open_opportunities(): void
    {
        $site = $this->makeCrmTestSite();

        CrmLead::create(['site_id' => $site->id, 'first_name' => 'A']);
        CrmLead::create(['site_id' => $site->id, 'first_name' => 'B', 'status' => CrmLead::STATUS_CONVERTED]);
        CrmContact::create(['site_id' => $site->id, 'first_name' => 'C', 'lifecycle_stage' => CrmContact::STAGE_CUSTOMER]);

        $pipeline = CrmPipeline::create(['site_id' => $site->id, 'name' => 'Default']);
        $stage = CrmStage::create(['pipeline_id' => $pipeline->id, 'name' => 'Open', 'type' => 'open']);
        CrmOpportunity::create(['site_id' => $site->id, 'name' => 'Deal', 'pipeline_id' => $pipeline->id, 'stage_id' => $stage->id, 'amount' => 500000]);

        $data = $this->service()->build($site->id);

        $this->assertSame(1, $data['lead_count']); // converted lead excluded
        $this->assertSame(1, $data['customer_count']);
        $this->assertSame(1, $data['opportunity_count']);
        $this->assertSame(500000, $data['pipeline_value']);
    }

    public function test_won_and_lost_counts(): void
    {
        $site = $this->makeCrmTestSite();

        $pipeline = CrmPipeline::create(['site_id' => $site->id, 'name' => 'Default']);
        $stage = CrmStage::create(['pipeline_id' => $pipeline->id, 'name' => 'Open']);
        CrmOpportunity::create(['site_id' => $site->id, 'name' => 'Won Deal', 'pipeline_id' => $pipeline->id, 'stage_id' => $stage->id, 'status' => 'won']);
        CrmOpportunity::create(['site_id' => $site->id, 'name' => 'Lost Deal', 'pipeline_id' => $pipeline->id, 'stage_id' => $stage->id, 'status' => 'lost']);

        $data = $this->service()->build($site->id);

        $this->assertSame(1, $data['won_count']);
        $this->assertSame(1, $data['lost_count']);
    }

    public function test_today_tasks_only_includes_pending_tasks_due_today(): void
    {
        $site = $this->makeCrmTestSite();
        $contact = CrmContact::create(['site_id' => $site->id, 'first_name' => 'A']);
        $contact->tasks()->create(['site_id' => $site->id, 'title' => 'Today', 'due_at' => now()->addHours(2)]);
        $contact->tasks()->create(['site_id' => $site->id, 'title' => 'Tomorrow', 'due_at' => now()->addDay()]);
        $completed = $contact->tasks()->create(['site_id' => $site->id, 'title' => 'DoneToday', 'due_at' => now()->addHour()]);
        $completed->complete();

        $data = $this->service()->build($site->id);

        $this->assertCount(1, $data['today_tasks']);
        $this->assertSame('Today', $data['today_tasks']->first()->title);
    }

    public function test_dashboard_data_never_mixes_across_sites(): void
    {
        $siteOne = $this->makeCrmTestSite();
        $siteTwo = $this->makeCrmTestSite();

        CrmLead::create(['site_id' => $siteOne->id, 'first_name' => 'SiteOneLead']);
        CrmLead::create(['site_id' => $siteTwo->id, 'first_name' => 'SiteTwoLead']);
        CrmLead::create(['site_id' => $siteTwo->id, 'first_name' => 'SiteTwoLead2']);

        $dataSiteOne = $this->service()->build($siteOne->id);
        $dataSiteTwo = $this->service()->build($siteTwo->id);

        $this->assertSame(1, $dataSiteOne['lead_count']);
        $this->assertSame(2, $dataSiteTwo['lead_count']);
    }
}
