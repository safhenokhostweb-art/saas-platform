<?php

namespace Tests\Feature\Modules\MotionGraphics\Phase6;

use Illuminate\Foundation\Testing\RefreshDatabase;
use SaaSPlatform\CoreUtils\Traits\HasTenantContext;
use Tests\Support\CreatesMotionTestSite;
use Tests\TestCase;

/**
 * Phase 6 (docs/motion-integration-phase6-audit.md §6): the
 * `motion:internal-test-site` command is the only lever that turns the
 * feature flag on for a site, now that the global default is `null`
 * instead of a forced `false`. Proves it is scoped to exactly the named
 * site and never leaks to any other site.
 */
class InternalTestSiteCommandTest extends TestCase
{
    use RefreshDatabase;
    use CreatesMotionTestSite;

    private function hasFeatureFor(object $site): bool
    {
        $ctx = new class($site) {
            use HasTenantContext;
            public function __construct(private readonly object $tenant) {}
            public function current(): ?object { return $this->tenant; }
        };

        return $ctx->hasFeature('motion_graphics_integration');
    }

    public function test_the_global_default_is_null_not_a_forced_value(): void
    {
        $this->assertNull(config('feature-flags.motion_graphics_integration'));
    }

    public function test_enabling_the_flag_only_affects_the_named_site(): void
    {
        $siteA = $this->createSite('Site A');
        $siteB = $this->createSite('Site B');

        $this->assertFalse($this->hasFeatureFor($siteA));
        $this->assertFalse($this->hasFeatureFor($siteB));

        $this->artisan('motion:internal-test-site', ['site' => $siteA->id])->assertSuccessful();

        $this->assertTrue($this->hasFeatureFor($siteA->fresh()));
        $this->assertFalse($this->hasFeatureFor($siteB->fresh()));
    }

    public function test_disable_option_turns_it_back_off(): void
    {
        $site = $this->createSite();

        $this->artisan('motion:internal-test-site', ['site' => $site->id])->assertSuccessful();
        $this->assertTrue($this->hasFeatureFor($site->fresh()));

        $this->artisan('motion:internal-test-site', ['site' => $site->id, '--disable' => true])->assertSuccessful();
        $this->assertFalse($this->hasFeatureFor($site->fresh()));
    }

    public function test_it_fails_gracefully_for_an_unknown_site(): void
    {
        $this->artisan('motion:internal-test-site', ['site' => 999999])->assertFailed();
    }

    public function test_it_preserves_other_feature_keys_already_on_the_site(): void
    {
        $site = $this->createSite();
        $site->features = ['some_other_flag' => true];
        $site->save();

        $this->artisan('motion:internal-test-site', ['site' => $site->id])->assertSuccessful();

        $fresh = $site->fresh();
        $this->assertTrue($fresh->features['some_other_flag']);
        $this->assertTrue($fresh->features['motion_graphics_integration']);
    }
}
