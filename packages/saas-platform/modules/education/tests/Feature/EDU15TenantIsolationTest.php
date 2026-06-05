<?php

namespace SaaSPlatform\Modules\Education\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Orchestra\Testbench\TestCase;
use SaaSPlatform\Modules\Education\Contracts\Services\EducationDraftPersistenceServiceInterface;
use SaaSPlatform\Modules\Education\Dto\ContentGenerationJobDto;
use SaaSPlatform\Modules\Education\Dto\GeneratedContentDto;
use SaaSPlatform\Modules\Education\Models\EducationArticle;
use SaaSPlatform\Modules\Education\Models\EducationGenerationLog;
use SaaSPlatform\Modules\Education\Providers\EducationServiceProvider;
use SaaSPlatform\Modules\Education\Services\Education\EducationDraftPersistenceService;

/**
 * EDU-15 Security — Tenant Isolation
 *
 * Verifies that content generated for Site A is never accessible via Site B.
 * Verifies generation logs carry the correct site_id.
 * Verifies null siteId scoping is separate from any concrete site.
 */
class EDU15TenantIsolationTest extends TestCase
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

    // ----- Cross-site article isolation -----

    public function test_site_a_draft_not_visible_from_site_b(): void
    {
        $service = $this->service();
        $service->persist($this->content('Site A Article'), $this->job(siteId: 1), 1);

        // Query from Site B's perspective
        $siteB = EducationArticle::query()
            ->where('auto_generated', true)
            ->where('site_id', 2)
            ->count();

        $this->assertSame(0, $siteB);
    }

    public function test_draft_exists_check_is_site_scoped(): void
    {
        $service = $this->service();
        $service->persist($this->content('Cross-Site Title'), $this->job(siteId: 1), 1);

        // Same title but different site → should NOT exist
        $this->assertFalse($service->draftExists($this->job(siteId: 2), 'Cross-Site Title'));
    }

    public function test_null_site_draft_not_visible_from_tenant_site(): void
    {
        $service = $this->service();
        $service->persist($this->content('Platform Article'), $this->job(siteId: null), 1);

        // Site 1 should not see platform-level drafts
        $tenant = EducationArticle::query()
            ->where('auto_generated', true)
            ->where('site_id', 1)
            ->count();

        $this->assertSame(0, $tenant);
    }

    // ----- Generation log isolation -----

    public function test_generation_log_stores_correct_site_id(): void
    {
        $this->service()->persist($this->content('Log Site Test'), $this->job(siteId: 5), 1);

        $this->assertDatabaseHas('edu_generation_logs', ['site_id' => 5]);
    }

    public function test_generation_log_for_null_site_stores_null(): void
    {
        $this->service()->persist($this->content('Global Log Test'), $this->job(siteId: null), 1);

        $log = EducationGenerationLog::whereNull('site_id')->first();
        $this->assertNotNull($log);
    }

    // ----- No cross-site generation -----

    public function test_multiple_sites_generate_independent_drafts(): void
    {
        $service = $this->service();
        $service->persist($this->content('A Article'), $this->job(siteId: 1), 1);
        $service->persist($this->content('B Article'), $this->job(siteId: 2), 1);
        $service->persist($this->content('C Article'), $this->job(siteId: 3), 1);

        foreach ([1, 2, 3] as $siteId) {
            $count = EducationArticle::query()->where('site_id', $siteId)->where('auto_generated', true)->count();
            $this->assertSame(1, $count, "Site {$siteId} should have exactly 1 draft.");
        }
    }

    // ----- Author ID scoping -----

    public function test_author_id_stored_on_draft(): void
    {
        $article = $this->service()->persist($this->content('Author Test'), $this->job(siteId: 1), authorId: 42);
        $this->assertSame(42, $article->author_id);
    }

    // ----- Module slug scoping -----

    public function test_draft_module_slug_matches_job(): void
    {
        $article = $this->service()->persist(
            $this->content('Module Test'),
            $this->job(siteId: 1, moduleSlug: 'payments'),
            authorId: 1,
        );

        $this->assertSame('payments', $article->module_slug);
    }

    // -----------------------------------------------------------------------

    private function content(string $title): GeneratedContentDto
    {
        return new GeneratedContentDto(
            title:           $title,
            summary:         'Summary',
            body:            str_repeat('Body content ', 10),
            tags:            [],
            confidenceScore: 70.0,
            provider:        'null',
        );
    }

    private function job(?int $siteId, string $moduleSlug = 'invoices'): ContentGenerationJobDto
    {
        return new ContentGenerationJobDto(
            moduleSlug:   $moduleSlug,
            contentType:  'articles',
            priority:     'High',
            siteId:       $siteId,
            sourceReason: 'content_deficit',
        );
    }
}
