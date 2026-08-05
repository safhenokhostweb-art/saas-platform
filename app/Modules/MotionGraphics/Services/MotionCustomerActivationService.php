<?php

namespace App\Modules\MotionGraphics\Services;

use App\Models\Site;
use App\Modules\MotionGraphics\DTO\CustomerRuntimeManifest;
use App\Modules\MotionGraphics\DTO\MotionAdapterContext;
use App\Modules\MotionGraphics\Exceptions\MotionAdapterFailClosedException;
use App\Modules\MotionGraphics\Models\SiteAssetPackage;
use App\Modules\MotionGraphics\Support\MotionAuditEvents;
use Illuminate\Support\Facades\DB;
use SaaSPlatform\CoreUtils\Contracts\TenantContextContract;

/**
 * Phase 8 (docs/motion-customer-runtime-phase8/TARGETED_ENABLEMENT.md):
 * the Controlled Activation Flow (task section 8) — everything this
 * service does is a read/write against the SaaS platform's own database
 * (`sites.features`, the existing audit log). It never opens a network
 * connection, never touches `customer-site/`, never executes anything
 * against a remote server — per the explicit instruction that Phase 8
 * must not connect to or deploy against any real server.
 *
 * "Deployment" here means: verify preconditions, build the manifest
 * (MotionCustomerRuntimeService), and flip the same per-site feature
 * flag Phase 6 already built (`sites.features['motion_graphics_integration']`).
 * There is no separate file/code deployment step — the customer-site
 * consumer code (MotionRuntimeClient et al.) is assumed already present,
 * exactly as every prior phase's audits confirmed for other reasons.
 */
final class MotionCustomerActivationService
{
    private const FEATURE_KEY = 'motion_graphics_integration';

    public function __construct(
        private readonly TenantContextContract $tenantContext,
        private readonly MotionAuditEvents $auditEvents,
        private readonly MotionCustomerRuntimeService $runtimeService,
    ) {}

    /**
     * Step "Validate site, license and health" + "Prepare deployment
     * manifest" + "Generate customer runtime package" — verifies every
     * precondition and returns the manifest that *would* be delivered,
     * without enabling anything. Safe to call repeatedly (read-only
     * against this site; never touches another site).
     *
     * @throws MotionAdapterFailClosedException on any unmet precondition
     */
    public function prepare(Site $site, MotionAdapterContext $context): CustomerRuntimeManifest
    {
        if (! $site->is_active) {
            $this->fail($site, $context, 'site_inactive', 'The site is not active.');
        }

        $this->tenantContext->set($site);

        try {
            $manifest = $this->runtimeService->buildManifestForSite($site, $context);
        } catch (MotionAdapterFailClosedException $e) {
            $this->fail($site, $context, $e->failureCode, $e->getMessage());
        }

        return $manifest;
    }

    /**
     * Step "Enable site-specific flag" — the only site-mutating action in
     * this whole service.
     *
     * Phase 8 hard-capped this at exactly one site globally
     * (`another_site_already_enabled`) — a deliberate stand-in for real
     * entitlement, appropriate for "one controlled test site" when no
     * commercial entitlement existed yet. Phase 9
     * (docs/motion-commercialization/ENTITLEMENT_RULES.md) removes that
     * cap: `prepare()` above already calls
     * MotionCustomerRuntimeService::buildManifestForSite(), which already
     * calls RealMotionLicenseGateway::validateEntitlement() — and since
     * the `motion-graphics` module is now `type=paid`
     * (docs/motion-commercialization/AUDIT_REPORT.md §5), that check now
     * requires a real, site-specific `ModuleLicense` row
     * (ModuleLicenseService::hasValidLicense()'s existing TYPE_PAID gate,
     * unmodified). A real, per-site, revocable/expirable entitlement
     * check is strictly stronger than an arbitrary single-site cap — this
     * still satisfies "do not enable globally" (every site needs its own
     * valid license) and "do not activate real customers automatically"
     * (this method is never called by the purchase-fulfillment pipeline
     * itself — see ENTITLEMENT_RULES.md).
     *
     * @throws MotionAdapterFailClosedException
     */
    public function enable(Site $site, MotionAdapterContext $context): CustomerRuntimeManifest
    {
        $manifest = $this->prepare($site, $context);

        $this->auditEvents->record(MotionAuditEvents::CUSTOMER_DEPLOYMENT_STARTED, [
            'site_id' => $site->id,
            'template_reference' => $manifest->templateReference,
            'package_id' => $manifest->packageId,
            'manifest_checksum' => $manifest->manifestChecksum,
            'previous_state' => (bool) ($site->features[self::FEATURE_KEY] ?? false),
            'actor' => $context->actorId,
            'correlation_id' => $context->correlationId,
        ]);

        DB::transaction(function () use ($site): void {
            $features = $site->features ?? [];
            $features[self::FEATURE_KEY] = true;
            $site->features = $features;
            $site->save();
        });

        $this->auditEvents->record(MotionAuditEvents::CUSTOMER_DEPLOYMENT_COMPLETED, [
            'site_id' => $site->id,
            'manifest_checksum' => $manifest->manifestChecksum,
            'new_state' => true,
            'actor' => $context->actorId,
            'correlation_id' => $context->correlationId,
        ]);

        $this->auditEvents->record(MotionAuditEvents::CUSTOMER_RUNTIME_ENABLED, [
            'site_id' => $site->id,
            'template_reference' => $manifest->templateReference,
            'package_id' => $manifest->packageId,
            'manifest_checksum' => $manifest->manifestChecksum,
            'previous_state' => false,
            'new_state' => true,
            'actor' => $context->actorId,
            'correlation_id' => $context->correlationId,
        ]);

        return $manifest;
    }

    /**
     * Rollback: disable the site flag. Nothing else needs restoring —
     * the manifest is computed on demand from `SiteAssetPackage`, never
     * persisted separately, so there is no separate "previous manifest"
     * to restore; disabling the flag alone makes every future fetch fail
     * closed (`motion_adapter_disabled`), and the customer-site cache
     * naturally expires within its existing 300s TTL. Existing site
     * content is untouched — this call only ever writes `sites.features`.
     */
    public function disable(Site $site, MotionAdapterContext $context, string $reason): void
    {
        $wasEnabled = (bool) ($site->features[self::FEATURE_KEY] ?? false);

        $this->auditEvents->record(MotionAuditEvents::CUSTOMER_ROLLBACK_STARTED, [
            'site_id' => $site->id,
            'previous_state' => $wasEnabled,
            'reason' => $reason,
            'actor' => $context->actorId,
            'correlation_id' => $context->correlationId,
        ]);

        DB::transaction(function () use ($site): void {
            $features = $site->features ?? [];
            $features[self::FEATURE_KEY] = false;
            $site->features = $features;
            $site->save();
        });

        $this->auditEvents->record(MotionAuditEvents::CUSTOMER_ROLLBACK_COMPLETED, [
            'site_id' => $site->id,
            'previous_state' => $wasEnabled,
            'new_state' => false,
            'reason' => $reason,
            'actor' => $context->actorId,
            'correlation_id' => $context->correlationId,
        ]);

        $this->auditEvents->record(MotionAuditEvents::CUSTOMER_RUNTIME_DISABLED, [
            'site_id' => $site->id,
            'previous_state' => $wasEnabled,
            'new_state' => false,
            'reason' => $reason,
            'actor' => $context->actorId,
            'correlation_id' => $context->correlationId,
        ]);
    }

    /**
     * Task section 9's "logical" health checks — everything checkable
     * without a live HTTP request to a real site (explicitly out of
     * scope this phase): the manifest builds successfully, its checksum
     * is stable across two independent builds of the same state (proves
     * determinism, not a fluke), and the active package genuinely has at
     * least one asset.
     *
     * @return array{healthy: bool, manifest_checksum: ?string, asset_count: int, reason: ?string}
     */
    public function healthCheck(Site $site, MotionAdapterContext $context): array
    {
        try {
            $first = $this->prepare($site, $context);
            $second = $this->prepare($site, $context);
        } catch (MotionAdapterFailClosedException $e) {
            $this->auditEvents->record(MotionAuditEvents::CUSTOMER_HEALTHCHECK_FAILED, [
                'site_id' => $site->id,
                'reason' => $e->failureCode.': '.$e->getMessage(),
                'actor' => $context->actorId,
                'correlation_id' => $context->correlationId,
            ]);

            return ['healthy' => false, 'manifest_checksum' => null, 'asset_count' => 0, 'reason' => $e->failureCode];
        }

        if ($first->manifestChecksum !== $second->manifestChecksum) {
            $this->auditEvents->record(MotionAuditEvents::CUSTOMER_HEALTHCHECK_FAILED, [
                'site_id' => $site->id,
                'reason' => 'checksum_unstable',
                'actor' => $context->actorId,
                'correlation_id' => $context->correlationId,
            ]);

            return ['healthy' => false, 'manifest_checksum' => $first->manifestChecksum, 'asset_count' => count($first->assets), 'reason' => 'checksum_unstable'];
        }

        if (count($first->assets) === 0) {
            $this->auditEvents->record(MotionAuditEvents::CUSTOMER_HEALTHCHECK_FAILED, [
                'site_id' => $site->id,
                'reason' => 'empty_package',
                'actor' => $context->actorId,
                'correlation_id' => $context->correlationId,
            ]);

            return ['healthy' => false, 'manifest_checksum' => $first->manifestChecksum, 'asset_count' => 0, 'reason' => 'empty_package'];
        }

        return ['healthy' => true, 'manifest_checksum' => $first->manifestChecksum, 'asset_count' => count($first->assets), 'reason' => null];
    }

    /**
     * @return never
     */
    private function fail(Site $site, MotionAdapterContext $context, string $code, string $reason): void
    {
        $this->auditEvents->record(MotionAuditEvents::CUSTOMER_DEPLOYMENT_FAILED, [
            'site_id' => $site->id,
            'reason' => $code.': '.$reason,
            'actor' => $context->actorId,
            'correlation_id' => $context->correlationId,
        ]);

        throw new MotionAdapterFailClosedException($code, $reason);
    }
}
