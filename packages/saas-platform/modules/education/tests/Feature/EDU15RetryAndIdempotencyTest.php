<?php

namespace SaaSPlatform\Modules\Education\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Orchestra\Testbench\TestCase;
use SaaSPlatform\Modules\Education\Dto\ContentGenerationJobDto;
use SaaSPlatform\Modules\Education\Dto\GeneratedContentDto;
use SaaSPlatform\Modules\Education\Models\EducationArticle;
use SaaSPlatform\Modules\Education\Models\EducationGenerationLog;
use SaaSPlatform\Modules\Education\Providers\EducationServiceProvider;
use SaaSPlatform\Modules\Education\Services\Education\EducationDraftPersistenceService;

/**
 * EDU-15 Retry Safety & Idempotency
 */
class EDU15RetryAndIdempotencyTest extends TestCase
{
    use RefreshDatabase;

    protected function getPackageProviders($app): array
    {
        return [EducationServiceProvider::class];
    }

    // ----- draftExists idempotency guard -----

    public function test_draft_exists_prevents_double_creation(): void
    {
        $service = new EducationDraftPersistenceService();
        $job     = $this->job();
        $content = $this->content('Idempotent Title');

        $service->persist($content, $job, 1);

        // Simulate a command retry — draftExists should catch this
        $this->assertTrue($service->draftExists($job, 'Idempotent Title'));

        // Manually create again only if draftExists returns false (simulates command logic)
        if (! $service->draftExists($job, 'Idempotent Title')) {
            $service->persist($content, $job, 1);
        }

        $this->assertDatabaseCount('edu_articles', 1);
    }

    // ----- Persist is atomic (transaction) -----

    public function test_article_and_log_are_created_together(): void
    {
        $service = new EducationDraftPersistenceService();

        $service->persist($this->content('Atomic Article'), $this->job(), 1);

        $this->assertDatabaseCount('edu_articles', 1);
        $this->assertDatabaseCount('edu_generation_logs', 1);
    }

    // ----- Multiple retries produce bounded growth -----

    public function test_consecutive_persist_calls_create_separate_entries(): void
    {
        $service = new EducationDraftPersistenceService();
        $job     = $this->job();

        // Different titles — each should be stored independently
        $service->persist($this->content('Article A'), $job, 1);
        $service->persist($this->content('Article B'), $job, 1);

        $this->assertDatabaseCount('edu_articles', 2);
        $this->assertDatabaseCount('edu_generation_logs', 2);
    }

    // ----- Generation log status is 'success' on valid persist -----

    public function test_persisted_log_status_is_success(): void
    {
        (new EducationDraftPersistenceService())->persist($this->content('Log Status Test'), $this->job(), 1);

        $this->assertDatabaseHas('edu_generation_logs', [
            'status' => EducationGenerationLog::STATUS_SUCCESS,
        ]);
    }

    // ----- Draft status never changes to published during persist -----

    public function test_persisted_article_remains_draft_after_multiple_persists(): void
    {
        $service = new EducationDraftPersistenceService();
        $service->persist($this->content('Never Publish'), $this->job(), 1);
        $service->persist($this->content('Still Draft'),   $this->job(), 1);

        $nonDraft = EducationArticle::where('auto_generated', true)
            ->where('status', '!=', EducationArticle::STATUS_DRAFT)
            ->count();

        $this->assertSame(0, $nonDraft);
    }

    // ----- draftExists is module AND site scoped -----

    public function test_draft_exists_is_false_for_different_module(): void
    {
        $service = new EducationDraftPersistenceService();
        $service->persist($this->content('Module X Title'), $this->job('invoices'), 1);

        $differentModuleJob = $this->job('payments');
        $this->assertFalse($service->draftExists($differentModuleJob, 'Module X Title'));
    }

    // -----------------------------------------------------------------------

    private function job(string $moduleSlug = 'invoices'): ContentGenerationJobDto
    {
        return new ContentGenerationJobDto($moduleSlug, 'articles', 'High', 1, 'content_deficit');
    }

    private function content(string $title): GeneratedContentDto
    {
        return new GeneratedContentDto(
            title:           $title,
            summary:         'Summary',
            body:            str_repeat('Content body text. ', 6),
            tags:            [],
            confidenceScore: 70.0,
            provider:        'null',
        );
    }
}
