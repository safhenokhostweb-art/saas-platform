<?php

namespace Tests\Feature\Modules\SeoAdvanced;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use SaaSPlatform\Modules\SeoAdvanced\Models\SeoAdvancedKeyword;
use Tests\TestCase;

/**
 * Phase 18-D — Part 5/8: Admin access to the Report/Keyword panels, and —
 * critically — that the Customer Portal report route (no site_id in the
 * URL, see SeoAdvancedCustomerReportController) never leaks another
 * site's keyword/audit data.
 */
class SeoAdvancedReportAndKeywordAccessTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::create([
            'mobile' => '0912' . random_int(1000000, 9999999), 'password' => Hash::make('password'),
            'is_admin' => true, 'is_active' => true,
        ]);
    }

    private function regularUser(): User
    {
        return User::create([
            'mobile' => '0912' . random_int(1000000, 9999999), 'password' => Hash::make('password'),
            'is_admin' => false, 'is_active' => true,
        ]);
    }

    public function test_admin_keywords_page_requires_authentication(): void
    {
        $this->get(route('seo-advanced.admin.keywords', 1))->assertRedirect(route('login'));
    }

    public function test_admin_report_page_reachable_for_an_authenticated_admin(): void
    {
        $response = $this->actingAs($this->admin())->get(route('seo-advanced.admin.report', 1));

        $response->assertOk();
    }

    public function test_admin_can_store_and_see_a_keyword(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('seo-advanced.admin.keywords.store', 1), [
            'keyword' => 'خرید لپ‌تاپ',
        ])->assertRedirect(route('seo-advanced.admin.keywords', 1));

        $this->assertDatabaseHas('seo_advanced_keywords', ['site_id' => 1, 'keyword' => 'خرید لپ‌تاپ']);

        $this->actingAs($admin)->get(route('seo-advanced.admin.keywords', 1))->assertSee('خرید لپ‌تاپ');
    }

    public function test_customer_report_route_requires_authentication(): void
    {
        $this->get(route('seo-advanced.site.report'))->assertRedirect(route('login'));
    }

    public function test_customer_report_never_shows_another_sites_keywords(): void
    {
        // Site 1 is the fixed single-tenant site this route always resolves
        // to (see SeoAdvancedCustomerReportController::SINGLE_TENANT_SITE_ID).
        SeoAdvancedKeyword::create(['site_id' => 1, 'keyword' => 'کلمه سایت من']);
        SeoAdvancedKeyword::create(['site_id' => 2, 'keyword' => 'کلمه سایت دیگر']);

        $response = $this->actingAs($this->regularUser())->get(route('seo-advanced.site.report'));

        $response->assertOk();
        $response->assertSee('کلمه سایت من');
        $response->assertDontSee('کلمه سایت دیگر');
    }
}
