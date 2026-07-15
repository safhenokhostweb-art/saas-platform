<?php

namespace SaaSPlatform\Modules\MotionGraphics\Tests\Unit;

use Illuminate\Validation\ValidationException;
use SaaSPlatform\Modules\MotionGraphics\Contracts\MotionAssetPipelineContract;
use SaaSPlatform\Modules\MotionGraphics\Contracts\MotionAssetStorageContract;
use SaaSPlatform\Modules\MotionGraphics\Tests\TestCase;

final class AssetPipelineTest extends TestCase
{
    public function test_pipeline_compiles_optimizes_previews_checksums_and_uses_version_path(): void
    {
        $result = app(MotionAssetPipelineContract::class)->prepare($this->svgInput(), 'iran-network', 3);
        $this->assertSame('svg-js', $result['engine']);
        $this->assertSame(64, strlen($result['source_checksum']));
        $this->assertSame('/motion/iran-network/v3/animation.svg', $result['compiled_assets'][0]['url']);
        $this->assertSame('generated', $result['preview_metadata']['status']);
        $this->assertTrue(app(MotionAssetStorageContract::class)->exists('motion/iran-network/v3/animation.svg'));
    }

    /** @dataProvider unsafeInputs */
    public function test_pipeline_rejects_unsafe_inputs(array $mutations): void
    {
        $input = array_replace_recursive($this->svgInput(), $mutations);
        $this->expectException(ValidationException::class);
        app(MotionAssetPipelineContract::class)->prepare($input, 'unsafe', 1);
    }

    public static function unsafeInputs(): array
    {
        return [
            'path traversal' => [['files' => [0 => ['relative_path' => '../escape.svg']]]],
            'remote dependency' => [['dependencies' => ['https://evil.test/lib.js']]],
            'scripted svg' => [['files' => [0 => ['contents' => '<svg><script>alert(1)</script></svg>']]]],
            'unsupported engine' => [['engine' => 'unknown-engine']],
            'malformed svg' => [['files' => [0 => ['contents' => 'not-svg']]]],
        ];
    }

    public function test_pipeline_failure_removes_partial_output(): void
    {
        $input = $this->svgInput();
        $input['files'][] = ['relative_path' => '../fail.svg', 'contents' => 'x', 'mime' => 'image/svg+xml'];
        try {
            app(MotionAssetPipelineContract::class)->prepare($input, 'atomic', 1);
            $this->fail('Validation exception was expected.');
        } catch (ValidationException) {
            $this->assertFalse(app(MotionAssetStorageContract::class)->exists('motion/atomic/v1/animation.svg'));
        }
    }
}
