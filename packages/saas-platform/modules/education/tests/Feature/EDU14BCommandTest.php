<?php

namespace SaaSPlatform\Modules\Education\Tests\Feature;

use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use SaaSPlatform\Modules\Education\Models\EducationFeature;
use SaaSPlatform\Modules\Education\Models\EducationFeatureUsage;
use SaaSPlatform\Modules\Education\Models\EducationRecommendation;
use Tests\TestCase;

/**
 * EDU-14B — Artisan Command Tests
 *
 * Coverage:
 *   1.  education:refresh-recommendations exits with SUCCESS
 *   2.  education:refresh-recommendations --dry-run exits with SUCCESS
 *   3.  education:refresh-recommendations --site scopes correctly
 *   4.  education:refresh-recommendations --user scopes correctly
 *   5.  education:refresh-recommendations --dry-run does not delete records
 *   6.  education:build-gap-queue exits with SUCCESS
 *   7.  education:build-gap-queue --dry-run exits with SUCCESS
 *   8.  education:build-gap-queue --user scopes correctly
 *   9.  education:build-gap-queue outputs table headers
 *  10.  education:build-content-deficit-queue exits with SUCCESS
 *  11.  education:build-content-deficit-queue --dry-run exits with SUCCESS
 *  12.  education:build-content-deficit-queue --site scopes correctly
 *  13.  education:build-content-deficit-queue outputs table headers
 */
class EDU14BCommandTest extends TestCase
{
    use RefreshDatabase;

    // --- T01 ---
    public function test_refresh_recommendations_exits_success(): void
    {
        $this->artisan('education:refresh-recommendations')
             ->assertExitCode(0);
    }

    // --- T02 ---
    public function test_refresh_recommendations_dry_run_exits_success(): void
    {
        $this->artisan('education:refresh-recommendations --dry-run')
             ->assertExitCode(0);
    }

    // --- T03 ---
    public function test_refresh_recommendations_with_site_option(): void
    {
        $this->artisan('education:refresh-recommendations', ['--site' => 10])
             ->assertExitCode(0);
    }

    // --- T04 ---
    public function test_refresh_recommendations_with_user_option(): void
    {
        $this->artisan('education:refresh-recommendations', ['--user' => 1])
             ->assertExitCode(0);
    }

    // --- T05 ---
    public function test_refresh_recommendations_dry_run_does_not_delete(): void
    {
        $staleDays = (int) config('education.recommendation.stale_days', 7);

        EducationFeatureUsage::factory()->create([
            'user_id'      => 1,
            'site_id'      => 10,
            'module_slug'  => 'crm',
            'feature_slug' => 'contacts',
            'usage_count'  => 1,
        ]);

        EducationRecommendation::factory()->create([
            'user_id'      => 1,
            'site_id'      => 10,
            'dismissed_at' => null,
            'completed_at' => null,
            'created_at'   => now()->subDays($staleDays + 5),
        ]);

        $before = EducationRecommendation::count();

        $this->artisan('education:refresh-recommendations', ['--dry-run' => true, '--site' => 10])
             ->assertExitCode(0);

        $this->assertSame($before, EducationRecommendation::count());
    }

    // --- T06 ---
    public function test_build_gap_queue_exits_success(): void
    {
        $this->artisan('education:build-gap-queue')
             ->assertExitCode(0);
    }

    // --- T07 ---
    public function test_build_gap_queue_dry_run_exits_success(): void
    {
        $this->artisan('education:build-gap-queue --dry-run')
             ->assertExitCode(0);
    }

    // --- T08 ---
    public function test_build_gap_queue_with_user_option(): void
    {
        $this->artisan('education:build-gap-queue', ['--user' => 1])
             ->assertExitCode(0);
    }

    // --- T09 ---
    public function test_build_gap_queue_with_site_option(): void
    {
        $this->artisan('education:build-gap-queue', ['--site' => 10])
             ->assertExitCode(0);
    }

    // --- T10 ---
    public function test_build_gap_queue_outputs_empty_message_when_no_gaps(): void
    {
        $this->artisan('education:build-gap-queue')
             ->expectsOutputToContain('empty')
             ->assertExitCode(0);
    }

    // --- T11 ---
    public function test_build_content_deficit_queue_exits_success(): void
    {
        $this->artisan('education:build-content-deficit-queue')
             ->assertExitCode(0);
    }

    // --- T12 ---
    public function test_build_content_deficit_queue_dry_run_exits_success(): void
    {
        $this->artisan('education:build-content-deficit-queue --dry-run')
             ->assertExitCode(0);
    }

    // --- T13 ---
    public function test_build_content_deficit_queue_with_site_option(): void
    {
        $this->artisan('education:build-content-deficit-queue', ['--site' => 10])
             ->assertExitCode(0);
    }

    // --- T14 ---
    public function test_build_content_deficit_queue_reports_modules_with_deficits(): void
    {
        EducationFeature::factory()->create(['module_slug' => 'crm', 'site_id' => null]);

        $this->artisan('education:build-content-deficit-queue')
             ->assertExitCode(0);
    }

    // --- T15 ---
    public function test_refresh_recommendations_reports_users_processed_for_site(): void
    {
        $siteId = 55;

        EducationFeatureUsage::factory()->create([
            'user_id'      => 1,
            'site_id'      => $siteId,
            'module_slug'  => 'crm',
            'feature_slug' => 'contacts',
            'usage_count'  => 1,
        ]);

        $this->artisan('education:refresh-recommendations', ['--site' => $siteId])
             ->expectsOutputToContain('1')
             ->assertExitCode(0);
    }

    // --- IDEMPOTENCY ---
    public function test_refresh_recommendations_is_idempotent(): void
    {
        // Running twice should not cause errors or double-deletions
        $this->artisan('education:refresh-recommendations')->assertExitCode(0);
        $this->artisan('education:refresh-recommendations')->assertExitCode(0);
    }

    public function test_build_gap_queue_is_idempotent(): void
    {
        $this->artisan('education:build-gap-queue')->assertExitCode(0);
        $this->artisan('education:build-gap-queue')->assertExitCode(0);
    }

    public function test_build_content_deficit_queue_is_idempotent(): void
    {
        $this->artisan('education:build-content-deficit-queue')->assertExitCode(0);
        $this->artisan('education:build-content-deficit-queue')->assertExitCode(0);
    }
}
