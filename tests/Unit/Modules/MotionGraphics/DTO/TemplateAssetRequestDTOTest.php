<?php

namespace Tests\Unit\Modules\MotionGraphics\DTO;

use App\Modules\MotionGraphics\DTO\MotionAssetReferenceDTO;
use App\Modules\MotionGraphics\DTO\TemplateAssetRequestDTO;
use InvalidArgumentException;
use Tests\TestCase;

class TemplateAssetRequestDTOTest extends TestCase
{
    public function test_it_builds_from_array_with_nested_assets(): void
    {
        $dto = TemplateAssetRequestDTO::fromArray([
            'template_id' => 'template-1',
            'required_assets' => [
                ['asset_id' => 'motion-1', 'version' => 1, 'type' => 'lottie'],
            ],
            'optional_assets' => [
                ['asset_id' => 'motion-2', 'version' => 2, 'type' => 'svg-js'],
            ],
        ]);

        $this->assertSame('template-1', $dto->templateId);
        $this->assertCount(1, $dto->requiredAssets);
        $this->assertCount(1, $dto->optionalAssets);
        $this->assertInstanceOf(MotionAssetReferenceDTO::class, $dto->requiredAssets[0]);
    }

    public function test_it_rejects_non_reference_entries(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new TemplateAssetRequestDTO('template-1', [new \stdClass()]);
    }

    public function test_it_defaults_to_empty_asset_lists(): void
    {
        $dto = TemplateAssetRequestDTO::fromArray(['template_id' => 'template-1']);

        $this->assertSame([], $dto->requiredAssets);
        $this->assertSame([], $dto->optionalAssets);
    }
}
