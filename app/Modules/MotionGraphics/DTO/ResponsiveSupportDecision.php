<?php

namespace App\Modules\MotionGraphics\DTO;

/**
 * Outcome of the responsive-support validation step (task scope item 4).
 * `AssetReadModel` carries no responsive/breakpoint data today — same
 * honest-stub pattern as LicenseCompatibilityDecision and
 * PerformanceBudgetDecision.
 */
class ResponsiveSupportDecision
{
    private function __construct(
        public readonly bool $allowed,
        public readonly string $code,
        public readonly string $reason,
    ) {}

    public static function allow(string $code = 'responsive_ok', string $reason = 'Responsive support is satisfied.'): self
    {
        return new self(true, $code, $reason);
    }

    public static function deny(string $code, string $reason): self
    {
        return new self(false, $code, $reason);
    }

    public static function notImplemented(): self
    {
        return new self(true, 'responsive_support_not_implemented', 'No responsive/breakpoint data is exposed by the read contract yet; the step passes by explicit, documented default.');
    }
}
