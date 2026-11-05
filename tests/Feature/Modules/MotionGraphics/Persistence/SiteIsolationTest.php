<?php

namespace Tests\Feature\Modules\MotionGraphics\Persistence;

use App\Models\Site;
use App\Models\User;
use App\Modules\MotionGraphics\Contracts\MotionAuthorizationInterface;
use App\Modules\MotionGraphics\DTO\MotionAdapterContext;
use App\Modules\MotionGraphics\Exceptions\MotionAdapterFailClosedException;
use App\Modules\MotionGraphics\Models\SiteAssetPackage;
use App\Modules\MotionGraphics\Models\SiteAssetPackageItem;
use App\Modules\MotionGraphics\Models\TemplateAssetPackage;
use App\Modules\MotionGraphics\Services\SiteAssetPackageActivationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use SaaSPlatform\CoreUtils\Contracts\Services\ActivityLoggerInterface;
use SaaSPlatform\CoreUtils\Contracts\TenantContextContract;
use SaaSPlatform\Modules\MotionGraphics\Contracts\Read\Model\AssetReadModel;
use SaaSPlatform\Modules\MotionGraphics\Contracts\Read\Model\RuntimeProjectionReadModel;
use SaaSPlatform\Modules\MotionGraphics\Contracts\Read\MotionRuntimeProjectionReadContract;
use Tests\Support\CreatesMotionTestSite;
use Tests\TestCase;

/**
 * Task scope item 7 (Site Isolation): a site can access only its own
 * Site Package; it can never reach another site's package or rollback
 * another site's item, resolved through the real container end to end.
 */
class SiteIsolationTest extends TestCase
{
    use RefreshDatabase;
    use CreatesMotionTestSite;

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('feature-flags.motion_graphics_integration', true);

        $this->app->instance(ActivityLoggerInterface::class, new class implements ActivityLoggerInterface {
            public function log(string $action, ?string $description = null, ?object $subject = null, array $properties = []): void {}
        });
    }

    private function admin(): User
    {
        $admin = new User();
        $admin->id = 1;
        $admin->is_admin = true;
        $admin->site_id = null;

        return $admin;
    }

    private function bindTrustedSite(Site $site): void
    {
        $this->app->instance(TenantContextContract::class, new class($site) implements TenantContextContract {
            public function __construct(private readonly Site $site) {}
            public function current(): ?object { return $this->site; }
            public function type(): ?string { return 'site'; }
            public function isReseller(): bool { return false; }
            public function isSite(): bool { return true; }
            public function reseller(): ?object { return null; }
            public function site(): ?object { return $this->site; }
            public function id(): ?int { return $this->site->id; }
            public function hasTenant(): bool { return true; }
            public function hasFeature(string $feature): bool { return (bool) config("feature-flags.{$feature}"); }
        });
    }

    private function bindPublishedAsset(string $assetId, int $version): void
    {
        $this->app->instance(MotionRuntimeProjectionReadContract::class, new class($assetId, $version) implements MotionRuntimeProjectionReadContract {
            public function __construct(private readonly string $assetId, private readonly int $version) {}
            public function projectAsset(string $assetId, ?string $siteIdentifier = null): ?RuntimeProjectionReadModel {
                if ($assetId !== $this->assetId) {
                    return null;
                }
                return new RuntimeProjectionReadModel(
                    assetReference: new AssetReadModel($assetId, $this->version, 'lottie', null, ['license_tier' => 'free'], 'checksum', false),
                    publishedVersion: $this->version,
                    optimizedReference: '/motion/x/v'.$this->version.'/a.json',
                );
            }
        });
    }

    public function test_a_site_can_activate_and_see_only_its_own_package(): void
    {
        $siteA = $this->createSite('Site A');
        $templatePackage = TemplateAssetPackage::query()->create([
            'template_reference' => 'template-1',
            'required_assets' => [['asset_id' => 'motion-1', 'version' => 1]],
            'package_version' => 1,
        ]);

        $this->actingAs($this->admin());
        $this->bindTrustedSite($siteA);
        $this->bindPublishedAsset('motion-1', 1);

        $this->app->make(SiteAssetPackageActivationService::class)->activate(
            $templatePackage, null, new MotionAdapterContext('user:1', (string) $siteA->id, 'corr-1')
        );

        $this->assertSame(1, SiteAssetPackage::query()->where('site_id', $siteA->id)->count());
    }

    public function test_a_site_cannot_roll_back_another_sites_item(): void
    {
        $siteA = $this->createSite('Site A');
        $siteB = $this->createSite('Site B');
        $templatePackage = TemplateAssetPackage::query()->create([
            'template_reference' => 'template-1',
            'required_assets' => [['asset_id' => 'motion-1', 'version' => 1]],
            'package_version' => 1,
        ]);

        $this->actingAs($this->admin());
        $this->bindTrustedSite($siteA);
        $this->bindPublishedAsset('motion-1', 1);
        $this->app->make(SiteAssetPackageActivationService::class)->activate(
            $templatePackage, null, new MotionAdapterContext('user:1', (string) $siteA->id, 'corr-1')
        );
        $item = SiteAssetPackageItem::query()->first();

        // Switch the trusted tenant context to Site B and attempt to roll
        // back Site A's item. The Motion Graphics singletons already
        // resolved above captured Site A's tenant context at construction
        // time, so they must be forgotten to pick up the new binding —
        // otherwise this would (incorrectly) keep proving isolation
        // against Site A's own context instead of Site B's.
        $this->bindTrustedSite($siteB);
        $this->app->forgetInstance(SiteAssetPackageActivationService::class);
        $this->app->forgetInstance(MotionAuthorizationInterface::class);

        try {
            $this->app->make(SiteAssetPackageActivationService::class)->rollback(
                $item, 1, new MotionAdapterContext('user:1', (string) $siteB->id, 'corr-1')
            );
            $this->fail('Expected a fail-closed exception.');
        } catch (MotionAdapterFailClosedException $e) {
            $this->assertSame('tenant_isolation_violation', $e->failureCode);
        }
    }
}
