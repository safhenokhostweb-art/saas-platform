<?php

namespace App\Modules\MotionGraphics\DTO;

/**
 * Outcome of `MotionAssetResolverContract::validateCompatibility()`.
 */
class CompatibilityDecision
{
    private function __construct(
        public readonly bool $compatible,
        public readonly string $code,
        public readonly string $reason,
    ) {}

    public static function compatible(string $code = 'compatible', string $reason = 'Asset satisfies template requirement.'): self
    {
        return new self(true, $code, $reason);
    }

    public static function incompatible(string $code, string $reason): self
    {
        return new self(false, $code, $reason);
    }
}
