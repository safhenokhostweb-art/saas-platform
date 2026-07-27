<?php

namespace Tests\Unit\Modules\MotionGraphics\Support;

use App\Modules\MotionGraphics\Support\ReadModelMapper;
use SaaSPlatform\Modules\MotionGraphics\Contracts\Read\Model\AssetReadModel;
use SaaSPlatform\Modules\MotionGraphics\Contracts\Read\Model\RuntimeProjectionReadModel;
use Tests\TestCase;

class ReadModelMapperTest extends TestCase
{
    public function test_it_maps_an_asset_read_model_into_a_dto(): void
    {
        $model = new AssetReadModel('motion-1', 2, 'lottie', 'finance', ['engine' => 'lottie', 'tags' => ['finance']], 'checksum123', true);

        $dto = ReadModelMapper::toAssetReferenceDTO($model);

        $this->assertSame('motion-1', $dto->assetId);
        $this->assertSame(2, $dto->version);
        $this->assertSame('lottie', $dto->type);
        $this->assertSame('finance', $dto->compatibility['category']);
        $this->assertSame('checksum123', $dto->compatibility['checksum']);
        $this->assertTrue($dto->compatibility['theme_support']);
        $this->assertSame(['finance'], $dto->compatibility['tags']);
    }

    public function test_it_maps_a_runtime_projection_read_model_into_a_dto(): void
    {
        $asset = new AssetReadModel('motion-1', 2, 'lottie', null, [], 'checksum123', false);
        $model = new RuntimeProjectionReadModel(
            assetReference: $asset,
            publishedVersion: 2,
            optimizedReference: '/motion/foo/v2/animation.json',
            themeTokens: ['primary' => '#fff'],
            placementRules: ['dimensions' => ['width' => 800]],
        );

        $dto = ReadModelMapper::toRuntimeProjectionDTO($model);

        $this->assertSame(2, $dto->publishedVersion);
        $this->assertSame('/motion/foo/v2/animation.json', $dto->optimizedReference);
        $this->assertSame(['primary' => '#fff'], $dto->themeTokens);
        $this->assertSame(['dimensions' => ['width' => 800]], $dto->placementRules);
        $this->assertSame('motion-1', $dto->assetReference->assetId);
    }
}
