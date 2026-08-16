<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\FinanceLedgerEntry;
use App\Models\Site;
use App\Models\SiteUpdate;
use App\Services\LicenseServerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/** Regression coverage for the canonical SiteContext tenant API boundary. */
class TenantApiSecurityTest extends TestCase
{
    use RefreshDatabase;

    private function createSite(string $label): Site
    {
        $customer = Customer::query()->create([
            'name' => "Customer {$label}",
            'mobile' => '0912'.random_int(1000000, 9999999),
        ]);

        return Site::query()->create([
            'customer_id' => $customer->id,
            'title' => "Site {$label}",
            'domain' => strtolower("{$label}.tenant.test"),
            'is_active' => true,
            'support_expires_at' => now()->addMonth(),
            'support_level' => 'pro',
        ]);
    }

    private function credentialFor(Site $site): string
    {
        return app(LicenseServerService::class)->issue($site, $site->domain);
    }

    private function authenticatedAs(Site $site, string $credential): static
    {
        return $this->withToken($credential)->withHeaders([
            'X-Tenant-Domain' => $site->domain,
        ]);
    }

    public function test_finance_ledger_uses_the_canonical_site_not_request_site_input(): void
    {
        $siteA = $this->createSite('A');
        $siteB = $this->createSite('B');
        $keyA = $this->credentialFor($siteA);

        $response = $this->authenticatedAs($siteA, $keyA)->postJson('/api/ledger/push-event?site_id='.$siteB->id, [
            'event_type' => 'subscription_payment',
            'amount' => 120000,
            'site_id' => $siteB->id,
            'reference_type' => 'tenant_api_security',
            'reference_id' => 1001,
        ]);

        $response->assertOk()->assertJson(['success' => true, 'recorded' => true]);
        $this->assertDatabaseHas('finance_ledger_entries', [
            'site_id' => $siteA->id,
            'amount' => 120000,
            'reference_type' => 'tenant_api_security',
            'reference_id' => 1001,
        ]);
        $this->assertDatabaseMissing('finance_ledger_entries', ['site_id' => $siteB->id, 'reference_id' => 1001]);
    }

    public function test_finance_ledger_rejects_missing_tenant_credentials(): void
    {
        $this->postJson('/api/ledger/push-event', [
            'event_type' => 'order_payment',
            'amount' => 100,
        ])->assertStatus(401)->assertJsonPath('error', 'missing_credentials');

        $this->assertSame(0, FinanceLedgerEntry::query()->count());
    }

    public function test_site_config_uses_the_canonical_site_even_when_client_supplies_another_site(): void
    {
        $siteA = $this->createSite('A');
        $siteB = $this->createSite('B');
        $keyA = $this->credentialFor($siteA);

        $response = $this->authenticatedAs($siteA, $keyA)->getJson('/api/site-config?site_id='.$siteB->id.'&domain='.$siteB->domain.'&debug=1');

        $response->assertOk()
            ->assertJsonPath('allowed', true)
            ->assertJsonPath('site_title', $siteA->title)
            ->assertJsonPath('debug.site_id', $siteA->id);
        $this->assertNotSame($siteB->title, $response->json('site_title'));
    }

    public function test_site_config_rejects_unauthenticated_requests(): void
    {
        $this->getJson('/api/site-config')->assertStatus(401)->assertJsonPath('error', 'missing_credentials');
    }

    public function test_update_check_uses_canonical_site_and_preserves_valid_flow(): void
    {
        $siteA = $this->createSite('A');
        $siteB = $this->createSite('B');
        $keyA = $this->credentialFor($siteA);
        SiteUpdate::query()->create(['site_id' => $siteA->id, 'version_label' => '2.0.0', 'distributed_at' => now(), 'notes' => 'A release']);
        SiteUpdate::query()->create(['site_id' => $siteB->id, 'version_label' => '9.9.9', 'distributed_at' => now(), 'notes' => 'B release']);

        $response = $this->authenticatedAs($siteA, $keyA)->getJson('/api/updates/check?product=site_template&current_version=1.0.0&site_id='.$siteB->id.'&domain='.$siteB->domain);

        $response->assertOk()
            ->assertJsonPath('allowed', true)
            ->assertJsonPath('latest_version', '2.0.0')
            ->assertJsonPath('update_available', true);
        $this->assertNotSame('9.9.9', $response->json('latest_version'));
    }

    public function test_update_routes_reject_missing_tenant_credentials(): void
    {
        $this->getJson('/api/updates/check?product=site_template')
            ->assertStatus(401)
            ->assertJsonPath('error', 'missing_credentials');
    }

    public function test_update_and_site_config_routes_keep_throttle_and_tenant_api_middleware(): void
    {
        foreach (['api.updates.check', 'api.updates.modules', 'api.updates.module-history', 'api.updates.report-versions', 'api.site-config.show'] as $name) {
            $middleware = Route::getRoutes()->getByName($name)?->gatherMiddleware() ?? [];

            $this->assertContains('tenant.api', $middleware, "{$name} must resolve canonical tenant context.");
            $this->assertContains('throttle:'.($name === 'api.site-config.show' ? '60,1' : '30,1'), $middleware, "{$name} must retain throttling.");
        }
    }
}
