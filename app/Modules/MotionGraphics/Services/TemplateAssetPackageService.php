<?php

namespace App\Modules\MotionGraphics\Services;

use App\Modules\MotionGraphics\Contracts\MotionAuthorizationInterface;
use App\Modules\MotionGraphics\DTO\MotionAdapterContext;
use App\Modules\MotionGraphics\Exceptions\MotionAdapterFailClosedException;
use App\Modules\MotionGraphics\Models\TemplateAssetPackage;
use App\Modules\MotionGraphics\Support\AbstractMotionAdapter;
use App\Modules\MotionGraphics\Support\MotionAuditEvents;
use Illuminate\Support\Facades\DB;
use SaaSPlatform\CoreUtils\Contracts\TenantContextContract;
use SaaSPlatform\Modules\MotionGraphics\Contracts\Read\MotionAssetReadContract;

/**
 * Creates frozen Template Asset Package definitions: given a set of
 * caller-supplied {asset_id, version?} references, resolves each to its
 * currently readable published version (an omitted version resolves the
 * asset's published version at creation time) and freezes the result —
 * see docs/motion-persistence/VERSION_BINDING_RULES.md ("Template Asset
 * Reference -> Resolve Published Version -> Bind version snapshot").
 * Only Platform Admin may call this (`motion.manage_template_package` is
 * not among the Customer abilities the permission gateway grants) — see
 * docs/motion-persistence/OWNERSHIP_RULES.md.
 */
final class TemplateAssetPackageService extends AbstractMotionAdapter
{
    public function __construct(
        TenantContextContract $tenantContext,
        MotionAuditEvents $auditEvents,
        private readonly MotionAuthorizationInterface $authorization,
        private readonly MotionAssetReadContract $assets,
    ) {
        parent::__construct($tenantContext, $auditEvents);
    }

    /**
     * @param list<array{asset_id: string, version?: int}> $requiredAssetRefs
     * @param list<array{asset_id: string, version?: int}> $optionalAssetRefs
     * @param array<string, mixed> $compatibilityRules
     */
    public function createTemplateAssetPackage(
        string $templateReference,
        array $requiredAssetRefs,
        array $optionalAssetRefs,
        array $compatibilityRules,
        MotionAdapterContext $context,
    ): TemplateAssetPackage {
        $this->ensureEnabled();

        $decision = $this->authorization->authorize(
            $context->actorId, 'motion.manage_template_package', $templateReference, $context->siteId
        );
        if (! $decision->allowed) {
            throw new MotionAdapterFailClosedException($decision->code, $decision->reason);
        }

        $required = $this->resolveReferences($requiredAssetRefs);
        if ($required['unresolved'] !== []) {
            throw new MotionAdapterFailClosedException(
                'unresolved_required_asset',
                'One or more required template assets could not be resolved to a published version.'
            );
        }

        $optional = $this->resolveReferences($optionalAssetRefs);

        $package = DB::transaction(function () use ($templateReference, $required, $optional, $compatibilityRules, $context): TemplateAssetPackage {
            $nextVersion = 1 + (int) TemplateAssetPackage::query()
                ->where('template_reference', $templateReference)
                ->max('package_version');

            return TemplateAssetPackage::query()->create([
                'template_reference' => $templateReference,
                'required_assets' => $required['resolved'],
                'optional_assets' => $optional['resolved'],
                'compatibility_rules' => $compatibilityRules,
                'package_version' => $nextVersion,
                'created_by_actor_identifier' => $context->actorId,
            ]);
        });

        $this->auditEvents->record(MotionAuditEvents::TEMPLATE_PACKAGE_CREATED, [
            'template_reference' => $templateReference,
            'package_version' => $package->package_version,
            'required_asset_count' => count($required['resolved']),
            'optional_asset_count' => count($optional['resolved']),
            'actor' => $context->actorId,
            'correlation_id' => $context->correlationId,
        ]);

        return $package;
    }

    /**
     * Resolves each reference to its currently readable version without
     * writing anything — exposed publicly so callers (e.g.
     * MotionRuntimeInstallationService) can compare a fresh resolution
     * against an existing TemplateAssetPackage for idempotency before
     * deciding whether a new package row is actually needed. See
     * docs/motion-runtime-installation/IDEMPOTENCY_RULES.md.
     *
     * @param list<array{asset_id: string, version?: int}> $references
     * @return array{resolved: list<array{asset_id: string, version: int}>, unresolved: list<string>}
     */
    public function resolveReferences(array $references): array
    {
        $resolved = [];
        $unresolved = [];

        foreach ($references as $reference) {
            $assetId = $reference['asset_id'] ?? null;
            if (! is_string($assetId) || $assetId === '') {
                $unresolved[] = is_string($assetId) ? $assetId : 'invalid-reference';
                continue;
            }

            $version = $reference['version'] ?? $this->assets->resolveActiveVersion($assetId);
            if ($version === null || $this->assets->findByCanonicalId($assetId, $version) === null) {
                $unresolved[] = $assetId;
                continue;
            }

            $resolved[] = ['asset_id' => $assetId, 'version' => $version];
        }

        return ['resolved' => $resolved, 'unresolved' => $unresolved];
    }
}
