<?php

namespace Tests\Feature\Admin;

use App\Models\CanvasAssetVersion;
use App\Models\CanvasButtonPack;
use App\Models\CanvasButtonPreset;
use App\Models\CanvasHeaderPreset;
use App\Models\CanvasIconItem;
use App\Models\CanvasIconPack;
use App\Models\Role;
use App\Models\Site;
use App\Models\SiteAssetGrant;
use App\Models\User;
use App\Services\AssetProvisioning\CanvasAssetProvisioningService;
use App\Services\AssetProvisioning\SiteAssetEntitlementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use SaaSPlatform\CoreUtils\Contracts\Services\ActivityLoggerInterface;
use SaaSPlatform\ThemeRuntime\Access\AssetRef;
use Tests\TestCase;

class CanvasAssetManagementTest extends TestCase
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

    private function admin(): User
    {
        $user = User::create([
            'mobile' => '0912' . random_int(1000000, 9999999),
            'password' => Hash::make('password'),
            'is_admin' => true,
            'is_active' => true,
        ]);

        if ($role = Role::where('slug', Role::SUPER_ADMIN)->first()) {
            $user->roles()->attach($role->id);
        }

        return $user;
    }

    private function createSite(string $domain = 'tenant-test.example.ir'): Site
    {
        $customer = \App\Models\Customer::create([
            'name' => 'مشتری تست',
            'mobile' => '0912' . random_int(1000000, 9999999),
        ]);

        return Site::create([
            'customer_id' => $customer->id,
            'title' => 'سایت تستی مشتری',
            'domain' => $domain,
            'is_active' => true,
            'business_vertical' => 'general',
        ]);
    }

    public function test_admin_can_view_canvas_assets_index(): void
    {
        $admin = $this->admin();

        $response = $this->actingAs($admin)->get(route('admin.canvas-assets.index'));
        $response->assertStatus(200);
        $response->assertViewIs('admin.canvas-assets.index');
        $response->assertSee('کتابخانه دکمه‌ها (دسته‌ای)');
        $response->assertSee('کتابخانه آیکن‌های اختصاصی (دسته‌ای)');
        $response->assertSee('قالب‌های هدر (تکی/دسته‌ای)');
        $response->assertSee('ویجت‌های موشن گرافیک (تکی)');
    }

    public function test_admin_can_create_update_and_delete_button_pack(): void
    {
        $admin = $this->admin();

        // 1. Create pack
        $storeResponse = $this->actingAs($admin)->post(route('admin.canvas-assets.button-packs.store'), [
            'title' => 'پکیج دکمه نئونی',
            'slug' => 'neon-pack',
            'category_label' => 'فانتزی',
            'description' => 'دکمه‌های نورانی و فانتزی',
            'access_type' => 'paid',
            'price' => 50000,
            'discount_price' => 35000,
            'is_active' => '1',
        ]);

        $storeResponse->assertRedirect(route('admin.canvas-assets.index', ['tab' => 'buttons']));
        $this->assertDatabaseHas('canvas_button_packs', [
            'slug' => 'neon-pack',
            'title' => 'پکیج دکمه نئونی',
            'access_type' => 'paid',
            'price' => 50000,
            'current_version' => '1.0.0',
        ]);

        // Verify initial version created in history
        $this->assertDatabaseHas('canvas_asset_versions', [
            'asset_kind' => AssetRef::KIND_BUTTON_PACK,
            'asset_slug' => 'neon-pack',
            'version' => '1.0.0',
        ]);

        $pack = CanvasButtonPack::where('slug', 'neon-pack')->firstOrFail();

        // 2. Update pack
        $updateResponse = $this->actingAs($admin)->put(route('admin.canvas-assets.button-packs.update', $pack), [
            'title' => 'پکیج دکمه نئونی پیشرفته',
            'slug' => 'neon-pack',
            'category_label' => 'مدرن',
            'access_type' => 'free',
            'price' => 0,
            'is_active' => '1',
        ]);

        $updateResponse->assertRedirect(route('admin.canvas-assets.index', ['tab' => 'buttons']));
        $this->assertSame('پکیج دکمه نئونی پیشرفته', $pack->fresh()->title);
        $this->assertSame('free', $pack->fresh()->access_type);

        // 3. Delete pack
        $deleteResponse = $this->actingAs($admin)->delete(route('admin.canvas-assets.button-packs.destroy', $pack));
        $deleteResponse->assertRedirect(route('admin.canvas-assets.index', ['tab' => 'buttons']));
        $this->assertDatabaseMissing('canvas_button_packs', ['id' => $pack->id]);
    }

    public function test_admin_can_manage_button_presets(): void
    {
        $admin = $this->admin();
        $pack = CanvasButtonPack::create([
            'title' => 'پک تست',
            'slug' => 'test-pack',
            'access_type' => 'free',
            'price' => 0,
            'current_version' => '1.0.0',
            'is_active' => true,
        ]);

        // Add preset
        $response = $this->actingAs($admin)->post(route('admin.canvas-assets.button-presets.store', $pack), [
            'title' => 'دکمه گرادیان آبی',
            'slug' => 'blue-gradient',
            'css_tokens' => json_encode([
                'padding' => '12px 24px',
                'border_radius' => '12px',
                'background' => 'linear-gradient(135deg, #3b82f6, #1d4ed8)',
                'text_color' => '#ffffff',
            ]),
        ]);

        $response->assertRedirect(route('admin.canvas-assets.index', ['tab' => 'buttons']));
        $this->assertDatabaseHas('canvas_button_presets', [
            'pack_id' => $pack->id,
            'slug' => 'blue-gradient',
            'title' => 'دکمه گرادیان آبی',
        ]);

        $preset = CanvasButtonPreset::where('slug', 'blue-gradient')->firstOrFail();

        // Delete preset
        $delResponse = $this->actingAs($admin)->delete(route('admin.canvas-assets.button-presets.destroy', $preset));
        $delResponse->assertRedirect(route('admin.canvas-assets.index', ['tab' => 'buttons']));
        $this->assertDatabaseMissing('canvas_button_presets', ['id' => $preset->id]);
    }

    public function test_admin_can_publish_new_version_with_changelog_and_semver_check(): void
    {
        $admin = $this->admin();
        $pack = CanvasButtonPack::create([
            'title' => 'پک ورژن‌دار',
            'slug' => 'ver-pack',
            'access_type' => 'paid',
            'price' => 20000,
            'current_version' => '1.0.0',
            'is_active' => true,
        ]);

        // 1. Publish valid newer version
        $response = $this->actingAs($admin)->post(route('admin.canvas-assets.button-packs.publish-version', $pack), [
            'version' => '1.1.0',
            'changelog' => 'افزودن ۳ پریست شیشه‌ای و رفع تداخل هاور',
        ]);

        $response->assertRedirect(route('admin.canvas-assets.index', ['tab' => 'buttons']));
        $this->assertSame('1.1.0', $pack->fresh()->current_version);
        $this->assertDatabaseHas('canvas_asset_versions', [
            'asset_kind' => AssetRef::KIND_BUTTON_PACK,
            'asset_slug' => 'ver-pack',
            'version' => '1.1.0',
            'changelog' => 'افزودن ۳ پریست شیشه‌ای و رفع تداخل هاور',
        ]);

        // 2. Publish invalid lower or equal version should fail
        $failResponse = $this->actingAs($admin)->post(route('admin.canvas-assets.button-packs.publish-version', $pack), [
            'version' => '1.0.5',
            'changelog' => 'نسخه عقب‌تر',
        ]);

        $failResponse->assertSessionHasErrors(['version']);
        $this->assertSame('1.1.0', $pack->fresh()->current_version);
    }

    public function test_admin_can_create_icon_pack_and_add_svg_items(): void
    {
        $admin = $this->admin();

        // Create icon pack
        $storeResponse = $this->actingAs($admin)->post(route('admin.canvas-assets.icon-packs.store'), [
            'title' => 'آیکن‌های گل و گیاه',
            'slug' => 'plants-pack',
            'category_label' => 'طبیعت',
            'access_type' => 'paid',
            'price' => 45000,
            'is_active' => '1',
        ]);

        $storeResponse->assertRedirect(route('admin.canvas-assets.index', ['tab' => 'icons']));
        $pack = CanvasIconPack::where('slug', 'plants-pack')->firstOrFail();

        // Add SVG item with malicious script attempt (should be sanitized)
        $svgWithScript = '<svg viewBox="0 0 24 24"><script>alert("xss")</script><path d="M12 2L2 22h20L12 2z"/></svg>';
        $addSvgResponse = $this->actingAs($admin)->post(route('admin.canvas-assets.icon-items.store', $pack), [
            'title' => 'مثلث طبیعی',
            'slug' => 'nature-triangle',
            'keywords' => 'طبیعت, مثلث, سبز',
            'svg_content' => $svgWithScript,
            'view_box' => '0 0 24 24',
        ]);

        $addSvgResponse->assertRedirect(route('admin.canvas-assets.index', ['tab' => 'icons']));
        $item = CanvasIconItem::where('slug', 'nature-triangle')->firstOrFail();
        $this->assertStringNotContainsString('<script>', $item->svg_content);
        $this->assertStringContainsString('<path', $item->svg_content);
    }

    public function test_admin_can_create_header_preset_and_publish_version(): void
    {
        $admin = $this->admin();

        $response = $this->actingAs($admin)->post(route('admin.canvas-assets.header-presets.store'), [
            'title' => 'هدر فلت مدرن',
            'slug' => 'header-flat-modern',
            'component_alias' => 'nav_header',
            'access_type' => 'paid',
            'price' => 75000,
            'is_active' => '1',
        ]);

        $response->assertRedirect(route('admin.canvas-assets.index', ['tab' => 'headers']));
        $header = CanvasHeaderPreset::where('slug', 'header-flat-modern')->firstOrFail();

        // Publish version 1.2.0
        $verResponse = $this->actingAs($admin)->post(route('admin.canvas-assets.header-presets.publish-version', $header), [
            'version' => '1.2.0',
            'changelog' => 'سازگاری کامل با منوی موبایلی چسبان',
        ]);

        $verResponse->assertRedirect(route('admin.canvas-assets.index', ['tab' => 'headers']));
        $this->assertSame('1.2.0', $header->fresh()->current_version);
    }

    public function test_admin_can_grant_and_revoke_assets_for_sites(): void
    {
        $admin = $this->admin();
        $site1 = $this->createSite('site1.example.ir');
        $site2 = $this->createSite('site2.example.ir');

        $pack = CanvasButtonPack::create([
            'title' => 'پک دکمه VIP',
            'slug' => 'button-vip',
            'access_type' => 'paid',
            'price' => 99000,
            'current_version' => '1.0.0',
            'is_active' => true,
        ]);

        // 1. Grant to both sites
        $grantResponse = $this->actingAs($admin)->post(route('admin.canvas-assets.grants.store'), [
            'asset_kind' => AssetRef::KIND_BUTTON_PACK,
            'asset_key' => 'button-vip',
            'site_ids' => [$site1->id, $site2->id],
            'version' => '1.0.0',
            'reference' => 'هدیه ثبت نام',
        ]);

        $grantResponse->assertRedirect();

        // Verify active records in site_asset_grants
        $this->assertDatabaseHas('site_asset_grants', [
            'site_id' => $site1->id,
            'asset_kind' => AssetRef::KIND_BUTTON_PACK,
            'asset_key' => 'button-vip',
            'status' => SiteAssetGrant::STATUS_ACTIVE,
            'granted_version' => '1.0.0',
        ]);
        $this->assertDatabaseHas('site_asset_grants', [
            'site_id' => $site2->id,
            'asset_kind' => AssetRef::KIND_BUTTON_PACK,
            'asset_key' => 'button-vip',
            'status' => SiteAssetGrant::STATUS_ACTIVE,
        ]);

        // Verify via Entitlement Service that grants are immediately active
        $entitlementService = app(SiteAssetEntitlementService::class);
        $entitlements1 = $entitlementService->forSite($site1);
        $this->assertTrue($entitlements1->hasGrant(AssetRef::KIND_BUTTON_PACK . ':button-vip'));

        $entitlements2 = $entitlementService->forSite($site2);
        $this->assertTrue($entitlements2->hasGrant(AssetRef::KIND_BUTTON_PACK . ':button-vip'));

        // 2. Revoke from site 1
        $revokeResponse = $this->actingAs($admin)->post(route('admin.canvas-assets.grants.revoke'), [
            'asset_kind' => AssetRef::KIND_BUTTON_PACK,
            'asset_key' => 'button-vip',
            'site_ids' => [$site1->id],
        ]);

        $revokeResponse->assertRedirect();

        // Site 1 should no longer have grant
        $entitlements1After = $entitlementService->forSite($site1);
        $this->assertFalse($entitlements1After->hasGrant(AssetRef::KIND_BUTTON_PACK . ':button-vip'));

        // Site 2 should still have active grant
        $entitlements2After = $entitlementService->forSite($site2);
        $this->assertTrue($entitlements2After->hasGrant(AssetRef::KIND_BUTTON_PACK . ':button-vip'));
    }
}
