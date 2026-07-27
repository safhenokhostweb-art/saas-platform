<?php

namespace App\Modules\MotionGraphics\Exceptions;

use RuntimeException;

/**
 * Thrown whenever the adapter must fail closed: the integration flag is
 * disabled, contract version is unsupported, tenant context does not
 * match, or the requested module capability is not yet exposed by this
 * phase. Carries a stable code and a safe message only — see
 * docs/motion-saas-integration/INTEGRATION_BOUNDARY.md: "Unknown
 * versions, missing site context, stale checksums, or unavailable
 * bindings fail closed."
 */
class MotionAdapterFailClosedException extends RuntimeException
{
    public readonly string $failureCode;

    public function __construct(string $failureCode, string $safeMessage)
    {
        parent::__construct($safeMessage);
        $this->failureCode = $failureCode;
    }
}
