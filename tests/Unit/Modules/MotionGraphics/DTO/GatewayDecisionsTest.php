<?php

namespace Tests\Unit\Modules\MotionGraphics\DTO;

use App\Modules\MotionGraphics\DTO\DeploymentGatewayResult;
use App\Modules\MotionGraphics\DTO\LicenseGatewayDecision;
use Tests\TestCase;

class GatewayDecisionsTest extends TestCase
{
    public function test_deployment_gateway_result_allow_is_explicit(): void
    {
        $result = DeploymentGatewayResult::allow('accepted', 'ok', 'prepared');

        $this->assertTrue($result->allowed);
        $this->assertSame('accepted', $result->code);
        $this->assertSame('prepared', $result->status);
    }

    public function test_deployment_gateway_result_deny_is_explicit(): void
    {
        $result = DeploymentGatewayResult::deny('gateway_denied', 'no');

        $this->assertFalse($result->allowed);
        $this->assertSame('gateway_denied', $result->code);
        $this->assertNull($result->status);
    }

    public function test_license_gateway_decision_allow_and_deny(): void
    {
        $this->assertTrue(LicenseGatewayDecision::allow()->allowed);
        $this->assertFalse(LicenseGatewayDecision::deny('license_denied', 'no')->allowed);
    }
}
