<?php

namespace App\Modules\MotionGraphics\DTO;

/**
 * Explicit outcome of a MotionLicenseGatewayContract call. This phase
 * ships only a fake allow/deny adapter (task scope item 5 — "Do not
 * connect real License system yet"); the decision shape is what a real
 * license backend would need to satisfy later. See
 * docs/motion-deployment-preparation/LICENSE_BOUNDARY.md.
 */
class LicenseGatewayDecision
{
    private function __construct(
        public readonly bool $allowed,
        public readonly string $code,
        public readonly string $reason,
    ) {}

    public static function allow(string $code = 'entitled', string $reason = 'Entitlement confirmed.'): self
    {
        return new self(true, $code, $reason);
    }

    public static function deny(string $code, string $reason): self
    {
        return new self(false, $code, $reason);
    }
}
