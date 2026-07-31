<?php

namespace App\Modules\MotionGraphics\DTO;

/**
 * Explicit outcome of a MotionDeploymentGatewayContract call — same
 * "never a bare bool, always a stable code + safe reason" pattern as
 * AuthorizationDecision. See docs/motion-deployment-preparation/DEPLOYMENT_GATEWAY.md.
 */
class DeploymentGatewayResult
{
    private function __construct(
        public readonly bool $allowed,
        public readonly string $code,
        public readonly string $reason,
        public readonly ?string $status = null,
    ) {}

    public static function allow(string $code = 'accepted', string $reason = 'Accepted by the deployment gateway.', ?string $status = null): self
    {
        return new self(true, $code, $reason, $status);
    }

    public static function deny(string $code, string $reason): self
    {
        return new self(false, $code, $reason);
    }
}
