<?php

namespace Tests\Unit\Modules\Crm;

use Illuminate\Foundation\Testing\RefreshDatabase;
use SaaSPlatform\Modules\Crm\Models\CrmLead;
use SaaSPlatform\Modules\Crm\Models\CrmPipeline;
use SaaSPlatform\Modules\Crm\Models\CrmStage;
use SaaSPlatform\Modules\Crm\Services\CrmLeadConversionService;
use Tests\TestCase;

class CrmLeadConversionTest extends TestCase
{
    use RefreshDatabase;

    private function service(): CrmLeadConversionService
    {
        return new CrmLeadConversionService();
    }

    public function test_converting_a_lead_creates_a_contact_and_marks_it_converted(): void
    {
        $lead = CrmLead::create(['first_name' => 'Ali', 'last_name' => 'Rezaei', 'email' => 'ali@example.com']);

        $contact = $this->service()->convert($lead);

        $this->assertSame('Ali Rezaei', $contact->full_name);
        $this->assertSame('ali@example.com', $contact->email);

        $lead->refresh();
        $this->assertTrue($lead->isConverted());
        $this->assertSame($contact->id, $lead->converted_contact_id);
        $this->assertNotNull($lead->converted_at);
    }

    public function test_converting_with_create_company_creates_and_links_a_company(): void
    {
        $lead = CrmLead::create(['first_name' => 'Ali', 'company_name' => 'Acme Inc']);

        $contact = $this->service()->convert($lead, ['create_company' => true]);

        $this->assertSame('Acme Inc', $contact->company->name);
        $this->assertSame($contact->company_id, $lead->fresh()->converted_company_id);
    }

    public function test_converting_with_create_opportunity_creates_and_links_a_deal(): void
    {
        $pipeline = CrmPipeline::create(['name' => 'Default', 'is_default' => true]);
        CrmStage::create(['pipeline_id' => $pipeline->id, 'name' => 'New', 'type' => 'open', 'position' => 1]);

        $lead = CrmLead::create(['first_name' => 'Ali']);

        $contact = $this->service()->convert($lead, [
            'create_opportunity' => true, 'opportunity_name' => 'Ali Deal', 'opportunity_amount' => 500000,
        ]);

        $opportunity = $lead->fresh()->convertedOpportunity;
        $this->assertNotNull($opportunity);
        $this->assertSame('Ali Deal', $opportunity->name);
        $this->assertSame(500000, $opportunity->amount);
        $this->assertSame($contact->id, $opportunity->contact_id);
    }

    public function test_converting_an_already_converted_lead_throws(): void
    {
        $lead = CrmLead::create(['first_name' => 'Ali', 'status' => CrmLead::STATUS_CONVERTED]);

        $this->expectException(\RuntimeException::class);
        $this->service()->convert($lead);
    }
}
