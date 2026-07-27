<?php

namespace App\Modules\MotionGraphics\Support;

use App\Modules\MotionGraphics\Exceptions\MotionAdapterFailClosedException;
use SaaSPlatform\CoreUtils\Contracts\TenantContextContract;

/**
 * Shared fail-closed guards for every Motion Graphics adapter class:
 * the `motion_graphics_integration` feature flag, and tenant-context
 * verification. Tenant isolation is enforced here rather than trusting
 * whatever `site_id` a caller supplies — see
 * docs/motion-saas-integration/INTEGRATION_BOUNDARY.md ("Never trust
 * incoming site_id directly. Resolve tenant from existing platform
 * context.").
 */
abstract class AbstractMotionAdapter
{
    public const FEATURE_FLAG = 'motion_graphics_integration';

    public function __construct(
        protected readonly TenantContextContract $tenantContext,
        protected readonly MotionAuditEvents $auditEvents,
    ) {}

    /**
     * @throws MotionAdapterFailClosedException when the integration flag is disabled
     */
    protected function ensureEnabled(): void
    {
        if (! $this->tenantContext->hasFeature(self::FEATURE_FLAG)) {
            throw new MotionAdapterFailClosedException(
                'motion_adapter_disabled',
                'The Motion Graphics adapter is disabled.'
            );
        }
    }

    /**
     * The platform-trusted current site identifier, or null when there is
     * no resolvable site tenant (e.g. a Platform Admin request).
     */
    protected function trustedSiteId(): ?string
    {
        $site = $this->tenantContext->site();

        if ($site === null || ! isset($site->id)) {
            return null;
        }

        return (string) $site->id;
    }

    /**
     * @throws MotionAdapterFailClosedException when the claimed site does not match the trusted tenant context
     */
    protected function assertSiteMatches(string $claimedSiteId): void
    {
        $trusted = $this->trustedSiteId();

        if ($trusted === null || $trusted !== $claimedSiteId) {
            throw new MotionAdapterFailClosedException(
                'tenant_context_mismatch',
                'The requested site does not match the authenticated tenant context.'
            );
        }
    }
}
