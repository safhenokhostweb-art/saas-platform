<?php

namespace Tests\Feature\AssetAccess;

use App\Models\Module;
use App\Models\ModuleLicense;
use App\Models\PlatformAssetPolicy;
use App\Models\Site;
use App\Models\SiteAssetGrant;
use App\Models\Theme;
use App\Models\User;
use App\Services\AssetProvisioning\SiteAssetEntitlementService;
use App\Services\LicenseServerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use SaaSPlatform\CoreUtils\Contracts\Services\ActivityLoggerInterface;
use SaaSPlatform\ThemeRuntime\Access\AssetAccessCatalog;
use SaaSPlatform\ThemeRuntime\Access\AssetAccessGate;
use SaaSPlatform\ThemeRuntime\Access\SiteAssetEntitlements;
use Tests\Support\CreatesMotionTestSite;
use Tests\TestCase;

/**
 * ADR-087 end to end on the platform: the real licence → module
 * projection → provisioning API → tenant-side decision, the central
 * panel's writes, tenant isolation, and the platform canvas gate. No
 * fakes except the activity logger, which records what was audited.
 */
class AssetProvisioningTest extends TestCase
{
    use RefreshDatabase;
    use CreatesMotionTestSite;

    /** @var list<array{0: string, 1: array}> */
    private array $audited = [];

    protected function setUp(): void
    {
        parent::setUp();
        AssetAccessCatalog::flush();
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
            'password' => Hash::make('password'), 'is_admin' => true, 'is_active' => true,
        ]);
        if ($role = \App\Models\Role::where('slug', \App\Models\Role::SUPER_ADMIN)->first()) {
            $user->roles()->attach($role->id);
        }

        return $user;
    }

    private function licensedSite(string $domain = 'solar.test'): array
    {
        $site = $this->createSite('Solar ' . $domain);
        $key = $this->app->make(LicenseServerService::class)->issue($site, $domain);

        return [$site, $key, $domain];
    }

    private function fetch(string $key, string $domain): array
    {
        return $this->withToken($key)->getJson('/api/asset-entitlements?domain=' . $domain)->assertOk()->json();
    }

    /** The real path a licence key takes into the projection: licence row + active pivot. */
    private function licenseKit(Site $site): void
    {
        $module = Module::query()->where('slug', 'solar_industry_kit')->firstOrFail();
        ModuleLicense::query()->create([
            'site_id' => $site->id, 'module_id' => $module->id, 'license_type' => ModuleLicense::TYPE_ONE_TIME,
            'purchased_at' => now(), 'status' => ModuleLicense::STATUS_ACTIVE,
        ]);
        $site->modules()->attach($module->id, ['is_active' => true]);
    }

    public function test_the_migration_registers_the_kit_as_a_licence_only_key(): void
    {
        $module = Module::query()->where('slug', 'solar_industry_kit')->firstOrFail();

        $this->assertTrue($module->isLicenseOnly());
        $this->assertFalse((bool) $module->marketplace_visible);
    }

    public function test_the_api_requires_a_licence(): void
    {
        $this->getJson('/api/asset-entitlements?domain=solar.test')->assertStatus(401);
        $this->withToken('not-a-real-key-000000')->getJson('/api/asset-entitlements?domain=solar.test')->assertStatus(403);
    }

    public function test_a_plain_site_is_provisioned_public_assets_only(): void
    {
        [, $key, $domain] = $this->licensedSite();
        $payload = $this->fetch($key, $domain);

        $this->assertTrue($payload['available']);
        $this->assertSame(1, $payload['schema']);
        $this->assertNull($payload['vertical']);
        $this->assertSame([], $payload['grants']);
        $site = SiteAssetEntitlements::fromPayload($payload);
        $this->assertNotNull($site);
        $cards = AssetAccessGate::annotateTemplateCards(\SaaSPlatform\ThemeRuntime\TemplateLibrary::list(), $site);
        $this->assertNotContains('lib:solar-header', array_column($cards, 'id'));
        $this->assertContains('lib:header-glass', array_column($cards, 'id'));
    }

    public function test_vertical_plus_licensed_kit_unlocks_the_solar_family_end_to_end(): void
    {
        [$site, $key, $domain] = $this->licensedSite();
        $service = $this->app->make(SiteAssetEntitlementService::class);

        $service->setVertical($site, 'solar_energy');
        $teaser = SiteAssetEntitlements::fromPayload($this->fetch($key, $domain));
        $this->assertSame('locked', AssetAccessGate::decideTemplate('lib:solar-header', $teaser)->state);

        $this->licenseKit($site);
        $payload = $this->fetch($key, $domain);
        $this->assertSame('solar_energy', $payload['vertical']);
        $this->assertContains('solar_industry_kit', $payload['entitlement_keys']);
        $this->assertTrue(AssetAccessGate::decideTemplate('lib:solar-header', SiteAssetEntitlements::fromPayload($payload))->allowed());
        $this->assertTrue(AssetAccessGate::decideTemplate('lib:solar-page-home', SiteAssetEntitlements::fromPayload($payload))->allowed());
    }

    public function test_panel_grant_and_revoke_reach_only_that_site_and_are_audited(): void
    {
        [$siteA, $keyA, $domainA] = $this->licensedSite('a.test');
        [, $keyB, $domainB] = $this->licensedSite('b.test');
        $this->actingAs($admin = $this->admin());

        $this->post(route('admin.sites.asset-access.grant', $siteA), ['asset' => 'template:solar-footer', 'source' => 'purchase', 'reference' => 'ORD-7'])
            ->assertRedirect()->assertSessionHas('success');

        $this->assertSame(['template:solar-footer'], $this->fetch($keyA, $domainA)['grants']);
        $this->assertSame([], $this->fetch($keyB, $domainB)['grants'], 'a grant never leaks to another tenant');
        $grant = SiteAssetGrant::query()->sole();
        $this->assertSame([SiteAssetGrant::SOURCE_PURCHASE, 'ORD-7', $admin->id], [$grant->source, $grant->reference, $grant->granted_by]);

        $this->post(route('admin.sites.asset-access.revoke', $siteA), ['asset' => 'template:solar-footer'])->assertSessionHas('success');
        $this->assertSame([], $this->fetch($keyA, $domainA)['grants']);
        $this->assertSame(SiteAssetGrant::STATUS_REVOKED, $grant->fresh()->status);
        $this->assertSame($admin->id, $grant->fresh()->revoked_by);

        // Re-granting reactivates the same row.
        $this->post(route('admin.sites.asset-access.grant', $siteA), ['asset' => 'template:solar-footer', 'source' => 'admin_grant']);
        $this->assertSame(1, SiteAssetGrant::query()->count());
        $this->assertTrue($grant->fresh()->isActive());

        $events = array_column($this->audited, 0);
        $this->assertSame(2, count(array_keys($events, SiteAssetEntitlementService::EVENT_GRANTED, true)));
        $this->assertContains(SiteAssetEntitlementService::EVENT_REVOKED, $events);
    }

    public function test_the_panel_refuses_nonsense(): void
    {
        [$site] = $this->licensedSite();
        $this->actingAs($this->admin());

        $this->post(route('admin.sites.asset-access.grant', $site), ['asset' => 'template:header-glass', 'source' => 'admin_grant'])->assertSessionHas('error');
        $this->post(route('admin.sites.asset-access.grant', $site), ['asset' => 'template:no-such', 'source' => 'admin_grant'])->assertSessionHas('error');
        $this->post(route('admin.sites.asset-access.grant', $site), ['asset' => 'motion:x', 'source' => 'admin_grant'])->assertSessionHas('error');
        $this->post(route('admin.sites.asset-access.revoke', $site), ['asset' => 'template:solar-header'])->assertSessionHas('error');
        $this->post(route('admin.sites.asset-access.vertical', $site), ['business_vertical' => 'mining'])->assertSessionHas('error');
        $this->assertSame(0, SiteAssetGrant::query()->count());
        $this->assertNull($site->fresh()->business_vertical);

        $this->post(route('admin.sites.asset-access.vertical', $site), ['business_vertical' => 'solar_energy'])->assertSessionHas('success');
        $this->assertSame('solar_energy', $site->fresh()->business_vertical);
    }

    public function test_an_override_closes_or_opens_one_asset_for_every_site_without_a_release(): void
    {
        [, $key, $domain] = $this->licensedSite();
        $this->actingAs($this->admin());

        $this->post(route('admin.asset-access.overrides.save'), [
            'asset' => 'template:header-glass', 'visibility' => 'restricted', 'entitlement_keys' => 'premium_headers, vip',
            'locked_display' => 'teaser', 'note' => 'campaign',
        ])->assertSessionHas('success');
        $row = PlatformAssetPolicy::query()->sole();
        $this->assertSame(['premium_headers', 'vip'], $row->entitlement_keys);

        $site = SiteAssetEntitlements::fromPayload($this->fetch($key, $domain));
        $this->assertSame('locked', AssetAccessGate::decideTemplate('lib:header-glass', $site)->state);

        $this->post(route('admin.asset-access.overrides.save'), ['asset' => 'template:solar-header', 'visibility' => 'public', 'locked_display' => 'hidden']);
        $site = SiteAssetEntitlements::fromPayload($this->fetch($key, $domain));
        $this->assertTrue(AssetAccessGate::decideTemplate('lib:solar-header', $site)->allowed());

        $this->post(route('admin.asset-access.overrides.clear'), ['asset' => 'template:header-glass'])->assertSessionHas('success');
        $site = SiteAssetEntitlements::fromPayload($this->fetch($key, $domain));
        $this->assertTrue(AssetAccessGate::decideTemplate('lib:header-glass', $site)->allowed());
        $this->assertContains(SiteAssetEntitlementService::EVENT_OVERRIDE_CLEARED, array_column($this->audited, 0));
    }

    public function test_the_panel_pages_render(): void
    {
        [$site] = $this->licensedSite();
        $this->actingAs($this->admin());

        $this->get(route('admin.asset-access.index'))->assertOk()->assertSee('template:solar-header')->assertSee('کیت صنعت برق و انرژی خورشیدی');
        $this->get(route('admin.sites.asset-access', $site))->assertOk()
            ->assertSee('site-asset-row-template:solar-header', false)
            ->assertSee('حوزهٔ کسب‌وکار سایت مطابق نیست');
    }

    private function theme(?int $siteId): Theme
    {
        return Theme::create([
            'name' => 'Access', 'slug' => 'access-' . uniqid(), 'site_id' => $siteId,
            'type' => Theme::TYPE_FULL, 'source' => Theme::SOURCE_SEEDER,
            'status' => Theme::STATUS_DRAFT, 'category' => Theme::CATEGORY_SAAS,
            'header_blocks' => [], 'footer_blocks' => [],
            'pages' => ['home' => ['title' => 'Home', 'slug' => '', 'blocks' => []]],
            'settings' => [],
        ]);
    }

    public function test_the_platform_canvas_gates_a_site_owned_theme_and_badges_the_global_catalog(): void
    {
        [$site] = $this->licensedSite();
        $this->actingAs($this->admin());

        $global = $this->theme(null);
        $cards = collect($this->getJson(route('admin.theme-builder.canvas.templates', $global) . '?type=header')->assertOk()->json('templates'))->keyBy('id');
        $this->assertTrue($cards->has('lib:solar-header'));
        $this->assertSame('platform_designer', $cards['lib:solar-header']['access']['reason']);
        $this->assertSame('اختصاصی لایسنس', $cards['lib:solar-header']['access']['badge']);
        $this->assertFalse($cards['lib:header-glass']['access']['restricted']);
        $this->assertNull($cards['lib:header-glass']['access']['badge']);
        $this->postJson(route('admin.theme-builder.canvas.insert-template', $global), ['template_id' => 'lib:solar-header'])->assertOk();

        $owned = $this->theme($site->id);
        $ids = array_column($this->getJson(route('admin.theme-builder.canvas.templates', $owned))->json('templates'), 'id');
        $this->assertNotContains('lib:solar-header', $ids);
        $this->postJson(route('admin.theme-builder.canvas.insert-template', $owned), ['template_id' => 'lib:solar-header'])
            ->assertStatus(403)->assertJsonPath('success', false);

        $this->app->make(SiteAssetEntitlementService::class)->grant($site, 'template:solar-header');
        $this->postJson(route('admin.theme-builder.canvas.insert-template', $owned), ['template_id' => 'lib:solar-header'])->assertOk()->assertJsonPath('zone', 'header');
    }
}
