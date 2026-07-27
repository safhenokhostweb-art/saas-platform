<?php

namespace Tests\Unit\Modules\MotionGraphics\DTO;

use App\Modules\MotionGraphics\DTO\MotionAssetReferenceDTO;
use InvalidArgumentException;
use Tests\TestCase;

class MotionAssetReferenceDTOTest extends TestCase
{
    public function test_it_builds_from_array(): void
    {
        $dto = MotionAssetReferenceDTO::fromArray([
            'asset_id' => 'motion-123',
            'version' => '3',
            'type' => 'lottie',
            'compatibility' => ['industry' => 'finance'],
        ]);

        $this->assertSame('motion-123', $dto->assetId);
        $this->assertSame(3, $dto->version);
        $this->assertSame('lottie', $dto->type);
        $this->assertSame(['industry' => 'finance'], $dto->compatibility);
    }

    public function test_to_array_round_trips(): void
    {
        $dto = new MotionAssetReferenceDTO('motion-1', 1, 'svg-js', ['section' => 'hero']);

        $this->assertSame([
            'asset_id' => 'motion-1',
            'version' => 1,
            'type' => 'svg-js',
            'compatibility' => ['section' => 'hero'],
        ], $dto->toArray());
    }

    public function test_it_rejects_forbidden_compatibility_keys(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new MotionAssetReferenceDTO('motion-1', 1, 'lottie', ['ai_metadata' => ['prompt' => 'x']]);
    }
}
