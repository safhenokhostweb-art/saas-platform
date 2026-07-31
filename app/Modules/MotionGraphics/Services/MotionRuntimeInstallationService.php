<?php

namespace App\Modules\MotionGraphics\Services;

use App\Modules\MotionGraphics\Contracts\MotionAuthorizationInterface;
use App\Modules\MotionGraphics\DTO\ActivationPlan;
use App\Modules\MotionGraphics\DTO\BrandVariantReference;
use App\Modules\MotionGraphics\DTO\MotionAdapterContext;
use App\Modules\MotionGraphics\Exceptions\MotionAdapterFailClosedException;
use App\Modules\MotionGraphics\Models\AssetActivation;
use App\Modules\MotionGraphics\Models\SiteAssetPackage;
use App\Modules\MotionGraphics\Models\SiteAssetPackageItem;
use App\Modules\MotionGraphics\Models\TemplateAssetPackage;
use App\Modules\MotionGraphics\Support\AbstractMotionAdapter;
use App\Modules\MotionGraphics\Support\MotionAuditEvents;
use App\Modules\MotionGraphics\Support\ReadModelMapper;
use App\Modules\MotionGraphics\Support\TemplateBuilderCompatibilityValidator;
use Illuminate\Support\Facades\DB;
use SaaSPlatform\CoreUtils\Contracts\TenantContextContract;
use SaaSPlatform\Modules\MotionGraphics\Contracts\Read\MotionAssetReadContract;
use SaaSPlatform\Modules\MotionGraphics\Contracts\Read\MotionRuntimeProjectionReadContract;
use Throwable;

/**
 * Phase 4 orchestration bridge:
 *
 * ```text
 * Template Selected
 *   -> Template Asset Package Resolver   (TemplateAssetPackageService, Phase 3, reused)
 *   -> Compatibility Validation          (TemplateBuilderCompatibilityValidator, new)
 *   -> Site Asset Package Creation       (SiteAssetPackageActivationService, Phase 3, reused)
 *   -> Version Snapshot Binding          (SiteAssetPackageActivationService, Phase 3, reused)
 *   -> Runtime Projection Generation     (MotionRuntimeProjectionReadContract, Phase 2, reused)
 * ```
 *
 * This service composes existing Phase 1-3 building blocks; it never
 * duplicates their validation/persistence logic and never copies,
 * modifies, or publishes a physical Master Asset file — see
 * docs/motion-runtime-installation/RUNTIME_INSTALLATION_FLOW.md.
 */
final class MotionRuntimeInstallationService extends AbstractMotionAdapter
{
    public function __construct(
        TenantContextContract $tenantContext,
        MotionAuditEvents $auditEvents,
        private readonly MotionAuthorizationInterface $authorization,
        private readonly TemplateAssetPackageService $templatePackages,
        private readonly SiteAssetPackageActivationService $activationService,
        private readonly MotionAssetReadContract $assets,
        private readonly MotionRuntimeProjectionReadContract $projections,
        private readonly TemplateBuilderCompatibilityValidator $compatibilityValidator,
    ) {
        parent::__construct($tenantContext, $auditEvents);
    }

    /**
     * @param list<array{asset_id: string, version?: int}> $requiredAssetRefs
     * @param list<array{asset_id: string, version?: int}> $optionalAssetRefs
     * @param array<string, mixed> $compatibilityRules
     */
    public function installTemplateForSite(
        string $templateReference,
        array $requiredAssetRefs,
        array $optionalAssetRefs,
        array $compatibilityRules,
        string|BrandVariantReference|null $brandVariant,
        MotionAdapterContext $context,
    ): ActivationPlan {
        $this->ensureEnabled();

        $decision = $this->authorization->authorize(
            $context->actorId, 'motion.manage_template_package', $templateReference, $context->siteId
        );
        if (! $decision->allowed) {
            throw new MotionAdapterFailClosedException($decision->code, $decision->reason);
        }
        $this->assertSiteMatches($context->siteId);

        if ($brandVariant instanceof BrandVariantReference && ! $brandVariant->isApproved()) {
            throw new MotionAdapterFailClosedException(
                'brand_variant_not_approved',
                'The brand variant has not been approved and cannot be installed.'
            );
        }
        $brandVariantReference = $brandVariant instanceof BrandVariantReference ? $brandVariant->variantId : $brandVariant;

        $this->auditEvents->record(MotionAuditEvents::TEMPLATE_ACTIVATION_STARTED, [
            'template_reference' => $templateReference,
            'site_id' => $context->siteId,
            'actor' => $context->actorId,
            'correlation_id' => $context->correlationId,
        ]);

        try {
            $plan = DB::transaction(function () use (
                $templateReference, $requiredAssetRefs, $optionalAssetRefs, $compatibilityRules, $brandVariantReference, $context
            ): ActivationPlan {
                $templatePackage = $this->resolveOrCreateTemplatePackage(
                    $templateReference, $requiredAssetRefs, $optionalAssetRefs, $compatibilityRules, $context
                );

                $this->validateTemplateBuilderCompatibility($templatePackage);

                $existingSitePackage = SiteAssetPackage::query()
                    ->where('site_id', $this->trustedSiteId())
                    ->where('template_reference', $templateReference)
                    ->first();
                $existingItemIds = $existingSitePackage?->items()->pluck('id') ?? collect();
                $activationCountBefore = $existingItemIds->isEmpty()
                    ? 0
                    : AssetActivation::query()->whereIn('site_asset_package_item_id', $existingItemIds)->count();

                $sitePackage = $this->activationService->activate($templatePackage, $brandVariantReference, $context);

                $activationCountAfter = AssetActivation::query()
                    ->whereIn('site_asset_package_item_id', $sitePackage->items->pluck('id'))
                    ->count();

                $projections = $this->projectSitePackage($sitePackage, $context);

                return new ActivationPlan(
                    sitePackage: $sitePackage,
                    items: $sitePackage->items->map(fn (SiteAssetPackageItem $item): array => [
                        'asset_id' => $item->asset_id,
                        'version' => $item->active_version,
                        'activation_type' => AssetActivation::query()
                            ->where('site_asset_package_item_id', $item->id)
                            ->latest('id')
                            ->value('activation_type') ?? AssetActivation::TYPE_INSTALL,
                    ])->all(),
                    projections: $projections,
                    wasAlreadyUpToDate: $existingSitePackage !== null && $activationCountAfter === $activationCountBefore,
                );
            });
        } catch (Throwable $e) {
            $this->auditEvents->record(MotionAuditEvents::TEMPLATE_ACTIVATION_FAILED, [
                'template_reference' => $templateReference,
                'site_id' => $context->siteId,
                'actor' => $context->actorId,
                'correlation_id' => $context->correlationId,
                'error_code' => $e instanceof MotionAdapterFailClosedException ? $e->failureCode : 'unexpected_error',
            ]);

            throw $e;
        }

        $this->auditEvents->record(MotionAuditEvents::TEMPLATE_ACTIVATION_COMPLETED, [
            'template_reference' => $templateReference,
            'site_id' => $context->siteId,
            'site_package_id' => $plan->sitePackage->id,
            'item_count' => count($plan->items),
            'was_already_up_to_date' => $plan->wasAlreadyUpToDate,
            'actor' => $context->actorId,
            'correlation_id' => $context->correlationId,
        ]);

        return $plan;
    }

    /**
     * Customer-accessible read path: projects an already-activated
     * package's items into customer-safe runtime projections. Never
     * creates, changes, or activates anything — see
     * docs/motion-runtime-installation/PROJECTION_SECURITY.md.
     *
     * @return list<\App\Modules\MotionGraphics\DTO\RuntimeProjectionDTO>
     */
    public function projectSitePackage(SiteAssetPackage $sitePackage, MotionAdapterContext $context): array
    {
        $this->ensureEnabled();

        $decision = $this->authorization->authorize(
            $context->actorId, 'motion.use', (string) $sitePackage->id, $context->siteId
        );
        if (! $decision->allowed) {
            throw new MotionAdapterFailClosedException($decision->code, $decision->reason);
        }
        $this->assertSiteMatches($context->siteId);

        $projected = [];
        foreach ($sitePackage->items as $item) {
            $projection = $this->projections->projectAsset($item->asset_id, $context->siteId);
            if ($projection === null) {
                // Fail soft per item: an asset that can no longer be
                // projected is omitted, never fabricated — the rest of
                // the package's projections remain trustworthy.
                continue;
            }

            $projected[] = ReadModelMapper::toRuntimeProjectionDTO($projection);

            $this->auditEvents->record(MotionAuditEvents::RUNTIME_PROJECTION_GENERATED, [
                'site_id' => $sitePackage->site_id,
                'asset_id' => $item->asset_id,
                'version' => $projection->publishedVersion,
                'correlation_id' => $context->correlationId,
            ]);
        }

        return $projected;
    }

    /**
     * @param list<array{asset_id: string, version?: int}> $requiredAssetRefs
     * @param list<array{asset_id: string, version?: int}> $optionalAssetRefs
     * @param array<string, mixed> $compatibilityRules
     */
    private function resolveOrCreateTemplatePackage(
        string $templateReference,
        array $requiredAssetRefs,
        array $optionalAssetRefs,
        array $compatibilityRules,
        MotionAdapterContext $context,
    ): TemplateAssetPackage {
        $required = $this->templatePackages->resolveReferences($requiredAssetRefs);
        if ($required['unresolved'] !== []) {
            throw new MotionAdapterFailClosedException(
                'unresolved_required_asset',
                'One or more required template assets could not be resolved to a published version.'
            );
        }
        $optional = $this->templatePackages->resolveReferences($optionalAssetRefs);

        $latest = TemplateAssetPackage::query()
            ->where('template_reference', $templateReference)
            ->orderByDesc('package_version')
            ->first();

        if ($latest !== null
            && $this->sameResolved($latest->required_assets, $required['resolved'])
            && $this->sameResolved($latest->optional_assets, $optional['resolved'])
            && $this->sameResolved($latest->compatibility_rules ?? [], $compatibilityRules)) {
            // Idempotent: nothing about this template's resolved
            // requirements has changed since the last package — see
            // docs/motion-runtime-installation/IDEMPOTENCY_RULES.md.
            return $latest;
        }

        return $this->templatePackages->createTemplateAssetPackage(
            $templateReference, $required['resolved'], $optional['resolved'], $compatibilityRules, $context
        );
    }

    /**
     * A plain `===` on the two sides is not reliable here: one side was
     * just resolved in PHP, the other round-tripped through a MySQL JSON
     * column, which does not guarantee preserving original key order.
     * Canonicalizing (sorting map keys, keeping list order) before
     * comparing makes the idempotency check correct regardless of that
     * round trip. See docs/motion-runtime-installation/IDEMPOTENCY_RULES.md.
     */
    private function sameResolved(array $a, array $b): bool
    {
        return $this->canonicalize($a) === $this->canonicalize($b);
    }

    private function canonicalize(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        $normalized = array_map(fn (mixed $v): mixed => $this->canonicalize($v), $value);

        if (! array_is_list($normalized)) {
            ksort($normalized);
        }

        return $normalized;
    }

    private function validateTemplateBuilderCompatibility(TemplateAssetPackage $templatePackage): void
    {
        foreach ($templatePackage->required_assets as $reference) {
            $assetModel = $this->assets->findByCanonicalId($reference['asset_id'], $reference['version']);
            if ($assetModel === null) {
                throw new MotionAdapterFailClosedException(
                    'asset_not_available',
                    "Required asset [{$reference['asset_id']}] is not currently readable."
                );
            }

            $report = $this->compatibilityValidator->validate(
                ReadModelMapper::toAssetReferenceDTO($assetModel),
                $templatePackage->compatibility_rules ?? []
            );
            if (! $report->valid) {
                throw new MotionAdapterFailClosedException(
                    'compatibility_rejected',
                    $report->errors[0]['message'] ?? 'Compatibility validation failed.'
                );
            }
        }
    }
}
