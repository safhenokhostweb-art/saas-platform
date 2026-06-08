<?php

namespace SaaSPlatform\Modules\Education\Tests\Feature;

use Illuminate\Support\Facades\Cache;
use Orchestra\Testbench\TestCase;
use SaaSPlatform\CoreUtils\AI\Governance\TenantAiQuotaManager;
use SaaSPlatform\Modules\Education\Ai\Providers\CircuitBreakerAiContentProvider;
use SaaSPlatform\Modules\Education\Contracts\Ai\AiContentProviderInterface;
use SaaSPlatform\Modules\Education\Dto\ContentGenerationJobDto;
use SaaSPlatform\Modules\Education\Dto\GeneratedContentDto;
use SaaSPlatform\Modules\Education\Exceptions\AiProviderException;
use SaaSPlatform\Modules\Education\Providers\EducationServiceProvider;

/**
 * EDU-17 Task 4 — TenantAiQuotaManager integration with CircuitBreakerAiContentProvider.
 *
 * Covers:
 *   1. Quota disabled → no check, no increment
 *   2. Quota enabled + within limit → generation succeeds, usage incremented
 *   3. Quota enabled + limit exceeded → AiProviderException before calling inner provider
 *   4. Quota key format: edu_site_{siteId}
 *   5. siteId null → key is edu_site_platform
 */
class TenantAiQuotaIntegrationTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [EducationServiceProvider::class];
    }

    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('cache.default', 'array');
        $app['config']->set('education.ai.circuit_breaker.enabled', false); // disable CB for isolation
        $app['config']->set('education.ai.quota.enabled', false);
        $app['config']->set('ai.quotas.default_daily', 10);
    }

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    private function makeJob(?int $siteId = 1): ContentGenerationJobDto
    {
        return new ContentGenerationJobDto(
            moduleSlug:   'invoices',
            contentType:  'articles',
            priority:     'normal',
            siteId:       $siteId,
            sourceReason: 'content_deficit',
        );
    }

    private function makeSuccessDto(): GeneratedContentDto
    {
        return new GeneratedContentDto(
            title:           'عنوان تست',
            summary:         'خلاصه تست',
            body:            str_repeat('محتوا ', 30),
            tags:            ['تست'],
            confidenceScore: 85.0,
            provider:        'stub',
        );
    }

    private function makeInner(bool $throws = false): AiContentProviderInterface
    {
        $dto = $this->makeSuccessDto();

        return new class($dto, $throws) implements AiContentProviderInterface {
            public int $callCount = 0;
            public function __construct(
                private GeneratedContentDto $dto,
                private bool $throws,
            ) {}
            public function generate(ContentGenerationJobDto $job): GeneratedContentDto
            {
                $this->callCount++;
                if ($this->throws) throw new AiProviderException('inner error');
                return $this->dto;
            }
            public function isEnabled(): bool { return true; }
            public function providerName(): string { return 'stub'; }
        };
    }

    private function makeProvider(AiContentProviderInterface $inner, ?TenantAiQuotaManager $quota = null): CircuitBreakerAiContentProvider
    {
        return new CircuitBreakerAiContentProvider($inner, $quota);
    }

    // ─── Tests ───────────────────────────────────────────────────────────────

    public function test_quota_disabled_skips_check_and_increment(): void
    {
        config(['education.ai.quota.enabled' => false]);

        $quota = new TenantAiQuotaManager();
        $inner = $this->makeInner();

        $this->makeProvider($inner, $quota)->generate($this->makeJob(1));

        // inner was called
        $this->assertSame(1, $inner->callCount);
        // usage was NOT incremented (quota disabled)
        $this->assertSame(0, $quota->getDailyUsage('edu_site_1'));
    }

    public function test_quota_enabled_within_limit_succeeds_and_increments(): void
    {
        config(['education.ai.quota.enabled' => true]);

        $quota = new TenantAiQuotaManager();
        $inner = $this->makeInner();

        $result = $this->makeProvider($inner, $quota)->generate($this->makeJob(42));

        $this->assertSame('عنوان تست', $result->title);
        $this->assertSame(1, $inner->callCount);
        // usage incremented per-tenant
        $this->assertSame(1, $quota->getDailyUsage('edu_site_42'));
        // system total also incremented
        $this->assertSame(1, $quota->getSystemUsageReport(1)[date('Y-m-d')]);
    }

    public function test_quota_exceeded_throws_before_calling_inner(): void
    {
        config(['education.ai.quota.enabled' => true]);

        $quota = new TenantAiQuotaManager();
        // Set limit to 1 and pre-fill usage
        $quota->setCustomLimit('edu_site_5', 1);
        $quota->increment('edu_site_5'); // now at 1/1 → exceeded

        $inner = $this->makeInner();

        $this->expectException(AiProviderException::class);
        $this->expectExceptionMessageMatches('/quota exceeded/i');

        $this->makeProvider($inner, $quota)->generate($this->makeJob(5));

        $this->assertSame(0, $inner->callCount);
    }

    public function test_quota_key_uses_site_id(): void
    {
        config(['education.ai.quota.enabled' => true]);

        $quota = new TenantAiQuotaManager();
        $this->makeProvider($this->makeInner(), $quota)->generate($this->makeJob(99));

        $this->assertSame(1, $quota->getDailyUsage('edu_site_99'));
        $this->assertSame(0, $quota->getDailyUsage('edu_site_1'));
    }

    public function test_null_site_id_uses_platform_key(): void
    {
        config(['education.ai.quota.enabled' => true]);

        $quota = new TenantAiQuotaManager();
        $this->makeProvider($this->makeInner(), $quota)->generate($this->makeJob(null));

        $this->assertSame(1, $quota->getDailyUsage('edu_site_platform'));
    }
}
