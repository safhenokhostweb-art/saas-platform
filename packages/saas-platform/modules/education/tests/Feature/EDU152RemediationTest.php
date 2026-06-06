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
 * EDU-15.2 Remediation — verifies F-02, RC-01, and S-02 fixes.
 */
class EDU152RemediationTest extends TestCase
{
    use RefreshDatabase;

    protected function getPackageProviders($app): array
    {
        return [EducationServiceProvider::class];
    }

    // =========================================================================
    // F-02 — Generation Analytics Accuracy
    // =========================================================================

    public function test_logFailure_writes_validation_failed_status(): void
    {
        $service = new EducationDraftPersistenceService();
        $service->logFailure($this->job(), EducationGenerationLog::STATUS_VALIDATION_FAILED, 'claude');

        $this->assertDatabaseHas('edu_generation_logs', [
            'status'       => 'validation_failed',
            'provider'     => 'claude',
            'module_slug'  => 'invoices',
            'site_id'      => 1,
        ]);
    }

    public function test_logFailure_writes_provider_error_status(): void
    {
        $service = new EducationDraftPersistenceService();
        $service->logFailure($this->job(), EducationGenerationLog::STATUS_PROVIDER_ERROR, 'openai');

        $this->assertDatabaseHas('edu_generation_logs', [
            'status'   => 'provider_error',
            'provider' => 'openai',
        ]);
    }

    public function test_logFailure_does_not_create_any_article(): void
    {
        $service = new EducationDraftPersistenceService();
        $service->logFailure($this->job(), EducationGenerationLog::STATUS_PROVIDER_ERROR, 'null');

        $this->assertDatabaseCount('edu_articles', 0);
    }

    public function test_logFailure_null_site_id_stored_as_null(): void
    {
        $service = new EducationDraftPersistenceService();
        $service->logFailure(
            new ContentGenerationJobDto('invoices', 'articles', 'High', null, 'content_deficit'),
            EducationGenerationLog::STATUS_VALIDATION_FAILED,
            'null',
        );

        $log = EducationGenerationLog::first();
        $this->assertNull($log->site_id);
    }

    public function test_logFailure_edu_article_id_is_null(): void
    {
        $service = new EducationDraftPersistenceService();
        $service->logFailure($this->job(), EducationGenerationLog::STATUS_PROVIDER_ERROR, 'claude');

        $log = EducationGenerationLog::first();
        $this->assertNull($log->edu_article_id);
    }

    public function test_logFailure_confidence_score_is_null(): void
    {
        $service = new EducationDraftPersistenceService();
        $service->logFailure($this->job(), EducationGenerationLog::STATUS_VALIDATION_FAILED, 'null');

        $log = EducationGenerationLog::first();
        $this->assertNull($log->confidence_score);
    }

    public function test_success_rate_becomes_accurate_with_mixed_statuses(): void
    {
        $service = new EducationDraftPersistenceService();

        // 1 success
        $service->persist($this->content('Success Article'), $this->job(), authorId: 1);

        // 1 validation failure
        $service->logFailure($this->job('invoices2'), EducationGenerationLog::STATUS_VALIDATION_FAILED, 'claude');

        // 1 provider error
        $service->logFailure($this->job('invoices3'), EducationGenerationLog::STATUS_PROVIDER_ERROR, 'claude');

        $total   = EducationGenerationLog::where('site_id', 1)->count();
        $success = EducationGenerationLog::where('site_id', 1)->where('status', 'success')->count();

        $this->assertSame(3, $total);
        $this->assertSame(1, $success);
        // success_rate = 1/3 ≈ 0.3333 — not 1.0
        $this->assertEqualsWithDelta(1 / 3, round($success / $total, 4), 0.0001);
    }

    // =========================================================================
    // RC-01 — Duplicate Draft Race Condition
    // =========================================================================

    public function test_persist_is_idempotent_when_called_twice_with_same_data(): void
    {
        $service = new EducationDraftPersistenceService();
        $job     = $this->job();
        $content = $this->content('Idempotent Title');

        $first  = $service->persist($content, $job, authorId: 1);
        $second = $service->persist($content, $job, authorId: 1);

        // Same row returned — no duplicate created
        $this->assertSame($first->id, $second->id);
        $this->assertDatabaseCount('edu_articles', 1);
    }

    public function test_persist_idempotent_logs_only_one_success_entry(): void
    {
        $service = new EducationDraftPersistenceService();
        $job     = $this->job();
        $content = $this->content('Log Idempotent Title');

        $service->persist($content, $job, authorId: 1);
        $service->persist($content, $job, authorId: 1);

        // Only 1 log row (the second call returns the existing article without inserting a log)
        $this->assertDatabaseCount('edu_generation_logs', 1);
    }

    public function test_persist_creates_separate_drafts_for_different_titles(): void
    {
        $service = new EducationDraftPersistenceService();
        $job     = $this->job();

        $service->persist($this->content('Title Alpha'), $job, authorId: 1);
        $service->persist($this->content('Title Beta'),  $job, authorId: 1);

        $this->assertDatabaseCount('edu_articles', 2);
    }

    public function test_persist_creates_separate_drafts_for_different_modules(): void
    {
        $service = new EducationDraftPersistenceService();

        $service->persist($this->content('Same Title'), $this->job('invoices'), authorId: 1);
        $service->persist($this->content('Same Title'), $this->job('payments'), authorId: 1);

        $this->assertDatabaseCount('edu_articles', 2);
    }

    public function test_persist_creates_separate_drafts_for_different_sites(): void
    {
        $service = new EducationDraftPersistenceService();

        $service->persist($this->content('Same Title'), $this->job('invoices', siteId: 1), authorId: 1);
        $service->persist($this->content('Same Title'), $this->job('invoices', siteId: 2), authorId: 1);

        $this->assertDatabaseCount('edu_articles', 2);
    }

    // =========================================================================
    // S-02 — Authorization
    // =========================================================================

    public function test_non_admin_user_gets_403(): void
    {
        $nonAdmin = $this->makeUser(isAdmin: false);

        $response = $this->actingAs($nonAdmin)->getJson('/api/v1/education/generated-content');

        $response->assertStatus(403);
    }

    public function test_admin_user_gets_200(): void
    {
        $admin = $this->makeUser(isAdmin: true);

        $response = $this->actingAs($admin)->getJson('/api/v1/education/generated-content');

        $response->assertStatus(200);
    }

    public function test_unauthenticated_request_still_returns_401(): void
    {
        $this->getJson('/api/v1/education/generated-content')->assertStatus(401);
    }

    public function test_admin_sees_response_shape(): void
    {
        $admin = $this->makeUser(isAdmin: true);

        $response = $this->actingAs($admin)->getJson('/api/v1/education/generated-content');

        $response->assertJsonStructure([
            'data' => [
                'draft_count',
                'approved_count',
                'rejected_count',
                'provider_usage',
                'recent_drafts',
            ],
        ]);
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    private function job(string $moduleSlug = 'invoices', ?int $siteId = 1): ContentGenerationJobDto
    {
        return new ContentGenerationJobDto(
            moduleSlug:   $moduleSlug,
            contentType:  'articles',
            priority:     'High',
            siteId:       $siteId,
            sourceReason: 'content_deficit',
        );
    }

    private function content(string $title): GeneratedContentDto
    {
        return new GeneratedContentDto(
            title:           $title,
            summary:         'Summary',
            body:            str_repeat('Body content text. ', 6),
            tags:            [],
            confidenceScore: 75.0,
            provider:        'claude',
        );
    }

    private function makeUser(bool $isAdmin): object
    {
        return new class($isAdmin) {
            public int    $id       = 1;
            public string $role     = 'user';
            public bool   $is_admin;

            public function __construct(bool $isAdmin)
            {
                $this->is_admin = $isAdmin;
            }

            public function can(string $ability): bool
            {
                return false;
            }
        };
    }
}
