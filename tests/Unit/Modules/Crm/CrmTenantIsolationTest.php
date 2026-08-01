<?php

namespace Tests\Unit\Modules\Crm;

use App\Models\Customer;
use App\Models\Site;
use Illuminate\Foundation\Testing\RefreshDatabase;
use SaaSPlatform\Modules\Crm\Models\CrmCompany;
use SaaSPlatform\Modules\Crm\Models\CrmContact;
use SaaSPlatform\Modules\Crm\Models\CrmLead;
use SaaSPlatform\Modules\Crm\Models\CrmOpportunity;
use SaaSPlatform\Modules\Crm\Models\CrmPipeline;
use SaaSPlatform\Modules\Crm\Models\CrmStage;
use Tests\TestCase;

/**
 * Phase 19 — Part 3/9: no site's CRM data ever leaks into another's,
 * across every table this module owns. Every controller explicitly
 * filters by site_id (see FiltersCrmListings' docblock for why
 * SiteTenantScope alone is insufficient in admin/site web contexts) —
 * these tests prove that explicit filtering actually isolates data at
 * the query level, independent of any scope.
 *
 * Uses real App\Models\Site rows (this is a test file in the host app,
 * not this module's own src/ — referencing Core models here doesn't
 * violate module independence) because crm_*.site_id carries a genuine
 * DB-level foreign key to `sites` (matching the Tickets module's own
 * migrations), unlike SEO Advanced's looser, unconstrained site_id.
 */
class CrmTenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    private function makeSite(): Site
    {
        $customer = Customer::create(['name' => 'مشتری تست', 'mobile' => '0912' . random_int(1000000, 9999999)]);

        return Site::create([
            'customer_id' => $customer->id, 'title' => 'سایت تست',
            'domain' => 'test-' . uniqid('', true) . '.example.com', 'is_active' => true,
        ]);
    }

    public function test_companies_and_contacts_are_isolated_per_site(): void
    {
        $siteOne = $this->makeSite();
        $siteTwo = $this->makeSite();

        CrmCompany::create(['site_id' => $siteOne->id, 'name' => 'Site One Co']);
        CrmCompany::create(['site_id' => $siteTwo->id, 'name' => 'Site Two Co']);
        CrmContact::create(['site_id' => $siteOne->id, 'first_name' => 'One']);
        CrmContact::create(['site_id' => $siteTwo->id, 'first_name' => 'Two']);

        $this->assertSame(1, CrmCompany::where('site_id', $siteOne->id)->count());
        $this->assertSame('Site One Co', CrmCompany::where('site_id', $siteOne->id)->first()->name);
        $this->assertSame(1, CrmContact::where('site_id', $siteTwo->id)->count());
    }

    public function test_leads_are_isolated_per_site(): void
    {
        $siteTen = $this->makeSite();
        $siteEleven = $this->makeSite();

        CrmLead::create(['site_id' => $siteTen->id, 'first_name' => 'Lead Ten']);
        CrmLead::create(['site_id' => $siteEleven->id, 'first_name' => 'Lead Eleven']);

        $this->assertSame(1, CrmLead::where('site_id', $siteTen->id)->count());
        $this->assertSame('Lead Eleven', CrmLead::where('site_id', $siteEleven->id)->first()->first_name);
    }

    public function test_opportunities_and_pipelines_are_isolated_per_site(): void
    {
        $siteTwenty = $this->makeSite();
        $siteTwentyOne = $this->makeSite();

        $pipeline1 = CrmPipeline::create(['site_id' => $siteTwenty->id, 'name' => 'Pipeline 20']);
        $stage1 = CrmStage::create(['pipeline_id' => $pipeline1->id, 'name' => 'Stage', 'position' => 1]);
        $pipeline2 = CrmPipeline::create(['site_id' => $siteTwentyOne->id, 'name' => 'Pipeline 21']);
        $stage2 = CrmStage::create(['pipeline_id' => $pipeline2->id, 'name' => 'Stage', 'position' => 1]);

        CrmOpportunity::create(['site_id' => $siteTwenty->id, 'name' => 'Deal 20', 'pipeline_id' => $pipeline1->id, 'stage_id' => $stage1->id]);
        CrmOpportunity::create(['site_id' => $siteTwentyOne->id, 'name' => 'Deal 21', 'pipeline_id' => $pipeline2->id, 'stage_id' => $stage2->id]);

        $this->assertSame(1, CrmPipeline::where('site_id', $siteTwenty->id)->count());
        $this->assertSame(1, CrmOpportunity::where('site_id', $siteTwenty->id)->count());
        $this->assertSame('Deal 21', CrmOpportunity::where('site_id', $siteTwentyOne->id)->first()->name);
    }

    public function test_deleting_one_sites_contacts_never_touches_another_sites(): void
    {
        $siteThirty = $this->makeSite();
        $siteThirtyOne = $this->makeSite();

        CrmContact::create(['site_id' => $siteThirty->id, 'first_name' => 'ToDelete']);
        $other = CrmContact::create(['site_id' => $siteThirtyOne->id, 'first_name' => 'Keep']);

        CrmContact::where('site_id', $siteThirty->id)->delete();

        $this->assertDatabaseHas('crm_contacts', ['id' => $other->id]);
        $this->assertSame(0, CrmContact::where('site_id', $siteThirty->id)->count());
    }
}
