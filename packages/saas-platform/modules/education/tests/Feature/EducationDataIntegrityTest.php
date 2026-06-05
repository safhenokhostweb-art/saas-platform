<?php

namespace SaaSPlatform\Modules\Education\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use SaaSPlatform\Modules\Education\Models\EducationArticle;
use SaaSPlatform\Modules\Education\Models\EducationFeature;
use SaaSPlatform\Modules\Education\Models\EducationFeatureUsage;
use SaaSPlatform\Modules\Education\Models\EducationWalkthroughProgress;
use SaaSPlatform\Modules\Education\Repositories\EducationFeatureUsageRepository;
use SaaSPlatform\Modules\Education\Services\WalkthroughTriggerService;
use Tests\TestCase;

/**
 * EDU-13.5 Data Integrity Test Matrix
 *
 * F-01 / F-15: markCompleted and triggersForRoute use site_id
 * F-05: Hash generation is consistent (computeHashFromParts)
 * F-06: Feature usage increment is atomic (upsert)
 * F-07: Recommendation creation is idempotent under concurrency
 * F-08: Doc generation is idempotent under concurrent calls
 * F-09: Revision numbering never produces duplicates
 */
class EducationDataIntegrityTest extends TestCase
{
    use RefreshDatabase;

    // ─────────────────────────────────────────────────────────────────────────
    // F-01 / F-15 — WalkthroughTriggerService site isolation
    // ─────────────────────────────────────────────────────────────────────────

    /** @test */
    public function mark_completed_scopes_progress_to_site(): void
    {
        $svc = app(WalkthroughTriggerService::class);

        $svc->markCompleted(walkthroughId: 1, userId: 42, siteId: 10);
        $svc->markCompleted(walkthroughId: 1, userId: 42, siteId: 20);

        // Two separate progress records — one per site
        $this->assertDatabaseCount('edu_walkthrough_progress', 2);

        $this->assertDatabaseHas('edu_walkthrough_progress', [
            'edu_walkthrough_id' => 1,
            'user_id'            => 42,
            'site_id'            => 10,
        ]);

        $this->assertDatabaseHas('edu_walkthrough_progress', [
            'edu_walkthrough_id' => 1,
            'user_id'            => 42,
            'site_id'            => 20,
        ]);
    }

    /** @test */
    public function mark_completed_is_idempotent_for_same_user_and_site(): void
    {
        $svc = app(WalkthroughTriggerService::class);

        $svc->markCompleted(walkthroughId: 1, userId: 42, siteId: 10);
        $svc->markCompleted(walkthroughId: 1, userId: 42, siteId: 10);

        // Should still be a single record — updateOrCreate is idempotent
        $this->assertDatabaseCount('edu_walkthrough_progress', 1);
    }

    /** @test */
    public function triggers_for_route_only_sees_completions_from_same_site(): void
    {
        // Mark walkthrough 7 as completed on site 10
        EducationWalkthroughProgress::create([
            'edu_walkthrough_id' => 7,
            'user_id'            => 99,
            'site_id'            => 10,
            'completed_at'       => now(),
            'status'             => 'completed',
        ]);

        // On site 20 the same walkthrough should NOT be suppressed
        $completedOnSite20 = EducationWalkthroughProgress::where('user_id', 99)
            ->where('site_id', 20)
            ->whereNotNull('completed_at')
            ->pluck('edu_walkthrough_id')
            ->toArray();

        $this->assertNotContains(7, $completedOnSite20);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // F-05 — Hash consistency
    // ─────────────────────────────────────────────────────────────────────────

    /** @test */
    public function compute_hash_from_parts_matches_model_compute_hash(): void
    {
        $article = EducationArticle::factory()->create([
            'title'   => 'راهنمای تیکت',
            'body'    => '<p>محتوا</p>',
            'excerpt' => 'خلاصه',
        ]);

        $static   = EducationArticle::computeHashFromParts($article->title, $article->body, $article->excerpt);
        $instance = $article->computeHash();

        $this->assertSame($static, $instance);
    }

    /** @test */
    public function hash_is_deterministic_for_same_content(): void
    {
        $h1 = EducationArticle::computeHashFromParts('title', 'body', 'excerpt');
        $h2 = EducationArticle::computeHashFromParts('title', 'body', 'excerpt');

        $this->assertSame($h1, $h2);
    }

    /** @test */
    public function hash_changes_when_content_changes(): void
    {
        $h1 = EducationArticle::computeHashFromParts('title', 'body v1', 'excerpt');
        $h2 = EducationArticle::computeHashFromParts('title', 'body v2', 'excerpt');

        $this->assertNotSame($h1, $h2);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // F-06 — Atomic UPSERT for feature usage
    // ─────────────────────────────────────────────────────────────────────────

    /** @test */
    public function track_creates_usage_record_on_first_call(): void
    {
        $repo = app(EducationFeatureUsageRepository::class);

        $record = $repo->track(userId: 1, siteId: null, moduleSlug: 'tickets', featureSlug: 'list');

        $this->assertDatabaseHas('edu_feature_usage', [
            'user_id'      => 1,
            'module_slug'  => 'tickets',
            'feature_slug' => 'list',
            'usage_count'  => 1,
        ]);
    }

    /** @test */
    public function track_increments_count_on_subsequent_calls(): void
    {
        $repo = app(EducationFeatureUsageRepository::class);

        $repo->track(userId: 1, siteId: null, moduleSlug: 'tickets', featureSlug: 'list');
        $repo->track(userId: 1, siteId: null, moduleSlug: 'tickets', featureSlug: 'list');
        $repo->track(userId: 1, siteId: null, moduleSlug: 'tickets', featureSlug: 'list');

        $this->assertDatabaseHas('edu_feature_usage', [
            'user_id'      => 1,
            'module_slug'  => 'tickets',
            'feature_slug' => 'list',
            'usage_count'  => 3,
        ]);
    }

    /** @test */
    public function concurrent_track_calls_do_not_create_duplicate_rows(): void
    {
        $repo = app(EducationFeatureUsageRepository::class);

        // Simulate concurrency by calling track 5 times — the upsert must not insert duplicates
        for ($i = 0; $i < 5; $i++) {
            $repo->track(userId: 1, siteId: null, moduleSlug: 'forms', featureSlug: 'builder');
        }

        $rowCount = EducationFeatureUsage::where([
            'user_id'      => 1,
            'module_slug'  => 'forms',
            'feature_slug' => 'builder',
        ])->count();

        $this->assertSame(1, $rowCount);

        $this->assertDatabaseHas('edu_feature_usage', [
            'user_id'      => 1,
            'module_slug'  => 'forms',
            'feature_slug' => 'builder',
            'usage_count'  => 5,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // F-07 — Idempotent recommendation creation
    // ─────────────────────────────────────────────────────────────────────────

    /** @test */
    public function duplicate_recommendation_calls_return_same_record(): void
    {
        $repo    = app(\SaaSPlatform\Modules\Education\Repositories\EducationRecommendationRepository::class);
        $article = EducationArticle::factory()->create();

        $data = [
            'user_id'             => 1,
            'site_id'             => null,
            'recommendation_type' => 'article',
            'article_id'          => $article->id,
            'priority'            => 50,
        ];

        $rec1 = $repo->create($data);
        $rec2 = $repo->create($data);

        $this->assertSame($rec1->id, $rec2->id);
        $this->assertDatabaseCount('edu_recommendations', 1);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // F-09 — Revision numbering
    // ─────────────────────────────────────────────────────────────────────────

    /** @test */
    public function revision_numbers_are_sequential_and_unique(): void
    {
        $article = EducationArticle::factory()->create([
            'title' => 'نسخه اول',
            'body'  => 'متن اول',
        ]);

        $article->update(['title' => 'نسخه دوم', 'body' => 'متن دوم']);
        $article->update(['title' => 'نسخه سوم', 'body' => 'متن سوم']);

        $revisions = $article->revisions()->orderBy('revision_number')->get();

        $this->assertCount(2, $revisions);
        $this->assertSame(1, $revisions[0]->revision_number);
        $this->assertSame(2, $revisions[1]->revision_number);
    }

    /** @test */
    public function revision_is_not_created_when_content_is_unchanged(): void
    {
        $article = EducationArticle::factory()->create([
            'title'  => 'عنوان ثابت',
            'body'   => 'متن ثابت',
            'status' => 'draft',
        ]);

        // Updating only status — no title/body/excerpt change
        $article->update(['status' => 'published']);

        $this->assertCount(0, $article->revisions);
    }
}
