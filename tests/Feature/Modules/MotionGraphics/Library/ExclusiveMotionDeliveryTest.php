<?php

namespace Tests\Feature\Modules\MotionGraphics\Library;

use App\Models\Module;
use App\Models\ModuleLicense;
use App\Models\Site;
use App\Models\User;
use App\Modules\MotionGraphics\Models\MotionBespokeAssignment;
use App\Modules\MotionGraphics\Models\MotionBespokeCommission;
use App\Modules\MotionGraphics\Models\MotionBespokePurchase;
use App\Modules\MotionGraphics\Models\SiteAssetPackageItem;
use App\Modules\MotionGraphics\Services\MotionCustomerRuntimeService;
use App\Modules\MotionGraphics\Services\MotionLibraryCatalogService;
use App\Modules\MotionGraphics\Support\MotionAssetLicensePolicy;
use App\Modules\MotionGraphics\Support\MotionPrivateAssetUrl;
use App\Modules\MotionGraphics\Support\RealMotionLicenseGateway;
use App\Services\LicenseServerService;
use App\Services\ModuleLicenseService;
use Database\Seeders\ModuleSeeder;
use Database\Seeders\MotionGraphicsDemoSiteSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Testing\TestResponse;
use RuntimeException;
use SaaSPlatform\Modules\MotionGraphics\Models\MotionGraphic;
use Tests\Support\CreatesMotionTestSite;
use Tests\Support\InteractsWithMotionRuntimeInstallation;
use Tests\TestCase;

/**
 * ADR-081 end to end, no fakes in the motion chain: an exclusive library
 * motion declared in metadata.json for site A reaches site A's
 * /api/motion-runtime/manifest (package path and bespoke path) with a
 * site-scoped signed URL, and site B can neither see it in its manifest nor
 * fetch its file. The public catalog (hero-motion, business-growth-motion)
 * stays byte-for-byte as before.
 */
final class ExclusiveMotionDeliveryTest extends TestCase
{
    use RefreshDatabase;
    use CreatesMotionTestSite;
    use InteractsWithMotionRuntimeInstallation;

    private const SLUG = 'site-a-exclusive-motion';
    private const NAME = 'موشن اختصاصی سایت الف';
    private const SIGNED = '#^/motion/private/site-a-exclusive-motion/v1/(\d+)/(\d+)/([a-f0-9]{64})/animation\.svg$#';

    private string $library;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fakeActivityLogger();
        $this->library = storage_path('framework/testing/exclusive-motion-'.bin2hex(random_bytes(6)));
        config([
            'motion-library.exclusive_library_path' => $this->library,
            'motion-library.exclusive_assets' => [self::SLUG => 'v1'],
        ]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->library);
        parent::tearDown();
    }

    /** Writes the exclusive library version: hero-motion v2's real files, owned by $owner. */
    private function writeExclusive(?int $ownerSiteId, string $version = 'v1', string $extraElement = ''): void
    {
        $source = MotionLibraryCatalogService::libraryDirectory('hero-motion', 'v2');
        $metadata = json_decode((string) file_get_contents($source.'/metadata.json'), true);
        $metadata['slug'] = self::SLUG;
        $metadata['name'] = self::NAME;
        $metadata['license_tier'] = 'free';
        $metadata['ownership'] = $ownerSiteId === null
            ? ['type' => 'platform', 'license' => 'internal', 'customer_restriction' => false]
            : ['type' => 'customer_specific', 'site_id' => $ownerSiteId, 'customer_restriction' => true];

        $dir = $this->library.'/'.self::SLUG.'/'.$version;
        File::ensureDirectoryExists($dir);
        file_put_contents($dir.'/metadata.json', json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        $svg = (string) file_get_contents($source.'/animation.svg');
        $close = strrpos($svg, '</svg>');
        file_put_contents($dir.'/animation.svg', substr($svg, 0, $close).$extraElement.substr($svg, $close));
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

    private function exclusiveUuid(): string
    {
        return (string) MotionGraphic::query()->where('slug', self::SLUG)->value('uuid');
    }

    /** @return array<string, mixed>|null */
    private function exclusiveEntry(TestResponse $manifest): ?array
    {
        foreach ($manifest->json('assets') ?? [] as $asset) {
            if ($asset['asset_id'] === $this->exclusiveUuid()) {
                return $asset;
            }
        }

        return null;
    }

    public function test_the_owner_site_receives_its_exclusive_motion_with_full_fields_and_a_signed_url(): void
    {
        $siteA = $this->createSite('A');
        $this->writeExclusive($siteA->id);
        $this->provision($siteA, $this->admin());

        $manifest = $this->fetchManifest($siteA)->assertOk()->assertJsonPath('available', true)->assertJsonCount(3, 'assets');

        // The public entries keep their unsigned public references.
        $references = array_column($manifest->json('assets'), 'optimized_reference');
        $this->assertContains('/motion/hero-motion/v2/animation.svg', $references);
        $this->assertContains('/motion/business-growth-motion/v2/animation.svg', $references);

        $entry = $this->exclusiveEntry($manifest);
        $this->assertNotNull($entry);
        $this->assertSame(self::NAME, $entry['name']);
        $this->assertSame('hero-motion', $entry['category']);
        $this->assertContains('window_title', array_column($entry['parameters'], 'key'));
        $this->assertMatchesRegularExpression(self::SIGNED, $entry['optimized_reference']);
        preg_match(self::SIGNED, $entry['optimized_reference'], $m);
        $this->assertSame((string) $siteA->id, $m[1]);
        foreach (['ownership', 'owner_site_identifier', 'source_definition', 'storage_path'] as $forbidden) {
            $this->assertArrayNotHasKey($forbidden, $entry);
        }

        $this->assertSame(
            MotionGraphic::OWNERSHIP_CUSTOMER_SPECIFIC,
            MotionGraphic::query()->where('slug', self::SLUG)->value('ownership_type')
        );
    }

    public function test_another_site_never_sees_the_exclusive_motion_in_its_manifest(): void
    {
        $siteA = $this->createSite('A');
        $siteB = $this->createSite('B');
        $this->writeExclusive($siteA->id);
        $admin = $this->admin();
        $this->provision($siteA, $admin);
        $this->provision($siteB, $admin);

        $manifest = $this->fetchManifest($siteB)->assertOk()->assertJsonPath('available', true)->assertJsonCount(2, 'assets');
        $this->assertNull($this->exclusiveEntry($manifest));
        $this->assertStringNotContainsString(self::SLUG, (string) $manifest->getContent());
        $this->assertStringNotContainsString('/motion/private/', (string) $manifest->getContent());

        $bPackageItems = SiteAssetPackageItem::query()
            ->whereHas('siteAssetPackage', fn ($q) => $q->where('site_id', $siteB->id))
            ->pluck('asset_id')->all();
        $this->assertNotContains($this->exclusiveUuid(), $bPackageItems, 'activation must never install another site\'s exclusive asset');
    }

    public function test_the_signed_url_serves_only_the_owner_site(): void
    {
        $siteA = $this->createSite('A');
        $siteB = $this->createSite('B');
        $this->writeExclusive($siteA->id);
        $admin = $this->admin();
        $this->provision($siteA, $admin);
        $this->provision($siteB, $admin);

        $url = $this->exclusiveEntry($this->fetchManifest($siteA))['optimized_reference'];
        preg_match(self::SIGNED, $url, $m);
        [, $site, $expires, $signature] = $m;

        $ok = $this->get($url)->assertOk()
            ->assertHeader('Content-Type', 'image/svg+xml; charset=utf-8')
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        $this->assertStringContainsString('private', (string) $ok->headers->get('Cache-Control'));
        $this->assertStringNotContainsString('public', (string) $ok->headers->get('Cache-Control'));
        $this->assertStringContainsString('<svg', (string) $ok->getContent());

        // Runtime parameters ride the query string without breaking the signature.
        $custom = $this->get($url.'?window_title='.rawurlencode('عنوان سفارشی'))->assertOk();
        $this->assertStringContainsString('عنوان سفارشی', (string) $custom->getContent());

        // Site B: A's signature re-scoped to B, and a genuine signature for B.
        $this->get(str_replace("/{$site}/{$expires}/", "/{$siteB->id}/{$expires}/", $url))->assertNotFound();
        $signedForB = (new MotionPrivateAssetUrl())->sign(MotionPrivateAssetUrl::baseReference(self::SLUG, 'v1'), $siteB->id);
        $this->get($signedForB)->assertNotFound();

        // Tampered, unsigned, public-route and extended-expiry forms.
        $this->get(str_replace($signature, str_repeat('0', 64), $url))->assertNotFound();
        $this->get(str_replace("/{$expires}/", '/'.((int) $expires + MotionPrivateAssetUrl::WINDOW_SECONDS).'/', $url))->assertNotFound();
        $this->get(MotionPrivateAssetUrl::baseReference(self::SLUG, 'v1'))->assertNotFound();
        $this->get('/motion/'.self::SLUG.'/v1/animation.svg')->assertNotFound();

        // Expired.
        $this->travel(15)->days();
        $this->get($url)->assertNotFound();
    }

    public function test_a_bespoke_assignment_rides_the_main_manifest_and_revocation_is_immediate(): void
    {
        $siteA = $this->createSite('A');
        $siteB = $this->createSite('B');
        $this->writeExclusive($siteA->id);
        $this->app->make(MotionLibraryCatalogService::class)->sync();
        ModuleSeeder::seedMotionModules();
        $admin = $this->admin();
        foreach ([$siteA, $siteB] as $site) {
            $this->app->make(ModuleLicenseService::class)->grantByAdmin(
                $site, Module::query()->where('slug', RealMotionLicenseGateway::MODULE_SLUG)->value('id'), $admin
            );
            $site->features = ['motion_graphics_integration' => true];
            $site->save();
        }

        // Site A has no package at all: before the assignment nothing is delivered.
        $this->fetchManifest($siteA)->assertOk()->assertJson(['available' => false, 'reason' => 'no_active_package']);

        $commission = MotionBespokeCommission::query()->create([
            'site_id' => $siteA->id, 'title' => 'Hero A', 'status' => MotionBespokeCommission::STATUS_PUBLISHED,
            'asset_id' => $this->exclusiveUuid(), 'published_at' => now(),
        ]);
        MotionBespokePurchase::query()->create([
            'commission_id' => $commission->id, 'site_id' => $siteA->id, 'amount_irr' => 1, 'purchased_at' => now(),
        ]);
        $assignment = MotionBespokeAssignment::query()->create([
            'commission_id' => $commission->id, 'site_id' => $siteA->id, 'asset_id' => $this->exclusiveUuid(),
            'active_version' => 1, 'status' => MotionBespokeAssignment::STATUS_ACTIVE, 'assigned_at' => now(),
        ]);

        $manifest = $this->fetchManifest($siteA)->assertOk()
            ->assertJsonPath('available', true)
            ->assertJsonPath('template_reference', MotionCustomerRuntimeService::BESPOKE_ONLY_TEMPLATE_REFERENCE)
            ->assertJsonCount(1, 'assets')
            ->assertJsonPath('assets.0.name', self::NAME)
            ->assertJsonPath('assets.0.category', 'hero-motion');
        $this->assertNotEmpty($manifest->json('assets.0.parameters'));
        $url = $manifest->json('assets.0.optimized_reference');
        $this->assertMatchesRegularExpression(self::SIGNED, $url);
        $this->get($url)->assertOk();

        // The separate bespoke endpoint carries the same full entry.
        $key = $this->app->make(LicenseServerService::class)->issue($siteA, 'bespoke-a.test');
        $this->withToken($key)->getJson('/api/motion-runtime/bespoke-manifest?domain=bespoke-a.test')->assertOk()
            ->assertJsonPath('assets.0.name', self::NAME)
            ->assertJsonPath('assets.0.optimized_reference', $url);

        // Site B gets nothing from A's bespoke assignment.
        $this->fetchManifest($siteB)->assertOk()->assertJson(['available' => false, 'reason' => 'no_active_package']);

        // Revoking removes it from the manifest and closes the URL at once.
        $assignment->update(['status' => MotionBespokeAssignment::STATUS_REVOKED]);
        $this->fetchManifest($siteA)->assertOk()->assertJson(['available' => false, 'reason' => 'no_active_package']);
        $this->get($url)->assertNotFound();
    }

    public function test_locked_premium_items_and_bespoke_assets_coexist_in_one_manifest(): void
    {
        $siteA = $this->createSite('A');
        $this->writeExclusive($siteA->id);

        // The public package, premium item included, is installed while the
        // site is licensed; the exclusive asset is published afterwards and
        // reaches the site only through its bespoke assignment.
        config(['motion-library.exclusive_assets' => []]);
        $this->provision($siteA, $this->admin());
        config(['motion-library.exclusive_assets' => [self::SLUG => 'v1']]);
        $this->app->make(MotionLibraryCatalogService::class)->sync();

        // The premium license lapses: without re-activation the premium item
        // becomes a locked asset (e6295b32).
        $premiumModule = Module::query()->where('slug', MotionAssetLicensePolicy::PREMIUM_MODULE_SLUG)->value('id');
        foreach (ModuleLicense::query()->where('site_id', $siteA->id)->where('module_id', $premiumModule)->get() as $license) {
            $this->app->make(ModuleLicenseService::class)->revoke($license);
        }

        $commission = MotionBespokeCommission::query()->create([
            'site_id' => $siteA->id, 'title' => 'Hero A', 'status' => MotionBespokeCommission::STATUS_PUBLISHED,
            'asset_id' => $this->exclusiveUuid(), 'published_at' => now(),
        ]);
        MotionBespokeAssignment::query()->create([
            'commission_id' => $commission->id, 'site_id' => $siteA->id, 'asset_id' => $this->exclusiveUuid(),
            'active_version' => 1, 'status' => MotionBespokeAssignment::STATUS_ACTIVE, 'assigned_at' => now(),
        ]);

        $manifest = $this->fetchManifest($siteA)->assertOk()->assertJsonPath('available', true)
            ->assertJsonCount(2, 'assets')
            ->assertJsonCount(1, 'locked_assets')
            ->assertJsonPath('locked_assets.0.reason', 'premium_license_required');
        $this->assertNotNull($this->exclusiveEntry($manifest), 'the bespoke asset is delivered next to the free package item');
        $this->assertContains('/motion/hero-motion/v2/animation.svg', array_column($manifest->json('assets'), 'optimized_reference'));
    }

    public function test_a_bespoke_bound_version_is_not_retired_by_a_library_update(): void
    {
        $siteA = $this->createSite('A');
        $this->writeExclusive($siteA->id);
        $catalog = $this->app->make(MotionLibraryCatalogService::class);
        $catalog->sync();
        $commission = MotionBespokeCommission::query()->create([
            'site_id' => $siteA->id, 'title' => 'Hero A', 'status' => MotionBespokeCommission::STATUS_PUBLISHED,
            'asset_id' => $this->exclusiveUuid(), 'published_at' => now(),
        ]);
        MotionBespokeAssignment::query()->create([
            'commission_id' => $commission->id, 'site_id' => $siteA->id, 'asset_id' => $this->exclusiveUuid(),
            'active_version' => 1, 'status' => MotionBespokeAssignment::STATUS_ACTIVE, 'assigned_at' => now(),
        ]);

        $this->writeExclusive($siteA->id, 'v1', '<desc>revised</desc>');
        $this->assertSame(2, $catalog->sync()[self::SLUG]['version']);

        $motion = MotionGraphic::query()->where('slug', self::SLUG)->firstOrFail();
        $this->assertSame('published', $motion->versions()->where('version', 1)->value('status'));
    }

    public function test_public_motions_are_unchanged_by_an_exclusive_asset(): void
    {
        $catalog = $this->app->make(MotionLibraryCatalogService::class);
        $public = fn (): array => MotionGraphic::query()->whereIn('slug', array_keys(MotionLibraryCatalogService::ASSETS))
            ->orderBy('slug')->get()
            ->mapWithKeys(fn (MotionGraphic $m): array => [$m->slug => [
                $m->ownership_type, $m->published_version,
                $m->versions()->orderBy('version')->get()->map(fn ($v): array => [$v->version, $v->status, $v->compiled_assets])->all(),
            ]])->all();

        config(['motion-library.exclusive_assets' => []]);
        $catalog->sync();
        $before = $public();

        $siteA = $this->createSite('A');
        $this->writeExclusive($siteA->id);
        config(['motion-library.exclusive_assets' => [self::SLUG => 'v1']]);
        $catalog->sync();

        $this->assertSame($before, $public());
        $hero = collect($before['hero-motion'][2])->firstWhere(0, $before['hero-motion'][1]);
        $this->assertSame(MotionGraphic::OWNERSHIP_GLOBAL, $before['hero-motion'][0]);
        $this->assertSame('/motion/hero-motion/v2/animation.svg', $hero[2][0]['url']);
        $cache = (string) $this->get('/motion/hero-motion/v2/animation.svg')->assertOk()->headers->get('Cache-Control');
        $this->assertStringContainsString('public', $cache);
        $this->assertStringContainsString('immutable', $cache);
    }

    public function test_the_catalog_refuses_an_exclusive_asset_without_a_valid_owner(): void
    {
        $this->writeExclusive(null);
        $this->assertSyncFails('must declare ownership.site_id');

        $this->writeExclusive(999999);
        $this->assertSyncFails('does not exist');
    }

    public function test_ownership_is_fixed_after_the_first_publish(): void
    {
        $siteA = $this->createSite('A');
        $siteB = $this->createSite('B');
        $this->writeExclusive($siteA->id);
        $this->app->make(MotionLibraryCatalogService::class)->sync();

        $this->writeExclusive($siteB->id);
        $this->assertSyncFails('ownership cannot change');
        $this->assertSame((string) $siteA->id, MotionGraphic::query()->where('slug', self::SLUG)->value('owner_site_identifier'));
    }

    private function assertSyncFails(string $message): void
    {
        try {
            $this->app->make(MotionLibraryCatalogService::class)->sync();
            $this->fail('sync() should have refused the exclusive asset.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString($message, $e->getMessage());
        }
    }
}
