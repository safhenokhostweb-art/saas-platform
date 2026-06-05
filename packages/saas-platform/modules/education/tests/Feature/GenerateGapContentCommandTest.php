<?php

namespace SaaSPlatform\Modules\Education\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Orchestra\Testbench\TestCase;
use SaaSPlatform\Modules\Education\Ai\Providers\NullAiContentProvider;
use SaaSPlatform\Modules\Education\Contracts\Ai\AiContentProviderInterface;
use SaaSPlatform\Modules\Education\Contracts\Services\ContentDeficitQueueServiceInterface;
use SaaSPlatform\Modules\Education\Contracts\Services\EducationAiContentGenerationServiceInterface;
use SaaSPlatform\Modules\Education\Contracts\Services\EducationDraftPersistenceServiceInterface;
use SaaSPlatform\Modules\Education\Contracts\Services\KnowledgeGapQueueServiceInterface;
use SaaSPlatform\Modules\Education\Dto\GeneratedContentDto;
use SaaSPlatform\Modules\Education\Dto\KnowledgeGapQueueItemDto;
use SaaSPlatform\Modules\Education\Models\EducationArticle;
use SaaSPlatform\Modules\Education\Providers\EducationServiceProvider;
use SaaSPlatform\Modules\Education\Services\Education\EducationAiContentGenerationService;
use SaaSPlatform\Modules\Education\Services\Education\GeneratedContentValidator;

/**
 * EDU-15 Phase 6b — education:generate-gap-content command
 */
class GenerateGapContentCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function getPackageProviders($app): array
    {
        return [EducationServiceProvider::class];
    }

    public function test_command_exits_silently_when_provider_disabled(): void
    {
        $this->app->bind(AiContentProviderInterface::class, NullAiContentProvider::class);

        $this->artisan('education:generate-gap-content')
            ->expectsOutputToContain('AI provider is not enabled')
            ->assertSuccessful();
    }

    public function test_dry_run_does_not_create_articles(): void
    {
        $this->bindFakeGapGenerator($this->validDto());

        $this->artisan('education:generate-gap-content', ['--dry-run' => true])
            ->expectsOutputToContain('DRY-RUN')
            ->assertSuccessful();

        $this->assertDatabaseCount('edu_articles', 0);
    }

    public function test_dry_run_outputs_would_create_message(): void
    {
        $this->bindFakeGapGenerator($this->validDto());

        $this->artisan('education:generate-gap-content', ['--dry-run' => true])
            ->expectsOutputToContain('Would create gap-remediation draft');
    }

    public function test_site_option_passed_to_service(): void
    {
        $mock = $this->createMock(EducationAiContentGenerationServiceInterface::class);
        $mock->method('isProviderEnabled')->willReturn(true);
        $mock->expects($this->once())
            ->method('generateFromGaps')
            ->with(3, $this->anything())
            ->willReturn(collect());

        $this->app->bind(EducationAiContentGenerationServiceInterface::class, fn() => $mock);
        $this->app->bind(EducationDraftPersistenceServiceInterface::class, fn() => $this->createMock(EducationDraftPersistenceServiceInterface::class));

        $this->artisan('education:generate-gap-content', ['--site' => '3'])
            ->assertSuccessful();
    }

    public function test_module_option_passed_to_service(): void
    {
        $mock = $this->createMock(EducationAiContentGenerationServiceInterface::class);
        $mock->method('isProviderEnabled')->willReturn(true);
        $mock->expects($this->once())
            ->method('generateFromGaps')
            ->with($this->anything(), 'payments')
            ->willReturn(collect());

        $this->app->bind(EducationAiContentGenerationServiceInterface::class, fn() => $mock);
        $this->app->bind(EducationDraftPersistenceServiceInterface::class, fn() => $this->createMock(EducationDraftPersistenceServiceInterface::class));

        $this->artisan('education:generate-gap-content', ['--module' => 'payments'])
            ->assertSuccessful();
    }

    public function test_skips_existing_gap_draft(): void
    {
        $persistence = $this->createMock(EducationDraftPersistenceServiceInterface::class);
        $persistence->method('draftExists')->willReturn(true);
        $persistence->expects($this->never())->method('persist');

        $this->app->bind(EducationDraftPersistenceServiceInterface::class, fn() => $persistence);
        $this->bindFakeGapGenerator($this->validDto());

        $this->artisan('education:generate-gap-content')
            ->expectsOutputToContain('SKIP')
            ->assertSuccessful();
    }

    public function test_empty_gap_queue_succeeds_silently(): void
    {
        $generator = $this->createMock(EducationAiContentGenerationServiceInterface::class);
        $generator->method('isProviderEnabled')->willReturn(true);
        $generator->method('generateFromGaps')->willReturn(collect());

        $this->app->bind(EducationAiContentGenerationServiceInterface::class, fn() => $generator);
        $this->app->bind(EducationDraftPersistenceServiceInterface::class, fn() => $this->createMock(EducationDraftPersistenceServiceInterface::class));

        $this->artisan('education:generate-gap-content')
            ->expectsOutputToContain('queue empty')
            ->assertSuccessful();
    }

    public function test_failed_persistence_returns_failure_exit_code(): void
    {
        $persistence = $this->createMock(EducationDraftPersistenceServiceInterface::class);
        $persistence->method('draftExists')->willReturn(false);
        $persistence->method('persist')->willThrowException(new \RuntimeException('DB down'));

        $this->app->bind(EducationDraftPersistenceServiceInterface::class, fn() => $persistence);
        $this->bindFakeGapGenerator($this->validDto());

        $this->artisan('education:generate-gap-content')
            ->expectsOutputToContain('FAIL')
            ->assertFailed();
    }

    // -----------------------------------------------------------------------

    private function bindFakeGapGenerator(GeneratedContentDto $dto): void
    {
        $now      = \Carbon\Carbon::now();
        $gapQueue = $this->createMock(KnowledgeGapQueueServiceInterface::class);
        $gapQueue->method('buildForSite')->willReturn(collect([
            new KnowledgeGapQueueItemDto(1, 1, 'invoices', 90.0, 'Critical', $now),
        ]));

        $provider = $this->createMock(AiContentProviderInterface::class);
        $provider->method('isEnabled')->willReturn(true);
        $provider->method('providerName')->willReturn('mock');
        $provider->method('generate')->willReturn($dto);

        $service = new EducationAiContentGenerationService(
            $provider,
            $this->createMock(ContentDeficitQueueServiceInterface::class),
            $gapQueue,
            new GeneratedContentValidator(),
        );

        $this->app->bind(EducationAiContentGenerationServiceInterface::class, fn() => $service);
        $this->app->bind(EducationDraftPersistenceServiceInterface::class, function () use ($dto) {
            $mock = $this->createMock(EducationDraftPersistenceServiceInterface::class);
            $mock->method('draftExists')->willReturn(false);
            $mock->method('persist')->willReturnCallback(function () use ($dto) {
                $a = new EducationArticle(['title' => $dto->title]);
                $a->id = 1;
                return $a;
            });
            return $mock;
        });
    }

    private function validDto(): GeneratedContentDto
    {
        return new GeneratedContentDto(
            title:           'درک شکاف دانشی در فاکتورها',
            summary:         'خلاصه',
            body:            str_repeat('محتوا ', 15),
            tags:            [],
            confidenceScore: 85.0,
            provider:        'mock',
        );
    }
}
