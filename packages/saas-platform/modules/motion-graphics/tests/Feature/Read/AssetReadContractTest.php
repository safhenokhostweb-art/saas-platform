<?php

namespace SaaSPlatform\Modules\MotionGraphics\Tests\Feature\Read;

use ReflectionClass;
use SaaSPlatform\Modules\MotionGraphics\Contracts\Read\MotionAssetReadContract;
use SaaSPlatform\Modules\MotionGraphics\Contracts\Read\Model\AssetReadModel;
use SaaSPlatform\Modules\MotionGraphics\Models\MotionGraphic;
use SaaSPlatform\Modules\MotionGraphics\Services\MotionLibraryService;
use SaaSPlatform\Modules\MotionGraphics\Tests\TestCase;

final class AssetReadContractTest extends TestCase
{
    public function test_it_resolves_the_published_version_by_default(): void
    {
        $motion = $this->published('canonical');

        $asset = app(MotionAssetReadContract::class)->findByCanonicalId($motion->uuid);

        $this->assertInstanceOf(AssetReadModel::class, $asset);
        $this->assertSame($motion->uuid, $asset->assetId);
        $this->assertSame(1, $asset->version);
        $this->assertSame('svg', $asset->type);
        $this->assertSame('finance', $asset->compatibility['tags'][0] ?? null);
    }

    public function test_it_returns_null_for_a_draft_only_asset(): void
    {
        $library = app(MotionLibraryService::class);
        $motion = $library->createFromPipeline($this->motionData('draft-only'), $this->svgInput(), $this->admin);

        $asset = app(MotionAssetReadContract::class)->findByCanonicalId($motion->uuid);

        $this->assertNull($asset);
    }

    public function test_it_returns_null_for_an_unknown_asset_id(): void
    {
        $this->assertNull(app(MotionAssetReadContract::class)->findByCanonicalId('unknown-uuid'));
    }

    public function test_it_can_resolve_an_explicit_approved_version(): void
    {
        $library = app(MotionLibraryService::class);
        $motion = $library->createFromPipeline($this->motionData('approved-only'), $this->svgInput(), $this->admin);
        $library->approve($motion, 1, $this->admin);

        $asset = app(MotionAssetReadContract::class)->findByCanonicalId($motion->uuid, 1);

        $this->assertNotNull($asset);
        $this->assertSame(1, $asset->version);
    }

    public function test_it_cannot_resolve_a_draft_version_even_when_explicitly_requested(): void
    {
        $library = app(MotionLibraryService::class);
        $motion = $library->createFromPipeline($this->motionData('draft-explicit'), $this->svgInput(), $this->admin);

        $this->assertNull(app(MotionAssetReadContract::class)->findByCanonicalId($motion->uuid, 1));
    }

    public function test_verify_lifecycle_state_matches_the_real_status(): void
    {
        $motion = $this->published('lifecycle-check');
        $reader = app(MotionAssetReadContract::class);

        $this->assertTrue($reader->verifyLifecycleState($motion->uuid, 1, 'published'));
        $this->assertFalse($reader->verifyLifecycleState($motion->uuid, 1, 'draft'));
    }

    public function test_retired_version_is_unreadable_unless_policy_allows_it(): void
    {
        $library = app(MotionLibraryService::class);
        $motion = $library->createFromPipeline($this->motionData('retire-me'), $this->svgInput('V1'), $this->admin);
        $library->approve($motion, 1, $this->admin);
        $library->publish($motion, 1, $this->admin);
        $v2 = $library->createVersionFromPipeline($motion->fresh(), $this->svgInput('V2'), 'v2', $this->admin);
        $library->approve($motion->fresh(), $v2->version, $this->admin);
        $library->publish($motion->fresh(), $v2->version, $this->admin);

        $reader = app(MotionAssetReadContract::class);
        $this->assertNull($reader->findByCanonicalId($motion->uuid, 1));

        config(['motion-graphics.deprecated_versions_readable' => true]);
        $this->assertNotNull($reader->findByCanonicalId($motion->uuid, 1));
    }

    public function test_the_read_model_structurally_cannot_carry_source_or_ai_fields(): void
    {
        $properties = array_map(
            fn (\ReflectionProperty $property): string => $property->getName(),
            (new ReflectionClass(AssetReadModel::class))->getProperties()
        );

        // `name` is picker display metadata (Theme Builder Motion Picker),
        // not a source or AI field.
        $this->assertEqualsCanonicalizing(
            ['assetId', 'version', 'type', 'category', 'compatibility', 'checksum', 'themeSupport', 'name'],
            $properties
        );
    }

    private function published(string $slug, string $ownership = MotionGraphic::OWNERSHIP_GLOBAL, ?string $site = null): MotionGraphic
    {
        $library = app(MotionLibraryService::class);
        $group = $this->group($ownership, $site);
        $data = $this->motionData($slug, $group, $ownership, $site);
        $motion = $library->createFromPipeline($data, $this->svgInput(), $this->admin);
        $motion->tags()->sync([\SaaSPlatform\Modules\MotionGraphics\Models\MotionTag::query()->firstOrCreate(['slug' => 'finance'], ['name' => 'Finance'])->id]);
        $library->approve($motion, 1, $this->admin);
        $library->publish($motion, 1, $this->admin);

        return $motion->fresh();
    }
}
