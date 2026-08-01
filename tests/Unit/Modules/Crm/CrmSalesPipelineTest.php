<?php

namespace Tests\Unit\Modules\Crm;

use Illuminate\Foundation\Testing\RefreshDatabase;
use SaaSPlatform\Modules\Crm\Models\CrmOpportunity;
use SaaSPlatform\Modules\Crm\Models\CrmPipeline;
use SaaSPlatform\Modules\Crm\Models\CrmStage;
use Tests\TestCase;

/**
 * Phase 19 — Part 5: Sales Pipeline business rules — probability
 * (stage default vs per-deal override), weighted/forecast amount, and
 * the stage-transition side effects (won/lost auto-handling).
 */
class CrmSalesPipelineTest extends TestCase
{
    use RefreshDatabase;

    private function makeOpportunity(int $probability = 40, int $amount = 1000000): CrmOpportunity
    {
        $pipeline = CrmPipeline::create(['name' => 'Default']);
        $stage = CrmStage::create(['pipeline_id' => $pipeline->id, 'name' => 'Qualified', 'probability' => $probability]);

        return CrmOpportunity::create([
            'name' => 'Deal', 'pipeline_id' => $pipeline->id, 'stage_id' => $stage->id, 'amount' => $amount,
        ]);
    }

    public function test_effective_probability_falls_back_to_stage_default(): void
    {
        $opportunity = $this->makeOpportunity(probability: 40);

        $this->assertSame(40, $opportunity->effectiveProbability());
    }

    public function test_effective_probability_uses_deal_level_override_when_set(): void
    {
        $opportunity = $this->makeOpportunity(probability: 40);
        $opportunity->update(['probability' => 75]);

        $this->assertSame(75, $opportunity->effectiveProbability());
    }

    public function test_weighted_amount_is_amount_times_effective_probability(): void
    {
        $opportunity = $this->makeOpportunity(probability: 50, amount: 2000000);

        $this->assertSame(1000000, $opportunity->weightedAmount());
    }

    public function test_move_to_stage_of_type_won_marks_the_opportunity_won(): void
    {
        $opportunity = $this->makeOpportunity();
        $wonStage = CrmStage::create(['pipeline_id' => $opportunity->pipeline_id, 'name' => 'Won', 'type' => 'won', 'probability' => 100]);

        $opportunity->moveToStage($wonStage);

        $opportunity->refresh();
        $this->assertSame(CrmOpportunity::STATUS_WON, $opportunity->status);
        $this->assertSame($wonStage->id, $opportunity->stage_id);
        $this->assertNotNull($opportunity->closed_at);
    }

    public function test_move_to_stage_of_type_lost_marks_the_opportunity_lost(): void
    {
        $opportunity = $this->makeOpportunity();
        $lostStage = CrmStage::create(['pipeline_id' => $opportunity->pipeline_id, 'name' => 'Lost', 'type' => 'lost', 'probability' => 0]);

        $opportunity->moveToStage($lostStage);

        $this->assertSame(CrmOpportunity::STATUS_LOST, $opportunity->fresh()->status);
    }

    public function test_move_to_stage_of_type_open_does_not_close_the_deal(): void
    {
        $opportunity = $this->makeOpportunity();
        $nextStage = CrmStage::create(['pipeline_id' => $opportunity->pipeline_id, 'name' => 'Negotiation', 'type' => 'open', 'probability' => 60]);

        $opportunity->moveToStage($nextStage);

        $this->assertSame(CrmOpportunity::STATUS_OPEN, $opportunity->fresh()->status);
        $this->assertNull($opportunity->fresh()->closed_at);
    }

    public function test_mark_won_and_mark_lost_record_the_given_reason(): void
    {
        $opportunity = $this->makeOpportunity();
        $opportunity->markWon('client loved the demo');

        $this->assertSame('client loved the demo', $opportunity->fresh()->won_reason);

        $other = $this->makeOpportunity();
        $other->markLost('budget cut');

        $this->assertSame('budget cut', $other->fresh()->lost_reason);
    }

    public function test_pipeline_stages_are_ordered_by_position(): void
    {
        $pipeline = CrmPipeline::create(['name' => 'Default']);
        CrmStage::create(['pipeline_id' => $pipeline->id, 'name' => 'Second', 'position' => 2]);
        CrmStage::create(['pipeline_id' => $pipeline->id, 'name' => 'First', 'position' => 1]);

        $this->assertSame(['First', 'Second'], $pipeline->stages()->pluck('name')->all());
    }
}
