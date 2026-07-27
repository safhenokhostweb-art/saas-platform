<?php

namespace Tests\Unit\Modules\MotionGraphics\Adapters;

use App\Modules\MotionGraphics\Adapters\SiteAssetPackageResolverAdapter;
use App\Modules\MotionGraphics\Contracts\MotionAuthorizationInterface;
use App\Modules\MotionGraphics\DTO\AuthorizationDecision;
use App\Modules\MotionGraphics\DTO\MotionAdapterContext;
use App\Modules\MotionGraphics\DTO\MotionAssetReferenceDTO;
use App\Modules\MotionGraphics\DTO\SiteAssetPackageDTO;
use App\Modules\MotionGraphics\DTO\TemplateAssetRequestDTO;
use App\Modules\MotionGraphics\Exceptions\MotionAdapterFailClosedException;
use App\Modules\MotionGraphics\Support\MotionAuditEvents;
use Mockery;
use SaaSPlatform\CoreUtils\Contracts\TenantContextContract;
use SaaSPlatform\Modules\MotionGraphics\Contracts\Read\Model\AssetReadModel;
use SaaSPlatform\Modules\MotionGraphics\Contracts\Read\Model\LineageEntry;
use SaaSPlatform\Modules\MotionGraphics\Contracts\Read\MotionAssetReadContract;
use SaaSPlatform\Modules\MotionGraphics\Contracts\Read\MotionVersionReadContract;
use Tests\TestCase;

class SiteAssetPackageResolverAdapterTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function tenantContext(bool $flagEnabled, ?string $siteId): TenantContextContract
    {
        $tenantContext = Mockery::mock(TenantContextContract::class);
        $tenantContext->shouldReceive('hasFeature')->with('motion_graphics_integration')->andReturn($flagEnabled);
        $tenantContext->shouldReceive('site')->andReturn($siteId === null ? null : (object) ['id' => $siteId]);

        return $tenantContext;
    }

    private function adapter(
        bool $flagEnabled,
        ?string $siteId,
        ?MotionAuthorizationInterface $authorization = null,
        ?MotionAuditEvents $auditEvents = null,
        ?MotionAssetReadContract $assets = null,
        ?MotionVersionReadContract $versions = null,
    ): SiteAssetPackageResolverAdapter {
        return new SiteAssetPackageResolverAdapter(
            $this->tenantContext($flagEnabled, $siteId),
            $auditEvents ?? Mockery::mock(MotionAuditEvents::class)->shouldIgnoreMissing(),
            $authorization ?? Mockery::mock(MotionAuthorizationInterface::class),
            $assets ?? Mockery::mock(MotionAssetReadContract::class)->shouldIgnoreMissing(),
            $versions ?? Mockery::mock(MotionVersionReadContract::class),
        );
    }

    public function test_create_candidate_maps_required_assets_into_the_package(): void
    {
        $authorization = Mockery::mock(MotionAuthorizationInterface::class);
        $authorization->shouldReceive('authorize')->andReturn(AuthorizationDecision::allow());

        $auditEvents = Mockery::mock(MotionAuditEvents::class);
        $auditEvents->shouldReceive('record')->once()->with(MotionAuditEvents::PACKAGE_CREATED, Mockery::type('array'));

        $assets = Mockery::mock(MotionAssetReadContract::class);
        $assets->shouldReceive('findByCanonicalId')->with('motion-1', 1)->andReturn(
            new AssetReadModel('motion-1', 1, 'lottie', null, [], 'abc', false)
        );

        $adapter = $this->adapter(true, 'site-1', $authorization, $auditEvents, $assets);

        $template = new TemplateAssetRequestDTO('template-1', [new MotionAssetReferenceDTO('motion-1', 1, 'lottie')]);
        $candidate = $adapter->createCandidate($template, 'brand-a', new MotionAdapterContext('user:1', 'site-1', 'corr-1'));

        $this->assertSame('site-1', $candidate->siteId);
        $this->assertSame('template-1', $candidate->templateId);
        $this->assertCount(1, $candidate->resolvedAssets);
        $this->assertSame('brand-a', $candidate->brandVariant);
    }

    public function test_create_candidate_rejects_an_unresolved_required_asset(): void
    {
        $authorization = Mockery::mock(MotionAuthorizationInterface::class);
        $authorization->shouldReceive('authorize')->andReturn(AuthorizationDecision::allow());

        $assets = Mockery::mock(MotionAssetReadContract::class);
        $assets->shouldReceive('findByCanonicalId')->with('motion-1', 1)->andReturnNull();

        $adapter = $this->adapter(true, 'site-1', $authorization, null, $assets);
        $template = new TemplateAssetRequestDTO('template-1', [new MotionAssetReferenceDTO('motion-1', 1, 'lottie')]);

        try {
            $adapter->createCandidate($template, null, new MotionAdapterContext('user:1', 'site-1', 'corr-1'));
            $this->fail('Expected a fail-closed exception.');
        } catch (MotionAdapterFailClosedException $e) {
            $this->assertSame('unresolved_required_asset', $e->failureCode);
        }
    }

    public function test_create_candidate_rejects_a_site_id_that_does_not_match_the_trusted_tenant(): void
    {
        $authorization = Mockery::mock(MotionAuthorizationInterface::class);
        $authorization->shouldReceive('authorize')->andReturn(AuthorizationDecision::allow());

        $adapter = $this->adapter(true, 'site-1', $authorization);

        $template = new TemplateAssetRequestDTO('template-1');

        $this->expectException(MotionAdapterFailClosedException::class);

        // Context claims site-2 while the trusted tenant is site-1.
        $adapter->createCandidate($template, null, new MotionAdapterContext('user:1', 'site-2', 'corr-1'));
    }

    public function test_validate_ownership_rejects_a_candidate_belonging_to_another_site(): void
    {
        $adapter = $this->adapter(true, 'site-1');

        $candidate = new SiteAssetPackageDTO('site-1', 'template-1');

        $decision = $adapter->validateOwnership($candidate, 'site-2');

        $this->assertFalse($decision->owns);
        $this->assertSame('tenant_isolation_violation', $decision->code);
    }

    public function test_lineage_maps_read_contract_entries(): void
    {
        $authorization = Mockery::mock(MotionAuthorizationInterface::class);
        $authorization->shouldReceive('authorize')->andReturn(AuthorizationDecision::allow());

        $versions = Mockery::mock(MotionVersionReadContract::class);
        $versions->shouldReceive('lineage')->with('motion-1')->andReturn([
            new LineageEntry(1, null, 'published'),
            new LineageEntry(2, 1, 'retired'),
        ]);

        $adapter = $this->adapter(true, 'site-1', $authorization, null, null, $versions);

        $lineage = $adapter->lineage('motion-1', 1, new MotionAdapterContext('user:1', 'site-1', 'corr-1'));

        $this->assertSame([
            ['version' => 1, 'parent_version' => null, 'status' => 'published'],
            ['version' => 2, 'parent_version' => 1, 'status' => 'retired'],
        ], $lineage);
    }
}
