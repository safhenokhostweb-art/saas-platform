<?php

namespace App\Modules\MotionGraphics\Services;

use App\Models\ThemeTemplate;
use App\Modules\MotionGraphics\Contracts\MotionAuthorizationInterface;
use App\Modules\MotionGraphics\DTO\ActivationPlan;
use App\Modules\MotionGraphics\DTO\AssetCompatibilityPreview;
use App\Modules\MotionGraphics\DTO\MotionAdapterContext;
use App\Modules\MotionGraphics\DTO\MotionAdminDashboardSummary;
use App\Modules\MotionGraphics\Exceptions\MotionAdapterFailClosedException;
use App\Modules\MotionGraphics\Models\SiteAssetPackage;
use App\Modules\MotionGraphics\Models\TemplateAssetPackage;
use App\Modules\MotionGraphics\Support\AbstractMotionAdapter;
use App\Modules\MotionGraphics\Support\MotionAuditEvents;
use App\Modules\MotionGraphics\Support\ReadModelMapper;
use App\Modules\MotionGraphics\Support\TemplateBuilderCompatibilityValidator;
use App\Modules\MotionGraphics\DTO\ValidationReport;
use Illuminate\Support\Collection;
use SaaSPlatform\CoreUtils\Contracts\TenantContextContract;
use SaaSPlatform\Modules\MotionGraphics\Contracts\Read\MotionAssetLibrarySummaryContract;
use SaaSPlatform\Modules\MotionGraphics\Contracts\Read\MotionAssetReadContract;

/**
 * Phase 7 (docs/motion-admin-management/): the first operational admin
 * workflow for Motion Graphics. This is a thin composition layer over
 * the Phase 3-6 services — it never re-implements resolution,
 * persistence, compatibility, or licensing logic, it only:
 *
 *  1. gates every method behind the same fail-closed chain every other
 *     Motion adapter uses (ensureEnabled + MotionAuthorizationInterface),
 *     with the four admin-only abilities the task specifies
 *     (motion.view_library, motion.manage_template_package,
 *     motion.activate_site_package, motion.view_runtime_projection);
 *  2. delegates the actual work to TemplateAssetPackageService,
 *     MotionRuntimeInstallationService, and TemplateBuilderCompatibilityValidator;
 *  3. records one additional `motion.admin.*` audit event per action,
 *     alongside (never instead of) the events those services already record.
 *
 * Every method requires a `MotionAdapterContext` whose `siteId` names the
 * Internal Test Site the admin is currently working against — callers
 * (the admin controller) are responsible for having already called
 * `TenantContextContract::set($site)` for that same site, exactly as
 * every prior phase's tests do via their own `bindTrustedSite()` helper.
 * Dashboard/template-listing counts are platform-wide by nature, but are
 * still gated through a site context so the same fail-closed feature-flag
 * check (`motion_graphics_integration`, scoped per site since Phase 6)
 * applies uniformly — there is no "siteless" admin action.
 */
final class MotionGraphicsAdminService extends AbstractMotionAdapter
{
    private const ABILITY_VIEW_LIBRARY = 'motion.view_library';
    private const ABILITY_MANAGE_TEMPLATE_PACKAGE = 'motion.manage_template_package';
    private const ABILITY_ACTIVATE_SITE_PACKAGE = 'motion.activate_site_package';
    private const ABILITY_VIEW_RUNTIME_PROJECTION = 'motion.view_runtime_projection';

    public function __construct(
        TenantContextContract $tenantContext,
        MotionAuditEvents $auditEvents,
        private readonly MotionAuthorizationInterface $authorization,
        private readonly MotionAssetLibrarySummaryContract $assetLibrary,
        private readonly TemplateAssetPackageService $templatePackages,
        private readonly MotionRuntimeInstallationService $runtimeInstallation,
        private readonly TemplateBuilderCompatibilityValidator $compatibilityValidator,
        private readonly MotionAssetReadContract $assets,
    ) {
        parent::__construct($tenantContext, $auditEvents);
    }

    public function dashboardSummary(MotionAdapterContext $context): MotionAdminDashboardSummary
    {
        $this->authorizeAdmin($context, self::ABILITY_VIEW_LIBRARY);

        return new MotionAdminDashboardSummary(
            totalAssets: $this->assetLibrary->countPublishedAssets(),
            totalTemplates: ThemeTemplate::query()->count(),
            assignedPackages: TemplateAssetPackage::query()->count(),
            activatedInternalSites: SiteAssetPackage::query()
                ->where('activation_status', SiteAssetPackage::STATUS_ACTIVE)
                ->distinct('site_id')
                ->count('site_id'),
        );
    }

    /**
     * @return Collection<int, ThemeTemplate>
     */
    public function listTemplates(MotionAdapterContext $context): Collection
    {
        $this->authorizeAdmin($context, self::ABILITY_VIEW_LIBRARY);

        return ThemeTemplate::query()->orderByDesc('id')->limit(200)->get();
    }

    /**
     * @return Collection<int, SiteAssetPackage>
     */
    public function listActivatedPackagesForSite(MotionAdapterContext $context): Collection
    {
        $this->authorizeAdmin($context, self::ABILITY_VIEW_RUNTIME_PROJECTION);

        return SiteAssetPackage::query()
            ->where('site_id', $context->siteId)
            ->where('activation_status', SiteAssetPackage::STATUS_ACTIVE)
            ->with('items')
            ->orderByDesc('id')
            ->get();
    }

    /**
     * @param list<array{asset_id: string, version?: int}> $requiredAssetRefs
     * @param array<string, mixed> $compatibilityRules
     * @return list<AssetCompatibilityPreview>
     */
    public function previewCompatibility(array $requiredAssetRefs, array $compatibilityRules, MotionAdapterContext $context): array
    {
        $this->authorizeAdmin($context, self::ABILITY_MANAGE_TEMPLATE_PACKAGE);

        $previews = [];
        foreach ($requiredAssetRefs as $reference) {
            $assetId = $reference['asset_id'] ?? null;
            if (! is_string($assetId) || $assetId === '') {
                continue;
            }

            $version = $reference['version'] ?? $this->assets->resolveActiveVersion($assetId);
            $assetModel = $version === null ? null : $this->assets->findByCanonicalId($assetId, $version);

            if ($assetModel === null) {
                $previews[] = new AssetCompatibilityPreview(
                    $assetId,
                    ValidationReport::fail([
                        ['code' => 'asset_not_available', 'message' => "Asset [{$assetId}] is not currently readable."],
                    ])
                );
                continue;
            }

            $dto = ReadModelMapper::toAssetReferenceDTO($assetModel);
            $previews[] = new AssetCompatibilityPreview($assetId, $this->compatibilityValidator->validate($dto, $compatibilityRules));
        }

        return $previews;
    }

    /**
     * @param list<array{asset_id: string, version?: int}> $requiredAssetRefs
     * @param list<array{asset_id: string, version?: int}> $optionalAssetRefs
     * @param array<string, mixed> $compatibilityRules
     */
    public function assignPackageToTemplate(
        string $templateReference,
        array $requiredAssetRefs,
        array $optionalAssetRefs,
        array $compatibilityRules,
        MotionAdapterContext $context,
    ): TemplateAssetPackage {
        $this->authorizeAdmin($context, self::ABILITY_MANAGE_TEMPLATE_PACKAGE);

        $package = $this->templatePackages->createTemplateAssetPackage(
            $templateReference, $requiredAssetRefs, $optionalAssetRefs, $compatibilityRules, $context
        );

        $this->auditEvents->record(MotionAuditEvents::ADMIN_PACKAGE_ASSIGNED, [
            'template_reference' => $templateReference,
            'package_id' => $package->id,
            'package_version' => $package->package_version,
            'actor' => $context->actorId,
            'correlation_id' => $context->correlationId,
        ]);

        return $package;
    }

    /**
     * @param list<array{asset_id: string, version?: int}> $requiredAssetRefs
     * @param list<array{asset_id: string, version?: int}> $optionalAssetRefs
     * @param array<string, mixed> $compatibilityRules
     */
    public function activateForInternalSite(
        string $templateReference,
        array $requiredAssetRefs,
        array $optionalAssetRefs,
        array $compatibilityRules,
        ?string $brandVariant,
        MotionAdapterContext $context,
    ): ActivationPlan {
        $this->authorizeAdmin($context, self::ABILITY_ACTIVATE_SITE_PACKAGE);

        $plan = $this->runtimeInstallation->installTemplateForSite(
            $templateReference, $requiredAssetRefs, $optionalAssetRefs, $compatibilityRules, $brandVariant, $context
        );

        $this->auditEvents->record(MotionAuditEvents::ADMIN_SITE_ACTIVATION, [
            'template_reference' => $templateReference,
            'site_id' => $context->siteId,
            'site_package_id' => $plan->sitePackage->id,
            'was_already_up_to_date' => $plan->wasAlreadyUpToDate,
            'actor' => $context->actorId,
            'correlation_id' => $context->correlationId,
        ]);

        return $plan;
    }

    /**
     * @return list<\App\Modules\MotionGraphics\DTO\RuntimeProjectionDTO>
     */
    public function previewRuntimeProjection(SiteAssetPackage $sitePackage, MotionAdapterContext $context): array
    {
        $this->authorizeAdmin($context, self::ABILITY_VIEW_RUNTIME_PROJECTION);

        if ((string) $sitePackage->site_id !== $context->siteId) {
            throw new MotionAdapterFailClosedException(
                'tenant_isolation_violation',
                'This site package does not belong to the requesting site.'
            );
        }

        $projections = $this->runtimeInstallation->projectSitePackage($sitePackage, $context);

        $this->auditEvents->record(MotionAuditEvents::ADMIN_RUNTIME_PREVIEW, [
            'site_id' => $context->siteId,
            'site_package_id' => $sitePackage->id,
            'asset_count' => count($projections),
            'actor' => $context->actorId,
            'correlation_id' => $context->correlationId,
        ]);

        return $projections;
    }

    private function authorizeAdmin(MotionAdapterContext $context, string $ability): void
    {
        $this->ensureEnabled();

        $decision = $this->authorization->authorize($context->actorId, $ability, 'motion-graphics-admin', $context->siteId);
        if (! $decision->allowed) {
            throw new MotionAdapterFailClosedException($decision->code, $decision->reason);
        }

        $this->assertSiteMatches($context->siteId);
    }
}
