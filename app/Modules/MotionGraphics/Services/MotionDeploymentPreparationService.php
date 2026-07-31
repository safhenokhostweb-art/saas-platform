<?php

namespace App\Modules\MotionGraphics\Services;

use App\Modules\MotionGraphics\Contracts\MotionAuthorizationInterface;
use App\Modules\MotionGraphics\Contracts\MotionDeploymentGatewayContract;
use App\Modules\MotionGraphics\Contracts\MotionLicenseGatewayContract;
use App\Modules\MotionGraphics\DTO\DeploymentManifest;
use App\Modules\MotionGraphics\DTO\MotionAdapterContext;
use App\Modules\MotionGraphics\DTO\MotionAssetReferenceDTO;
use App\Modules\MotionGraphics\DTO\RuntimeProjectionDTO;
use App\Modules\MotionGraphics\DTO\ValidationReport;
use App\Modules\MotionGraphics\Exceptions\MotionAdapterFailClosedException;
use App\Modules\MotionGraphics\Models\SiteAssetPackage;
use App\Modules\MotionGraphics\Support\AbstractMotionAdapter;
use App\Modules\MotionGraphics\Support\DeploymentLifecycle;
use App\Modules\MotionGraphics\Support\MotionAuditEvents;
use App\Modules\MotionGraphics\Support\ReadModelMapper;
use App\Modules\MotionGraphics\Support\TemplateBuilderCompatibilityValidator;
use Illuminate\Support\Str;
use SaaSPlatform\CoreUtils\Contracts\TenantContextContract;
use SaaSPlatform\Modules\MotionGraphics\Contracts\Read\MotionAssetReadContract;
use SaaSPlatform\Modules\MotionGraphics\Contracts\Read\MotionRuntimeProjectionReadContract;

/**
 * Prepares (never executes) a Motion Graphics deployment for an already
 * -activated Site Asset Package (Phase 3/4). Drives the state lifecycle
 * documented in docs/motion-deployment-preparation/DEPLOYMENT_LIFECYCLE.md:
 *
 * ```text
 * prepareDeployment()  -> Prepared
 * validateDeployment() -> Validated
 * approveDeployment()  -> Approved
 * markReady()          -> Ready   (never further — "Deployed" is a future phase)
 * ```
 *
 * Never executes a deployment, transfers a file, or modifies a customer
 * server — every write this service performs is in-memory DTO
 * construction; the only "external" calls are to the provider-neutral,
 * fake-only MotionDeploymentGatewayContract and
 * MotionLicenseGatewayContract.
 */
final class MotionDeploymentPreparationService extends AbstractMotionAdapter
{
    public function __construct(
        TenantContextContract $tenantContext,
        MotionAuditEvents $auditEvents,
        private readonly MotionAuthorizationInterface $authorization,
        private readonly MotionLicenseGatewayContract $license,
        private readonly MotionDeploymentGatewayContract $deploymentGateway,
        private readonly MotionAssetReadContract $assets,
        private readonly MotionRuntimeProjectionReadContract $projections,
        private readonly TemplateBuilderCompatibilityValidator $compatibilityValidator,
    ) {
        parent::__construct($tenantContext, $auditEvents);
    }

    public function prepareDeployment(SiteAssetPackage $sitePackage, MotionAdapterContext $context): DeploymentManifest
    {
        $this->ensureEnabled();
        $this->authorizeStep($context, 'motion.prepare_deployment', (string) $sitePackage->uuid);
        $this->assertSiteMatches($context->siteId);

        if ((string) $sitePackage->site_id !== $context->siteId) {
            throw new MotionAdapterFailClosedException(
                'tenant_isolation_violation',
                'This site package does not belong to the requesting site.'
            );
        }
        if ($sitePackage->activation_status !== SiteAssetPackage::STATUS_ACTIVE) {
            throw new MotionAdapterFailClosedException(
                'site_package_not_active',
                'Only an active site package can be prepared for deployment.'
            );
        }
        if ($sitePackage->items->isEmpty()) {
            throw new MotionAdapterFailClosedException(
                'empty_site_package',
                'A site package with no items cannot be deployed.'
            );
        }

        $templatePackage = $sitePackage->templateAssetPackage;
        $compatibilityRules = $templatePackage->compatibility_rules ?? [];

        $assetReferences = [];
        $runtimeProjections = [];

        foreach ($sitePackage->items as $item) {
            $assetModel = $this->assets->findByCanonicalId($item->asset_id, $item->active_version);
            if ($assetModel === null) {
                throw new MotionAdapterFailClosedException(
                    'asset_not_available',
                    "Asset [{$item->asset_id}] is not currently readable."
                );
            }

            $assetDto = ReadModelMapper::toAssetReferenceDTO($assetModel);
            $compatibility = $this->compatibilityValidator->validate($assetDto, $compatibilityRules);
            if (! $compatibility->valid) {
                throw new MotionAdapterFailClosedException(
                    'compatibility_rejected',
                    $compatibility->errors[0]['message'] ?? 'Template compatibility validation failed.'
                );
            }

            $licenseDecision = $this->license->checkAvailability($item->asset_id, $context->siteId);
            if (! $licenseDecision->allowed) {
                throw new MotionAdapterFailClosedException($licenseDecision->code, $licenseDecision->reason);
            }

            $projection = $this->projections->projectAsset($item->asset_id, $context->siteId);
            if ($projection === null) {
                throw new MotionAdapterFailClosedException(
                    'asset_not_available_for_site',
                    "Asset [{$item->asset_id}] does not have a published runtime projection for this site."
                );
            }

            $assetReferences[] = $assetDto;
            $runtimeProjections[] = ReadModelMapper::toRuntimeProjectionDTO($projection);
        }

        $entitlementDecision = $this->license->validateEntitlement($context->siteId, $templatePackage->template_reference);
        if (! $entitlementDecision->allowed) {
            throw new MotionAdapterFailClosedException($entitlementDecision->code, $entitlementDecision->reason);
        }

        $manifest = new DeploymentManifest(
            uuid: (string) Str::uuid(),
            siteReference: $context->siteId,
            templateReference: $templatePackage->template_reference,
            packageVersion: $templatePackage->package_version,
            assetReferences: $assetReferences,
            runtimeProjectionReferences: $runtimeProjections,
            checksum: $this->computeChecksum($context->siteId, $templatePackage->template_reference, $templatePackage->package_version, $assetReferences, $runtimeProjections),
            compatibilityResult: ValidationReport::pass(),
        );

        $gatewayResult = $this->deploymentGateway->prepareDeployment($manifest);
        if (! $gatewayResult->allowed) {
            throw new MotionAdapterFailClosedException($gatewayResult->code, $gatewayResult->reason);
        }

        $this->auditEvents->record(MotionAuditEvents::DEPLOYMENT_PREPARED, [
            'manifest_uuid' => $manifest->uuid,
            'site_id' => $context->siteId,
            'template_reference' => $manifest->templateReference,
            'asset_count' => count($assetReferences),
            'actor' => $context->actorId,
            'correlation_id' => $context->correlationId,
        ]);

        return $manifest;
    }

    public function validateDeployment(DeploymentManifest $manifest, MotionAdapterContext $context): DeploymentManifest
    {
        $manifest = $this->transition($manifest, DeploymentManifest::STATUS_VALIDATED, 'motion.prepare_deployment', $context);

        $entitlementDecision = $this->license->validateEntitlement($context->siteId, $manifest->templateReference);
        if (! $entitlementDecision->allowed) {
            throw new MotionAdapterFailClosedException($entitlementDecision->code, $entitlementDecision->reason);
        }

        $targetResult = $this->deploymentGateway->validateTarget($manifest->siteReference, $context);
        if (! $targetResult->allowed) {
            throw new MotionAdapterFailClosedException($targetResult->code, $targetResult->reason);
        }

        $validated = $manifest->withStatus(DeploymentManifest::STATUS_VALIDATED);

        $this->auditEvents->record(MotionAuditEvents::DEPLOYMENT_VALIDATED, [
            'manifest_uuid' => $validated->uuid,
            'site_id' => $context->siteId,
            'actor' => $context->actorId,
            'correlation_id' => $context->correlationId,
        ]);

        return $validated;
    }

    public function approveDeployment(DeploymentManifest $manifest, MotionAdapterContext $context): DeploymentManifest
    {
        $approved = $this->transition($manifest, DeploymentManifest::STATUS_APPROVED, 'motion.approve_deployment', $context)
            ->withStatus(DeploymentManifest::STATUS_APPROVED);

        $this->auditEvents->record(MotionAuditEvents::DEPLOYMENT_APPROVED, [
            'manifest_uuid' => $approved->uuid,
            'site_id' => $context->siteId,
            'actor' => $context->actorId,
            'correlation_id' => $context->correlationId,
        ]);

        return $approved;
    }

    public function markReady(DeploymentManifest $manifest, MotionAdapterContext $context): DeploymentManifest
    {
        $manifest = $this->transition($manifest, DeploymentManifest::STATUS_READY, 'motion.prepare_deployment', $context);

        $targetResult = $this->deploymentGateway->validateTarget($manifest->siteReference, $context);
        if (! $targetResult->allowed) {
            throw new MotionAdapterFailClosedException($targetResult->code, $targetResult->reason);
        }

        $ready = $manifest->withStatus(DeploymentManifest::STATUS_READY);

        $this->auditEvents->record(MotionAuditEvents::DEPLOYMENT_READY, [
            'manifest_uuid' => $ready->uuid,
            'site_id' => $context->siteId,
            'actor' => $context->actorId,
            'correlation_id' => $context->correlationId,
        ]);

        return $ready;
    }

    private function transition(DeploymentManifest $manifest, string $to, string $ability, MotionAdapterContext $context): DeploymentManifest
    {
        $this->ensureEnabled();
        $this->authorizeStep($context, $ability, $manifest->uuid);
        $this->assertSiteMatches($context->siteId);

        if ($manifest->siteReference !== $context->siteId) {
            throw new MotionAdapterFailClosedException(
                'tenant_isolation_violation',
                'This deployment manifest does not belong to the requesting site.'
            );
        }
        if (! DeploymentLifecycle::canTransition($manifest->status, $to)) {
            throw new MotionAdapterFailClosedException(
                'invalid_lifecycle_transition',
                "Cannot move a deployment from [{$manifest->status}] to [{$to}]."
            );
        }

        return $manifest;
    }

    private function authorizeStep(MotionAdapterContext $context, string $ability, string $resourceRef): void
    {
        $decision = $this->authorization->authorize($context->actorId, $ability, $resourceRef, $context->siteId);
        if (! $decision->allowed) {
            throw new MotionAdapterFailClosedException($decision->code, $decision->reason);
        }
    }

    /**
     * @param list<MotionAssetReferenceDTO> $assetReferences
     * @param list<RuntimeProjectionDTO> $runtimeProjections
     */
    private function computeChecksum(
        string $siteReference,
        string $templateReference,
        int $packageVersion,
        array $assetReferences,
        array $runtimeProjections,
    ): string {
        $payload = [
            'site_reference' => $siteReference,
            'template_reference' => $templateReference,
            'package_version' => $packageVersion,
            'assets' => array_map(fn (MotionAssetReferenceDTO $dto): array => $dto->toArray(), $assetReferences),
            'projections' => array_map(fn (RuntimeProjectionDTO $dto): array => [
                'asset_id' => $dto->assetReference->assetId,
                'version' => $dto->publishedVersion,
                'optimized_reference' => $dto->optimizedReference,
            ], $runtimeProjections),
        ];

        return hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
    }
}
