<?php

namespace Tests\Unit\Modules\MotionGraphics\Support;

use App\Modules\MotionGraphics\DTO\DeploymentManifest;
use App\Modules\MotionGraphics\DTO\MotionAdapterContext;
use App\Modules\MotionGraphics\DTO\MotionAssetReferenceDTO;
use App\Modules\MotionGraphics\DTO\ValidationReport;
use App\Modules\MotionGraphics\Support\FakeMotionDeploymentGateway;
use App\Modules\MotionGraphics\Support\FakeMotionLicenseGateway;
use Tests\TestCase;

/**
 * Task scope item 3/5: "no real deployment implementation... create
 * fake adapter for tests" / "Implementation: Fake allow/deny adapter
 * only."
 */
class FakeGatewaysTest extends TestCase
{
    private function manifest(): DeploymentManifest
    {
        return new DeploymentManifest(
            'manifest-1', 'site-1', 'template-1', 1, [], [], 'checksum', ValidationReport::pass()
        );
    }

    public function test_the_deployment_gateway_allows_by_default_and_records_status(): void
    {
        $gateway = new FakeMotionDeploymentGateway();

        $result = $gateway->prepareDeployment($this->manifest());

        $this->assertTrue($result->allowed);
        $this->assertSame(DeploymentManifest::STATUS_PREPARED, $gateway->reportStatus('manifest-1'));
    }

    public function test_the_deployment_gateway_can_be_configured_to_deny(): void
    {
        $gateway = new FakeMotionDeploymentGateway(allow: false);

        $result = $gateway->prepareDeployment($this->manifest());

        $this->assertFalse($result->allowed);
        $this->assertSame('gateway_denied', $result->code);
    }

    public function test_reporting_status_for_an_unknown_manifest_is_explicit(): void
    {
        $gateway = new FakeMotionDeploymentGateway();

        $this->assertSame('unknown', $gateway->reportStatus('never-seen'));
    }

    public function test_validate_target_reflects_the_configured_allow_state(): void
    {
        $context = new MotionAdapterContext('user:1', 'site-1', 'corr-1');

        $this->assertTrue((new FakeMotionDeploymentGateway(allow: true))->validateTarget('site-1', $context)->allowed);
        $this->assertFalse((new FakeMotionDeploymentGateway(allow: false))->validateTarget('site-1', $context)->allowed);
    }

    public function test_the_license_gateway_allows_by_default(): void
    {
        $gateway = new FakeMotionLicenseGateway();

        $this->assertTrue($gateway->checkAvailability('motion-1', 'site-1')->allowed);
        $this->assertTrue($gateway->validateEntitlement('site-1', 'template-1')->allowed);
    }

    public function test_the_license_gateway_can_be_configured_to_deny(): void
    {
        $gateway = new FakeMotionLicenseGateway(allow: false);

        $this->assertFalse($gateway->checkAvailability('motion-1', 'site-1')->allowed);
        $this->assertFalse($gateway->validateEntitlement('site-1', 'template-1')->allowed);
        $this->assertSame('license_denied', $gateway->checkAvailability('motion-1', 'site-1')->code);
    }
}
