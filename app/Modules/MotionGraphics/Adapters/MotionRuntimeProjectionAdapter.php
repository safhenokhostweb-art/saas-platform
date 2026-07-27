<?php

namespace App\Modules\MotionGraphics\Adapters;

use App\Modules\MotionGraphics\Contracts\MotionAuthorizationInterface;
use App\Modules\MotionGraphics\Contracts\MotionRuntimeProjectionInterface;
use App\Modules\MotionGraphics\DTO\ForbiddenFieldGuard;
use App\Modules\MotionGraphics\DTO\MotionAdapterContext;
use App\Modules\MotionGraphics\DTO\RuntimeProjectionDTO;
use App\Modules\MotionGraphics\DTO\ValidationReport;
use App\Modules\MotionGraphics\Exceptions\MotionAdapterFailClosedException;
use App\Modules\MotionGraphics\Support\AbstractMotionAdapter;
use App\Modules\MotionGraphics\Support\MotionAuditEvents;
use App\Modules\MotionGraphics\Support\ReadModelMapper;
use SaaSPlatform\CoreUtils\Contracts\TenantContextContract;
use SaaSPlatform\Modules\MotionGraphics\Contracts\Read\MotionRuntimeProjectionReadContract;

/**
 * Laravel-side implementation of `MotionRuntimeProjectionInterface`.
 * Delegates to the standalone module's own `MotionRuntimeProjectionReadContract`
 * (docs/motion-read-contracts/RUNTIME_PROJECTION_CONTRACT.md), which
 * enforces published-only, tenant-isolated output itself. A dedicated
 * Site Asset Package persistence model does not exist yet (see
 * docs/motion-saas-integration/PERSISTENCE_DESIGN.md), so in this phase
 * `$sitePackageId` is the canonical asset id — the future Deployment
 * Engine phase will resolve a real package id to its constituent asset
 * ids before calling this method; that resolution itself stays out of
 * scope here (no Deployment Engine activation).
 */
final class MotionRuntimeProjectionAdapter extends AbstractMotionAdapter implements MotionRuntimeProjectionInterface
{
    public function __construct(
        TenantContextContract $tenantContext,
        MotionAuditEvents $auditEvents,
        private readonly MotionAuthorizationInterface $authorization,
        private readonly MotionRuntimeProjectionReadContract $projections,
    ) {
        parent::__construct($tenantContext, $auditEvents);
    }

    public function projectPublished(string $sitePackageId, int $version, MotionAdapterContext $context): RuntimeProjectionDTO
    {
        $this->ensureEnabled();

        $decision = $this->authorization->authorize($context->actorId, 'motion.use', $sitePackageId, $context->siteId);
        if (! $decision->allowed) {
            throw new MotionAdapterFailClosedException($decision->code, $decision->reason);
        }

        $this->assertSiteMatches($context->siteId);

        $projection = $this->projections->projectAsset($sitePackageId, $context->siteId);
        if ($projection === null || $projection->publishedVersion !== $version) {
            throw new MotionAdapterFailClosedException(
                'projection_not_available',
                'No published runtime projection is available for the requested asset/version.'
            );
        }

        $this->auditEvents->record(MotionAuditEvents::RUNTIME_REQUESTED, [
            'site_package_id' => $sitePackageId,
            'version' => $version,
            'site_id' => $context->siteId,
            'correlation_id' => $context->correlationId,
        ]);

        return ReadModelMapper::toRuntimeProjectionDTO($projection);
    }

    public function validateProjection(RuntimeProjectionDTO $projection): ValidationReport
    {
        if (! $this->tenantContext->hasFeature(self::FEATURE_FLAG)) {
            return ValidationReport::fail([['code' => 'motion_adapter_disabled', 'message' => 'The Motion Graphics adapter is disabled.']]);
        }

        $errors = [];

        try {
            ForbiddenFieldGuard::assertClean($projection->themeTokens, 'theme_tokens');
            ForbiddenFieldGuard::assertClean($projection->placementRules, 'placement_rules');
        } catch (\InvalidArgumentException $e) {
            $errors[] = ['code' => 'forbidden_field', 'message' => $e->getMessage()];
        }

        if ($projection->publishedVersion !== $projection->assetReference->version) {
            $errors[] = ['code' => 'version_mismatch', 'message' => 'publishedVersion does not match the asset reference version.'];
        }

        return $errors === [] ? ValidationReport::pass() : ValidationReport::fail($errors);
    }
}
