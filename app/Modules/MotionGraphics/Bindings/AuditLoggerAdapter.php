<?php

namespace App\Modules\MotionGraphics\Bindings;

use SaaSPlatform\CoreUtils\Contracts\Services\ActivityLoggerInterface;
use SaaSPlatform\CoreUtils\Contracts\TenantContextContract;
use SaaSPlatform\Modules\MotionGraphics\Contracts\MotionAuditLoggerContract;

/**
 * Host-app implementation of MotionAuditLoggerContract, forwarding the
 * standalone module's own internal audit events (motion.created,
 * motion.published, ...) into the platform's existing activity log. A
 * no-op while the integration flag is disabled, matching the module's own
 * NullMotionAuditLogger default.
 */
final class AuditLoggerAdapter implements MotionAuditLoggerContract
{
    public function __construct(
        private readonly TenantContextContract $tenantContext,
        private readonly ActivityLoggerInterface $logger,
    ) {}

    public function record(string $event, array $context = []): void
    {
        if (! $this->tenantContext->hasFeature('motion_graphics_integration')) {
            return;
        }

        $this->logger->log($event, null, null, $context);
    }
}
