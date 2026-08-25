<?php

namespace Tests\Feature\Modules\MotionGraphics;

use App\Models\Theme;
use App\Modules\MotionGraphics\DTO\DeploymentManifest;
use App\Modules\MotionGraphics\DTO\MotionAdapterContext;
use App\Modules\MotionGraphics\Models\SiteAssetPackage;
use App\Modules\MotionGraphics\Services\MotionDeploymentPreparationService;
use App\Modules\MotionGraphics\Services\MotionGraphicsAdminService;
use App\Modules\MotionGraphics\Services\MotionRuntimeInstallationService;
use App\Modules\MotionGraphics\Support\ThemeReference;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\Support\CreatesMotionTestSite;
use Tests\Support\InteractsWithMotionRuntimeInstallation;
use Tests\TestCase;

/**
 * FINAL Motion UX Closure — Step 6: the real Clinic Premium authoring test.
 *
 * Reproduces the exact 3 module:motion blocks MarketplaceThemeSeedCommand::
 * seedMedicalPremium() declares for the real, sellable Clinic Premium theme
 * (hero: medical-hero-intro, section: medical-process-flow, cta:
 * medical-cta-pulse — see app/Console/Commands/MarketplaceThemeSeedCommand.php
 * lines ~889-941) and drives the assignment end-to-end using ONLY the ids the
 * new visual browse picker (MotionGraphicsAdminService::browseLibrary(), the
 * same read path the admin browse modal calls) returns — never a
 * hand-typed/hard-coded asset id — proving the full chain: admin visual
 * selection -> theme saves a stable reference -> template installs ->
 * deployment manifest is ready -> runtime projection is unchanged from the
 * pre-picker chain already proven by ThemeMotionBridgeIntegrationTest.
 */
class ClinicPremiumVisualAuthoringTest extends TestCase
{
    use RefreshDatabase;
    use CreatesMotionTestSite;
    use InteractsWithMotionRuntimeInstallation;

    private const BLOCK_PLACEMENTS = [
        'hero' => 'medical-hero-intro',
        'section' => 'medical-process-flow',
        'cta' => 'medical-cta-pulse',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('feature-flags.motion_graphics_integration', true);
        $this->fakeActivityLogger();
        $this->registerMotionGraphicsModule();
    }

    private function createClinicPremiumTheme(): Theme
    {
        $blocks = [
            ['type' => 'hero', 'props' => ['title' => 'مراقبت پزشکی درجه یک']],
            ['type' => 'module:motion', 'props' => ['motion_asset_id' => self::BLOCK_PLACEMENTS['hero'], 'placement' => 'hero']],
            ['type' => 'features_grid', 'props' => ['title' => 'خدمات تخصصی']],
            ['type' => 'module:motion', 'props' => ['motion_asset_id' => self::BLOCK_PLACEMENTS['section'], 'placement' => 'section']],
            ['type' => 'module:motion', 'props' => ['motion_asset_id' => self::BLOCK_PLACEMENTS['cta'], 'placement' => 'cta']],
        ];

        return Theme::query()->create([
            'name' => 'کلینیک پریمیوم',
            'slug' => 'clinic-premium',
            'description' => 'قالب پزشکی پریمیوم',
            'source' => Theme::SOURCE_SEEDER,
            'status' => Theme::STATUS_PUBLISHED,
            'category' => Theme::CATEGORY_MEDICAL,
            'is_marketplace' => true,
            'header_blocks' => [],
            'footer_blocks' => [],
            'pages' => [['slug' => 'home', 'blocks' => $blocks]],
            'settings' => [
                'motion' => [
                    'package_reference' => 'theme:clinic-premium',
                    'mode' => 'bundled',
                    'required_assets' => array_map(fn ($id) => ['asset_id' => $id], array_values(self::BLOCK_PLACEMENTS)),
                ],
            ],
        ]);
    }

    private function libraryAssets(): array
    {
        return [
            ['id' => 'medical-hero-intro', 'version' => 1, 'name' => 'Medical Hero Intro', 'category' => 'medical'],
            ['id' => 'medical-process-flow', 'version' => 1, 'name' => 'Medical Process Flow', 'category' => 'medical'],
            ['id' => 'medical-cta-pulse', 'version' => 1, 'name' => 'Medical CTA Pulse', 'category' => 'medical'],
        ];
    }

    public function test_all_three_clinic_premium_blocks_are_authored_via_the_visual_picker_and_the_theme_installs(): void
    {
        $site = $this->createSite();
        $theme = $this->createClinicPremiumTheme();
        $this->actingAs($this->admin());
        $this->bindTrustedSite($site);

        // ── Step: the admin BROWSES (never types an id) ──────────────────
        $this->bindLibraryBrowse(
            array_map(fn ($a) => new \SaaSPlatform\Modules\MotionGraphics\Contracts\Read\Model\AssetReadModel(
                $a['id'], $a['version'], 'svg', $a['category'], [], 'checksum', false, $a['name']
            ), $this->libraryAssets()),
            3
        );
        $context = new MotionAdapterContext('user:1', (string) $site->id, 'corr-browse');
        $browsed = $this->app->make(MotionGraphicsAdminService::class)->browseLibrary([], 1, 24, $context);

        $this->assertCount(3, $browsed['items']);
        $browsedIds = array_map(fn ($p) => $p->assetId, $browsed['items']);
        foreach (self::BLOCK_PLACEMENTS as $expectedAssetId) {
            $this->assertContains(
                $expectedAssetId,
                $browsedIds,
                "Clinic Premium block expects {$expectedAssetId} to be visually selectable — the admin picker never surfaced it."
            );
        }

        // ── Step: the admin SELECTS each block's card — the ids used below
        // come only from $browsed['items'] above, exactly as
        // motionBrowseSelect() in assignment-fields.blade.php would place
        // them into the assignment field, never hand-typed. ─────────────
        $selectedForInstall = array_map(
            fn ($assetId) => ['asset_id' => $assetId, 'version' => 1],
            $browsedIds
        );

        $this->bindPublishedAssets($this->libraryAssets());
        // browseLibrary() above may already have resolved (and cached as
        // singletons) services that capture MotionAssetReadContract at
        // construction time — forget them so the install below picks up
        // the fake just bound, mirroring ThemeMotionBridgeIntegrationTest's
        // own established pattern for this exact issue.
        $this->app->forgetInstance(MotionRuntimeInstallationService::class);
        $this->app->forgetInstance(\App\Modules\MotionGraphics\Services\SiteAssetPackageActivationService::class);
        $this->app->forgetInstance(\App\Modules\MotionGraphics\Services\TemplateAssetPackageService::class);

        $installContext = new MotionAdapterContext('user:1', (string) $site->id, 'corr-install');
        $plan = $this->app->make(MotionRuntimeInstallationService::class)->installTemplateForSite(
            ThemeReference::for($theme),
            $selectedForInstall,
            [],
            [],
            null,
            $installContext,
        );

        // ── theme saves a stable reference; template is installable ─────
        $this->assertSame(SiteAssetPackage::STATUS_ACTIVE, $plan->sitePackage->activation_status);
        $this->assertSame('theme:clinic-premium', $plan->sitePackage->template_reference);
        $this->assertCount(3, $plan->projections);

        // ── runtime chain unchanged: same manifest lifecycle as the
        // pre-picker chain already proven by ThemeMotionBridgeIntegrationTest ──
        $service = $this->app->make(MotionDeploymentPreparationService::class);
        $manifest = $service->prepareDeployment($plan->sitePackage->fresh(['items', 'templateAssetPackage']), $installContext);
        $manifest = $service->validateDeployment($manifest, $installContext);
        $manifest = $service->approveDeployment($manifest, $installContext);
        $manifest = $service->markReady($manifest, $installContext);

        $this->assertSame(DeploymentManifest::STATUS_READY, $manifest->status);

        $runtimePackage = $manifest->toCustomerRuntimePackage();
        $runtimeAssetIds = array_map(fn ($a) => $a->assetReference->assetId, $runtimePackage->assets);
        sort($runtimeAssetIds);
        $expected = array_values(self::BLOCK_PLACEMENTS);
        sort($expected);
        $this->assertSame($expected, $runtimeAssetIds);
    }

    public function test_the_browse_endpoint_a_platform_admin_actually_hits_carries_all_three_blocks_safe_payload_only(): void
    {
        $site = $this->createSite();
        $site->features = ['motion_graphics_integration' => true];
        $site->save();

        $this->bindLibraryBrowse(
            array_map(fn ($a) => new \SaaSPlatform\Modules\MotionGraphics\Contracts\Read\Model\AssetReadModel(
                $a['id'], $a['version'], 'svg', $a['category'], [], 'checksum', false, $a['name']
            ), $this->libraryAssets()),
            3
        );

        $response = $this->actingAs($this->admin())
            ->getJson(route('admin.motion-graphics.assets.browse', $site));

        $response->assertOk();
        $response->assertJsonCount(3, 'items');
        $ids = collect($response->json('items'))->pluck('asset_id')->all();
        foreach (self::BLOCK_PLACEMENTS as $expectedAssetId) {
            $this->assertContains($expectedAssetId, $ids);
        }

        $payload = json_encode($response->json('items'));
        foreach (['source_definition', 'editable_schema', 'ai_metadata', 'prompt', 'owner_site_identifier', 'credentials'] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $payload);
        }
    }
}
