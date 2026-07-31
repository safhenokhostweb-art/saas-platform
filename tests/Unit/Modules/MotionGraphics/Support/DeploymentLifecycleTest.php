<?php

namespace Tests\Unit\Modules\MotionGraphics\Support;

use App\Modules\MotionGraphics\DTO\DeploymentManifest;
use App\Modules\MotionGraphics\Support\DeploymentLifecycle;
use Tests\TestCase;

class DeploymentLifecycleTest extends TestCase
{
    public function test_the_forward_path_is_allowed(): void
    {
        $this->assertTrue(DeploymentLifecycle::canTransition(DeploymentManifest::STATUS_PREPARED, DeploymentManifest::STATUS_VALIDATED));
        $this->assertTrue(DeploymentLifecycle::canTransition(DeploymentManifest::STATUS_VALIDATED, DeploymentManifest::STATUS_APPROVED));
        $this->assertTrue(DeploymentLifecycle::canTransition(DeploymentManifest::STATUS_APPROVED, DeploymentManifest::STATUS_READY));
        $this->assertTrue(DeploymentLifecycle::canTransition(DeploymentManifest::STATUS_READY, DeploymentManifest::STATUS_DEPLOYED));
    }

    public function test_steps_cannot_be_skipped(): void
    {
        $this->assertFalse(DeploymentLifecycle::canTransition(DeploymentManifest::STATUS_PREPARED, DeploymentManifest::STATUS_APPROVED));
        $this->assertFalse(DeploymentLifecycle::canTransition(DeploymentManifest::STATUS_PREPARED, DeploymentManifest::STATUS_READY));
    }

    public function test_backward_transitions_are_never_allowed(): void
    {
        $this->assertFalse(DeploymentLifecycle::canTransition(DeploymentManifest::STATUS_VALIDATED, DeploymentManifest::STATUS_PREPARED));
        $this->assertFalse(DeploymentLifecycle::canTransition(DeploymentManifest::STATUS_READY, DeploymentManifest::STATUS_APPROVED));
    }

    public function test_deployed_has_no_further_transitions(): void
    {
        $this->assertFalse(DeploymentLifecycle::canTransition(DeploymentManifest::STATUS_DEPLOYED, DeploymentManifest::STATUS_PREPARED));
    }
}
