<?php

namespace App\Modules\MotionGraphics\Support;

use App\Modules\MotionGraphics\Contracts\MotionDeploymentGatewayContract;
use App\Modules\MotionGraphics\DTO\DeploymentGatewayResult;
use App\Modules\MotionGraphics\DTO\DeploymentManifest;
use App\Modules\MotionGraphics\DTO\MotionAdapterContext;

/**
 * The only MotionDeploymentGatewayContract implementation this phase
 * ships (task scope item 3 — "No real deployment implementation. Create
 * fake adapter for tests."). Never executes a deployment, transfers a
 * file, or touches a customer server — it only records that a manifest
 * was "seen" and answers status/target-validation questions from that
 * in-memory record, entirely process-local.
 */
final class FakeMotionDeploymentGateway implements MotionDeploymentGatewayContract
{
    /** @var array<string, string> */
    private array $statuses = [];

    public function __construct(
        private readonly bool $allow = true,
    ) {}

    public function prepareDeployment(DeploymentManifest $manifest): DeploymentGatewayResult
    {
        if (! $this->allow) {
            return DeploymentGatewayResult::deny('gateway_denied', 'The fake deployment gateway is configured to deny.');
        }

        $this->statuses[$manifest->uuid] = $manifest->status;

        return DeploymentGatewayResult::allow('accepted', 'Manifest accepted by the fake deployment gateway.', $manifest->status);
    }

    public function validateTarget(string $siteReference, MotionAdapterContext $context): DeploymentGatewayResult
    {
        return $this->allow
            ? DeploymentGatewayResult::allow('target_valid', 'Fake target validation passed.')
            : DeploymentGatewayResult::deny('gateway_denied', 'The fake deployment gateway is configured to deny.');
    }

    public function reportStatus(string $manifestUuid): string
    {
        return $this->statuses[$manifestUuid] ?? 'unknown';
    }
}
