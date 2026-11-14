<?php

namespace Tests\Feature\Modules\MotionGraphics\Library;

use App\Models\Site;
use App\Models\User;
use App\Modules\MotionGraphics\Exceptions\MotionAdapterFailClosedException;
use App\Modules\MotionGraphics\Models\MotionSiteAssetGrant;
use App\Modules\MotionGraphics\Models\SiteAssetPackage;
use App\Modules\MotionGraphics\Models\SiteAssetPackageItem;
use App\Modules\MotionGraphics\Services\MotionCatalogAdminService;
use App\Modules\MotionGraphics\Services\MotionLibraryCatalogService;
use App\Modules\MotionGraphics\Support\MotionPrivateAssetUrl;
use App\Services\LicenseServerService;
use Database\Seeders\MotionGraphicsDemoSiteSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use SaaSPlatform\Modules\MotionGraphics\Models\MotionGraphic;
use Tests\Support\CreatesMotionTestSite;
use Tests\Support\InteractsWithMotionRuntimeInstallation;
use Tests\TestCase;

/**
 * ADR-086 end to end, no fakes in the motion chain. The real library
 * catalog (hero-motion free/public, business-growth-motion premium/public,
 * solar-motion-graphic premium/PRIVATE) is synced, real sites are
 * provisioned through the demo provisioner (which grants the premium
 * licence), and every assertion goes through /api/motion-runtime/manifest
 * and the real file routes.
 *
 * - A private motion never reaches a site that was not granted it, not even
 *   one holding the `motion-graphics-premium` licence, and its file has no
 *   public URL.
 * - An admin grant in the central panel delivers it to exactly that site,
 *   signed for that site, under its own category, with its version label.
 * - Revoking the grant, or switching the motion off, removes it on the next
 *   fetch; switching it back on restores it.
 */
final class PrivateMotionLicensingTest extends TestCase
{
    use RefreshDatabase;
    use CreatesMotionTestSite;
    use InteractsWithMotionRuntimeInstallation;

    private const SOLAR = 'solar-motion-graphic';
    private const SIGNED = '#^/motion/private/solar-motion-graphic/v1/(\d+)/(\d+)/([a-f0-9]{64})/animation\.svg$#';

    protected function setUp(): void
    {
        parent::setUp();
        $this->fakeActivityLogger();
    }

    private function admin(): User
    {
        return User::factory()->create(['is_admin' => true, 'site_id' => null]);
    }

    private function provision(Site $site, User $admin): void
    {
        $this->app->make(MotionGraphicsDemoSiteSeeder::class)->provision($site->fresh(), $admin, true);
    }

    private function fetchManifest(Site $site): TestResponse
    {
        $domain = 'site'.$site->id.'.test';
        $key = $this->app->make(LicenseServerService::class)->issue($site, $domain);

        return $this->withToken($key)->getJson('/api/motion-runtime/manifest?domain='.$domain);
    }

    private function uuid(string $slug): string
    {
        return (string) MotionGraphic::query()->where('slug', $slug)->value('uuid');
    }

    /** @return array<string, mixed>|null */
    private function entry(TestResponse $manifest, string $slug): ?array
    {
        foreach ($manifest->json('assets') ?? [] as $asset) {
            if ($asset['asset_id'] === $this->uuid($slug)) {
                return $asset;
            }
        }

        return null;
    }

    /** @return list<string> slugs delivered by the manifest, in manifest order */
    private function orderedSlugs(TestResponse $manifest): array
    {
        $bySlug = MotionGraphic::query()->pluck('slug', 'uuid')->all();

        return array_map(fn (array $a): string => $bySlug[$a['asset_id']] ?? $a['asset_id'], $manifest->json('assets') ?? []);
    }

    /**
     * Sorted: package items come in index order of their uuid, which is
     * random, so only the set is meaningful (see orderedSlugs for order).
     *
     * @return list<string>
     */
    private function slugs(TestResponse $manifest): array
    {
        $slugs = $this->orderedSlugs($manifest);
        sort($slugs);

        return $slugs;
    }

    private function grantViaPanel(User $admin, Site $site, string $slug = self::SOLAR): TestResponse
    {
        $motion = MotionGraphic::query()->where('slug', $slug)->firstOrFail();

        return $this->actingAs($admin)->post(route('admin.motion-graphics.catalog.grants.store', $motion), [
            'site_id' => $site->id,
            'source' => MotionSiteAssetGrant::SOURCE_PURCHASE,
            'reference' => 'INV-1405-0042',
        ]);
    }

    private function toggleViaPanel(User $admin, string $slug, bool $enabled): TestResponse
    {
        $motion = MotionGraphic::query()->where('slug', $slug)->firstOrFail();

        return $this->actingAs($admin)->post(route('admin.motion-graphics.catalog.availability', $motion), ['enabled' => $enabled ? 1 : 0]);
    }

    public function test_a_private_motion_never_reaches_an_ungranted_site_even_with_the_premium_licence(): void
    {
        $site = $this->createSite('A');
        $this->provision($site, $this->admin());

        $manifest = $this->fetchManifest($site)->assertOk()->assertJsonPath('available', true);

        $this->assertSame(['business-growth-motion', 'hero-motion'], $this->slugs($manifest));
        $this->assertNull($this->entry($manifest, self::SOLAR));
        // Not even its id leaks through `locked_assets`.
        $this->assertNotContains($this->uuid(self::SOLAR), array_column($manifest->json('locked_assets'), 'asset_id'));
        $this->assertStringNotContainsString(self::SOLAR, $manifest->getContent());
        // Activation did not bind it either.
        $this->assertFalse(SiteAssetPackageItem::query()->where('asset_id', $this->uuid(self::SOLAR))->exists());

        // No public file, and the unsigned private base is not a route.
        $this->get('/motion/solar-motion-graphic/v1/animation.svg')->assertNotFound();
        $this->get(MotionPrivateAssetUrl::baseReference(self::SOLAR, 'v1'))->assertNotFound();
        // A correctly signed URL is still refused while the site holds no grant.
        $forged = (new MotionPrivateAssetUrl())->sign(MotionPrivateAssetUrl::baseReference(self::SOLAR, 'v1'), (int) $site->id);
        $this->get($forged)->assertNotFound();
    }

    public function test_an_admin_grant_delivers_it_to_that_site_only_signed_categorised_and_versioned(): void
    {
        $admin = $this->admin();
        $siteA = $this->createSite('A');
        $siteB = $this->createSite('B');
        $this->provision($siteA, $admin);
        $this->provision($siteB, $admin);

        $this->grantViaPanel($admin, $siteA)->assertRedirect(route('admin.motion-graphics.catalog.index'))->assertSessionHas('success');
        $this->assertDatabaseHas('motion_site_asset_grants', [
            'site_id' => $siteA->id, 'asset_id' => $this->uuid(self::SOLAR), 'status' => 'active',
            'source' => 'purchase', 'reference' => 'INV-1405-0042', 'granted_by_actor_identifier' => 'user:'.$admin->id,
        ]);

        // Delivered on the next fetch, without re-activating the package.
        $manifest = $this->fetchManifest($siteA)->assertOk();
        $this->assertSame(['business-growth-motion', 'hero-motion', self::SOLAR], $this->slugs($manifest));
        // Appended after the package items, so existing entries keep their positions.
        $this->assertSame(self::SOLAR, $this->orderedSlugs($manifest)[2]);
        $entry = $this->entry($manifest, self::SOLAR);
        $this->assertSame('موشن نیروگاه خورشیدی', $entry['name']);
        $this->assertSame('solar-motion', $entry['category']);
        $this->assertSame('موشن صنعت برق و انرژی', $entry['category_label']);
        $this->assertSame(1, $entry['published_version']);
        $this->assertSame('1.0.0', $entry['version_label']);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{12}$/', $entry['build_id']);
        $this->assertContains('title', array_column($entry['parameters'], 'key'));
        $this->assertMatchesRegularExpression(self::SIGNED, $entry['optimized_reference']);
        preg_match(self::SIGNED, $entry['optimized_reference'], $m);
        $this->assertSame((string) $siteA->id, $m[1]);

        $file = $this->get($entry['optimized_reference'].'?title='.rawurlencode('سولار پارس'));
        $file->assertOk();
        $this->assertStringStartsWith('image/svg+xml', $file->headers->get('Content-Type'));
        $this->assertStringContainsString('private', $file->headers->get('Cache-Control'));
        $this->assertStringContainsString('سولار پارس', $file->getContent());

        // Site B, also premium-licensed, still does not get it, and cannot
        // re-scope A's URL to itself.
        $this->assertNull($this->entry($this->fetchManifest($siteB)->assertOk(), self::SOLAR));
        $this->get(str_replace('/v1/'.$siteA->id.'/', '/v1/'.$siteB->id.'/', $entry['optimized_reference']))->assertNotFound();

        // Re-activating site A now binds it like any other package item.
        $this->provision($siteA, $admin);
        $this->assertTrue(SiteAssetPackageItem::query()
            ->where('asset_id', $this->uuid(self::SOLAR))
            ->whereIn('site_asset_package_id', SiteAssetPackage::query()->where('site_id', $siteA->id)->pluck('id'))
            ->where('is_active', true)->exists());
        $this->assertNotNull($this->entry($this->fetchManifest($siteA)->assertOk(), self::SOLAR));
    }

    public function test_revoking_the_grant_removes_it_on_the_next_fetch_and_closes_the_url_at_once(): void
    {
        $admin = $this->admin();
        $site = $this->createSite('A');
        $this->provision($site, $admin);
        $this->grantViaPanel($admin, $site);
        $this->provision($site, $admin); // bound as a package item too

        $url = $this->entry($this->fetchManifest($site)->assertOk(), self::SOLAR)['optimized_reference'];
        $this->get($url)->assertOk();

        $grant = MotionSiteAssetGrant::query()->where('site_id', $site->id)->firstOrFail();
        $this->actingAs($admin)->post(route('admin.motion-graphics.catalog.grants.revoke', $grant))->assertSessionHas('success');
        $this->assertSame('revoked', $grant->fresh()->status);

        $manifest = $this->fetchManifest($site)->assertOk()->assertJsonPath('available', true);
        $this->assertSame(['business-growth-motion', 'hero-motion'], $this->slugs($manifest));
        // The bound item is withheld without being reported as "premium".
        $this->assertNotContains($this->uuid(self::SOLAR), array_column($manifest->json('locked_assets'), 'asset_id'));
        $this->get($url)->assertNotFound();

        // Granting again restores it.
        $this->grantViaPanel($admin, $site);
        $this->assertNotNull($this->entry($this->fetchManifest($site)->assertOk(), self::SOLAR));
        $this->get($url)->assertOk();
    }

    public function test_the_admin_switch_hides_a_motion_from_every_manifest_and_restores_it(): void
    {
        $admin = $this->admin();
        $siteA = $this->createSite('A');
        $siteB = $this->createSite('B');
        $this->provision($siteA, $admin);
        $this->provision($siteB, $admin);
        $this->grantViaPanel($admin, $siteA);

        $this->toggleViaPanel($admin, 'hero-motion', false)->assertSessionHas('success');
        $hero = MotionGraphic::query()->where('slug', 'hero-motion')->firstOrFail();
        $this->assertFalse($hero->isRuntimeEnabled());
        $this->assertNotNull($hero->runtime_disabled_at);
        $this->assertSame('user:'.$admin->id, $hero->runtime_disabled_by_actor_identifier);

        // Gone from both sites at once; the rest of each manifest is intact
        // and nothing reports it as locked.
        $a = $this->fetchManifest($siteA)->assertOk()->assertJsonPath('available', true);
        $this->assertSame(['business-growth-motion', self::SOLAR], $this->slugs($a));
        $this->assertSame([], $a->json('locked_assets'));
        $this->assertSame(['business-growth-motion'], $this->slugs($this->fetchManifest($siteB)->assertOk()));

        // A sync (deploy, seeder) never re-enables it.
        $this->app->make(MotionLibraryCatalogService::class)->sync();
        $this->assertFalse($hero->fresh()->isRuntimeEnabled());

        // A private motion obeys the switch too, grant or not.
        $this->toggleViaPanel($admin, self::SOLAR, false);
        $this->assertSame(['business-growth-motion'], $this->slugs($this->fetchManifest($siteA)->assertOk()));

        // Switching back on restores exactly what each site had.
        $this->toggleViaPanel($admin, 'hero-motion', true);
        $this->toggleViaPanel($admin, self::SOLAR, true);
        $this->assertSame(['business-growth-motion', 'hero-motion', self::SOLAR], $this->slugs($this->fetchManifest($siteA)->assertOk()));
        $this->assertSame(['business-growth-motion', 'hero-motion'], $this->slugs($this->fetchManifest($siteB)->assertOk()));
        $this->assertNull($hero->fresh()->runtime_disabled_at);
    }

    public function test_disabling_every_motion_of_a_site_leaves_an_empty_but_valid_manifest(): void
    {
        $admin = $this->admin();
        $site = $this->createSite('A');
        $this->provision($site, $admin);

        $this->toggleViaPanel($admin, 'hero-motion', false);
        $this->toggleViaPanel($admin, 'business-growth-motion', false);

        $this->fetchManifest($site)->assertOk()->assertJsonPath('available', true)->assertJsonCount(0, 'assets');
    }

    public function test_a_public_library_motion_reaches_a_site_whose_package_predates_it(): void
    {
        $admin = $this->admin();
        $site = $this->createSite('A');
        $this->provision($site, $admin);

        // Simulate a package activated before business-growth-motion existed.
        SiteAssetPackageItem::query()->where('asset_id', $this->uuid('business-growth-motion'))->delete();

        $manifest = $this->fetchManifest($site)->assertOk();
        $this->assertSame(['business-growth-motion', 'hero-motion'], $this->slugs($manifest));
        $this->assertSame('v2', $this->entry($manifest, 'business-growth-motion')['version_label']);
    }

    public function test_every_manifest_entry_carries_its_version_label_and_build_id(): void
    {
        $site = $this->createSite('A');
        $this->provision($site, $this->admin());

        foreach ($this->fetchManifest($site)->assertOk()->json('assets') as $asset) {
            $motion = MotionGraphic::query()->where('uuid', $asset['asset_id'])->firstOrFail();
            $version = $motion->versions()->where('version', $asset['published_version'])->firstOrFail();

            $this->assertSame($version->versionLabel(), $asset['version_label']);
            $this->assertSame(substr($version->compiled_assets[0]['sha256'], 0, 12), $asset['build_id']);
            $this->assertSame($motion->category->name, $asset['category_label']);
        }
    }

    public function test_grants_are_only_for_published_private_global_motions(): void
    {
        $this->app->make(MotionLibraryCatalogService::class)->sync();
        $site = $this->createSite('A');
        $service = $this->app->make(MotionCatalogAdminService::class);

        foreach (['hero-motion', 'business-growth-motion'] as $public) {
            try {
                $service->grant(MotionGraphic::query()->where('slug', $public)->firstOrFail(), $site, 'admin_grant', null, null, 'user:1');
                $this->fail("A public motion [{$public}] must not be grantable.");
            } catch (MotionAdapterFailClosedException $e) {
                $this->assertSame('grant_not_applicable', $e->failureCode);
            }
        }

        $solar = MotionGraphic::query()->where('slug', self::SOLAR)->firstOrFail();
        try {
            $service->grant($solar, $site, 'gift', null, null, 'user:1');
            $this->fail('An unknown grant source must be refused.');
        } catch (MotionAdapterFailClosedException $e) {
            $this->assertSame('grant_source_invalid', $e->failureCode);
        }

        // Granting twice keeps one row and re-activates it.
        $first = $service->grant($solar, $site, 'admin_grant', null, null, 'user:1');
        $service->revoke($first, 'user:1');
        $again = $service->grant($solar, $site, 'license', 'LIC-7', null, 'user:2');
        $this->assertSame($first->id, $again->id);
        $this->assertSame('active', $again->status);
        $this->assertSame('license', $again->source);
        $this->assertNull($again->revoked_at);
        $this->assertSame(1, MotionSiteAssetGrant::query()->count());
    }

    public function test_the_catalog_page_shows_visibility_version_and_state_to_admins_only(): void
    {
        $admin = $this->admin();
        $site = $this->createSite('A');
        $this->provision($site, $admin);
        $this->grantViaPanel($admin, $site);
        $this->toggleViaPanel($admin, 'business-growth-motion', false);

        $page = $this->actingAs($admin)->get(route('admin.motion-graphics.catalog.index'))->assertOk();
        $page->assertSee('data-testid="motion-catalog-row-solar-motion-graphic"', false);
        $page->assertSeeInOrder(['data-testid="motion-visibility-solar-motion-graphic"', 'خصوصی'], false);
        $page->assertSeeInOrder(['data-testid="motion-visibility-hero-motion"', 'عمومی'], false);
        $page->assertSeeInOrder(['data-testid="motion-version-solar-motion-graphic"', '1.0.0'], false);
        $page->assertSeeInOrder(['data-testid="motion-version-hero-motion"', 'v2'], false);
        $page->assertSeeInOrder(['data-testid="motion-state-business-growth-motion"', 'غیرفعال'], false);
        $page->assertSee('data-testid="motion-grant-solar-motion-graphic-'.$site->id.'"', false);

        $customer = User::factory()->create(['is_admin' => false, 'site_id' => $site->id]);
        $this->actingAs($customer)->get(route('admin.motion-graphics.catalog.index'))->assertStatus(403);
        $solar = MotionGraphic::query()->where('slug', self::SOLAR)->firstOrFail();
        $this->actingAs($customer)->post(route('admin.motion-graphics.catalog.availability', $solar), ['enabled' => 0])->assertStatus(403);
        $this->assertTrue($solar->fresh()->isRuntimeEnabled());
    }
}
