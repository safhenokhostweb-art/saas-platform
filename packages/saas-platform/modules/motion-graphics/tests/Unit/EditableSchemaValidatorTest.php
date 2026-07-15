<?php

namespace SaaSPlatform\Modules\MotionGraphics\Tests\Unit;

use Illuminate\Validation\ValidationException;
use SaaSPlatform\Modules\MotionGraphics\Services\EditableSchemaValidator;
use SaaSPlatform\Modules\MotionGraphics\Tests\TestCase;

final class EditableSchemaValidatorTest extends TestCase
{
    public function test_nested_editable_values_are_validated(): void
    {
        $schema = $this->motionData('schema')['editable_schema_json'];
        $current = $this->svgInput()['source_definition'];
        $proposed = $current;
        $proposed['style']['speed'] = 2.5;
        $this->assertSame($proposed, app(EditableSchemaValidator::class)->validateComplete($current, $proposed, $schema));
    }

    /** @dataProvider invalidProposals */
    public function test_invalid_complete_definitions_are_rejected(callable $mutate): void
    {
        $schema = $this->motionData('schema-invalid')['editable_schema_json'];
        $current = $this->svgInput()['source_definition'];
        $this->expectException(ValidationException::class);
        app(EditableSchemaValidator::class)->validateComplete($current, $mutate($current), $schema);
    }

    public static function invalidProposals(): array
    {
        return [
            'missing field' => [fn ($value) => array_diff_key($value, ['title' => true])],
            'wrong nested type' => [function ($value) { $value['style']['speed'] = 'fast'; return $value; }],
            'range exceeded' => [function ($value) { $value['style']['speed'] = 9; return $value; }],
            'malicious extra field' => [function ($value) { $value['script'] = 'evil'; return $value; }],
        ];
    }
}
