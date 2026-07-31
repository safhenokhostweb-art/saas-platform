<?php

namespace App\Modules\MotionGraphics\DTO;

/**
 * Outcome of the license-compatibility validation step in the
 * activation pipeline (task scope item 6). No Motion-asset-specific
 * license/entitlement persistence exists yet — see
 * docs/motion-saas-integration/LICENSE_MODEL.md ("a contract only; it
 * does not implement pricing, payment, or entitlement persistence") —
 * so this decision currently always allows, with the explicit, stable,
 * auditable code `license_check_not_implemented` rather than silently
 * skipping the step. A future phase can wire real logic here without
 * changing the activation pipeline's shape.
 */
class LicenseCompatibilityDecision
{
    private function __construct(
        public readonly bool $allowed,
        public readonly string $code,
        public readonly string $reason,
    ) {}

    public static function allow(string $code = 'license_ok', string $reason = 'License is compatible.'): self
    {
        return new self(true, $code, $reason);
    }

    public static function deny(string $code, string $reason): self
    {
        return new self(false, $code, $reason);
    }

    public static function notImplemented(): self
    {
        return new self(true, 'license_check_not_implemented', 'No Motion-asset license backend exists yet; the step passes by explicit, documented default.');
    }
}
