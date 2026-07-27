<?php

namespace App\Modules\MotionGraphics\DTO;

/**
 * Cross-boundary request envelope. Carries only opaque identifiers and
 * correlation metadata — never ORM models, credentials, or authoring
 * data. See docs/motion-saas-integration/INTEGRATION_BOUNDARY.md:
 * "Cross-boundary requests carry contract version, opaque actor/customer
 * /site/template IDs, correlation and idempotency IDs, expected
 * version/checksum, license decision reference, and locale/direction."
 *
 * `siteId` here is the value the caller believes is current — every
 * adapter method re-derives the trusted site from TenantContext and
 * rejects a mismatch instead of trusting this field outright.
 */
class MotionAdapterContext
{
    public function __construct(
        public readonly string $actorId,
        public readonly string $siteId,
        public readonly string $correlationId,
        public readonly string $contractVersion = '1.0',
        public readonly ?string $idempotencyKey = null,
        public readonly ?string $expectedChecksum = null,
        public readonly ?string $licenseDecisionRef = null,
        public readonly ?string $locale = null,
        public readonly ?string $direction = null,
    ) {}
}
