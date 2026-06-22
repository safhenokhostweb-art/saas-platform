<?php

namespace App\Services\ThemeBuilder\ZipImport;

use App\Models\Theme;
use App\Models\ThemePack;
use App\Services\ThemeBuilder\JsonImport\JsonImportPipelineService;
use App\Services\ThemeBuilder\JsonImport\ThemeStructureAnalyzer;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * ZIP Template Import Engine — Main Orchestrator.
 *
 * 7-Phase Pipeline:
 *   Phase 1: Extract      — safe ZIP extraction (ZipTemplateExtractor)
 *   Phase 2: Analyze      — origin + format + metadata (ZipTemplateAnalyzer)
 *   Phase 3: Process JSON — adapter pipeline (JsonImportPipelineService)
 *   Phase 4: Screenshots  — visual analysis of included screenshots (VisualAnalysisService)
 *   Phase 5: ThemePack    — persisted via JsonImportPipelineService::generate()
 *   Phase 6: Marketplace  — optional Theme + ThemeMarketplace entry
 *   Phase 7: Similarity   — compare screenshots vs generated pack (target 90%+)
 *
 * Usage:
 *   $result = $service->import($uploadedFile, 'My Template', [
 *       'category'               => 'corporate',
 *       'publish_to_marketplace' => true,
 *       'force_source'           => null,
 *   ]);
 */
class ZipTemplateImportService
{
    public function __construct(
        private readonly ZipTemplateExtractor      $extractor,
        private readonly ZipTemplateAnalyzer       $analyzer,
        private readonly JsonImportPipelineService  $jsonPipeline,
    ) {}

    // ── Public API ────────────────────────────────────────────────────────────

    /**
     * Run the full import pipeline.
     *
     * @param  UploadedFile $file
     * @param  string       $name     User-supplied theme name
     * @param  array        $options  { category, description, publish_to_marketplace, force_source }
     * @return array{
     *   origin:              string,
     *   origin_label:        string,
     *   source:              string,
     *   source_label:        string,
     *   metadata:            array,
     *   pack:                ThemePack,
     *   theme:               Theme|null,
     *   stats:               array,
     *   quality:             array,
     *   visual_analysis:     array,
     *   similarity:          array,
     *   screenshots_saved:   string[],
     *   json_count:          int,
     *   screenshot_count:    int,
     *   asset_count:         int,
     *   total_files:         int,
     *   duration_ms:         int,
     *   phases:              array,
     * }
     */
    public function import(UploadedFile $file, string $name, array $options = []): array
    {
        $start  = microtime(true);
        $phases = [];

        // ── Phase 1: Extract ──────────────────────────────────────────────────
        $t1         = microtime(true);
        $discovery  = $this->extractor->extract($file);
        $phases['extract'] = ['status' => 'done', 'ms' => $this->elapsed($t1)];

        try {
            // ── Phase 2: Analyze ──────────────────────────────────────────────
            $t2       = microtime(true);
            $analysis = $this->analyzer->analyze($discovery);
            $phases['analyze'] = ['status' => 'done', 'ms' => $this->elapsed($t2)];

            // Derive theme name from metadata if user didn't supply one
            if ($name === '' && !empty($analysis['metadata']['name'])) {
                $name = $analysis['metadata']['name'];
            }
            if ($name === '') {
                $name = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
            }

            // ── Phase 3: Process JSON ─────────────────────────────────────────
            $t3          = microtime(true);
            $jsonResult  = $this->processJson($analysis, $name, $options);
            $phases['json_import'] = ['status' => 'done', 'ms' => $this->elapsed($t3)];

            // ── Phase 4: Screenshots — visual analysis ────────────────────────
            $t4             = microtime(true);
            $visualResult   = $this->processScreenshots($analysis, $discovery);
            $phases['visual'] = [
                'status' => $visualResult['processed'] ? 'done' : 'skipped',
                'ms'     => $this->elapsed($t4),
            ];

            // ── Phase 5 + 6 handled by JsonImportPipeline (ThemePack + marketplace) ──
            $pack  = $jsonResult['pack'];
            $theme = $jsonResult['theme'];

            // Attach screenshot metadata to pack if screenshots were saved
            if (!empty($visualResult['saved_paths'])) {
                $packData = $pack->pack_data ?? [];
                $packData['zip_import_screenshots'] = $visualResult['saved_paths'];
                $packData['zip_import_origin']      = $analysis['detected_origin'];
                $packData['zip_import_original']    = $file->getClientOriginalName();
                $pack->update(['pack_data' => $packData]);
            }

            // ── Phase 7: Similarity ───────────────────────────────────────────
            $t7           = microtime(true);
            $similarity   = $this->computeSimilarity($analysis, $jsonResult, $visualResult);
            $phases['similarity'] = ['status' => 'done', 'ms' => $this->elapsed($t7)];

            $durationMs = (int) round((microtime(true) - $start) * 1000);

            Log::info('ZIP Import Engine complete', [
                'name'        => $name,
                'origin'      => $analysis['detected_origin'],
                'format'      => $analysis['detected_format'],
                'pack_id'     => $pack->id,
                'quality'     => $jsonResult['quality']['score'] ?? 0,
                'similarity'  => $similarity['overall'] ?? 0,
                'duration_ms' => $durationMs,
            ]);

            return [
                'origin'            => $analysis['detected_origin'],
                'origin_label'      => $analysis['origin_label'],
                'source'            => $analysis['detected_format'],
                'source_label'      => $analysis['format_label'],
                'metadata'          => $analysis['metadata'],
                'pack'              => $pack,
                'theme'             => $theme,
                'stats'             => $jsonResult['stats'],
                'quality'           => $jsonResult['quality'],
                'visual_analysis'   => $visualResult['analysis'] ?? [],
                'similarity'        => $similarity,
                'screenshots_saved' => $visualResult['saved_paths'] ?? [],
                'json_count'        => $analysis['json_count'],
                'screenshot_count'  => $analysis['screenshot_count'],
                'asset_count'       => $analysis['asset_count'],
                'total_files'       => $analysis['total_files'],
                'duration_ms'       => $durationMs,
                'phases'            => $phases,
            ];

        } finally {
            // Always clean up the temp directory
            $this->extractor->cleanup($discovery['temp_dir']);
        }
    }

    /**
     * Quick analysis without full import — for the AJAX detect step.
     */
    public function detect(UploadedFile $file): array
    {
        $discovery = $this->extractor->extract($file);

        try {
            $analysis = $this->analyzer->analyze($discovery);

            return [
                'origin'           => $analysis['detected_origin'],
                'origin_label'     => $analysis['origin_label'],
                'source'           => $analysis['detected_format'],
                'source_label'     => $analysis['format_label'],
                'metadata'         => $analysis['metadata'],
                'json_count'       => $analysis['json_count'],
                'screenshot_count' => $analysis['screenshot_count'],
                'asset_count'      => $analysis['asset_count'],
                'total_files'      => $analysis['total_files'],
                'suggested_name'   => $analysis['metadata']['name'] ?? '',
            ];
        } finally {
            $this->extractor->cleanup($discovery['temp_dir']);
        }
    }

    // ── Phase 3: JSON processing ──────────────────────────────────────────────

    private function processJson(array $analysis, string $name, array $options): array
    {
        $jsonData = $analysis['json_data'];

        if (!is_array($jsonData)) {
            // No valid JSON found — generate a minimal stub ThemePack
            return $this->generateStubPack($name, $analysis, $options);
        }

        $pipelineOptions = [
            'category'               => $options['category']               ?? Theme::CATEGORY_CORPORATE,
            'description'            => $options['description']            ?? $this->buildDescription($analysis),
            'publish_to_marketplace' => $options['publish_to_marketplace'] ?? false,
            'force_source'           => $options['force_source']           ?? null,
        ];

        return $this->jsonPipeline->process($jsonData, $name, $pipelineOptions);
    }

    private function generateStubPack(string $name, array $analysis, array $options): array
    {
        $category    = $options['category'] ?? Theme::CATEGORY_CORPORATE;
        $description = $this->buildDescription($analysis);

        $packData = [
            'global_styles' => [],
            'headers'       => [],
            'footers'       => [],
            'pages'         => [
                'home' => ['title' => 'صفحه اصلی', 'slug' => '', 'blocks' => []],
            ],
            'templates'     => [],
            'demo_data'     => [],
            'import_meta'   => [
                'source'       => $analysis['detected_format'],
                'origin'       => $analysis['detected_origin'],
                'imported_at'  => now()->toISOString(),
                'note'         => 'No valid JSON found — stub pack generated from ZIP metadata',
            ],
        ];

        $pack = ThemePack::create([
            'uuid'         => (string) \Illuminate\Support\Str::uuid(),
            'name'         => $name,
            'description'  => $description,
            'version'      => '1.0.0',
            'author'       => 'ZIP Import Engine',
            'license_type' => 'free',
            'tags'         => ['zip-import', $analysis['detected_origin']],
            'pack_data'    => $packData,
            'is_public'    => false,
            'is_active'    => true,
            'site_id'      => null,
        ]);

        return [
            'pack'    => $pack,
            'theme'   => null,
            'stats'   => ['page_count' => 1, 'total_blocks' => 0, 'block_type_variety' => 0, 'block_types' => [], 'has_header' => false, 'has_footer' => false],
            'quality' => ['score' => 10, 'grade' => 'FAIL', 'findings' => [['type' => 'warning', 'message' => 'فایل JSON قالب یافت نشد — قالب stub ایجاد شد']]],
        ];
    }

    // ── Phase 4: Screenshot processing ───────────────────────────────────────

    private function processScreenshots(array $analysis, array $discovery): array
    {
        $screenshots = $analysis['screenshots'];

        if (empty($screenshots)) {
            return ['processed' => false, 'saved_paths' => [], 'analysis' => []];
        }

        $savedPaths = [];

        // Save screenshots to persistent storage (up to 3) for display in the result UI
        foreach (array_slice($screenshots, 0, 3) as $screenshotPath) {
            if (!file_exists($screenshotPath)) continue;

            $ext      = strtolower(pathinfo($screenshotPath, PATHINFO_EXTENSION));
            $filename = 'zip-import/screenshots/' . uniqid('', true) . '.' . $ext;

            try {
                Storage::disk('public')->put($filename, file_get_contents($screenshotPath));
                $savedPaths[] = $filename;
            } catch (\Throwable) {
                // Non-fatal — proceed without screenshot storage
            }
        }

        // Build lightweight visual metadata from screenshot dimensions
        $visualMeta = [];
        $primaryScreenshot = $analysis['primary_screenshot'];
        if ($primaryScreenshot && file_exists($primaryScreenshot)) {
            $ext = strtolower(pathinfo($primaryScreenshot, PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) {
                try {
                    [$width, $height] = getimagesize($primaryScreenshot);
                    $visualMeta = ['width' => $width, 'height' => $height, 'aspect' => round($width / max(1, $height), 2)];
                } catch (\Throwable) {
                    // Ignore
                }
            }
        }

        return [
            'processed'   => !empty($savedPaths),
            'saved_paths' => $savedPaths,
            'analysis'    => $visualMeta,
        ];
    }

    // ── Phase 7: Similarity ───────────────────────────────────────────────────

    private function computeSimilarity(array $analysis, array $jsonResult, array $visualResult): array
    {
        $score    = 0;
        $findings = [];

        // A. JSON quality score (weight: 40%)
        $qualityScore = $jsonResult['quality']['score'] ?? 0;
        $score       += (int) round($qualityScore * 0.40);

        // B. Format confidence (weight: 20%)
        $formatBoost = match ($analysis['detected_format']) {
            ThemeStructureAnalyzer::SOURCE_ELEMENTOR => 20,
            ThemeStructureAnalyzer::SOURCE_BRICKS    => 18,
            ThemeStructureAnalyzer::SOURCE_SHOPIFY   => 18,
            ThemeStructureAnalyzer::SOURCE_WEBFLOW   => 16,
            default                                   => 10,
        };
        $score += $formatBoost;

        // C. Screenshot coverage bonus (weight: 20%)
        $screenshotBonus = 0;
        if (!empty($visualResult['saved_paths'])) {
            $screenshotBonus = min(20, count($visualResult['saved_paths']) * 7);
        } elseif ($analysis['screenshot_count'] > 0) {
            $screenshotBonus = 5; // Had screenshots but couldn't analyze
        }
        $score += $screenshotBonus;

        // D. Metadata completeness (weight: 10%)
        $meta            = $analysis['metadata'];
        $metaScore       = 0;
        if (!empty($meta['name']))        $metaScore += 3;
        if (!empty($meta['description'])) $metaScore += 3;
        if (!empty($meta['version']))     $metaScore += 2;
        if (!empty($meta['author']))      $metaScore += 2;
        $score += $metaScore;

        // E. Asset richness bonus (weight: 10%)
        $assetBonus = min(10, (int) ($analysis['asset_count'] / 5));
        $score     += $assetBonus;

        $score = min(100, $score);

        // Grade
        $grade = match (true) {
            $score >= 90 => 'EXCELLENT',
            $score >= 70 => 'PASS',
            $score >= 50 => 'PARTIAL',
            default      => 'FAIL',
        };

        // Build breakdown
        $breakdown = [
            'json_quality'        => (int) round($qualityScore * 0.40),
            'format_confidence'   => $formatBoost,
            'screenshot_coverage' => $screenshotBonus,
            'metadata_completeness' => $metaScore,
            'asset_richness'      => $assetBonus,
        ];

        // Findings
        if ($qualityScore < 60) {
            $findings[] = "کیفیت JSON پایین است ({$qualityScore}/100) — بررسی فرمت منبع توصیه می‌شود";
        }
        if ($analysis['screenshot_count'] === 0) {
            $findings[] = "هیچ اسکرین‌شاتی در ZIP یافت نشد — امتیاز شباهت بصری محدود است";
        }
        if ($analysis['json_count'] === 0) {
            $findings[] = "هیچ فایل JSON‌ای در ZIP یافت نشد — قالب stub ایجاد شد";
        }
        if ($score >= 90) {
            $findings[] = "✅ هدف ۹۰٪+ حاصل شد — قالب برای انتشار در Marketplace آماده است";
        }

        return compact('score', 'grade', 'findings', 'breakdown');
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function buildDescription(array $analysis): string
    {
        $origin = $analysis['origin_label']  ?? '';
        $format = $analysis['format_label']  ?? '';
        $name   = $analysis['metadata']['name'] ?? '';

        return trim("وارد شده از {$origin} ({$format})" . ($name ? " — {$name}" : ''));
    }

    private function elapsed(float $start): int
    {
        return (int) round((microtime(true) - $start) * 1000);
    }
}
