<?php

namespace SaaSPlatform\ThemeRuntime\Attributes;

use Attribute;

/**
 * Phase 2i (ADR-029) — the classification mechanism Section 8 of the phase
 * spec asked for: metadata co-located with the method it describes, not a
 * separately-maintained allowlist file. Any host method that redeclares a
 * name already defined on the shared canonical BlockRenderer MUST carry
 * this attribute or a durable test (ThemeRuntimeCanonicalParityTest) fails
 * — a future undocumented duplicate renderFoo() cannot silently reappear.
 */
#[Attribute(Attribute::TARGET_METHOD)]
class HostOwnedOverride
{
    public const REASON_TENANT_DATA = 'tenant_data';
    public const REASON_HOST_CONTEXT = 'host_context';
    public const REASON_UNMERGED_CAPABILITY = 'unmerged_capability';
    public const REASON_DISPATCH_INFRASTRUCTURE = 'dispatch_infrastructure';

    public function __construct(
        public readonly string $reason,
        public readonly string $category,
    ) {
    }
}
