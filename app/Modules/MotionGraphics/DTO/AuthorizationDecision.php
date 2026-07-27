<?php

namespace App\Modules\MotionGraphics\DTO;

/**
 * Explicit allow/deny outcome for every adapter authorization check.
 * Never carries sensitive internals — only a stable machine code and a
 * safe, non-leaking reason. See
 * docs/motion-saas-integration/LARAVEL_ADAPTER_CONTRACT.md: "Every
 * decision is explicit ... and never throws sensitive internals across
 * the boundary."
 */
class AuthorizationDecision
{
    private function __construct(
        public readonly bool $allowed,
        public readonly string $code,
        public readonly string $reason,
        public readonly string $policyVersion = '1.0',
    ) {}

    public static function allow(string $code = 'allowed', string $reason = 'Authorization granted.'): self
    {
        return new self(true, $code, $reason);
    }

    public static function deny(string $code, string $reason): self
    {
        return new self(false, $code, $reason);
    }
}
