<?php

namespace App\Modules\MotionGraphics\Services;

use App\Modules\MotionGraphics\Contracts\MotionAssetResolverInterface;
use App\Modules\MotionGraphics\Contracts\MotionAuthorizationInterface;
use App\Models\Site;
use App\Modules\MotionGraphics\DTO\MotionAdapterContext;
use App\Modules\MotionGraphics\Exceptions\MotionAdapterFailClosedException;
use App\Modules\MotionGraphics\Models\AssetActivation;
use App\Modules\MotionGraphics\Models\PackageSnapshot;
use App\Modules\MotionGraphics\Models\SiteAssetPackage;
use App\Modules\MotionGraphics\Models\SiteAssetPackageItem;
use App\Modules\MotionGraphics\Models\TemplateAssetPackage;
use App\Modules\MotionGraphics\Support\AbstractMotionAdapter;
use App\Modules\MotionGraphics\Support\MotionAssetLicensePolicy;
use App\Modules\MotionGraphics\Support\MotionAuditEvents;
use App\Modules\MotionGraphics\Support\ReadModelMapper;
use Illuminate\Support\Facades\DB;
use SaaSPlatform\CoreUtils\Contracts\TenantContextContract;
use SaaSPlatform\Modules\MotionGraphics\Contracts\Read\MotionAssetReadContract;
use SaaSPlatform\Modules\MotionGraphics\Contracts\Read\MotionRuntimeProjectionReadContract;
use SaaSPlatform\Modules\MotionGraphics\Contracts\Read\MotionVersionReadContract;

/**
 * Activates a frozen TemplateAssetPackage for one site — resolving,
 * validating, and binding each required asset — and provides the
 * rollback foundation for a previously activated item. See
 * docs/motion-persistence/ACTIVATION_FLOW.md and
 * docs/motion-persistence/ROLLBACK_MODEL.md.
 *
 * Every required asset is fully validated (existence, published state,
 * site ownership, industry/theme compatibility, license compatibility)
 * before any row is written — an invalid package is rejected atomically,
 * never partially applied.
 *
 * Activation and rollback are Platform-Admin-only operations (task scope
 * docs/motion-runtime-installation/README.md — "Platform Admin: ...
 * activate template, rollback activation" / "Customer forbidden: ...
 * activate assets"). `motion.activate_site_package` and
 * `motion.rollback_site_package` are not among the abilities
 * PermissionGatewayAdapter grants a Customer, so only an admin actor
 * passes. Consuming an already-activated package's runtime projection
 * remains customer-accessible via `motion.use` elsewhere (see
 * MotionRuntimeInstallationService::projectSitePackage()).
 */
final class SiteAssetPackageActivationService extends AbstractMotionAdapter
{
    public function __construct(
        TenantContextContract $tenantContext,
        MotionAuditEvents $auditEvents,
        private readonly MotionAuthorizationInterface $authorization,
        private readonly MotionAssetResolverInterface $assetResolver,
        private readonly MotionAssetReadContract $assets,
        private readonly MotionVersionReadContract $versions,
        private readonly MotionRuntimeProjectionReadContract $projections,
        private readonly MotionAssetLicensePolicy $licensePolicy,
    ) {
        parent::__construct($tenantContext, $auditEvents);
    }

    public function activate(
        TemplateAssetPackage $templatePackage,
        ?string $brandVariantReference,
        MotionAdapterContext $context,
    ): SiteAssetPackage {
        $this->ensureEnabled();

        $decision = $this->authorization->authorize(
            $context->actorId, 'motion.activate_site_package', $templatePackage->template_reference, $context->siteId
        );
        if (! $decision->allowed) {
            throw new MotionAdapterFailClosedException($decision->code, $decision->reason);
        }
        $this->assertSiteMatches($context->siteId);

        $site = $this->tenantContext->site();
        if ($site === null || ! isset($site->id)) {
            throw new MotionAdapterFailClosedException('tenant_context_mismatch', 'No resolvable site tenant is present.');
        }

        $resolvedItems = [
            ...$this->validateRequiredAssets($templatePackage, $context->siteId, $site),
            ...$this->resolveEntitledOptionalAssets($templatePackage, $context, $site),
        ];

        return DB::transaction(function () use ($templatePackage, $brandVariantReference, $resolvedItems, $site, $context): SiteAssetPackage {
            $sitePackage = $this->findOrCreateSitePackage($templatePackage, $brandVariantReference, (int) $site->id, $context);

            foreach ($resolvedItems as $resolved) {
                $this->bindItem($sitePackage, $resolved, $context);
            }

            $this->captureSnapshot($sitePackage, $templatePackage, $resolvedItems);

            $sitePackage->update([
                'activation_status' => SiteAssetPackage::STATUS_ACTIVE,
                'activated_at' => now(),
            ]);

            $this->auditEvents->record(MotionAuditEvents::SITE_PACKAGE_CREATED, [
                'site_id' => $sitePackage->site_id,
                'template_asset_package_id' => $sitePackage->template_asset_package_id,
                'item_count' => count($resolvedItems),
                'actor' => $context->actorId,
                'correlation_id' => $context->correlationId,
            ]);

            return $sitePackage->fresh(['items', 'snapshots']);
        });
    }

    public function rollback(SiteAssetPackageItem $item, int $targetVersion, MotionAdapterContext $context): AssetActivation
    {
        $this->ensureEnabled();

        $decision = $this->authorization->authorize($context->actorId, 'motion.rollback_site_package', $item->asset_id, $context->siteId);
        if (! $decision->allowed) {
            throw new MotionAdapterFailClosedException($decision->code, $decision->reason);
        }
        $this->assertSiteMatches($context->siteId);

        $sitePackage = $item->siteAssetPackage;
        $site = $this->tenantContext->site();
        if ($site === null || $sitePackage === null || (int) $site->id !== (int) $sitePackage->site_id) {
            throw new MotionAdapterFailClosedException('tenant_isolation_violation', 'This asset does not belong to the requesting site.');
        }

        $version = $this->versions->resolveVersion($item->asset_id, $targetVersion);
        $assetModel = $version === null ? null : $this->assets->findByCanonicalId($item->asset_id, $targetVersion);
        if ($version === null || $assetModel === null) {
            throw new MotionAdapterFailClosedException(
                'version_not_available',
                'The requested rollback version is not currently readable under module policy.'
            );
        }

        return DB::transaction(function () use ($item, $targetVersion, $assetModel, $sitePackage, $context): AssetActivation {
            $previousVersion = $item->active_version;
            $item->update(['active_version' => $targetVersion, 'bound_checksum' => $assetModel->checksum]);

            $activation = AssetActivation::create([
                'site_asset_package_item_id' => $item->id,
                'asset_id' => $item->asset_id,
                'version' => $targetVersion,
                'previous_version' => $previousVersion,
                'activation_type' => AssetActivation::TYPE_ROLLBACK,
                'activated_at' => now(),
                'actor_identifier' => $context->actorId,
            ]);

            $this->captureSnapshotFromCurrentItems($sitePackage);

            $this->auditEvents->record(MotionAuditEvents::ASSET_ROLLBACK, [
                'site_id' => $sitePackage->site_id,
                'asset_id' => $item->asset_id,
                'version' => $targetVersion,
                'previous_version' => $previousVersion,
                'actor' => $context->actorId,
                'correlation_id' => $context->correlationId,
            ]);

            return $activation;
        });
    }

    /**
     * @return list<array{asset_id: string, version: int, checksum: string, type: string}>
     */
    private function validateRequiredAssets(TemplateAssetPackage $templatePackage, string $siteId, Site $site): array
    {
        $resolved = [];

        foreach ($templatePackage->required_assets as $reference) {
            $assetId = $reference['asset_id'];
            $version = (int) $reference['version'];

            $projection = $this->projections->projectAsset($assetId, $siteId);
            if ($projection === null || $projection->publishedVersion !== $version) {
                throw new MotionAdapterFailClosedException(
                    'asset_not_available_for_site',
                    "Required asset [{$assetId}] is not published, not found, or not owned by this site."
                );
            }

            $assetDto = ReadModelMapper::toAssetReferenceDTO($projection->assetReference);
            $compatibility = $this->assetResolver->validateCompatibility(
                $assetDto,
                $templatePackage->compatibility_rules ?? [],
                []
            );
            if (! $compatibility->compatible) {
                throw new MotionAdapterFailClosedException('compatibility_rejected', $compatibility->reason);
            }

            $license = $this->licensePolicy->decide($site, $assetDto->compatibility);
            if (! $license->allowed) {
                throw new MotionAdapterFailClosedException($license->code, $license->reason);
            }

            $resolved[] = [
                'asset_id' => $assetId,
                'version' => $projection->publishedVersion,
                'checksum' => $projection->assetReference->checksum,
                'type' => $projection->assetReference->type,
            ];
        }

        return $resolved;
    }

    /**
     * ADR-078: optional assets are what makes a library package useful —
     * every site gets the same template package, and each site receives
     * only the items it is entitled to. An optional asset that is not
     * published at its frozen version, not owned by the site, incompatible,
     * or not licensed is left out (and audited); it never fails the
     * activation the way a missing *required* asset does.
     *
     * @return list<array{asset_id: string, version: int, checksum: string, type: string}>
     */
    private function resolveEntitledOptionalAssets(TemplateAssetPackage $templatePackage, MotionAdapterContext $context, Site $site): array
    {
        $resolved = [];

        foreach ($templatePackage->optional_assets ?? [] as $reference) {
            $assetId = (string) ($reference['asset_id'] ?? '');
            $version = (int) ($reference['version'] ?? 0);

            $projection = $assetId === '' ? null : $this->projections->projectAsset($assetId, $context->siteId);
            $reason = match (true) {
                $projection === null || $projection->publishedVersion !== $version => 'asset_not_available_for_site',
                default => null,
            };

            if ($reason === null) {
                $assetDto = ReadModelMapper::toAssetReferenceDTO($projection->assetReference);
                $compatibility = $this->assetResolver->validateCompatibility($assetDto, $templatePackage->compatibility_rules ?? [], []);
                $license = $this->licensePolicy->decide($site, $assetDto->compatibility);
                $reason = ! $compatibility->compatible ? 'compatibility_rejected' : (! $license->allowed ? $license->code : null);
            }

            if ($reason !== null) {
                $this->auditEvents->record(MotionAuditEvents::ASSET_SKIPPED, [
                    'site_id' => $site->id,
                    'asset_id' => $assetId,
                    'version' => $version,
                    'reason' => $reason,
                    'actor' => $context->actorId,
                    'correlation_id' => $context->correlationId,
                ]);
                continue;
            }

            $resolved[] = [
                'asset_id' => $assetId,
                'version' => $projection->publishedVersion,
                'checksum' => $projection->assetReference->checksum,
                'type' => $projection->assetReference->type,
            ];
        }

        return $resolved;
    }

    private function findOrCreateSitePackage(
        TemplateAssetPackage $templatePackage,
        ?string $brandVariantReference,
        int $siteId,
        MotionAdapterContext $context,
    ): SiteAssetPackage {
        $existing = SiteAssetPackage::query()
            ->where('site_id', $siteId)
            ->where('template_reference', $templatePackage->template_reference)
            ->first();

        if ($existing === null) {
            return SiteAssetPackage::query()->create([
                'site_id' => $siteId,
                'template_reference' => $templatePackage->template_reference,
                'template_asset_package_id' => $templatePackage->id,
                'brand_variant_reference' => $brandVariantReference,
                'created_by_actor_identifier' => $context->actorId,
            ]);
        }

        if ($existing->brand_variant_reference !== $brandVariantReference) {
            throw new MotionAdapterFailClosedException(
                'brand_variant_conflict',
                'This site already activated this template package with a different brand variant.'
            );
        }

        if ($existing->template_asset_package_id !== $templatePackage->id) {
            // Upgrade this binding's pointer to the newer frozen package
            // version; items are upgraded individually in bindItem().
            $existing->update(['template_asset_package_id' => $templatePackage->id]);
        }

        return $existing;
    }

    /**
     * @param array{asset_id: string, version: int, checksum: string, type: string} $resolved
     */
    private function bindItem(SiteAssetPackage $sitePackage, array $resolved, MotionAdapterContext $context): void
    {
        $item = SiteAssetPackageItem::query()
            ->where('site_asset_package_id', $sitePackage->id)
            ->where('asset_id', $resolved['asset_id'])
            ->first();

        if ($item === null) {
            $item = SiteAssetPackageItem::query()->create([
                'site_asset_package_id' => $sitePackage->id,
                'asset_id' => $resolved['asset_id'],
                'active_version' => $resolved['version'],
                'bound_checksum' => $resolved['checksum'],
                'installed_at' => now(),
            ]);
            $activationType = AssetActivation::TYPE_INSTALL;
            $previousVersion = null;
        } elseif ($item->active_version !== $resolved['version']) {
            $previousVersion = $item->active_version;
            $item->update(['active_version' => $resolved['version'], 'bound_checksum' => $resolved['checksum']]);
            $activationType = AssetActivation::TYPE_UPGRADE;
        } else {
            return; // no change — idempotent re-activation, no new history entry.
        }

        AssetActivation::query()->create([
            'site_asset_package_item_id' => $item->id,
            'asset_id' => $resolved['asset_id'],
            'version' => $resolved['version'],
            'previous_version' => $previousVersion,
            'activation_type' => $activationType,
            'activated_at' => now(),
            'actor_identifier' => $context->actorId,
        ]);

        $this->auditEvents->record(MotionAuditEvents::ASSET_ACTIVATED, [
            'site_id' => $sitePackage->site_id,
            'asset_id' => $resolved['asset_id'],
            'version' => $resolved['version'],
            'activation_type' => $activationType,
            'actor' => $context->actorId,
            'correlation_id' => $context->correlationId,
        ]);
    }

    /**
     * @param list<array{asset_id: string, version: int, checksum: string, type: string}> $resolvedItems
     */
    private function captureSnapshot(SiteAssetPackage $sitePackage, TemplateAssetPackage $templatePackage, array $resolvedItems): void
    {
        $snapshot = [
            'template_reference' => $templatePackage->template_reference,
            'brand_variant_reference' => $sitePackage->brand_variant_reference,
            'assets' => $resolvedItems,
        ];

        PackageSnapshot::query()->create([
            'site_asset_package_id' => $sitePackage->id,
            'snapshot' => $snapshot,
            'checksum' => hash('sha256', json_encode($snapshot, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)),
            'captured_at' => now(),
        ]);
    }

    private function captureSnapshotFromCurrentItems(SiteAssetPackage $sitePackage): void
    {
        $templatePackage = $sitePackage->templateAssetPackage;
        $items = $sitePackage->items()->get();

        $this->captureSnapshot($sitePackage, $templatePackage, $items->map(fn (SiteAssetPackageItem $item): array => [
            'asset_id' => $item->asset_id,
            'version' => $item->active_version,
            'checksum' => $item->bound_checksum,
            'type' => null,
        ])->all());
    }
}
