<?php

namespace Tests\Unit\Modules\Crm;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use SaaSPlatform\Modules\Crm\Models\CrmCompany;
use SaaSPlatform\Modules\Crm\Models\CrmContact;
use SaaSPlatform\Modules\Crm\Models\CrmLead;
use SaaSPlatform\Modules\Crm\Models\CrmOpportunity;
use SaaSPlatform\Modules\Crm\Models\CrmPipeline;
use SaaSPlatform\Modules\Crm\Models\CrmStage;
use SaaSPlatform\Modules\Crm\Models\CrmTag;
use Tests\TestCase;

/**
 * Phase 19 — Part 3/4: exercises the real module classes (autoloaded via
 * the composer path repository), never App\Models — this module has no
 * dependency on the platform's Eloquent models at all.
 */
class CrmModelsTest extends TestCase
{
    use RefreshDatabase;

    // ─── Migrations ─────────────────────────────────────────────────────────

    public function test_all_eleven_crm_tables_exist_with_expected_columns(): void
    {
        $this->assertDatabaseCount('crm_companies', 0);
        $this->assertTrue(Schema::hasColumns('crm_companies', ['site_id', 'name', 'lifecycle_stage', 'archived_at']));
        $this->assertTrue(Schema::hasColumns('crm_contacts', ['site_id', 'company_id', 'lifecycle_stage']));
        $this->assertTrue(Schema::hasColumns('crm_leads', ['status', 'converted_contact_id', 'converted_opportunity_id']));
        $this->assertTrue(Schema::hasColumns('crm_opportunities', ['pipeline_id', 'stage_id', 'amount', 'probability', 'lost_reason', 'won_reason']));
        $this->assertTrue(Schema::hasColumns('crm_stages', ['probability', 'type']));
        $this->assertTrue(Schema::hasColumns('crm_activities', ['subject_type', 'subject_id', 'type']));
        $this->assertTrue(Schema::hasColumns('crm_tasks', ['due_at', 'remind_at', 'status']));
        $this->assertTrue(Schema::hasColumns('crm_attachments', ['attachable_type', 'attachable_id', 'disk', 'path']));
        $this->assertTrue(Schema::hasTable('crm_tags'));
        $this->assertTrue(Schema::hasTable('crm_taggables'));
        $this->assertTrue(Schema::hasTable('crm_pipelines'));
    }

    // ─── Defaults (DB-level default hydration gotcha) ──────────────────────

    public function test_company_defaults_to_lead_stage_immediately_after_create(): void
    {
        $company = CrmCompany::create(['name' => 'Acme']);

        $this->assertSame(CrmCompany::STAGE_LEAD, $company->lifecycle_stage);
    }

    public function test_opportunity_defaults_to_open_status_immediately_after_create(): void
    {
        $pipeline = CrmPipeline::create(['name' => 'Default']);
        $stage = CrmStage::create(['pipeline_id' => $pipeline->id, 'name' => 'New']);

        $opportunity = CrmOpportunity::create(['name' => 'Deal', 'pipeline_id' => $pipeline->id, 'stage_id' => $stage->id]);

        $this->assertSame(CrmOpportunity::STATUS_OPEN, $opportunity->status);
    }

    // ─── Relationships ──────────────────────────────────────────────────────

    public function test_contact_belongs_to_company_and_company_has_many_contacts(): void
    {
        $company = CrmCompany::create(['name' => 'Acme']);
        $contact = CrmContact::create(['first_name' => 'Ali', 'company_id' => $company->id]);

        $this->assertSame('Acme', $contact->company->name);
        $this->assertSame(1, $company->contacts()->count());
    }

    public function test_contact_full_name_accessor_combines_first_and_last_name(): void
    {
        $contact = CrmContact::create(['first_name' => 'Ali', 'last_name' => 'Rezaei']);

        $this->assertSame('Ali Rezaei', $contact->full_name);
    }

    // ─── Traits: Archivable / Favoritable / Taggable ───────────────────────

    public function test_archivable_toggles_archived_at_and_scopes_active_archived(): void
    {
        $active = CrmContact::create(['first_name' => 'Active']);
        $archived = CrmContact::create(['first_name' => 'Archived']);
        $archived->archive();

        $this->assertTrue($archived->fresh()->isArchived());
        $this->assertSame(1, CrmContact::active()->count());
        $this->assertSame(1, CrmContact::archived()->count());

        $archived->unarchive();
        $this->assertFalse($archived->fresh()->isArchived());
    }

    public function test_favoritable_toggles_is_favorite_and_scopes_favorites(): void
    {
        $contact = CrmContact::create(['first_name' => 'Ali']);

        $this->assertTrue($contact->toggleFavorite());
        $this->assertSame(1, CrmContact::favorites()->count());

        $this->assertFalse($contact->toggleFavorite());
        $this->assertSame(0, CrmContact::favorites()->count());
    }

    public function test_taggable_attaches_tags_across_different_entity_types(): void
    {
        $tag = CrmTag::create(['name' => 'VIP']);
        $contact = CrmContact::create(['first_name' => 'Ali']);
        $company = CrmCompany::create(['name' => 'Acme']);

        $contact->syncTags([$tag->id]);
        $company->syncTags([$tag->id]);

        $this->assertSame(['VIP'], $contact->tags()->pluck('name')->all());
        $this->assertSame(['VIP'], $company->tags()->pluck('name')->all());
        $this->assertSame(2, DB::table('crm_taggables')->where('tag_id', $tag->id)->count());
    }

    // ─── Search scopes ──────────────────────────────────────────────────────

    public function test_contact_search_scope_matches_name_email_or_phone(): void
    {
        CrmContact::create(['first_name' => 'Ali', 'last_name' => 'Rezaei', 'email' => 'ali@example.com']);
        CrmContact::create(['first_name' => 'Sara', 'last_name' => 'Ahmadi', 'email' => 'sara@example.com']);

        $this->assertSame(1, CrmContact::search('Rezaei')->count());
        $this->assertSame(1, CrmContact::search('sara@example.com')->count());
        $this->assertSame(2, CrmContact::search(null)->count());
    }
}
