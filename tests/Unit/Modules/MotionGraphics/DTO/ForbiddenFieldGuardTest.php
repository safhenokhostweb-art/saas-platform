<?php

namespace Tests\Unit\Modules\MotionGraphics\DTO;

use App\Modules\MotionGraphics\DTO\ForbiddenFieldGuard;
use InvalidArgumentException;
use Tests\TestCase;

class ForbiddenFieldGuardTest extends TestCase
{
    public function test_it_allows_clean_payloads(): void
    {
        ForbiddenFieldGuard::assertClean(['industry' => 'finance', 'style' => 'modern'], 'compatibility');
        $this->assertTrue(true);
    }

    /**
     * @dataProvider forbiddenKeys
     */
    public function test_it_rejects_forbidden_keys(string $key): void
    {
        $this->expectException(InvalidArgumentException::class);

        ForbiddenFieldGuard::assertClean([$key => 'secret'], 'theme_tokens');
    }

    public static function forbiddenKeys(): array
    {
        return [
            ['source_definition'],
            ['editable_schema'],
            ['ai_metadata'],
            ['prompt'],
            ['approvals'],
            ['ownership'],
            ['credentials'],
            ['storage_path'],
        ];
    }

    public function test_it_rejects_forbidden_keys_nested_inside_arrays(): void
    {
        $this->expectException(InvalidArgumentException::class);

        ForbiddenFieldGuard::assertClean(['placement' => ['ai_metadata' => ['prompt' => 'x']]], 'placement_rules');
    }
}
