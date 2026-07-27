<?php

namespace App\Modules\MotionGraphics\Support;

use SaaSPlatform\CoreUtils\Contracts\Services\ActivityLoggerInterface;

/**
 * Phase 1 audit-event preparation only (per task scope, item 7): records
 * the three adapter-level events into the platform's existing activity
 * log. This is distinct from the standalone module's own internal audit
 * trail (motion.created, motion.published, ...), which is recorded
 * separately by the module through MotionAuditLoggerContract.
 */
class MotionAuditEvents
{
    public const ASSET_RESOLVED = 'motion.asset.resolved';
    public const PACKAGE_CREATED = 'motion.package.created';
    public const RUNTIME_REQUESTED = 'motion.runtime.requested';

    public function __construct(
        private readonly ActivityLoggerInterface $logger,
    ) {}

    /**
     * @param array<string, mixed> $properties
     */
    public function record(string $event, array $properties = []): void
    {
        $this->logger->log($event, null, null, $properties);
    }
}
