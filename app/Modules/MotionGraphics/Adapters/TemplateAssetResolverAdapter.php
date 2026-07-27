<?php

namespace App\Modules\MotionGraphics\Adapters;

use App\Modules\MotionGraphics\Contracts\MotionAuthorizationInterface;
use App\Modules\MotionGraphics\Contracts\TemplateAssetResolverInterface;
use App\Modules\MotionGraphics\DTO\MotionAdapterContext;
use App\Modules\MotionGraphics\DTO\TemplateAssetRequestDTO;
use App\Modules\MotionGraphics\DTO\ValidationReport;
use App\Modules\MotionGraphics\Exceptions\MotionAdapterFailClosedException;
use App\Modules\MotionGraphics\Support\AbstractMotionAdapter;
use App\Modules\MotionGraphics\Support\MotionAuditEvents;
use SaaSPlatform\CoreUtils\Contracts\TenantContextContract;
use SaaSPlatform\Modules\MotionGraphics\Contracts\Read\TemplateAssetReadContract;

/**
 * Laravel-side implementation of `TemplateAssetResolverInterface`. Reads
 * the manifest data the caller supplies (no Template Builder connection
 * in this phase — see the task's "Out of Scope" list) but now confirms
 * each declared asset reference against the standalone module's real
 * `TemplateAssetReadContract`
 * (docs/motion-read-contracts/TEMPLATE_READ_MODEL.md) rather than
 * trusting the manifest blindly.
 */
final class TemplateAssetResolverAdapter extends AbstractMotionAdapter implements TemplateAssetResolverInterface
{
    public function __construct(
        TenantContextContract $tenantContext,
        MotionAuditEvents $auditEvents,
        private readonly MotionAuthorizationInterface $authorization,
        private readonly TemplateAssetReadContract $templateAssets,
    ) {
        parent::__construct($tenantContext, $auditEvents);
    }

    public function validateManifest(array $templateManifest, MotionAdapterContext $context): ValidationReport
    {
        if (! $this->tenantContext->hasFeature(self::FEATURE_FLAG)) {
            return ValidationReport::fail([['code' => 'motion_adapter_disabled', 'message' => 'The Motion Graphics adapter is disabled.']]);
        }

        $decision = $this->authorization->authorize($context->actorId, 'motion.view', $templateManifest['template_id'] ?? '', $context->siteId);
        if (! $decision->allowed) {
            return ValidationReport::fail([['code' => $decision->code, 'message' => $decision->reason]]);
        }

        $errors = [];
        if (empty($templateManifest['template_id']) || ! is_string($templateManifest['template_id'])) {
            $errors[] = ['code' => 'missing_template_id', 'message' => 'templateManifest.template_id is required.'];
        }
        foreach (['required_assets', 'optional_assets'] as $key) {
            if (isset($templateManifest[$key]) && ! is_array($templateManifest[$key])) {
                $errors[] = ['code' => 'invalid_asset_list', 'message' => "templateManifest.{$key} must be a list."];
            }
        }

        return $errors === [] ? ValidationReport::pass() : ValidationReport::fail($errors);
    }

    public function resolveRequiredAssets(
        array $templateManifest,
        array $siteProfile,
        MotionAdapterContext $context,
    ): TemplateAssetRequestDTO {
        $this->ensureEnabled();

        $decision = $this->authorization->authorize($context->actorId, 'motion.view', $templateManifest['template_id'] ?? '', $context->siteId);
        if (! $decision->allowed) {
            throw new MotionAdapterFailClosedException($decision->code, $decision->reason);
        }

        $declared = TemplateAssetRequestDTO::fromArray($templateManifest);

        $requiredResolution = $this->templateAssets->resolveReferences(
            array_map(static fn ($ref): array => ['asset_id' => $ref->assetId, 'version' => $ref->version], $declared->requiredAssets)
        );
        if ($requiredResolution->unresolved !== []) {
            throw new MotionAdapterFailClosedException(
                'unresolved_required_asset',
                'One or more required template assets are not currently readable from the standalone module.'
            );
        }

        $optionalResolution = $this->templateAssets->resolveReferences(
            array_map(static fn ($ref): array => ['asset_id' => $ref->assetId, 'version' => $ref->version], $declared->optionalAssets)
        );

        return new TemplateAssetRequestDTO(
            templateId: $declared->templateId,
            requiredAssets: $declared->requiredAssets,
            // Optional assets that don't currently resolve are dropped
            // rather than failing the whole template — they are optional.
            optionalAssets: array_values(array_filter(
                $declared->optionalAssets,
                static fn ($ref): bool => in_array($ref->assetId, array_map(
                    static fn ($resolved) => $resolved->assetId,
                    $optionalResolution->resolved
                ), true)
            )),
        );
    }

    public function createPackageDefinition(TemplateAssetRequestDTO $dependencyLock, MotionAdapterContext $context): array
    {
        $this->ensureEnabled();

        $decision = $this->authorization->authorize($context->actorId, 'motion.use', $dependencyLock->templateId, $context->siteId);
        if (! $decision->allowed) {
            throw new MotionAdapterFailClosedException($decision->code, $decision->reason);
        }

        return [
            'template_id' => $dependencyLock->templateId,
            'required_asset_count' => count($dependencyLock->requiredAssets),
            'optional_asset_count' => count($dependencyLock->optionalAssets),
            'contract_version' => $context->contractVersion,
            'correlation_id' => $context->correlationId,
        ];
    }
}
