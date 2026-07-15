<?php

namespace SaaSPlatform\Modules\MotionGraphics\Support;

use SaaSPlatform\Modules\MotionGraphics\Contracts\MotionDeploymentGatewayContract;

final class NullMotionDeploymentGateway implements MotionDeploymentGatewayContract
{
    public function published(string $motionUuid, int $version): void {}
}
