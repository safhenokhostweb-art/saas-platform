<?php

namespace Tests\Unit\Modules\Education;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Mockery;
use ReflectionMethod;
use SaaSPlatform\Modules\Education\Contracts\Services\EducationContextServiceInterface;
use SaaSPlatform\Modules\Education\Contracts\Services\EducationRecommendationServiceInterface;
use SaaSPlatform\Modules\Education\Contracts\Services\WalkthroughTriggerServiceInterface;
use SaaSPlatform\Modules\Education\Http\ViewComposers\EducationGuideComposer;
use Tests\TestCase;

/**
 * Regression guard for the /admin/dashboard 500: EducationGuideComposer is bound to
 * every admin.* view (View::composer(['admin.*', 'layouts.admin'], ...)), so a cache
 * backend outage (e.g. Redis down/unreachable) must never bubble up as an uncaught
 * exception and take down the whole admin panel.
 */
class EducationGuideComposerCacheFailureTest extends TestCase
{
    use RefreshDatabase;

    public function test_resolve_payload_falls_back_when_cache_backend_is_unreachable(): void
    {
        $admin = User::create([
            'mobile'    => '09190000000',
            'password'  => bcrypt('password'),
            'is_admin'  => true,
            'is_active' => true,
        ]);

        Cache::shouldReceive('remember')->andThrow(new \RuntimeException('Redis down'));

        $context = Mockery::mock(EducationContextServiceInterface::class);
        $context->shouldReceive('recommendForCurrentRoute')->andReturn(collect());

        $recs = Mockery::mock(EducationRecommendationServiceInterface::class);
        $recs->shouldReceive('activeForUser')->andReturn(collect());

        $composer = new EducationGuideComposer(
            $context,
            Mockery::mock(WalkthroughTriggerServiceInterface::class),
            $recs
        );

        $method = new ReflectionMethod($composer, 'resolvePayload');
        $method->setAccessible(true);

        // Platform admin -> siteId is null, so the payload must resolve without
        // ever touching the (throwing) cache-dependent walkthrough-trigger path.
        $payload = $method->invoke($composer, $admin->id, null, 'admin.dashboard', 'admin');

        $this->assertIsArray($payload);
        $this->assertArrayHasKey('guides', $payload);
        $this->assertArrayHasKey('walkthroughTriggers', $payload);
        $this->assertArrayHasKey('eduRecommendationCount', $payload);
    }
}
