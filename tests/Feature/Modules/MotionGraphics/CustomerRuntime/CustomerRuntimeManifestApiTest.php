<?php

namespace Tests\Feature\Modules\MotionGraphics\CustomerRuntime;

use App\Models\PlatformLicense;
use App\Models\Site;
use App\Modules\MotionGraphics\Models\SiteAssetPackage;
use App\Modules\MotionGraphics\Models\SiteAssetPackageItem;
use App\Modules\MotionGraphics\Models\TemplateAssetPackage;
use App\Services\LicenseServerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesMotionTestSite;
use Tests\Support\InteractsWithMotionRuntimeInstallation;
use Tests\TestCase;

/**
 * Phase 8: the real HTTP round trip through `tenant.api` — the canonical
 * integration boundary the audit determined — to the new customer
 * runtime manifest endpoint. No fakes for the auth layer: a real
 * PlatformLicense is issued via the real LicenseServerService and sent
 * as a real Bearer token, exactly as customer-site's
 * MotionRuntimeManifestReader does.
 */
class CustomerRuntimeManifestApiTest extends TestCase
{
    use RefreshDatabase;
    use CreatesMotionTestSite;
    use InteractsWithMotionRuntimeInstallation;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fakeActivityLogger();
        $this->registerMotionGraphicsModule();
    }

    private function issueLicenseFor(Site $site, string $domain = 'customer.test'): string
    {
        return $this->app->make(LicenseServerService::class)->issue($site, $domain);
    }

    private function activePackageFor(Site $site): SiteAssetPackage
    {
        // firstOrCreate: TemplateAssetPackage is shared across sites by
        // (template_reference, package_version) — a second site reusing
        // the same template reference must reuse the existing row, not
        // violate the unique constraint by trying to create a duplicate.
        $templatePackage = TemplateAssetPackage::query()->firstOrCreate(
            ['template_reference' => 'template-1', 'package_version' => 1],
            ['required_assets' => [['asset_id' => 'motion-1', 'version' => 1]]],
        );
        $sitePackage = SiteAssetPackage::query()->create([
            'site_id' => $site->id,
            'template_reference' => 'template-1',
            'template_asset_package_id' => $templatePackage->id,
            'activation_status' => SiteAssetPackage::STATUS_ACTIVE,
            'activated_at' => now(),
        ]);
        SiteAssetPackageItem::query()->create([
            'site_asset_package_id' => $sitePackage->id,
            'asset_id' => 'motion-1', 'active_version' => 1,
            'bound_checksum' => 'checksum-1', 'installed_at' => now(),
        ]);

        return $sitePackage;
    }

    private function enableSite(Site $site): void
    {
        $site->features = ['motion_graphics_integration' => true];
        $site->save();
    }

    public function test_a_valid_license_receives_the_manifest(): void
    {
        $site = $this->createSite();
        $this->enableSite($site);
        $this->activePackageFor($site);
        $this->bindPublishedAsset('motion-1', 1);
        $key = $this->issueLicenseFor($site);

        $response = $this->withToken($key)->getJson('/api/motion-runtime/manifest?domain=customer.test');

        $response->assertOk()
            ->assertJson([
                'available' => true,
                'schema_version' => '1.0',
                'site_reference' => (string) $site->id,
                'template_reference' => 'template-1',
            ])
            ->assertJsonPath('assets.0.asset_id', 'motion-1');
    }

    public function test_a_missing_credential_is_rejected(): void
    {
        $response = $this->getJson('/api/motion-runtime/manifest?domain=customer.test');

        $response->assertStatus(401)->assertJson(['success' => false, 'error' => 'missing_credentials']);
    }

    public function test_an_invalid_license_key_is_rejected(): void
    {
        $response = $this->withToken('not-a-real-key-000000')->getJson('/api/motion-runtime/manifest?domain=customer.test');

        $response->assertStatus(403);
    }

    public function test_a_revoked_license_is_rejected(): void
    {
        $site = $this->createSite();
        $this->enableSite($site);
        $key = $this->issueLicenseFor($site);
        PlatformLicense::query()->latest('id')->first()->update(['revoked_at' => now()]);

        $response = $this->withToken($key)->getJson('/api/motion-runtime/manifest?domain=customer.test');

        $response->assertStatus(403)->assertJsonPath('error', 'revoked');
    }

    public function test_a_domain_mismatch_is_rejected(): void
    {
        $site = $this->createSite();
        $this->enableSite($site);
        $key = $this->issueLicenseFor($site, 'the-real-domain.test');

        $response = $this->withToken($key)->getJson('/api/motion-runtime/manifest?domain=attacker-controlled.test');

        $response->assertStatus(403)->assertJsonPath('error', 'domain_mismatch');
    }

    public function test_a_disabled_site_returns_a_safe_unavailable_response_not_an_error(): void
    {
        $site = $this->createSite(); // never enabled
        $this->activePackageFor($site);
        $this->bindPublishedAsset('motion-1', 1);
        $key = $this->issueLicenseFor($site);

        $response = $this->withToken($key)->getJson('/api/motion-runtime/manifest?domain=customer.test');

        $response->assertOk()->assertJson(['available' => false, 'reason' => 'motion_adapter_disabled']);
    }

    public function test_a_site_with_no_active_package_returns_a_safe_unavailable_response(): void
    {
        $site = $this->createSite();
        $this->enableSite($site);
        $key = $this->issueLicenseFor($site);

        $response = $this->withToken($key)->getJson('/api/motion-runtime/manifest?domain=customer.test');

        $response->assertOk()->assertJson(['available' => false, 'reason' => 'no_active_package']);
    }

    public function test_one_sites_license_can_never_fetch_another_sites_manifest(): void
    {
        $siteA = $this->createSite('Site A');
        $this->enableSite($siteA);
        $this->activePackageFor($siteA);
        $siteB = $this->createSite('Site B');
        $this->enableSite($siteB);
        $this->activePackageFor($siteB);
        $this->bindPublishedAsset('motion-1', 1);

        $keyForA = $this->issueLicenseFor($siteA, 'site-a.test');

        $response = $this->withToken($keyForA)->getJson('/api/motion-runtime/manifest?domain=site-a.test');

        $response->assertOk()->assertJsonPath('site_reference', (string) $siteA->id);
        $this->assertNotEquals((string) $siteB->id, $response->json('site_reference'));
    }

    public function test_the_response_never_contains_forbidden_fields(): void
    {
        $site = $this->createSite();
        $this->enableSite($site);
        $this->activePackageFor($site);
        $this->bindPublishedAsset('motion-1', 1);
        $key = $this->issueLicenseFor($site);

        $response = $this->withToken($key)->getJson('/api/motion-runtime/manifest?domain=customer.test');

        $body = $response->getContent();
        foreach (['source_definition', 'ai_metadata', 'editable_schema', 'ownership', 'credentials', 'storage_path', 'prompt'] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $body);
        }
    }
}
