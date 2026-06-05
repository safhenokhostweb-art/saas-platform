<?php

namespace SaaSPlatform\Modules\Education\Tests\Feature;

use Orchestra\Testbench\TestCase;
use SaaSPlatform\Modules\Education\Dto\GeneratedContentDto;
use SaaSPlatform\Modules\Education\Exceptions\InvalidGeneratedContentException;
use SaaSPlatform\Modules\Education\Services\Education\GeneratedContentValidator;

/**
 * EDU-15 Phase 4 — Validator
 */
class GeneratedContentValidatorTest extends TestCase
{
    private GeneratedContentValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->validator = new GeneratedContentValidator();
    }

    public function test_valid_dto_passes_without_exception(): void
    {
        $dto = $this->validDto();
        $this->validator->validate($dto);
        $this->assertTrue(true); // no exception
    }

    public function test_empty_title_fails(): void
    {
        $this->expectException(InvalidGeneratedContentException::class);
        $dto = $this->validDto(title: '');
        $this->validator->validate($dto);
    }

    public function test_title_below_min_length_fails(): void
    {
        $this->expectException(InvalidGeneratedContentException::class);
        $dto = $this->validDto(title: 'Hi');
        $this->validator->validate($dto);
    }

    public function test_title_at_min_length_passes(): void
    {
        $dto = $this->validDto(title: 'Hello');
        $this->validator->validate($dto);
        $this->assertTrue(true);
    }

    public function test_title_exceeding_max_length_fails(): void
    {
        $this->expectException(InvalidGeneratedContentException::class);
        $dto = $this->validDto(title: str_repeat('A', 256));
        $this->validator->validate($dto);
    }

    public function test_empty_body_fails(): void
    {
        $this->expectException(InvalidGeneratedContentException::class);
        $dto = $this->validDto(body: '');
        $this->validator->validate($dto);
    }

    public function test_body_below_min_length_fails(): void
    {
        $this->expectException(InvalidGeneratedContentException::class);
        $dto = $this->validDto(body: 'Short body.');
        $this->validator->validate($dto);
    }

    public function test_body_at_min_length_passes(): void
    {
        $dto = $this->validDto(body: str_repeat('a', 100));
        $this->validator->validate($dto);
        $this->assertTrue(true);
    }

    public function test_body_exceeding_max_length_fails(): void
    {
        $this->expectException(InvalidGeneratedContentException::class);
        $dto = $this->validDto(body: str_repeat('a', 100_001));
        $this->validator->validate($dto);
    }

    public function test_body_with_empty_section_tags_fails(): void
    {
        $this->expectException(InvalidGeneratedContentException::class);
        $dto = $this->validDto(body: str_repeat('content ', 20) . '<section></section>');
        $this->validator->validate($dto);
    }

    public function test_body_with_whitespace_only_section_fails(): void
    {
        $this->expectException(InvalidGeneratedContentException::class);
        $dto = $this->validDto(body: str_repeat('content ', 20) . '<section>   </section>');
        $this->validator->validate($dto);
    }

    public function test_confidence_score_below_zero_fails(): void
    {
        $this->expectException(InvalidGeneratedContentException::class);
        $dto = $this->validDto(confidenceScore: -0.1);
        $this->validator->validate($dto);
    }

    public function test_confidence_score_above_100_fails(): void
    {
        $this->expectException(InvalidGeneratedContentException::class);
        $dto = $this->validDto(confidenceScore: 100.1);
        $this->validator->validate($dto);
    }

    public function test_confidence_score_zero_is_valid(): void
    {
        $dto = $this->validDto(confidenceScore: 0.0);
        $this->validator->validate($dto);
        $this->assertTrue(true);
    }

    public function test_confidence_score_100_is_valid(): void
    {
        $dto = $this->validDto(confidenceScore: 100.0);
        $this->validator->validate($dto);
        $this->assertTrue(true);
    }

    public function test_exception_contains_error_list(): void
    {
        try {
            $this->validator->validate($this->validDto(title: '', body: ''));
            $this->fail('Expected exception not thrown.');
        } catch (InvalidGeneratedContentException $e) {
            $this->assertNotEmpty($e->getErrors());
            $this->assertIsArray($e->getErrors());
        }
    }

    // -----------------------------------------------------------------------

    private function validDto(
        string $title = 'Valid Article Title Here',
        string $body = '',
        float  $confidenceScore = 75.0,
    ): GeneratedContentDto {
        return new GeneratedContentDto(
            title:           $title,
            summary:         'Summary text',
            body:            $body ?: str_repeat('محتوای مقاله ', 10),
            tags:            ['general'],
            confidenceScore: $confidenceScore,
            provider:        'null',
        );
    }
}
