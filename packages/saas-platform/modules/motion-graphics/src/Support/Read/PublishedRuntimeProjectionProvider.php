<?php

namespace SaaSPlatform\Modules\MotionGraphics\Support\Read;

use SaaSPlatform\Modules\MotionGraphics\Contracts\Read\Model\RuntimeProjectionReadModel;
use SaaSPlatform\Modules\MotionGraphics\Contracts\Read\MotionAssetReadContract;
use SaaSPlatform\Modules\MotionGraphics\Contracts\Read\MotionRuntimeProjectionReadContract;
use SaaSPlatform\Modules\MotionGraphics\Models\MotionGraphic;
use SaaSPlatform\Modules\MotionGraphics\Support\MotionSvgComposer;

/**
 * Database-backed MotionRuntimeProjectionReadContract. Builds the
 * published-only, customer-safe projection by composing
 * MotionAssetReadContract with the module's own tenant isolation rule
 * (the same ownership check MotionLibraryService::runtimeManifest()
 * enforces) — it never queries version snapshot/schema/AI columns.
 */
final class PublishedRuntimeProjectionProvider implements MotionRuntimeProjectionReadContract
{
    public function __construct(
        private readonly MotionAssetReadContract $assets,
    ) {}

    public function projectAsset(string $assetId, ?string $siteIdentifier = null): ?RuntimeProjectionReadModel
    {
        $motion = MotionGraphic::query()->where('uuid', $assetId)->first();
        if ($motion === null || $motion->published_version === null) {
            return null;
        }

        if ($motion->ownership_type === MotionGraphic::OWNERSHIP_CUSTOMER_SPECIFIC
            && $motion->owner_site_identifier !== $siteIdentifier) {
            // Never leak a customer-specific asset to another (or no) site.
            return null;
        }

        $asset = $this->assets->findByCanonicalId($assetId, $motion->published_version);
        if ($asset === null) {
            return null;
        }

        $publishedRecord = $motion->versions()->where('version', $motion->published_version)->where('status', 'published')->first();
        if ($publishedRecord === null) {
            return null;
        }

        $optimizedReference = $this->firstAssetUrl($publishedRecord->compiled_assets ?? []);
        if ($optimizedReference === null) {
            return null;
        }

        return new RuntimeProjectionReadModel(
            assetReference: $asset,
            publishedVersion: $motion->published_version,
            optimizedReference: $optimizedReference,
            themeTokens: $this->safeThemeTokens($motion->runtime_configuration ?? []),
            placementRules: $this->safePlacementRules($motion->dimensions ?? [], $motion->accessibility ?? []),
            // ADR-079: re-normalized on the way out, so only the public
            // runtime-parameter contract can ever cross this boundary.
            parameters: MotionSvgComposer::normalizeDefinitions((array) (($motion->runtime_configuration ?? [])['parameters'] ?? [])),
        );
    }

    /**
     * @param array<int, array<string, mixed>> $compiledAssets
     */
    private function firstAssetUrl(array $compiledAssets): ?string
    {
        $first = $compiledAssets[0] ?? null;

        return is_array($first) && isset($first['url']) && is_string($first['url']) ? $first['url'] : null;
    }

    /**
     * @param array<string, mixed> $runtimeConfiguration
     * @return array<string, mixed>
     */
    private function safeThemeTokens(array $runtimeConfiguration): array
    {
        $tokens = $runtimeConfiguration['theme_tokens'] ?? [];

        return is_array($tokens) ? $tokens : [];
    }

    /**
     * @param array<string, mixed> $dimensions
     * @param array<string, mixed> $accessibility
     * @return array<string, mixed>
     */
    private function safePlacementRules(array $dimensions, array $accessibility): array
    {
        return [
            'dimensions' => $dimensions,
            'reduced_motion_label' => $accessibility['label'] ?? null,
        ];
    }
}
