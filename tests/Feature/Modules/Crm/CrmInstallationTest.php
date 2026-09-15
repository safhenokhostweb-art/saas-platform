<?php

namespace Tests\Feature\Modules\Crm;

use App\Models\Customer;
use App\Models\Module;
use App\Models\ModuleRelease;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use SaaSPlatform\Modules\Crm\Models\CrmLead;
use Tests\TestCase;

/**
 * Phase 19 — Part 8/9: proves the Module Ecosystem works end-to-end for
 * CRM with zero platform code changes — only the catalog seed migration
 * (2026_08_01_110000_seed_crm_marketplace_module) was needed. Mirrors
 * SeoAdvancedInstallationTest's structure (Phase 18).
 */
class CrmInstallationTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::create([
            'mobile' => '0912' . random_int(1000000, 9999999), 'password' => Hash::make('password'),
            'is_admin' => true, 'is_active' => true,
        ]);
    }

    /** crm_*.site_id carries a real FK to sites (see CrmTenantIsolationTest's docblock) — inserts need a real row. */
    private function makeSite(): Site
    {
        $customer = Customer::create(['name' => 'مشتری تست', 'mobile' => '0912' . random_int(1000000, 9999999)]);

        return Site::create([
            'customer_id' => $customer->id, 'title' => 'سایت تست',
            'domain' => 'test-' . uniqid('', true) . '.example.com', 'is_active' => true,
        ]);
    }

    // ─── Catalog seed sanity ────────────────────────────────────────────────

    public function test_crm_suite_is_seeded_as_a_paid_marketplace_module_distinct_from_legacy_placeholder(): void
    {
        $module = Module::where('slug', 'crm-suite')->firstOrFail();

        $this->assertTrue($module->isPaidMarketplace());
        $this->assertDatabaseHas('products', ['module_id' => $module->id, 'type' => 'module_addon', 'is_active' => true]);

        // The pre-existing roadmap placeholder (slug=crm, price_irr=0) must be
        // completely untouched — a different row, never merged/renamed.
        $this->assertDatabaseHas('modules', ['slug' => 'crm']);
        $this->assertNotSame(
            Module::where('slug', 'crm')->value('id'),
            $module->id
        );
    }

    public function test_module_manifest_passes_official_schema(): void
    {
        $manifest = json_decode(
            file_get_contents(base_path('packages/saas-platform/modules/crm/module.json')),
            true
        );

        $result = (new \SaaSPlatform\CoreUtils\Support\ModuleManifestSchema())->validate($manifest);

        $this->assertTrue($result['valid'], implode('; ', $result['errors']));
    }

    // ─── Admin route access ─────────────────────────────────────────────────

    public function test_admin_companies_page_requires_authentication(): void
    {
        $this->get(route('crm.admin.companies.index', 1))->assertRedirect(route('login'));
    }

    public function test_admin_companies_page_reachable_for_an_authenticated_admin(): void
    {
        $response = $this->actingAs($this->admin())->get(route('crm.admin.companies.index', 1));

        $response->assertOk();
    }

    public function test_admin_can_create_a_company_and_see_it_listed(): void
    {
        $admin = $this->admin();
        $site = $this->makeSite();

        $this->actingAs($admin)->post(route('crm.admin.companies.store', $site->id), ['name' => 'Acme Inc'])
            ->assertRedirect(route('crm.admin.companies.index', $site->id));

        $this->assertDatabaseHas('crm_companies', ['site_id' => $site->id, 'name' => 'Acme Inc']);

        $this->actingAs($admin)->get(route('crm.admin.companies.index', $site->id))->assertSee('Acme Inc');
    }

    public function test_lead_conversion_route_creates_a_contact(): void
    {
        $admin = $this->admin();
        $site = $this->makeSite();

        $this->actingAs($admin)->post(route('crm.admin.leads.store', $site->id), ['first_name' => 'Ali']);
        $lead = CrmLead::where('site_id', $site->id)->firstOrFail();

        $this->actingAs($admin)->post(route('crm.admin.leads.convert', [$site->id, $lead]))
            ->assertRedirect(route('crm.admin.contacts.index', $site->id));

        $this->assertDatabaseHas('crm_suite_contacts', ['site_id' => $site->id, 'first_name' => 'Ali']);
        $this->assertDatabaseHas('crm_leads', ['id' => $lead->id, 'status' => 'converted']);
    }
}
