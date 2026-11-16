<?php

namespace App\Modules\MotionGraphics\Support;

use SaaSPlatform\CoreUtils\Contracts\Services\ActivityLoggerInterface;

/**
 * Adapter-level audit-event preparation (Phase 1 task scope item 7,
 * extended by Phase 3 task scope item 9): records events into the
 * platform's existing activity log. This is distinct from the
 * standalone module's own internal audit trail (motion.created,
 * motion.published, ...), which is recorded separately by the module
 * through MotionAuditLoggerContract.
 */
class MotionAuditEvents
{
    public const ASSET_RESOLVED = 'motion.asset.resolved';
    public const PACKAGE_CREATED = 'motion.package.created';
    public const RUNTIME_REQUESTED = 'motion.runtime.requested';

    // Phase 3 — persistence & site package activation.
    public const TEMPLATE_PACKAGE_CREATED = 'motion.template.package.created';
    public const SITE_PACKAGE_CREATED = 'motion.site.package.created';
    public const ASSET_ACTIVATED = 'motion.asset.activated';
    public const ASSET_ROLLBACK = 'motion.asset.rollback';
    // ADR-078: an optional/manifest item left out because the site is not
    // entitled to it (or it is no longer published). Never an error.
    public const ASSET_SKIPPED = 'motion.asset.skipped';

    // Phase 4 — runtime installation & template activation bridge.
    public const TEMPLATE_ACTIVATION_STARTED = 'motion.template.activation.started';
    public const TEMPLATE_ACTIVATION_COMPLETED = 'motion.template.activation.completed';
    public const TEMPLATE_ACTIVATION_FAILED = 'motion.template.activation.failed';
    public const RUNTIME_PROJECTION_GENERATED = 'motion.runtime.projection.generated';

    // Phase 5 — SaaS deployment & customer runtime integration preparation.
    // One event per docs/motion-deployment-preparation/DEPLOYMENT_LIFECYCLE.md state.
    public const DEPLOYMENT_PREPARED = 'motion.deployment.prepared';
    public const DEPLOYMENT_VALIDATED = 'motion.deployment.validated';
    public const DEPLOYMENT_APPROVED = 'motion.deployment.approved';
    public const DEPLOYMENT_READY = 'motion.deployment.ready';

    // Phase 7 — admin management & internal site activation
    // (docs/motion-admin-management/). One event per admin action, in
    // addition to (never instead of) the events the delegated-to
    // services already record.
    public const ADMIN_PACKAGE_ASSIGNED = 'motion.admin.package_assigned';
    public const ADMIN_SITE_ACTIVATION = 'motion.admin.site_activation';
    public const ADMIN_RUNTIME_PREVIEW = 'motion.admin.runtime_preview';

    // Phase 8 — real customer-site runtime integration & controlled live
    // test (docs/motion-customer-runtime-phase8/). Exactly the 9 events
    // the task specifies; every call site includes actor, site, template,
    // package, manifest checksum (where applicable), previous/new state,
    // correlation ID, and timestamp (via the shared audit logger), plus a
    // failure reason on the two failure events.
    public const CUSTOMER_DEPLOYMENT_PREPARED = 'motion.customer.deployment.prepared';
    public const CUSTOMER_DEPLOYMENT_STARTED = 'motion.customer.deployment.started';
    public const CUSTOMER_DEPLOYMENT_COMPLETED = 'motion.customer.deployment.completed';
    public const CUSTOMER_DEPLOYMENT_FAILED = 'motion.customer.deployment.failed';
    public const CUSTOMER_RUNTIME_ENABLED = 'motion.customer.runtime.enabled';
    public const CUSTOMER_RUNTIME_DISABLED = 'motion.customer.runtime.disabled';
    public const CUSTOMER_ROLLBACK_STARTED = 'motion.customer.rollback.started';
    public const CUSTOMER_ROLLBACK_COMPLETED = 'motion.customer.rollback.completed';
    public const CUSTOMER_HEALTHCHECK_FAILED = 'motion.customer.healthcheck.failed';

    // Phase X — bespoke/custom Motion commercialization
    // (docs/motion-custom-assets/). One event per commission lifecycle
    // transition and per assignment/rollback action.
    public const BESPOKE_COMMISSION_CREATED = 'motion.bespoke.commission.created';
    public const BESPOKE_COMMISSION_TRANSITIONED = 'motion.bespoke.commission.transitioned';
    public const BESPOKE_ASSIGNED = 'motion.bespoke.assigned';
    public const BESPOKE_ROLLBACK = 'motion.bespoke.rollback';
    public const BESPOKE_REVOKED = 'motion.bespoke.revoked';

    // ADR-086 — central catalog controls (MotionCatalogAdminService).
    public const CATALOG_ASSET_ENABLED = 'motion.catalog.asset.enabled';
    public const CATALOG_ASSET_DISABLED = 'motion.catalog.asset.disabled';
    public const CATALOG_ASSET_GRANTED = 'motion.catalog.asset.granted';
    public const CATALOG_ASSET_GRANT_REVOKED = 'motion.catalog.asset.grant_revoked';

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
