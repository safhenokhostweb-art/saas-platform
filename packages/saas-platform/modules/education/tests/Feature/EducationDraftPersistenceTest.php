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
 * EDU-15 Phase 5 — Draft Persistence
 */
class EducationDraftPersistenceTest extends TestCase
{
    use RefreshDatabase;

    protected function getPackageProviders($app): array
    {
        return [EducationServiceProvider::class];
    }

    private function service(): EducationDraftPersistenceService
    {
        return new EducationDraftPersistenceService();
    }

    // ----- Status is always draft -----

    public function test_persisted_article_has_draft_status(): void
    {
        $article = $this->service()->persist($this->validContent(), $this->makeJob(siteId: 1), authorId: 1);
        $this->assertSame(EducationArticle::STATUS_DRAFT, $article->status);
    }

    public function test_persisted_article_is_never_published(): void
    {
        $article = $this->service()->persist($this->validContent(), $this->makeJob(siteId: 1), authorId: 1);
        $this->assertNotSame(EducationArticle::STATUS_PUBLISHED, $article->status);
    }

    // ----- auto_generated flag -----

    public function test_persisted_article_has_auto_generated_flag(): void
    {
        $article = $this->service()->persist($this->validContent(), $this->makeJob(siteId: 1), authorId: 1);
        $this->assertTrue($article->auto_generated);
    }

    // ----- Site isolation -----

    public function test_article_site_id_matches_job_site_id(): void
    {
        $article = $this->service()->persist($this->validContent(), $this->makeJob(siteId: 7), authorId: 1);
        $this->assertSame(7, $article->site_id);
    }

    public function test_null_site_id_stored_as_null(): void
    {
        $article = $this->service()->persist($this->validContent(), $this->makeJob(siteId: null), authorId: 1);
        $this->assertNull($article->site_id);
    }

    // ----- Generation log -----

    public function test_generation_log_created_on_persist(): void
    {
        $this->service()->persist($this->validContent(), $this->makeJob(siteId: 1), authorId: 1);

        $this->assertDatabaseHas('edu_generation_logs', [
            'status'   => EducationGenerationLog::STATUS_SUCCESS,
            'provider' => 'null',
        ]);
    }

    public function test_generation_log_links_to_article(): void
    {
        $article = $this->service()->persist($this->validContent(), $this->makeJob(siteId: 1), authorId: 1);

        $this->assertDatabaseHas('edu_generation_logs', [
            'edu_article_id' => $article->id,
        ]);
    }

    // ----- Idempotency guard -----

    public function test_draft_exists_returns_true_for_duplicate(): void
    {
        $job     = $this->makeJob(siteId: 2);
        $content = $this->validContent();

        $this->service()->persist($content, $job, authorId: 1);

        $this->assertTrue($this->service()->draftExists($job, $content->title));
    }

    public function test_draft_exists_returns_false_for_different_title(): void
    {
        $job     = $this->makeJob(siteId: 2);
        $content = $this->validContent();

        $this->service()->persist($content, $job, authorId: 1);

        $this->assertFalse($this->service()->draftExists($job, 'A completely different title'));
    }

    public function test_draft_exists_is_site_scoped(): void
    {
        $jobSite2 = $this->makeJob(siteId: 2);
        $content  = $this->validContent();

        $this->service()->persist($content, $jobSite2, authorId: 1);

        $jobSite3 = $this->makeJob(siteId: 3);
        $this->assertFalse($this->service()->draftExists($jobSite3, $content->title));
    }

    // -----------------------------------------------------------------------

    private function makeJob(
        ?int   $siteId      = 1,
        string $moduleSlug  = 'invoices',
        string $contentType = 'articles',
    ): ContentGenerationJobDto {
        return new ContentGenerationJobDto(
            moduleSlug:   $moduleSlug,
            contentType:  $contentType,
            priority:     'High',
            siteId:       $siteId,
            sourceReason: 'content_deficit',
        );
    }

    private function validContent(): GeneratedContentDto
    {
        return new GeneratedContentDto(
            title:           'عنوان مقاله تستی برای فاکتور',
            summary:         'خلاصه مقاله',
            body:            str_repeat('محتوای متن ', 10),
            tags:            ['invoices'],
            confidenceScore: 78.0,
            provider:        'null',
        );
    }
}
