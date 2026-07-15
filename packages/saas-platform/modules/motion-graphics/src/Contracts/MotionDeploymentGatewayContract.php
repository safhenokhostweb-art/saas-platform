<?php

namespace SaaSPlatform\Modules\MotionGraphics\Contracts;

interface MotionDeploymentGatewayContract
{
    public function published(string $motionUuid, int $version): void;
}
