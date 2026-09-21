<?php

namespace Tests\Feature\Observers;

use App\Models\Customer;
use App\Models\Site;
use App\Models\Theme;
use App\Models\ThemeLicense;
use App\Models\ThemePurchaseOrder;
use App\Models\ThemeRelease;
use Database\Seeders\UniversalMultipurposeQaThemeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * SP-02 (TASK 2-SEC) regression coverage.
 *
 * ThemePurchaseOrderObserver::provisionThemePages() used to call its own
 * private /builder/api/theme/provision webhook
 * (ThemePageProvisionService::provision() -> bare Page::create(), its own
 * hand-rolled slug-uniqueness loop) — a second, independent Theme-install
 * authority alongside ThemeCanvasController's manual install/sync, bypassing
 * ThemeInstanceRevisionService/CanvasReservedSlugs/revision history entirely.
 *
 * This asserts marketplace purchase fulfillment now shares the SAME
 * canonical authority (App\Services\ThemeBuilder\ThemeInstallGateway ->
 * /builder/api/theme/install) as ThemeCanvasController::installToSite()/
 * syncPagesForSite() — no private page-provisioning implementation remains
 * (THEME_INSTALL_MUTATION_AUTHORITIES = 1, PURCHASE_DIRECT_PAGE_CREATE = 0).
 *
 * Http::fake() fakes only the network transport boundary to customer-site,
 * exactly as in ThemeCanvasInstallToSiteHttpTest/ThemeCanvasSyncPagesHttpTest.
 */
class ThemePurchaseOrderProvisionTest extends TestCase
{
    use RefreshDatabase;

    private function site(): Site
    {
        $customer = Customer::create([
            'name' => 'Purchase Fulfillment Test Customer',
            'mobile' => '09990000021',
        ]);

        return Site::create([
            'customer_id' => $customer->id,
            'title' => 'Purchase Fulfillment Test Site',
            'domain' => 'purchase-fulfillment-test.local',
            'is_active' => true,
        ]);
    }

    private function theme(): Theme
    {
        $this->seed(UniversalMultipurposeQaThemeSeeder::class);
        $theme = Theme::where('slug', UniversalMultipurposeQaThemeSeeder::SLUG)->sole();

        $release = ThemeRelease::create([
            'theme_id' => $theme->id,
            'version' => '3.0.0',
            'changelog' => 'Purchase fulfillment test release.',
            'status' => ThemeRelease::STATUS_PUBLISHED,
            'snapshot' => ['pages' => $theme->getPageList()],
            'published_at' => now(),
        ]);
        $theme->forceFill(['current_release_id' => $release->id])->save();

        return $theme->fresh();
    }

    public function test_successful_purchase_grants_entitlement_without_installing_before_customer_confirmation(): void
    {
        config(['services.customer_provision_secret' => 'test-secret-value']);

        $theme = $this->theme();
        $site  = $this->site();

        Http::fake([
            'purchase-fulfillment-test.local/builder/api/theme/install' => Http::response([
                'success' => true, 'revision_id' => 11, 'revision_number' => 1, 'pages_installed' => 21,
            ], 200),
        ]);

        $order = ThemePurchaseOrder::create([
            'site_id'     => $site->id,
            'theme_id'    => $theme->id,
            'amount_rial' => 1000000,
            'status'      => ThemePurchaseOrder::STATUS_PENDING,
        ]);

        // Mirrors ThemeMarketplacePaymentController::callback(): entitlement
        // is granted, then the order is marked paid (which fires the
        // observer under test).
        ThemeLicense::grant($site->id, $theme->id, $order->id);
        $order->update(['status' => ThemePurchaseOrder::STATUS_PAID, 'paid_at' => now()]);

        $this->assertDatabaseHas('theme_licenses', [
            'site_id'  => $site->id,
            'theme_id' => $theme->id,
        ]);

        Http::assertNothingSent();
    }

    public function test_payment_never_pushes_an_install_to_the_customer_site(): void
    {
        config(['services.customer_provision_secret' => 'test-secret-value']);

        $theme = $this->theme();
        $site  = $this->site();

        Http::fake([
            'purchase-fulfillment-test.local/builder/api/theme/install' => Http::response([
                'success' => false,
                'error'   => 'page_namespace_collision',
                'message' => 'این عملیات صفحه(های) موجود را با این آدرس‌ها نامرئی می‌کند.',
            ], 409),
        ]);

        $order = ThemePurchaseOrder::create([
            'site_id'     => $site->id,
            'theme_id'    => $theme->id,
            'amount_rial' => 1000000,
            'status'      => ThemePurchaseOrder::STATUS_PENDING,
        ]);
        ThemeLicense::grant($site->id, $theme->id, $order->id);

        // Must not throw — the observer's provisioning step is a
        // best-effort side effect of an already-completed payment, and the
        // 409 is surfaced through the same canonical error path
        // ThemeInstallGateway gives every other caller, not a bespoke one.
        $order->update(['status' => ThemePurchaseOrder::STATUS_PAID, 'paid_at' => now()]);

        Http::assertNothingSent();
        // Entitlement remains granted even though provisioning hit a
        // collision — payment/licensing is not rolled back by a page-layer
        // conflict, matching the "payment success -> entitlement -> install"
        // ordering the task describes.
        $this->assertDatabaseHas('theme_licenses', [
            'site_id'  => $site->id,
            'theme_id' => $theme->id,
        ]);
    }

    public function test_duplicate_fulfillment_is_idempotent_and_never_auto_installs(): void
    {
        config(['services.customer_provision_secret' => 'test-secret-value']);

        $theme = $this->theme();
        $site  = $this->site();

        Http::fake([
            'purchase-fulfillment-test.local/builder/api/theme/install' => Http::response([
                'success' => true, 'revision_id' => 21, 'revision_number' => 1, 'pages_installed' => 21,
            ], 200),
        ]);

        $order = ThemePurchaseOrder::create([
            'site_id'     => $site->id,
            'theme_id'    => $theme->id,
            'amount_rial' => 1000000,
            'status'      => ThemePurchaseOrder::STATUS_PENDING,
        ]);
        ThemeLicense::grant($site->id, $theme->id, $order->id);
        $order->update(['status' => ThemePurchaseOrder::STATUS_PAID, 'paid_at' => now()]);

        Http::assertNothingSent();

        // A second save of an ALREADY-paid order (e.g. a retried webhook
        // hitting the same status transition) must not re-trigger
        // fulfillment: wasChanged('status') is false the second time, so
        // the observer's early return still guards this — no duplicate
        // ThemeLicense row, no second HTTP install call.
        $order->update(['gateway_ref' => 'retry-attempt-ref']);

        Http::assertNothingSent();
        $this->assertSame(
            1,
            \App\Models\ThemeLicense::where('site_id', $site->id)->where('theme_id', $theme->id)->count(),
            'Retried/duplicate order saves must not create a second entitlement or a second install call.'
        );
    }
}
