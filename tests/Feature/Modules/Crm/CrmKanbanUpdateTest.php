<?php

namespace Tests\Feature\Modules\Crm;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use SaaSPlatform\Modules\Crm\Models\CrmOpportunity;
use SaaSPlatform\Modules\Crm\Models\CrmPipeline;
use SaaSPlatform\Modules\Crm\Models\CrmStage;
use Tests\Concerns\MakesCrmTestSites;
use Tests\TestCase;

/**
 * Phase 20 — Part 3/9: the Kanban board's drag-and-drop calls the SAME
 * move-stage route as the plain admin dropdown (see
 * CrmOpportunityController::moveStage()'s docblock) — this test proves
 * the JSON branch works end-to-end: no refresh, stage_changed_at
 * recorded, and a system Activity auto-logged.
 */
class CrmKanbanUpdateTest extends TestCase
{
    use MakesCrmTestSites;
    use RefreshDatabase;

    private function admin(): User
    {
        return User::create([
            'mobile' => '0912' . random_int(1000000, 9999999), 'password' => Hash::make('password'),
            'is_admin' => true, 'is_active' => true,
        ]);
    }

    public function test_moving_stage_via_json_updates_without_redirect_and_logs_activity(): void
    {
        $site = $this->makeCrmTestSite();
        $pipeline = CrmPipeline::create(['site_id' => $site->id, 'name' => 'Default']);
        $fromStage = CrmStage::create(['pipeline_id' => $pipeline->id, 'name' => 'New', 'position' => 1]);
        $toStage = CrmStage::create(['pipeline_id' => $pipeline->id, 'name' => 'Qualified', 'position' => 2]);
        $opportunity = CrmOpportunity::create([
            'site_id' => $site->id, 'name' => 'Deal', 'pipeline_id' => $pipeline->id, 'stage_id' => $fromStage->id,
        ]);

        $response = $this->actingAs($this->admin())
            ->postJson(route('crm.admin.opportunities.move-stage', [$site->id, $opportunity]), ['stage_id' => $toStage->id]);

        $response->assertOk()->assertJson(['success' => true]);

        $opportunity->refresh();
        $this->assertSame($toStage->id, $opportunity->stage_id);
        $this->assertNotNull($opportunity->stage_changed_at);
        $this->assertDatabaseHas('crm_suite_activities', [
            'subject_id' => $opportunity->id, 'type' => 'system',
        ]);
    }

    public function test_moving_to_a_won_stage_closes_the_deal(): void
    {
        $site = $this->makeCrmTestSite();
        $pipeline = CrmPipeline::create(['site_id' => $site->id, 'name' => 'Default']);
        $openStage = CrmStage::create(['pipeline_id' => $pipeline->id, 'name' => 'Open', 'type' => 'open']);
        $wonStage = CrmStage::create(['pipeline_id' => $pipeline->id, 'name' => 'Won', 'type' => 'won']);
        $opportunity = CrmOpportunity::create([
            'site_id' => $site->id, 'name' => 'Deal', 'pipeline_id' => $pipeline->id, 'stage_id' => $openStage->id,
        ]);

        $this->actingAs($this->admin())
            ->postJson(route('crm.admin.opportunities.move-stage', [$site->id, $opportunity]), ['stage_id' => $wonStage->id])
            ->assertOk();

        $this->assertSame(CrmOpportunity::STATUS_WON, $opportunity->fresh()->status);
    }

    public function test_move_stage_route_requires_authentication(): void
    {
        $site = $this->makeCrmTestSite();
        $pipeline = CrmPipeline::create(['site_id' => $site->id, 'name' => 'Default']);
        $stage = CrmStage::create(['pipeline_id' => $pipeline->id, 'name' => 'Open']);
        $opportunity = CrmOpportunity::create(['site_id' => $site->id, 'name' => 'Deal', 'pipeline_id' => $pipeline->id, 'stage_id' => $stage->id]);

        $this->postJson(route('crm.admin.opportunities.move-stage', [$site->id, $opportunity]), ['stage_id' => $stage->id])
            ->assertStatus(401); // unauthenticated JSON request
    }

    public function test_kanban_board_page_is_reachable_for_an_authenticated_admin(): void
    {
        $site = $this->makeCrmTestSite();
        CrmPipeline::create(['site_id' => $site->id, 'name' => 'Default', 'is_default' => true]);

        $this->actingAs($this->admin())
            ->get(route('crm.admin.opportunities.kanban', $site->id))
            ->assertOk();
    }
}
