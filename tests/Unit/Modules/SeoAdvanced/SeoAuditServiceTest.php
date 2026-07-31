<?php

namespace Tests\Unit\Modules\SeoAdvanced;

use Illuminate\Foundation\Testing\RefreshDatabase;
use SaaSPlatform\Modules\SeoAdvanced\Models\SeoAdvancedSetting;
use SaaSPlatform\Modules\SeoAdvanced\Services\SeoAuditor;
use Tests\TestCase;

/**
 * Phase 18-C — Part 4/7: SEO Audit Engine scenarios — the new heading/
 * canonical (Content) and robots/schema/sitemap (Technical) checks added
 * on top of Phase 18's original title/description/image-alt engine.
 */
class SeoAuditServiceTest extends TestCase
{
    use RefreshDatabase;

    private function auditor(): SeoAuditor
    {
        return new SeoAuditor();
    }

    // ─── Content: heading / canonical ──────────────────────────────────────

    public function test_missing_heading_is_flagged_only_when_explicitly_checked(): void
    {
        $result = $this->auditor()->audit('a good title here 1234567', 'a good description '.str_repeat('x', 80), [], heading: '');

        $this->assertContains('heading', $result['missing_meta']);
    }

    public function test_heading_not_checked_when_argument_omitted(): void
    {
        // Backward compatibility with every Phase 18 call site that never
        // passes $heading at all.
        $result = $this->auditor()->audit(null, null, []);

        $this->assertNotContains('heading', $result['missing_meta']);
    }

    public function test_missing_canonical_is_flagged_when_explicitly_checked(): void
    {
        $result = $this->auditor()->audit(null, null, [], canonical: '   ');

        $this->assertContains('canonical', $result['missing_meta']);
    }

    public function test_present_heading_and_canonical_are_not_flagged(): void
    {
        $result = $this->auditor()->audit(null, null, [], 'یک تیتر واقعی', 'https://example.com/page');

        $this->assertNotContains('heading', $result['missing_meta']);
        $this->assertNotContains('canonical', $result['missing_meta']);
    }

    // ─── Technical: robots / schema / sitemap ──────────────────────────────

    public function test_technical_checks_fail_when_no_settings_exist(): void
    {
        $technical = $this->auditor()->technicalChecks(999999);

        $this->assertFalse($technical['robots_configured']);
        $this->assertFalse($technical['schema_configured']);
        $this->assertTrue($technical['sitemap_available']); // always true once installed
    }

    public function test_technical_checks_pass_when_settings_are_configured(): void
    {
        SeoAdvancedSetting::create([
            'site_id' => 42, 'robots_directive' => 'index,follow', 'schema_type' => 'organization',
        ]);

        $technical = $this->auditor()->technicalChecks(42);

        $this->assertTrue($technical['robots_configured']);
        $this->assertTrue($technical['schema_configured']);
    }

    public function test_audit_and_store_includes_technical_issues_and_penalizes_score(): void
    {
        // No SeoAdvancedSetting row at all for this site → both technical
        // checks fail → 2 * 10 point penalty on top of the page-level score.
        $audit = $this->auditor()->auditAndStore(43, '/some-page', str_repeat('a', 45), str_repeat('y', 100));

        $this->assertContains('technical:robots_configured', $audit->missing_meta);
        $this->assertContains('technical:schema_configured', $audit->missing_meta);
        $this->assertSame(80, $audit->score); // 100 - (2 * 10)
    }

    public function test_audit_and_store_has_no_technical_penalty_when_fully_configured(): void
    {
        SeoAdvancedSetting::create(['site_id' => 44, 'robots_directive' => 'index,follow', 'schema_type' => 'website']);

        $audit = $this->auditor()->auditAndStore(44, '/some-page', str_repeat('a', 45), str_repeat('y', 100));

        $this->assertSame(100, $audit->score);
    }
}
