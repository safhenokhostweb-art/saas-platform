<?php

namespace Tests\Unit\Modules\MotionGraphics\DTO;

use App\Modules\MotionGraphics\DTO\MotionAssetReferenceDTO;
use App\Modules\MotionGraphics\DTO\RuntimeProjectionDTO;
use InvalidArgumentException;
use Tests\TestCase;

class RuntimeProjectionDTOTest extends TestCase
{
    public function test_it_holds_only_allowlisted_projection_data(): void
    {
        $asset = new MotionAssetReferenceDTO('motion-1', 2, 'lottie');
        $dto = new RuntimeProjectionDTO(
            assetReference: $asset,
            publishedVersion: 2,
            themeTokens: ['primary' => '#000000'],
            placementRules: ['section' => 'hero'],
        );

        $this->assertSame($asset, $dto->assetReference);
        $this->assertSame(2, $dto->publishedVersion);
        $this->assertSame(['primary' => '#000000'], $dto->themeTokens);
        $this->assertSame(['section' => 'hero'], $dto->placementRules);
    }

    public function test_it_rejects_ai_metadata_in_theme_tokens(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new RuntimeProjectionDTO(
            assetReference: new MotionAssetReferenceDTO('motion-1', 1, 'lottie'),
            publishedVersion: 1,
            themeTokens: ['ai_metadata' => 'leak'],
        );
    }

    public function test_it_rejects_source_definition_in_placement_rules(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new RuntimeProjectionDTO(
            assetReference: new MotionAssetReferenceDTO('motion-1', 1, 'lottie'),
            publishedVersion: 1,
            placementRules: ['source_definition' => 'leak'],
        );
    }
}
