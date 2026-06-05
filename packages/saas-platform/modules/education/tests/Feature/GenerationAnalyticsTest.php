<?php

namespace SaaSPlatform\Modules\Education\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Orchestra\Testbench\TestCase;
use SaaSPlatform\Modules\Education\Models\EducationArticle;
use SaaSPlatform\Modules\Education\Models\EducationGenerationLog;
use SaaSPlatform\Modules\Education\Providers\EducationServiceProvider;
use SaaSPlatform\Modules\Education\Services\Education\EducationAnalyticsService;

/**
 * EDU-15 Phase 7 — Generation Analytics
 */
class GenerationAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    protected function getPackageProviders($app): array
    {
        return [EducationServiceProvider::class];
    }

    // ----- Zero state -----

    public function test_generation_analytics_zero_state(): void
    {
        $result = $this->service()->generationAnalytics(1);

        $this->assertSame(0, $result['drafts_created']);
        $this->assertSame(0, $result['drafts_approved']);
        $this->assertSame(0, $result['drafts_rejected']);
        $this->assertSame(0.0, $result['generation_success_rate']);
        $this->assertSame([], $result['provider_usage']);
    }

    // ----- drafts_created -----

    public function test_drafts_created_counts_successful_logs(): void
    {
        EducationGenerationLog::create(['site_id' => 1, 'module_slug' => 'inv', 'content_type' => 'articles', 'provider' => 'null', 'status' => 'success', 'source_reason' => 'content_deficit']);
        EducationGenerationLog::create(['site_id' => 1, 'module_slug' => 'rep', 'content_type' => 'articles', 'provider' => 'null', 'status' => 'success', 'source_reason' => 'content_deficit']);

        $result = $this->service()->generationAnalytics(1);
        $this->assertSame(2, $result['drafts_created']);
    }

    // ----- drafts_approved -----

    public function test_drafts_approved_counts_published_auto_generated(): void
    {
        $this->createArticle(site_id: 1, status: 'published', auto_generated: true);
        $this->createArticle(site_id: 1, status: 'published', auto_generated: true);
        $this->createArticle(site_id: 1, status: 'draft',     auto_generated: true); // not approved

        $result = $this->service()->generationAnalytics(1);
        $this->assertSame(2, $result['drafts_approved']);
    }

    // ----- drafts_rejected -----

    public function test_drafts_rejected_counts_archived_auto_generated(): void
    {
        $this->createArticle(site_id: 1, status: 'archived', auto_generated: true);
        $this->createArticle(site_id: 1, status: 'archived', auto_generated: false); // not auto-generated

        $result = $this->service()->generationAnalytics(1);
        $this->assertSame(1, $result['drafts_rejected']);
    }

    // ----- generation_success_rate -----

    public function test_success_rate_calculated_correctly(): void
    {
        EducationGenerationLog::create(['site_id' => 1, 'module_slug' => 'a', 'content_type' => 'articles', 'provider' => 'null', 'status' => 'success', 'source_reason' => 'content_deficit']);
        EducationGenerationLog::create(['site_id' => 1, 'module_slug' => 'b', 'content_type' => 'articles', 'provider' => 'null', 'status' => 'validation_failed', 'source_reason' => 'content_deficit']);
        EducationGenerationLog::create(['site_id' => 1, 'module_slug' => 'c', 'content_type' => 'articles', 'provider' => 'null', 'status' => 'provider_error', 'source_reason' => 'content_deficit']);
        EducationGenerationLog::create(['site_id' => 1, 'module_slug' => 'd', 'content_type' => 'articles', 'provider' => 'null', 'status' => 'success', 'source_reason' => 'content_deficit']);

        $result = $this->service()->generationAnalytics(1);
        $this->assertSame(0.5, $result['generation_success_rate']); // 2 success / 4 total
    }

    // ----- provider_usage -----

    public function test_provider_usage_grouped_by_provider(): void
    {
        EducationGenerationLog::create(['site_id' => 1, 'module_slug' => 'a', 'content_type' => 'articles', 'provider' => 'claude', 'status' => 'success', 'source_reason' => 'content_deficit']);
        EducationGenerationLog::create(['site_id' => 1, 'module_slug' => 'b', 'content_type' => 'articles', 'provider' => 'claude', 'status' => 'success', 'source_reason' => 'content_deficit']);
        EducationGenerationLog::create(['site_id' => 1, 'module_slug' => 'c', 'content_type' => 'articles', 'provider' => 'openai', 'status' => 'success', 'source_reason' => 'content_deficit']);

        $result = $this->service()->generationAnalytics(1);
        $this->assertSame(2, $result['provider_usage']['claude']);
        $this->assertSame(1, $result['provider_usage']['openai']);
    }

    // ----- Site isolation -----

    public function test_analytics_isolated_by_site(): void
    {
        EducationGenerationLog::create(['site_id' => 1, 'module_slug' => 'a', 'content_type' => 'articles', 'provider' => 'null', 'status' => 'success', 'source_reason' => 'content_deficit']);
        EducationGenerationLog::create(['site_id' => 2, 'module_slug' => 'b', 'content_type' => 'articles', 'provider' => 'null', 'status' => 'success', 'source_reason' => 'content_deficit']);

        $resultSite1 = $this->service()->generationAnalytics(1);
        $this->assertSame(1, $resultSite1['drafts_created']);

        $resultSite2 = $this->service()->generationAnalytics(2);
        $this->assertSame(1, $resultSite2['drafts_created']);
    }

    public function test_null_site_id_only_sees_global_logs(): void
    {
        EducationGenerationLog::create(['site_id' => null, 'module_slug' => 'a', 'content_type' => 'articles', 'provider' => 'null', 'status' => 'success', 'source_reason' => 'content_deficit']);
        EducationGenerationLog::create(['site_id' => 1,    'module_slug' => 'b', 'content_type' => 'articles', 'provider' => 'null', 'status' => 'success', 'source_reason' => 'content_deficit']);

        $result = $this->service()->generationAnalytics(null);
        $this->assertSame(1, $result['drafts_created']); // only null site_id log
    }

    // ----- Return shape -----

    public function test_generation_analytics_has_required_keys(): void
    {
        $result = $this->service()->generationAnalytics(1);

        foreach (['drafts_created', 'drafts_approved', 'drafts_rejected', 'generation_success_rate', 'provider_usage'] as $key) {
            $this->assertArrayHasKey($key, $result);
        }
    }

    // -----------------------------------------------------------------------

    private function service(): EducationAnalyticsService
    {
        return $this->app->make(EducationAnalyticsService::class);
    }

    private function createArticle(int $site_id, string $status, bool $auto_generated): void
    {
        EducationArticle::create([
            'site_id'        => $site_id,
            'title'          => 'Test Article ' . uniqid(),
            'body'           => 'Content',
            'status'         => $status,
            'auto_generated' => $auto_generated,
            'module_slug'    => 'test',
        ]);
    }
}
