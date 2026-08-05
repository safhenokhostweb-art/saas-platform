<?php

namespace Tests\Feature\Modules\SeoAdvanced;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use SaaSPlatform\Modules\SeoAdvanced\Models\SeoAdvancedAudit;
use SaaSPlatform\Modules\SeoAdvanced\Models\SeoAdvancedKeyword;
use Tests\TestCase;

/**
 * Phase 18-E — Part 9/11: the customer-facing SEO dashboard
 * (Site\SeoAdvancedCustomerReportController) now also surfaces Google
 * Performance and Recommendations (Phase 18-E additions) on top of the
 * existing Score/Issues/Keywords/History (Phase 18-D) — this test
 * proves the page renders correctly in both the "not connected to
 * Google" state (the only state reachable without real credentials)
 * and that isolation still holds.
 */
class SeoCustomerDashboardTest extends TestCase
{
    use RefreshDatabase;

    private function regularUser(): User
    {
        return User::create([
            'mobile' => '0912' . random_int(1000000, 9999999), 'password' => Hash::make('password'),
            'is_admin' => false, 'is_active' => true,
        ]);
    }

    public function test_dashboard_shows_not_connected_state_when_google_is_not_linked(): void
    {
        $response = $this->actingAs($this->regularUser())->get(route('seo-advanced.site.report'));

        $response->assertOk();
        // No google_performance card should render when disconnected — the
        // view only prints the section inside @if ($google_performance).
        $response->assertDontSee('عملکرد در گوگل');
    }

    public function test_dashboard_shows_recommendations_derived_from_the_latest_audit(): void
    {
        SeoAdvancedAudit::create([
            'site_id' => 1, 'page_url' => '/x', 'score' => 60,
            'issues' => [['code' => 'title_missing', 'severity' => 'critical', 'message' => 'm', 'suggested_fix' => 'یک عنوان اضافه کنید.']],
            'audited_at' => now(),
        ]);

        $response = $this->actingAs($this->regularUser())->get(route('seo-advanced.site.report'));

        $response->assertOk();
        $response->assertSee('یک عنوان اضافه کنید.');
    }

    public function test_dashboard_never_shows_another_sites_audit_or_keywords(): void
    {
        // Site 1 is the fixed single-tenant site (SeoAdvancedCustomerReportController::SINGLE_TENANT_SITE_ID).
        SeoAdvancedAudit::create(['site_id' => 1, 'page_url' => '/mine', 'score' => 80, 'audited_at' => now()]);
        SeoAdvancedAudit::create(['site_id' => 2, 'page_url' => '/other-sites-secret-page', 'score' => 40, 'audited_at' => now()]);
        SeoAdvancedKeyword::create(['site_id' => 1, 'keyword' => 'کلمه سایت من']);
        SeoAdvancedKeyword::create(['site_id' => 2, 'keyword' => 'کلمه سایت دیگر']);

        $response = $this->actingAs($this->regularUser())->get(route('seo-advanced.site.report'));

        $response->assertOk();
        $response->assertSee('کلمه سایت من');
        $response->assertDontSee('کلمه سایت دیگر');
        $response->assertDontSee('/other-sites-secret-page');
    }

    public function test_dashboard_requires_authentication(): void
    {
        $this->get(route('seo-advanced.site.report'))->assertRedirect(route('login'));
    }
}
