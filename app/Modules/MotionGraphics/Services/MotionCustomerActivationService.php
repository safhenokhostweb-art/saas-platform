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
     * precondition and returns the manifest that *would* be delivered.
     * Net effect is read-only (never touches another site, and its own
     * flag flip — see withFlagTemporarilyOn() — is always reverted), so
     * it's safe to call repeatedly, including *before* a site has ever
     * been enabled (task's own "pre-flight checklist" in
     * REAL_CUSTOMER_TEST_PLAN.md calls this before `enable()`).
     *
     * @throws MotionAdapterFailClosedException on any unmet precondition
     */
    public function prepare(Site $site, MotionAdapterContext $context): CustomerRuntimeManifest
    {
        if (! $site->is_active) {
            $this->fail($site, $context, 'site_inactive', 'The site is not active.');
        }

        try {
            $manifest = $this->withFlagTemporarilyOn($site, $context, keepOnSuccess: false);
        } catch (MotionAdapterFailClosedException $e) {
            $this->fail($site, $context, $e->failureCode, $e->getMessage());
        }

        return $manifest;
    }

    /**
     * MotionCustomerRuntimeService::buildManifestForSite() requires
     * `ensureEnabled()` — the flag must already be on — which is correct
     * for the customer-facing fetch path (Phase 8's API controller) but
     * means a *validation-only* call (prepare()/healthCheck(), or the
     * first half of enable()) can't call it while the flag is still off,
     * exactly the state a not-yet-activated site is in. This is not a
     * bypass of ensureEnabled() — it still runs, and still fails closed —
     * it's flipped on first, inside the same DB transaction, so a failed
     * validation rolls the flag back to `$site`'s state *before this
     * call* automatically (both in the DB and, explicitly, in the
     * in-memory model, which a rolled-back transaction does not revert
     * on its own).
     *
     * @throws MotionAdapterFailClosedException
     */
    private function withFlagTemporarilyOn(Site $site, MotionAdapterContext $context, bool $keepOnSuccess): CustomerRuntimeManifest
    {
        $previousState = (bool) ($site->features[self::FEATURE_KEY] ?? false);

        try {
            return DB::transaction(function () use ($site, $context, $keepOnSuccess, $previousState): CustomerRuntimeManifest {
                $site->features = array_merge($site->features ?? [], [self::FEATURE_KEY => true]);
                $site->save();

                $manifest = $this->runtimeService->buildManifestForSite($site, $context);

                if (! $keepOnSuccess) {
                    $site->features = array_merge($site->features ?? [], [self::FEATURE_KEY => $previousState]);
                    $site->save();
                }

                return $manifest;
            });
        } catch (MotionAdapterFailClosedException $e) {
            $site->features = array_merge($site->features ?? [], [self::FEATURE_KEY => $previousState]);
            $site->save();
            throw $e;
        }
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
        if (! $site->is_active) {
            $this->fail($site, $context, 'site_inactive', 'The site is not active.');
        }

        $previousState = (bool) ($site->features[self::FEATURE_KEY] ?? false);

        $this->auditEvents->record(MotionAuditEvents::CUSTOMER_DEPLOYMENT_STARTED, [
            'site_id' => $site->id,
            'previous_state' => $previousState,
            'actor' => $context->actorId,
            'correlation_id' => $context->correlationId,
        ]);

        try {
            $manifest = $this->withFlagTemporarilyOn($site, $context, true);
        } catch (MotionAdapterFailClosedException $e) {
            $this->fail($site, $context, $e->failureCode, $e->getMessage());
        }

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
