<?php

namespace App\Modules\MotionGraphics\DTO;

/**
 * Outcome of the performance-budget validation step (task scope item 4).
 * `AssetReadModel` (docs/motion-read-contracts/ASSET_READ_CONTRACT.md)
 * carries no file-size/weight data today, so — exactly like
 * LicenseCompatibilityDecision — this decision is an honest, explicit,
 * auditable pass-through rather than a fabricated check. A future phase
 * that adds real weight data to the read contract only needs to replace
 * this one decision's implementation.
 */
class PerformanceBudgetDecision
{
    private function __construct(
        public readonly bool $allowed,
        public readonly string $code,
        public readonly string $reason,
    ) {}

    public static function allow(string $code = 'performance_ok', string $reason = 'Performance budget is satisfied.'): self
    {
        return new self(true, $code, $reason);
    }

    public static function deny(string $code, string $reason): self
    {
        return new self(false, $code, $reason);
    }

    public static function notImplemented(): self
    {
        return new self(true, 'performance_budget_not_implemented', 'No asset weight data is exposed by the read contract yet; the step passes by explicit, documented default.');
    }
}
