<?php

namespace SaaSPlatform\Modules\Education\Tests\Feature;

use Orchestra\Testbench\TestCase;
use SaaSPlatform\Modules\Education\Ai\Providers\ClaudeAiContentProvider;
use SaaSPlatform\Modules\Education\Ai\Providers\NullAiContentProvider;
use SaaSPlatform\Modules\Education\Contracts\Services\ContentDeficitQueueServiceInterface;
use SaaSPlatform\Modules\Education\Dto\ContentGenerationJobDto;
use SaaSPlatform\Modules\Education\Dto\GeneratedContentDto;
use SaaSPlatform\Modules\Education\Exceptions\AiProviderException;
use SaaSPlatform\Modules\Education\Exceptions\InvalidGeneratedContentException;
use SaaSPlatform\Modules\Education\Generation\ArticleDraftGenerator;
use SaaSPlatform\Modules\Education\Generation\LearningPathDraftGenerator;
use SaaSPlatform\Modules\Education\Generation\WalkthroughDraftGenerator;
use SaaSPlatform\Modules\Education\Services\Education\GeneratedContentValidator;

/**
 * EDU-15 Phase 2 — Draft Generator pipeline classes
 */
class DraftGeneratorTest extends TestCase
{
    private function makeJob(?int $siteId = 1, string $contentType = 'articles'): ContentGenerationJobDto
    {
        return new ContentGenerationJobDto(
            moduleSlug:   'invoices',
            contentType:  $contentType,
            priority:     'High',
            siteId:       $siteId,
            sourceReason: 'content_deficit',
        );
    }

    private function makeValidDto(string $title = 'Test Article'): GeneratedContentDto
    {
        return new GeneratedContentDto(
            title:           $title,
            summary:         'A concise summary.',
            body:            str_repeat('Lorem ipsum dolor sit amet. ', 10),
            tags:            ['invoices', 'billing'],
            confidenceScore: 80.0,
            provider:        'test',
        );
    }

    // ----- ArticleDraftGenerator -----

    public function test_article_generator_forces_articles_content_type(): void
    {
        $capturedJob = null;
        $provider = $this->createMock(\SaaSPlatform\Modules\Education\Contracts\Ai\AiContentProviderInterface::class);
        $provider->method('isEnabled')->willReturn(true);
        $provider->expects($this->once())
            ->method('generate')
            ->willReturnCallback(function (ContentGenerationJobDto $job) use (&$capturedJob) {
                $capturedJob = $job;
                return $this->makeValidDto();
            });

        $gen = new ArticleDraftGenerator($provider, new GeneratedContentValidator());
        $job = $this->makeJob(siteId: 1, contentType: 'remediation');
        $gen->generate($job);

        $this->assertSame(ContentDeficitQueueServiceInterface::TYPE_ARTICLES, $capturedJob->contentType);
    }

    public function test_article_generator_preserves_site_and_module(): void
    {
        $capturedJob = null;
        $provider = $this->createMock(\SaaSPlatform\Modules\Education\Contracts\Ai\AiContentProviderInterface::class);
        $provider->method('isEnabled')->willReturn(true);
        $provider->method('generate')->willReturnCallback(function ($job) use (&$capturedJob) {
            $capturedJob = $job;
            return $this->makeValidDto();
        });

        $gen = new ArticleDraftGenerator($provider, new GeneratedContentValidator());
        $gen->generate($this->makeJob(siteId: 42));

        $this->assertSame(42, $capturedJob->siteId);
        $this->assertSame('invoices', $capturedJob->moduleSlug);
    }

    public function test_article_generator_validates_result(): void
    {
        $provider = $this->createMock(\SaaSPlatform\Modules\Education\Contracts\Ai\AiContentProviderInterface::class);
        $provider->method('isEnabled')->willReturn(true);
        $provider->method('generate')->willReturn(new GeneratedContentDto(
            title: 'hi',  // too short — fails validation
            summary: '',
            body: '',
            tags: [],
            confidenceScore: 50.0,
            provider: 'test',
        ));

        $gen = new ArticleDraftGenerator($provider, new GeneratedContentValidator());

        $this->expectException(InvalidGeneratedContentException::class);
        $gen->generate($this->makeJob());
    }

    public function test_article_generator_delegates_provider_exception(): void
    {
        $provider = $this->createMock(\SaaSPlatform\Modules\Education\Contracts\Ai\AiContentProviderInterface::class);
        $provider->method('isEnabled')->willReturn(true);
        $provider->method('generate')->willThrowException(new AiProviderException('provider down'));

        $gen = new ArticleDraftGenerator($provider, new GeneratedContentValidator());

        $this->expectException(AiProviderException::class);
        $gen->generate($this->makeJob());
    }

    public function test_article_generator_is_enabled_proxies_provider(): void
    {
        $provider = new NullAiContentProvider();
        $gen = new ArticleDraftGenerator($provider, new GeneratedContentValidator());
        $this->assertFalse($gen->isEnabled());
    }

    public function test_article_generator_provider_name_proxies_provider(): void
    {
        $provider = new NullAiContentProvider();
        $gen = new ArticleDraftGenerator($provider, new GeneratedContentValidator());
        $this->assertSame('null', $gen->providerName());
    }

    // ----- WalkthroughDraftGenerator -----

    public function test_walkthrough_generator_forces_walkthroughs_content_type(): void
    {
        $capturedJob = null;
        $provider = $this->createMock(\SaaSPlatform\Modules\Education\Contracts\Ai\AiContentProviderInterface::class);
        $provider->method('isEnabled')->willReturn(true);
        $provider->method('generate')->willReturnCallback(function ($job) use (&$capturedJob) {
            $capturedJob = $job;
            return $this->makeValidDto();
        });

        $gen = new WalkthroughDraftGenerator($provider, new GeneratedContentValidator());
        $gen->generate($this->makeJob(siteId: 1, contentType: 'articles'));

        $this->assertSame(ContentDeficitQueueServiceInterface::TYPE_WALKTHROUGHS, $capturedJob->contentType);
    }

    public function test_walkthrough_generator_validates_result(): void
    {
        $provider = $this->createMock(\SaaSPlatform\Modules\Education\Contracts\Ai\AiContentProviderInterface::class);
        $provider->method('isEnabled')->willReturn(true);
        $provider->method('generate')->willReturn(new GeneratedContentDto('', '', '', [], 50.0, 'test'));

        $gen = new WalkthroughDraftGenerator($provider, new GeneratedContentValidator());

        $this->expectException(InvalidGeneratedContentException::class);
        $gen->generate($this->makeJob());
    }

    public function test_walkthrough_generator_is_disabled_with_null_provider(): void
    {
        $gen = new WalkthroughDraftGenerator(new NullAiContentProvider(), new GeneratedContentValidator());
        $this->assertFalse($gen->isEnabled());
    }

    // ----- LearningPathDraftGenerator -----

    public function test_learning_path_generator_forces_learning_paths_content_type(): void
    {
        $capturedJob = null;
        $provider = $this->createMock(\SaaSPlatform\Modules\Education\Contracts\Ai\AiContentProviderInterface::class);
        $provider->method('isEnabled')->willReturn(true);
        $provider->method('generate')->willReturnCallback(function ($job) use (&$capturedJob) {
            $capturedJob = $job;
            return $this->makeValidDto();
        });

        $gen = new LearningPathDraftGenerator($provider, new GeneratedContentValidator());
        $gen->generate($this->makeJob(siteId: 1, contentType: 'articles'));

        $this->assertSame(ContentDeficitQueueServiceInterface::TYPE_LEARNING_PATHS, $capturedJob->contentType);
    }

    public function test_learning_path_generator_preserves_null_site(): void
    {
        $capturedJob = null;
        $provider = $this->createMock(\SaaSPlatform\Modules\Education\Contracts\Ai\AiContentProviderInterface::class);
        $provider->method('isEnabled')->willReturn(true);
        $provider->method('generate')->willReturnCallback(function ($job) use (&$capturedJob) {
            $capturedJob = $job;
            return $this->makeValidDto();
        });

        $gen = new LearningPathDraftGenerator($provider, new GeneratedContentValidator());
        $gen->generate($this->makeJob(siteId: null));

        $this->assertNull($capturedJob->siteId);
    }

    public function test_learning_path_generator_is_disabled_with_null_provider(): void
    {
        $gen = new LearningPathDraftGenerator(new NullAiContentProvider(), new GeneratedContentValidator());
        $this->assertFalse($gen->isEnabled());
    }

    // ----- Cross-generator -----

    /** @dataProvider allGenerators */
    public function test_all_generators_return_dto_on_success(string $generatorClass): void
    {
        $provider = $this->createMock(\SaaSPlatform\Modules\Education\Contracts\Ai\AiContentProviderInterface::class);
        $provider->method('isEnabled')->willReturn(true);
        $provider->method('generate')->willReturn($this->makeValidDto());

        $gen = new $generatorClass($provider, new GeneratedContentValidator());
        $result = $gen->generate($this->makeJob());

        $this->assertInstanceOf(GeneratedContentDto::class, $result);
    }

    public static function allGenerators(): array
    {
        return [
            'article'       => [ArticleDraftGenerator::class],
            'walkthrough'   => [WalkthroughDraftGenerator::class],
            'learning_path' => [LearningPathDraftGenerator::class],
        ];
    }
}
