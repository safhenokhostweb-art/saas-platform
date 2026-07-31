<?php

namespace Tests\Feature\Modules\MotionGraphics\Persistence;

use App\Models\Site;
use App\Models\User;
use App\Modules\MotionGraphics\DTO\MotionAdapterContext;
use App\Modules\MotionGraphics\Exceptions\MotionAdapterFailClosedException;
use App\Modules\MotionGraphics\Models\AssetActivation;
use App\Modules\MotionGraphics\Models\PackageSnapshot;
use App\Modules\MotionGraphics\Models\SiteAssetPackage;
use App\Modules\MotionGraphics\Models\SiteAssetPackageItem;
use App\Modules\MotionGraphics\Models\TemplateAssetPackage;
use App\Modules\MotionGraphics\Services\SiteAssetPackageActivationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use ReflectionClass;
use SaaSPlatform\CoreUtils\Contracts\Services\ActivityLoggerInterface;
use SaaSPlatform\CoreUtils\Contracts\TenantContextContract;
use SaaSPlatform\Modules\MotionGraphics\Contracts\Read\Model\RuntimeProjectionReadModel;
use SaaSPlatform\Modules\MotionGraphics\Contracts\Read\MotionRuntimeProjectionReadContract;
use Tests\Support\CreatesMotionTestSite;
use Tests\TestCase;

/**
 * Task scope item 10 (Security): cross-tenant access denied, unpublished
 * assets denied, and Master Assets never modifiable from this
 * persistence layer.
 */
class PersistenceSecurityTest extends TestCase
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

    public function test_activation_is_denied_when_the_caller_claims_a_site_it_is_not_trusted_for(): void
    {
        $siteA = $this->createSite('Site A');
        $siteB = $this->createSite('Site B');
        $templatePackage = TemplateAssetPackage::query()->create([
            'template_reference' => 'template-1',
            'required_assets' => [['asset_id' => 'motion-1', 'version' => 1]],
            'package_version' => 1,
        ]);

        $this->actingAs($this->admin());
        $this->bindTrustedSite($siteA); // trusted tenant is Site A

        try {
            // The caller claims Site B in the context, but the trusted
            // tenant is Site A — this must be denied, not silently
            // resolved against Site A.
            $this->app->make(SiteAssetPackageActivationService::class)->activate(
                $templatePackage, null, new MotionAdapterContext('user:1', (string) $siteB->id, 'corr-1')
            );
            $this->fail('Expected a fail-closed exception.');
        } catch (MotionAdapterFailClosedException $e) {
            $this->assertSame('tenant_context_mismatch', $e->failureCode);
        }

        $this->assertSame(0, SiteAssetPackage::query()->count());
    }

    public function test_an_unpublished_asset_is_never_activated(): void
    {
        $site = $this->createSite();
        $templatePackage = TemplateAssetPackage::query()->create([
            'template_reference' => 'template-1',
            'required_assets' => [['asset_id' => 'motion-draft', 'version' => 1]],
            'package_version' => 1,
        ]);

        $this->actingAs($this->admin());
        $this->bindTrustedSite($site);
        // Simulates the real PublishedRuntimeProjectionProvider's
        // behavior for a draft-only asset: projectAsset() returns null.
        $this->app->instance(MotionRuntimeProjectionReadContract::class, new class implements MotionRuntimeProjectionReadContract {
            public function projectAsset(string $assetId, ?string $siteIdentifier = null): ?RuntimeProjectionReadModel { return null; }
        });

        try {
            $this->app->make(SiteAssetPackageActivationService::class)->activate(
                $templatePackage, null, new MotionAdapterContext('user:1', (string) $site->id, 'corr-1')
            );
            $this->fail('Expected a fail-closed exception.');
        } catch (MotionAdapterFailClosedException $e) {
            $this->assertSame('asset_not_available_for_site', $e->failureCode);
        }

        $this->assertSame(0, SiteAssetPackage::query()->count());
        $this->assertSame(0, SiteAssetPackageItem::query()->count());
        $this->assertSame(0, AssetActivation::query()->count());
        $this->assertSame(0, PackageSnapshot::query()->count());
    }

    /**
     * Master Asset modification denied: none of the Phase 3 persistence
     * models or services can write to the standalone module's own
     * tables — verified structurally (by construction), not just by
     * absence of a code path today. A future edit that accidentally
     * introduces a module-table reference here would fail this test.
     */
    public function test_no_persistence_model_or_service_references_the_modules_own_tables(): void
    {
        $forbiddenTables = ['motion_graphics', 'motion_graphic_versions', 'motion_groups', 'motion_categories', 'motion_tags', 'motion_graphic_deployments'];
        $forbiddenClasses = [
            \SaaSPlatform\Modules\MotionGraphics\Models\MotionGraphic::class,
            \SaaSPlatform\Modules\MotionGraphics\Models\MotionGraphicVersion::class,
            \SaaSPlatform\Modules\MotionGraphics\Models\MotionGraphicDeployment::class,
        ];

        $subjects = [
            TemplateAssetPackage::class, SiteAssetPackage::class, SiteAssetPackageItem::class,
            AssetActivation::class, PackageSnapshot::class,
            \App\Modules\MotionGraphics\Services\TemplateAssetPackageService::class,
            SiteAssetPackageActivationService::class,
        ];

        foreach ($subjects as $subject) {
            $source = file_get_contents((new ReflectionClass($subject))->getFileName());

            foreach ($forbiddenTables as $table) {
                $this->assertStringNotContainsString($table, $source, "{$subject} must never reference the module table [{$table}].");
            }
            foreach ($forbiddenClasses as $class) {
                $this->assertStringNotContainsString($class, $source, "{$subject} must never reference the module model [{$class}].");
            }
        }
    }
}
