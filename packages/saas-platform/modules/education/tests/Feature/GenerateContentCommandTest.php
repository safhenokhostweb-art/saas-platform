<?php

namespace SaaSPlatform\Modules\Education\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Orchestra\Testbench\TestCase;
use SaaSPlatform\Modules\Education\Ai\Providers\NullAiContentProvider;
use SaaSPlatform\Modules\Education\Contracts\Ai\AiContentProviderInterface;
use SaaSPlatform\Modules\Education\Contracts\Services\ContentDeficitQueueServiceInterface;
use SaaSPlatform\Modules\Education\Contracts\Services\EducationAiContentGenerationServiceInterface;
use SaaSPlatform\Modules\Education\Contracts\Services\EducationDraftPersistenceServiceInterface;
use SaaSPlatform\Modules\Education\Contracts\Services\KnowledgeGapQueueServiceInterface;
use SaaSPlatform\Modules\Education\Dto\ContentDeficitQueueItemDto;
use SaaSPlatform\Modules\Education\Dto\GeneratedContentDto;
use SaaSPlatform\Modules\Education\Models\EducationArticle;
use SaaSPlatform\Modules\Education\Providers\EducationServiceProvider;
use SaaSPlatform\Modules\Education\Services\Education\EducationAiContentGenerationService;
use SaaSPlatform\Modules\Education\Services\Education\GeneratedContentValidator;

/**
 * EDU-15 Phase 6a — education:generate-content command
 */
class GenerateContentCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function getPackageProviders($app): array
    {
        return [EducationServiceProvider::class];
    }

    // ----- Null provider -----

    public function test_command_succeeds_silently_when_provider_disabled(): void
    {
        $this->bindNullProvider();

        $this->artisan('education:generate-content')
            ->expectsOutputToContain('AI provider is not enabled')
            ->assertSuccessful();
    }

    // ----- Dry-run -----

    public function test_dry_run_does_not_create_articles(): void
    {
        $this->bindFakeEnabledGenerator($this->validDto());

        $this->artisan('education:generate-content', ['--dry-run' => true])
            ->expectsOutputToContain('DRY-RUN')
            ->assertSuccessful();

        $this->assertDatabaseCount('edu_articles', 0);
    }

    public function test_dry_run_outputs_would_create_message(): void
    {
        $this->bindFakeEnabledGenerator($this->validDto());

        $this->artisan('education:generate-content', ['--dry-run' => true])
            ->expectsOutputToContain('Would create draft');
    }

    // ----- Module scoping -----

    public function test_module_option_is_passed_to_service(): void
    {
        $mock = $this->createMock(EducationAiContentGenerationServiceInterface::class);
        $mock->method('isProviderEnabled')->willReturn(true);
        $mock->expects($this->once())
            ->method('generateFromDeficits')
            ->with($this->anything(), 'invoices')
            ->willReturn(collect());

        $this->app->bind(EducationAiContentGenerationServiceInterface::class, fn() => $mock);
        $this->app->bind(EducationDraftPersistenceServiceInterface::class, fn() => $this->createMock(EducationDraftPersistenceServiceInterface::class));

        $this->artisan('education:generate-content', ['--module' => 'invoices'])
            ->assertSuccessful();
    }

    // ----- Site scoping -----

    public function test_site_option_is_passed_as_int(): void
    {
        $mock = $this->createMock(EducationAiContentGenerationServiceInterface::class);
        $mock->method('isProviderEnabled')->willReturn(true);
        $mock->expects($this->once())
            ->method('generateFromDeficits')
            ->with(5, $this->anything())
            ->willReturn(collect());

        $this->app->bind(EducationAiContentGenerationServiceInterface::class, fn() => $mock);
        $this->app->bind(EducationDraftPersistenceServiceInterface::class, fn() => $this->createMock(EducationDraftPersistenceServiceInterface::class));

        $this->artisan('education:generate-content', ['--site' => '5'])
            ->assertSuccessful();
    }

    // ----- Idempotency -----

    public function test_skips_existing_draft(): void
    {
        $persistence = $this->createMock(EducationDraftPersistenceServiceInterface::class);
        $persistence->method('draftExists')->willReturn(true);
        $persistence->expects($this->never())->method('persist');

        $this->app->bind(EducationDraftPersistenceServiceInterface::class, fn() => $persistence);
        $this->bindFakeEnabledGenerator($this->validDto());

        $this->artisan('education:generate-content')
            ->expectsOutputToContain('SKIP')
            ->assertSuccessful();
    }

    // ----- Transaction safety -----

    public function test_one_failure_does_not_abort_batch(): void
    {
        $dto1 = $this->validDto('Title One ');
        $dto2 = $this->validDto('Title Two ');

        $generator = $this->createMock(EducationAiContentGenerationServiceInterface::class);
        $generator->method('isProviderEnabled')->willReturn(true);
        $generator->method('generateFromDeficits')->willReturn(collect([$dto1, $dto2]));

        $persistence = $this->createMock(EducationDraftPersistenceServiceInterface::class);
        $persistence->method('draftExists')->willReturn(false);
        $persistence->method('persist')
            ->willReturnCallback(function (GeneratedContentDto $dto) {
                if ($dto->title === 'Title One ') {
                    throw new \RuntimeException('DB error');
                }
                $article = new EducationArticle(['title' => $dto->title]);
                $article->id = 99;
                return $article;
            });

        $this->app->bind(EducationAiContentGenerationServiceInterface::class, fn() => $generator);
        $this->app->bind(EducationDraftPersistenceServiceInterface::class, fn() => $persistence);

        $this->artisan('education:generate-content')
            ->expectsOutputToContain('FAIL')
            ->expectsOutputToContain('OK');
    }

    // -----------------------------------------------------------------------

    private function bindNullProvider(): void
    {
        $this->app->bind(AiContentProviderInterface::class, NullAiContentProvider::class);
    }

    private function bindFakeEnabledGenerator(GeneratedContentDto $dto): void
    {
        $deficitQueue = $this->createMock(ContentDeficitQueueServiceInterface::class);
        $deficitQueue->method('build')->willReturn(collect([
            new ContentDeficitQueueItemDto('invoices', 'articles', 20.0, 'Critical'),
        ]));

        $gapQueue = $this->createMock(KnowledgeGapQueueServiceInterface::class);
        $gapQueue->method('buildForSite')->willReturn(collect());

        $provider = $this->createMock(AiContentProviderInterface::class);
        $provider->method('isEnabled')->willReturn(true);
        $provider->method('providerName')->willReturn('mock');
        $provider->method('generate')->willReturn($dto);

        $service = new EducationAiContentGenerationService(
            $provider, $deficitQueue, $gapQueue, new GeneratedContentValidator(),
        );

        $this->app->bind(EducationAiContentGenerationServiceInterface::class, fn() => $service);
        $this->app->bind(EducationDraftPersistenceServiceInterface::class, function () {
            $mock = $this->createMock(EducationDraftPersistenceServiceInterface::class);
            $mock->method('draftExists')->willReturn(false);
            $mock->method('persist')->willReturnCallback(function (GeneratedContentDto $d) {
                $a = new EducationArticle(['title' => $d->title]);
                $a->id = 1;
                return $a;
            });
            return $mock;
        });
    }

    private function validDto(string $titlePrefix = 'عنوان مقاله '): GeneratedContentDto
    {
        return new GeneratedContentDto(
            title:           $titlePrefix . 'آموزشی',
            summary:         'خلاصه',
            body:            str_repeat('محتوا ', 15),
            tags:            [],
            confidenceScore: 80.0,
            provider:        'mock',
        );
    }
}
