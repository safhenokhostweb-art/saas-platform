<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Theme;
use App\Models\ThemePack;
use App\Services\ThemeBuilder\JsonImport\JsonImportPipelineService;
use App\Services\ThemeBuilder\JsonImport\ThemeStructureAnalyzer;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * JSON Theme Import Engine Controller
 *
 * Flow:
 *   1. GET  /form         → upload + options form
 *   2. POST /detect       → AJAX: parse JSON, detect source, return analysis
 *   3. POST /import       → full pipeline: detect → adapt → map → generate ThemePack
 *   4. GET  /{pack}/result → result page (stats, quality, actions)
 *   5. POST /{pack}/install → create Theme from ThemePack → open canvas
 *   6. GET  /{pack}/export  → download normalized JSON
 */
class ThemeJsonImportController extends Controller
{
    public function __construct(
        private readonly JsonImportPipelineService $pipeline
    ) {}

    // ── Step 1: Form ──────────────────────────────────────────────────────────

    public function form()
    {
        $categories     = Theme::categoryLabel(...);
        $allCategories  = $this->categoryList();
        $supportedSources = JsonImportPipelineService::supportedSources();
        $recentImports  = ThemePack::whereNull('site_id')
            ->where('author', 'JSON Import Engine')
            ->latest()
            ->limit(5)
            ->get();

        return view('admin.theme-builder.json-import', compact(
            'allCategories', 'supportedSources', 'recentImports'
        ));
    }

    // ── Step 2: Detect (AJAX) ─────────────────────────────────────────────────

    public function detect(Request $request)
    {
        $request->validate([
            'json_file' => 'required|file|mimes:json,txt|max:10240',
        ]);

        try {
            $content = file_get_contents($request->file('json_file')->getRealPath());
            $json    = json_decode($content, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                return response()->json(['error' => 'فایل JSON معتبر نیست: ' . json_last_error_msg()], 422);
            }

            $detection = $this->pipeline->detectSource($json);

            return response()->json([
                'source'        => $detection['source'],
                'source_label'  => $detection['source_label'],
                'analysis'      => $detection['analysis'],
                'suggested_name'=> $detection['analysis']['title'] ?? '',
            ]);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    // ── Step 3: Import (full pipeline) ────────────────────────────────────────

    public function import(Request $request)
    {
        $data = $request->validate([
            'json_file'              => 'required|file|mimes:json,txt|max:20480',
            'name'                   => 'required|string|max:255',
            'category'               => 'nullable|string|max:64',
            'description'            => 'nullable|string|max:1000',
            'publish_to_marketplace' => 'nullable|boolean',
            'force_source'           => 'nullable|in:elementor,bricks,shopify,webflow,custom',
        ]);

        try {
            $content = file_get_contents($request->file('json_file')->getRealPath());
            $json    = json_decode($content, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                return back()->withErrors(['json_file' => 'فایل JSON معتبر نیست: ' . json_last_error_msg()]);
            }

            $options = [
                'category'               => $data['category'] ?? Theme::CATEGORY_CORPORATE,
                'description'            => $data['description'] ?? '',
                'publish_to_marketplace' => !empty($data['publish_to_marketplace']),
                'force_source'           => $data['force_source'] ?? null,
            ];

            $result = $this->pipeline->process($json, $data['name'], $options);

            return redirect()
                ->route('admin.theme-builder.json-import.result', $result['pack']->id)
                ->with('import_result', [
                    'source'      => $result['source'],
                    'source_label'=> $result['source_label'],
                    'stats'       => $result['stats'],
                    'quality'     => $result['quality'],
                    'duration_ms' => $result['duration_ms'],
                    'theme_id'    => $result['theme']?->id,
                ]);
        } catch (\Throwable $e) {
            return back()->withErrors(['json_file' => 'خطا در پردازش: ' . $e->getMessage()])->withInput();
        }
    }

    // ── Step 4: Result page ───────────────────────────────────────────────────

    public function result(ThemePack $pack)
    {
        $importResult  = session('import_result', []);
        $packSummary   = $pack->contentSummary();
        $allCategories = $this->categoryList();

        return view('admin.theme-builder.json-import-result', compact(
            'pack', 'importResult', 'packSummary', 'allCategories'
        ));
    }

    // ── Step 5: Install as Theme (open canvas) ────────────────────────────────

    public function install(Request $request, ThemePack $pack)
    {
        $data = $request->validate([
            'name'     => 'required|string|max:255',
            'category' => 'nullable|string|max:64',
            'site_id'  => 'nullable|integer|exists:sites,id',
        ]);

        $packData = $pack->pack_data ?? [];

        $theme = Theme::create([
            'name'          => $data['name'],
            'slug'          => Str::slug($data['name']) . '-' . Str::random(6),
            'description'   => $pack->description,
            'category'      => $data['category'] ?? Theme::CATEGORY_CORPORATE,
            'source'        => Theme::SOURCE_GENERATOR,
            'type'          => Theme::TYPE_FULL,
            'status'        => Theme::STATUS_DRAFT,
            'site_id'       => $data['site_id'] ?? null,
            'pages'         => $packData['pages'] ?? [],
            'global_styles' => $packData['global_styles'] ?? [],
            'header_blocks' => $packData['headers'][0] ?? [],
            'footer_blocks' => $packData['footers'][0] ?? [],
            'settings'      => ['imported_from_pack' => $pack->uuid],
        ]);

        return redirect()
            ->route('admin.theme-builder.canvas', $theme->id)
            ->with('success', "قالب «{$theme->name}» نصب شد و در ویرایشگر Canvas باز است.");
    }

    // ── Step 6: Export normalized JSON ────────────────────────────────────────

    public function export(ThemePack $pack)
    {
        $exportData = array_merge($pack->toExportArray(), [
            'normalized_structure' => [
                'global_styles' => ($pack->pack_data ?? [])['global_styles'] ?? [],
                'pages'         => ($pack->pack_data ?? [])['pages'] ?? [],
                'header_blocks' => ($pack->pack_data ?? [])['headers'][0] ?? [],
                'footer_blocks' => ($pack->pack_data ?? [])['footers'][0] ?? [],
            ],
        ]);

        $filename = Str::slug($pack->name) . '-normalized.json';

        return response()->json($exportData)
            ->header('Content-Disposition', "attachment; filename=\"{$filename}\"")
            ->header('Content-Type', 'application/json');
    }

    // ── Helper: category list ─────────────────────────────────────────────────

    private function categoryList(): array
    {
        $consts = [
            'corporate', 'personal', 'blog', 'news', 'educational', 'medical',
            'legal', 'lawyer', 'real_estate', 'restaurant', 'portfolio', 'agency',
            'marketplace', 'store', 'saas', 'landing', 'beauty', 'travel',
            'automotive', 'industrial', 'construction', 'fashion', 'electronics',
            'single_product',
        ];

        $list = [];
        foreach ($consts as $key) {
            $list[$key] = Theme::categoryLabel($key);
        }
        return $list;
    }
}
