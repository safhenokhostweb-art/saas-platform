<?php

namespace App\Modules\MotionGraphics\DTO;

/**
 * Outcome of an ownership check (`SiteAssetPackageContract::validateOwnership`,
 * `MotionAuthorizationContract::validateOwnership`).
 */
class OwnershipDecision
{
    private function __construct(
        public readonly bool $owns,
        public readonly string $code,
        public readonly string $reason,
    ) {}

    public static function owned(string $code = 'owned', string $reason = 'Resource belongs to the requesting site.'): self
    {
        return new self(true, $code, $reason);
    }

    public static function notOwned(string $code, string $reason): self
    {
        return new self(false, $code, $reason);
    }
}
