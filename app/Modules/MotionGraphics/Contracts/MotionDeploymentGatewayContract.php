<?php

namespace App\Modules\MotionGraphics\Contracts;

use App\Modules\MotionGraphics\DTO\DeploymentGatewayResult;
use App\Modules\MotionGraphics\DTO\DeploymentManifest;
use App\Modules\MotionGraphics\DTO\MotionAdapterContext;

/**
 * Provider-neutral contract for the future SaaS Deployment Engine (task
 * scope item 3) — mirrors the existing driver-abstraction pattern
 * already used for customer installations
 * (app/Services/CustomerInstallation/Drivers/*), so a real cPanel/SSH/
 * Docker-style deployment backend could implement this later without
 * changing MotionDeploymentPreparationService.
 *
 * No real implementation exists in this phase — only
 * App\Modules\MotionGraphics\Support\FakeMotionDeploymentGateway
 * (bound by default) and whatever a test constructs directly. This
 * contract's own methods never execute a deployment, transfer a file,
 * or touch a customer server — they only prepare/validate/report.
 *
 * Distinct from the standalone module's own, narrower
 * SaaSPlatform\Modules\MotionGraphics\Contracts\MotionDeploymentGatewayContract
 * (a single `published()` notification hook the module calls into the
 * host) — different namespace, different purpose, no relation.
 */
interface MotionDeploymentGatewayContract
{
    public function prepareDeployment(DeploymentManifest $manifest): DeploymentGatewayResult;

    public function validateTarget(string $siteReference, MotionAdapterContext $context): DeploymentGatewayResult;

    /**
     * One of DeploymentManifest::STATUS_* or 'unknown'.
     */
    public function reportStatus(string $manifestUuid): string;
}
