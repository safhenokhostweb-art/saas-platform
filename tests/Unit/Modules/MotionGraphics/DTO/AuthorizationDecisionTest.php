<?php

namespace Tests\Unit\Modules\MotionGraphics\DTO;

use App\Modules\MotionGraphics\DTO\AuthorizationDecision;
use Tests\TestCase;

class AuthorizationDecisionTest extends TestCase
{
    public function test_allow_produces_an_explicit_positive_decision(): void
    {
        $decision = AuthorizationDecision::allow('ok', 'granted');

        $this->assertTrue($decision->allowed);
        $this->assertSame('ok', $decision->code);
        $this->assertSame('granted', $decision->reason);
    }

    public function test_deny_produces_an_explicit_negative_decision_with_a_stable_code(): void
    {
        $decision = AuthorizationDecision::deny('tenant_context_mismatch', 'site mismatch');

        $this->assertFalse($decision->allowed);
        $this->assertSame('tenant_context_mismatch', $decision->code);
        $this->assertSame('site mismatch', $decision->reason);
    }
}
