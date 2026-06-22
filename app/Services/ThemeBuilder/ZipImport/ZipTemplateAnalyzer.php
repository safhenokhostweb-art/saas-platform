<?php

namespace App\Services\ThemeBuilder\ZipImport;

use App\Services\ThemeBuilder\JsonImport\ThemeStructureAnalyzer;

/**
 * Phase 2: Analyze extracted ZIP contents.
 *
 * Responsibilities:
 *  - Detect commercial origin (TemplateGoat / ThemeForest / Envato / Elementor Library / Bricks Library)
 *  - Detect JSON format (Elementor / Bricks / Shopify / Webflow / Custom)  — via ThemeStructureAnalyzer
 *  - Choose the primary JSON file from multiple candidates
 *  - Choose the best screenshot for visual analysis
 *  - Extract metadata (name, version, description, author) from package.json / style.css / README
 */
class ZipTemplateAnalyzer
{
    // Commercial origins
    public const ORIGIN_TEMPLATEGOAT  = 'templategoat';
    public const ORIGIN_THEMEFOREST   = 'themeforest';
    public const ORIGIN_ENVATO        = 'envato';
    public const ORIGIN_ELEMENTOR_LIB = 'elementor_lib';
    public const ORIGIN_BRICKS_LIB    = 'bricks_lib';
    public const ORIGIN_UNKNOWN       = 'unknown';

    // JSON file priority keywords (most specific first)
    private const JSON_PRIORITY = [
        'elementor-export', 'elementor-template', 'elementor',
        'bricks-export', 'bricks-template', 'bricks',
        'webflow', 'shopify', 'sections',
        'template', 'export', 'content', 'theme', 'data', 'page',
    ];

    // Screenshot priority keywords
    private const SCREENSHOT_PRIORITY = ['screenshot', 'preview', 'thumbnail', 'cover', 'banner', 'demo'];

    public function __construct(
        private readonly ThemeStructureAnalyzer $structureAnalyzer
    ) {}

    // ── Public API ────────────────────────────────────────────────────────────

    /**
     * Full analysis of extracted ZIP discovery map.
     *
     * @param  array $discovery  Output of ZipTemplateExtractor::extract()
     * @return array{
     *   detected_format:     string,
     *   detected_origin:     string,
     *   origin_label:        string,
     *   format_label:        string,
     *   primary_json:        string|null,
     *   json_data:           array|null,
     *   json_analysis:       array,
     *   metadata:            array,
     *   primary_screenshot:  string|null,
     *   screenshots:         string[],
     *   json_count:          int,
     *   screenshot_count:    int,
     *   asset_count:         int,
     *   total_files:         int,
     * }
     */
    public function analyze(array $discovery): array
    {
        $jsonFiles       = $discovery['json_files']       ?? [];
        $screenshotFiles = $discovery['screenshot_files'] ?? [];
        $metadataFiles   = $discovery['metadata_files']   ?? [];
        $allFiles        = $discovery['all_files']        ?? [];
        $tempDir         = $discovery['temp_dir']         ?? '';

        // 1. Detect commercial origin
        $origin = $this->detectOrigin($allFiles, $metadataFiles, $tempDir);

        // 2. Metadata from README / package.json / style.css
        $metadata = $this->extractMetadata($metadataFiles, $allFiles);

        // 3. Choose primary JSON and detect builder format
        [$primaryJson, $jsonData, $detectedFormat, $jsonAnalysis] = $this->processJsonFiles($jsonFiles, $origin);

        // 4. Screenshots
        $primaryScreenshot = $this->choosePrimaryScreenshot($screenshotFiles);

        return [
            'detected_format'    => $detectedFormat,
            'detected_origin'    => $origin,
            'origin_label'       => self::allOrigins()[$origin] ?? $origin,
            'format_label'       => ThemeStructureAnalyzer::allSources()[$detectedFormat] ?? $detectedFormat,
            'primary_json'       => $primaryJson,
            'json_data'          => $jsonData,
            'json_analysis'      => $jsonAnalysis,
            'metadata'           => $metadata,
            'primary_screenshot' => $primaryScreenshot,
            'screenshots'        => array_slice($screenshotFiles, 0, 6),
            'json_count'         => count($jsonFiles),
            'screenshot_count'   => count($screenshotFiles),
            'asset_count'        => count($discovery['asset_files'] ?? []),
            'total_files'        => count($allFiles),
        ];
    }

    public static function allOrigins(): array
    {
        return [
            self::ORIGIN_TEMPLATEGOAT  => 'TemplateGoat',
            self::ORIGIN_THEMEFOREST   => 'ThemeForest / Envato',
            self::ORIGIN_ENVATO        => 'Envato Elements',
            self::ORIGIN_ELEMENTOR_LIB => 'Elementor Library',
            self::ORIGIN_BRICKS_LIB    => 'Bricks Builder Library',
            self::ORIGIN_UNKNOWN       => 'ناشناخته',
        ];
    }

    // ── Origin detection ──────────────────────────────────────────────────────

    private function detectOrigin(array $allFiles, array $metadataFiles, string $tempDir): string
    {
        $filePathStr = strtolower(implode('|', $allFiles));

        // 1. Read metadata files for branding strings
        foreach ($metadataFiles as $metaPath) {
            if (!file_exists($metaPath)) continue;
            $content = strtolower(file_get_contents($metaPath));

            if (str_contains($content, 'templategoat'))                            return self::ORIGIN_TEMPLATEGOAT;
            if (str_contains($content, 'themeforest') || str_contains($content, 'envato elements')) return self::ORIGIN_ENVATO;
            if (str_contains($content, 'themeforest'))                             return self::ORIGIN_THEMEFOREST;
            if (str_contains($content, 'elementor')   && str_contains($content, 'template')) return self::ORIGIN_ELEMENTOR_LIB;
            if (str_contains($content, 'bricks')      && str_contains($content, 'template')) return self::ORIGIN_BRICKS_LIB;
        }

        // 2. File path hints
        if (str_contains($filePathStr, 'templategoat'))    return self::ORIGIN_TEMPLATEGOAT;
        if (str_contains($filePathStr, 'envato'))          return self::ORIGIN_ENVATO;
        if (str_contains($filePathStr, 'themeforest'))     return self::ORIGIN_THEMEFOREST;

        // 3. WordPress style.css theme header = ThemeForest pattern
        foreach ($metadataFiles as $metaPath) {
            if (strtolower(basename($metaPath)) !== 'style.css') continue;
            $content = file_get_contents($metaPath);
            if (str_contains($content, 'Theme Name:') && str_contains($content, 'Version:')) {
                return self::ORIGIN_THEMEFOREST;
            }
        }

        // 4. File structure hints for library exports
        if (str_contains($filePathStr, 'elementor')) return self::ORIGIN_ELEMENTOR_LIB;
        if (str_contains($filePathStr, 'bricks'))    return self::ORIGIN_BRICKS_LIB;

        return self::ORIGIN_UNKNOWN;
    }

    // ── JSON processing ───────────────────────────────────────────────────────

    private function processJsonFiles(array $jsonFiles, string $origin): array
    {
        if (empty($jsonFiles)) {
            return [null, null, ThemeStructureAnalyzer::SOURCE_CUSTOM, []];
        }

        $primaryJson = $this->choosePrimaryJson($jsonFiles, $origin);

        if (!$primaryJson || !file_exists($primaryJson)) {
            return [null, null, ThemeStructureAnalyzer::SOURCE_CUSTOM, []];
        }

        $content = file_get_contents($primaryJson);
        $decoded = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
            // Try next JSON file
            foreach ($jsonFiles as $file) {
                if ($file === $primaryJson) continue;
                $alt = json_decode(file_get_contents($file), true);
                if (is_array($alt)) {
                    $decoded     = $alt;
                    $primaryJson = $file;
                    break;
                }
            }
        }

        if (!is_array($decoded)) {
            return [$primaryJson, null, ThemeStructureAnalyzer::SOURCE_CUSTOM, []];
        }

        $format   = $this->structureAnalyzer->detect($decoded);
        $analysis = $this->structureAnalyzer->analyze($decoded, $format);

        return [$primaryJson, $decoded, $format, $analysis];
    }

    private function choosePrimaryJson(array $jsonFiles, string $origin): ?string
    {
        // Origin-specific first choice
        $originKeywords = match ($origin) {
            self::ORIGIN_TEMPLATEGOAT  => ['elementor-export', 'template', 'elementor'],
            self::ORIGIN_THEMEFOREST   => ['elementor', 'bricks', 'export', 'template'],
            self::ORIGIN_ELEMENTOR_LIB => ['elementor', 'export', 'template'],
            self::ORIGIN_BRICKS_LIB   => ['bricks', 'export', 'template'],
            default                    => self::JSON_PRIORITY,
        };

        foreach ($originKeywords as $keyword) {
            foreach ($jsonFiles as $file) {
                if (str_contains(strtolower(basename($file, '.json')), $keyword)) {
                    return $file;
                }
            }
        }

        // Fall back to general priority
        foreach (self::JSON_PRIORITY as $keyword) {
            foreach ($jsonFiles as $file) {
                if (str_contains(strtolower(basename($file, '.json')), $keyword)) {
                    return $file;
                }
            }
        }

        // Last resort: largest file (most content)
        return $jsonFiles[0] ?? null;
    }

    // ── Screenshot selection ──────────────────────────────────────────────────

    private function choosePrimaryScreenshot(array $screenshots): ?string
    {
        if (empty($screenshots)) return null;

        foreach (self::SCREENSHOT_PRIORITY as $kw) {
            foreach ($screenshots as $file) {
                if (str_contains(strtolower(basename($file)), $kw)) return $file;
            }
        }

        // Already sorted by file size descending from Extractor — largest = most likely actual screenshot
        return $screenshots[0];
    }

    // ── Metadata extraction ───────────────────────────────────────────────────

    private function extractMetadata(array $metadataFiles, array $allFiles): array
    {
        $meta = ['name' => '', 'version' => '', 'description' => '', 'author' => '', 'tags' => []];

        foreach ($metadataFiles as $filePath) {
            if (!file_exists($filePath)) continue;
            $basename = strtolower(basename($filePath));

            if (in_array($basename, ['package.json', 'composer.json'], true)) {
                $data = json_decode(file_get_contents($filePath), true) ?? [];
                $meta['name']        = $meta['name']        ?: ($data['name']        ?? '');
                $meta['version']     = $meta['version']     ?: ($data['version']     ?? '');
                $meta['description'] = $meta['description'] ?: ($data['description'] ?? '');
                $meta['author']      = $meta['author']      ?: (is_string($data['author'] ?? null) ? $data['author'] : '');
                if (!empty($data['keywords'])) {
                    $meta['tags'] = array_merge($meta['tags'], (array) $data['keywords']);
                }
                continue;
            }

            if (in_array($basename, ['readme.txt', 'readme.md', 'readme'], true)) {
                $content = file_get_contents($filePath);
                if (!$meta['name']) {
                    $lines = array_filter(explode("\n", trim($content)));
                    $firstLine = trim(reset($lines), "# \t=");
                    if ($firstLine && strlen($firstLine) < 100) $meta['name'] = $firstLine;
                }
                continue;
            }

            if ($basename === 'style.css') {
                $content = file_get_contents($filePath);
                if (preg_match('/Theme Name:\s*(.+)/i',   $content, $m)) $meta['name']        = $meta['name']        ?: trim($m[1]);
                if (preg_match('/Version:\s*(.+)/i',      $content, $m)) $meta['version']     = $meta['version']     ?: trim($m[1]);
                if (preg_match('/Description:\s*(.+)/i',  $content, $m)) $meta['description'] = $meta['description'] ?: trim($m[1]);
                if (preg_match('/Author:\s*(.+)/i',       $content, $m)) $meta['author']      = $meta['author']      ?: trim($m[1]);
                if (preg_match('/Tags:\s*(.+)/i',         $content, $m)) {
                    $tags = array_map('trim', explode(',', $m[1]));
                    $meta['tags'] = array_merge($meta['tags'], $tags);
                }
                continue;
            }
        }

        // Derive name from top-level directory if still empty
        if (!$meta['name'] && !empty($allFiles)) {
            $topDirs = array_unique(array_filter(array_map(function (string $f) {
                $parts = explode('/', str_replace('\\', '/', $f));
                return count($parts) > 1 ? $parts[0] : '';
            }, $allFiles)));

            if (count($topDirs) === 1) {
                $meta['name'] = ucwords(str_replace(['-', '_', '.'], ' ', reset($topDirs)));
            }
        }

        $meta['tags'] = array_unique(array_filter($meta['tags']));

        return $meta;
    }
}
