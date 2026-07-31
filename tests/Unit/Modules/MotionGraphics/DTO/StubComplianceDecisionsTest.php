<?php

namespace Tests\Unit\Modules\MotionGraphics\DTO;

use App\Modules\MotionGraphics\DTO\LicenseCompatibilityDecision;
use App\Modules\MotionGraphics\DTO\PerformanceBudgetDecision;
use App\Modules\MotionGraphics\DTO\ResponsiveSupportDecision;
use Tests\TestCase;

/**
 * The three honest-stub validation decisions (task scope item 4) all
 * expose the same explicit, auditable "not implemented" shape rather
 * than silently passing.
 */
class StubComplianceDecisionsTest extends TestCase
{
    public function test_license_decision_not_implemented_is_explicit_and_allows(): void
    {
        $decision = LicenseCompatibilityDecision::notImplemented();

        $this->assertTrue($decision->allowed);
        $this->assertSame('license_check_not_implemented', $decision->code);
    }

    public function test_performance_budget_decision_not_implemented_is_explicit_and_allows(): void
    {
        $decision = PerformanceBudgetDecision::notImplemented();

        $this->assertTrue($decision->allowed);
        $this->assertSame('performance_budget_not_implemented', $decision->code);
    }

    public function test_responsive_support_decision_not_implemented_is_explicit_and_allows(): void
    {
        $decision = ResponsiveSupportDecision::notImplemented();

        $this->assertTrue($decision->allowed);
        $this->assertSame('responsive_support_not_implemented', $decision->code);
    }

    public function test_each_decision_supports_an_explicit_denial(): void
    {
        $this->assertFalse(LicenseCompatibilityDecision::deny('license_expired', 'no')->allowed);
        $this->assertFalse(PerformanceBudgetDecision::deny('too_heavy', 'no')->allowed);
        $this->assertFalse(ResponsiveSupportDecision::deny('not_responsive', 'no')->allowed);
    }
}
