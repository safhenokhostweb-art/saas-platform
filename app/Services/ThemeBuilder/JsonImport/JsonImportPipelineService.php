<?php

namespace App\Services\ThemeBuilder\JsonImport;

use App\Services\ThemeBuilder\JsonImport\Adapters\BricksAdapter;
use App\Services\ThemeBuilder\JsonImport\Adapters\CustomBuilderAdapter;
use App\Services\ThemeBuilder\JsonImport\Adapters\ElementorAdapter;
use App\Services\ThemeBuilder\JsonImport\Adapters\ShopifyAdapter;
use App\Services\ThemeBuilder\JsonImport\Adapters\WebflowAdapter;
use Illuminate\Support\Facades\Log;

/**
 * Main entry point for the JSON Theme Import Engine.
 *
 * Pipeline:
 *   JSON input
 *     → ThemeStructureAnalyzer::detect()   (source format detection)
 *     → ThemeStructureAnalyzer::analyze()  (metadata summary)
 *     → Adapter::convert()                 (format-specific normalization)
 *     → WidgetMapper (called by adapters)  (block type mapping)
 *     → ThemePackGenerator::generate()     (ThemePack + optional marketplace)
 *
 * Usage:
 *   $result = $pipeline->process($jsonArray, 'My Theme', [
 *       'category' => 'store',
 *       'publish_to_marketplace' => false,
 *   ]);
 *   $pack = $result['pack'];
 */
class JsonImportPipelineService
{
    public function __construct(
        private readonly ThemeStructureAnalyzer $analyzer,
        private readonly WidgetMapper           $mapper,
        private readonly ThemePackGenerator     $generator,
    ) {}

    /**
     * Run the full import pipeline.
     *
     * @param  array  $json     Decoded JSON from any supported builder
     * @param  string $name     Theme name (user-supplied)
     * @param  array  $options  { category, description, publish_to_marketplace, force_source }
     * @return array {
     *   source:       string,
     *   source_label: string,
     *   analysis:     array,
     *   structure:    array,
     *   pack:         ThemePack,
     *   theme:        ?Theme,
     *   stats:        array,
     *   quality:      array,
     *   duration_ms:  int,
     * }
     */
    public function process(array $json, string $name, array $options = []): array
    {
        $start = microtime(true);

        // ── Phase 1: Detect source ────────────────────────────────────────────
        $source = $options['force_source'] ?? $this->analyzer->detect($json);

        // ── Phase 2: Analyze structure (metadata only) ─────────────────────────
        $analysis = $this->analyzer->analyze($json, $source);

        // ── Phase 3: Adapter converts JSON → normalized structure ──────────────
        $structure = $this->adapt($json, $source);

        // ── Phase 4: Generate ThemePack (+ optional marketplace entry) ─────────
        $result = $this->generator->generate($structure, $name, $options);

        $durationMs = (int) round((microtime(true) - $start) * 1000);

        Log::info('JSON Import Pipeline complete', [
            'source'       => $source,
            'name'         => $name,
            'pages'        => $result['stats']['page_count'],
            'blocks'       => $result['stats']['total_blocks'],
            'quality'      => $result['quality']['score'],
            'duration_ms'  => $durationMs,
        ]);

        return array_merge($result, [
            'source'       => $source,
            'source_label' => ThemeStructureAnalyzer::allSources()[$source] ?? $source,
            'analysis'     => $analysis,
            'structure'    => $structure,
            'duration_ms'  => $durationMs,
        ]);
    }

    /**
     * Detect source only (no full processing).
     */
    public function detectSource(array $json): array
    {
        $source   = $this->analyzer->detect($json);
        $analysis = $this->analyzer->analyze($json, $source);
        return [
            'source'       => $source,
            'source_label' => ThemeStructureAnalyzer::allSources()[$source] ?? $source,
            'analysis'     => $analysis,
        ];
    }

    // ── Adapter routing ───────────────────────────────────────────────────────

    private function adapt(array $json, string $source): array
    {
        return match ($source) {
            ThemeStructureAnalyzer::SOURCE_ELEMENTOR => (new ElementorAdapter($this->mapper))->convert($json),
            ThemeStructureAnalyzer::SOURCE_BRICKS    => (new BricksAdapter($this->mapper))->convert($json),
            ThemeStructureAnalyzer::SOURCE_SHOPIFY   => (new ShopifyAdapter($this->mapper))->convert($json),
            ThemeStructureAnalyzer::SOURCE_WEBFLOW   => (new WebflowAdapter($this->mapper))->convert($json),
            default                                  => (new CustomBuilderAdapter())->convert($json),
        };
    }

    // ── Static source list for UI ─────────────────────────────────────────────

    public static function supportedSources(): array
    {
        return ThemeStructureAnalyzer::allSources();
    }
}
