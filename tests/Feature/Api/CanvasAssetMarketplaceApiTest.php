<?php

namespace Tests\Feature\Api;

use App\Models\CanvasButtonPack;
use App\Models\CanvasHeaderPreset;
use App\Models\CanvasIconPack;
use App\Models\Customer;
use App\Models\Site;
use App\Models\SiteAssetGrant;
use App\Services\AssetProvisioning\SiteAssetEntitlementService;
use App\Services\LicenseServerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use SaaSPlatform\CoreUtils\Contracts\Services\ActivityLoggerInterface;
use SaaSPlatform\ThemeRuntime\Access\AssetRef;
use Tests\TestCase;

class CanvasAssetMarketplaceApiTest extends TestCase
{
    use RefreshDatabase;

    private array $audited = [];

    protected function setUp(): void
    {
        parent::setUp();

        $audited = &$this->audited;
        $this->app->instance(ActivityLoggerInterface::class, new class($audited) implements ActivityLoggerInterface {
            public function __construct(private array &$sink) {}

            public function log(string $action, ?string $description = null, ?object $subject = null, array $properties = []): void
            {
                $this->sink[] = [$action, $properties];
            }
        });
    }

    private function licensedSite(string $domain = 'tenant-canvas.test'): array
    {
        $customer = Customer::create([
            'name' => 'مشتری مارکت‌پلیس',
            'mobile' => '0912' . random_int(1000000, 9999999),
        ]);

        $site = Site::create([
            'customer_id' => $customer->id,
            'title' => 'سایت مارکت‌پلیس',
            'domain' => $domain,
            'is_active' => true,
            'business_vertical' => 'general',
        ]);

        $key = $this->app->make(LicenseServerService::class)->issue($site, $domain);

        return [$site, $key, $domain];
    }

    public function test_tenant_can_fetch_canvas_asset_catalog(): void
    {
        [$site, $key, $domain] = $this->licensedSite('catalog.test');

        CanvasButtonPack::create([
            'title' => 'پکیج دکمه شیشه‌ای',
            'slug' => 'glass-buttons',
            'access_type' => 'paid',
            'price' => 49000,
            'current_version' => '1.0.0',
            'is_active' => true,
        ]);

        CanvasIconPack::create([
            'title' => 'پکیج آیکن رایگان',
            'slug' => 'free-icons',
            'access_type' => 'free',
            'price' => 0,
            'current_version' => '1.0.0',
            'is_active' => true,
        ]);

        $response = $this->withToken($key)->getJson('/api/canvas-marketplace?domain=' . $domain);
        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonStructure([
            'success',
            'catalog' => ['buttons', 'icons', 'headers', 'motions'],
            'site' => ['id', 'title', 'domain'],
        ]);

        $buttons = collect($response->json('catalog.buttons'));
        $this->assertTrue($buttons->contains('slug', 'glass-buttons'));

        $icons = collect($response->json('catalog.icons'));
        $this->assertTrue($icons->contains('slug', 'free-icons'));
    }

    public function test_tenant_can_claim_free_asset(): void
    {
        [$site, $key, $domain] = $this->licensedSite('claim.test');

        CanvasIconPack::create([
            'title' => 'آیکن‌های هدیه',
            'slug' => 'gift-icons',
            'access_type' => 'free',
            'price' => 0,
            'current_version' => '1.0.0',
            'is_active' => true,
        ]);

        // Claim free asset
        $response = $this->withToken($key)->postJson('/api/canvas-marketplace/claim?domain=' . $domain, [
            'asset_kind' => AssetRef::KIND_ICON_PACK,
            'asset_key' => 'gift-icons',
        ]);

        $response->assertOk();
        $response->assertJsonPath('success', true);

        // Verify active grant created
        $this->assertDatabaseHas('site_asset_grants', [
            'site_id' => $site->id,
            'asset_kind' => AssetRef::KIND_ICON_PACK,
            'asset_key' => 'gift-icons',
            'status' => SiteAssetGrant::STATUS_ACTIVE,
        ]);

        // Verify immediately reflected in entitlements endpoint
        $entitlements = $this->withToken($key)->getJson('/api/asset-entitlements?domain=' . $domain)->json();
        $this->assertContains('icon_pack:gift-icons', $entitlements['grants'] ?? []);
    }

    public function test_tenant_cannot_claim_paid_asset_for_free(): void
    {
        [$site, $key, $domain] = $this->licensedSite('refusal.test');

        CanvasButtonPack::create([
            'title' => 'پکیج دکمه پریمیوم',
            'slug' => 'premium-btn',
            'access_type' => 'paid',
            'price' => 99000,
            'current_version' => '1.0.0',
            'is_active' => true,
        ]);

        // Attempt to claim paid asset
        $response = $this->withToken($key)->postJson('/api/canvas-marketplace/claim?domain=' . $domain, [
            'asset_kind' => AssetRef::KIND_BUTTON_PACK,
            'asset_key' => 'premium-btn',
        ]);

        $response->assertStatus(403);
        $this->assertFalse($response->json('success'));
        $this->assertDatabaseMissing('site_asset_grants', [
            'site_id' => $site->id,
            'asset_key' => 'premium-btn',
        ]);
    }

    public function test_tenant_can_purchase_and_activate_paid_asset(): void
    {
        [$site, $key, $domain] = $this->licensedSite('purchase.test');

        CanvasHeaderPreset::create([
            'title' => 'هدر لاکچری',
            'slug' => 'luxury-header',
            'component_alias' => 'nav_header',
            'access_type' => 'paid',
            'price' => 120000,
            'current_version' => '1.0.0',
            'is_active' => true,
        ]);

        // Purchase asset
        $response = $this->withToken($key)->postJson('/api/canvas-marketplace/purchase?domain=' . $domain, [
            'asset_kind' => AssetRef::KIND_HEADER,
            'asset_key' => 'luxury-header',
        ]);

        $response->assertOk();
        $response->assertJsonPath('success', true);

        // Verify grant
        $this->assertDatabaseHas('site_asset_grants', [
            'site_id' => $site->id,
            'asset_kind' => AssetRef::KIND_HEADER,
            'asset_key' => 'luxury-header',
            'status' => SiteAssetGrant::STATUS_ACTIVE,
        ]);

        // Verify immediate entitlement sync
        $entitlements = $this->withToken($key)->getJson('/api/asset-entitlements?domain=' . $domain)->json();
        $this->assertContains('header:luxury-header', $entitlements['grants'] ?? []);
    }

    public function test_catalog_exposes_version_history_and_changelog(): void
    {
        [$site, $key, $domain] = $this->licensedSite('history.test');

        $pack = CanvasButtonPack::create([
            'title' => 'پکیج دکمه با تاریخچه',
            'slug' => 'history-btn-pack',
            'access_type' => 'paid',
            'price' => 50000,
            'current_version' => '1.2.0',
            'is_active' => true,
        ]);

        \App\Models\CanvasAssetVersion::create([
            'asset_kind' => AssetRef::KIND_BUTTON_PACK,
            'asset_slug' => 'history-btn-pack',
            'version' => '1.0.0',
            'changelog' => 'انتشار اولیه',
            'is_current' => false,
            'published_at' => now()->subDays(5),
        ]);

        \App\Models\CanvasAssetVersion::create([
            'asset_kind' => AssetRef::KIND_BUTTON_PACK,
            'asset_slug' => 'history-btn-pack',
            'version' => '1.2.0',
            'changelog' => 'اضافه شدن استایل‌های نئومورفیسم',
            'is_current' => true,
            'published_at' => now(),
        ]);

        $response = $this->withToken($key)->getJson('/api/canvas-marketplace?domain=' . $domain);
        $response->assertOk();

        $button = collect($response->json('catalog.buttons'))->firstWhere('slug', 'history-btn-pack');
        $this->assertNotNull($button);
        $this->assertEquals('1.2.0', $button['current_version']);
        $this->assertIsArray($button['versions_history']);
        $this->assertCount(2, $button['versions_history']);
        $this->assertEquals('1.2.0', $button['versions_history'][0]['version']);
        $this->assertEquals('اضافه شدن استایل‌های نئومورفیسم', $button['versions_history'][0]['changelog']);
    }

    public function test_payment_callback_successfully_grants_purchased_asset(): void
    {
        [$site, $key, $domain] = $this->licensedSite('callback.test');

        CanvasButtonPack::create([
            'title' => 'دکمه مدرن پرداختی',
            'slug' => 'modern-paid-btn',
            'access_type' => 'paid',
            'price' => 75000,
            'current_version' => '1.0.0',
            'is_active' => true,
        ]);

        // Create pending purchase order
        $order = \App\Models\CanvasAssetPurchaseOrder::create([
            'site_id' => $site->id,
            'customer_id' => $site->customer_id,
            'asset_kind' => AssetRef::KIND_BUTTON_PACK,
            'asset_key' => 'modern-paid-btn',
            'asset_version' => '1.0.0',
            'amount_toman' => 75000,
            'amount_rial' => 750000,
            'status' => \App\Models\CanvasAssetPurchaseOrder::STATUS_PENDING,
            'redirect_back_url' => 'https://callback.test/admin/canvas-marketplace?tab=buttons',
        ]);

        // Mock gateway
        $mockGateway = new class implements \App\Services\Payment\PaymentGatewayInterface {
            public function name(): string { return 'zarinpal'; }
            public function request(int $amountRial, string $callbackUrl, string $description, ?string $orderId = null): array {
                return ['success' => true, 'redirect_url' => 'https://zarinpal.com/pay', 'authority' => 'TEST_AUTH_123'];
            }
            public function verify(string $authority, int $amountRial, ?string $orderId = null): array {
                return ['verified' => true, 'ref' => 'REF_TEST_9988'];
            }
        };
        $this->app->instance(\App\Services\Payment\PaymentGatewayInterface::class, $mockGateway);

        // Call payment callback
        $response = $this->get('/canvas-marketplace/payment-callback/' . $order->id . '?Authority=TEST_AUTH_123&Status=OK');
        $response->assertRedirect();
        $this->assertStringContainsString('payment=success', $response->headers->get('Location'));
        $this->assertStringContainsString('ref=REF_TEST_9988', $response->headers->get('Location'));

        // Verify order is paid
        $order->refresh();
        $this->assertEquals(\App\Models\CanvasAssetPurchaseOrder::STATUS_PAID, $order->status);
        $this->assertEquals('REF_TEST_9988', $order->gateway_ref);

        // Verify grant was created
        $this->assertDatabaseHas('site_asset_grants', [
            'site_id' => $site->id,
            'asset_kind' => AssetRef::KIND_BUTTON_PACK,
            'asset_key' => 'modern-paid-btn',
            'status' => SiteAssetGrant::STATUS_ACTIVE,
        ]);
    }
}
