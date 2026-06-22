<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Theme;
use App\Models\ThemePack;
use App\Services\ThemeBuilder\ZipImport\ZipTemplateAnalyzer;
use App\Services\ThemeBuilder\ZipImport\ZipTemplateImportService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * ZIP Template Import Engine Controller
 *
 * Flow:
 *   1. GET  /form         → upload form + options
 *   2. POST /detect       → AJAX: parse ZIP, detect origin + format, return summary
 *   3. POST /import       → full pipeline → ThemePack (+ optional marketplace entry)
 *   4. GET  /{pack}/result → result page (stats, quality, similarity, screenshots)
 *   5. POST /{pack}/install → create Theme → open Canvas editor
 *   6. GET  /{pack}/export  → download normalized JSON from pack
 *
 * Routes prefix: admin/theme-builder/zip-import
 */
class ThemeZipImportController extends Controller
{
    public function __construct(
        private readonly ZipTemplateImportService $service
    ) {}

    // ── Step 1: Upload Form ───────────────────────────────────────────────────

    public function form()
    {
        $allCategories  = $this->categoryList();
        $allOrigins     = ZipTemplateAnalyzer::allOrigins();
        $recentImports  = ThemePack::whereNull('site_id')
            ->where('author', 'ZIP Import Engine')
            ->latest()
            ->limit(5)
            ->get();

        return view('admin.theme-builder.zip-import', compact(
            'allCategories', 'allOrigins', 'recentImports'
        ));
    }

    // ── Step 2: Detect (AJAX) ─────────────────────────────────────────────────

    public function detect(Request $request)
    {
        $request->validate([
            'zip_file' => 'required|file|mimes:zip|max:102400', // 100 MB
        ]);

        try {
            $result = $this->service->detect($request->file('zip_file'));

            return response()->json([
                'origin'           => $result['origin'],
                'origin_label'     => $result['origin_label'],
                'source'           => $result['source'],
                'source_label'     => $result['source_label'],
                'metadata'         => $result['metadata'],
                'json_count'       => $result['json_count'],
                'screenshot_count' => $result['screenshot_count'],
                'asset_count'      => $result['asset_count'],
                'total_files'      => $result['total_files'],
                'suggested_name'   => $result['suggested_name'],
            ]);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    // ── Step 3: Full Import ───────────────────────────────────────────────────

    public function import(Request $request)
    {
        $data = $request->validate([
            'zip_file'               => 'required|file|mimes:zip|max:102400',
            'name'                   => 'nullable|string|max:255',
            'category'               => 'nullable|string|max:64',
            'description'            => 'nullable|string|max:1000',
            'publish_to_marketplace' => 'nullable|boolean',
            'force_source'           => 'nullable|in:elementor,bricks,shopify,webflow,custom',
        ]);

        try {
            $name    = $data['name'] ?? '';
            $options = [
                'category'               => $data['category']               ?? Theme::CATEGORY_CORPORATE,
                'description'            => $data['description']            ?? '',
                'publish_to_marketplace' => !empty($data['publish_to_marketplace']),
                'force_source'           => $data['force_source']           ?? null,
            ];

            $result = $this->service->import($request->file('zip_file'), $name, $options);

            return redirect()
                ->route('theme-builder.zip-import.result', $result['pack']->id)
                ->with('zip_import_result', [
                    'origin'           => $result['origin'],
                    'origin_label'     => $result['origin_label'],
                    'source'           => $result['source'],
                    'source_label'     => $result['source_label'],
                    'metadata'         => $result['metadata'],
                    'stats'            => $result['stats'],
                    'quality'          => $result['quality'],
                    'similarity'       => $result['similarity'],
                    'screenshots_saved'=> $result['screenshots_saved'],
                    'json_count'       => $result['json_count'],
                    'screenshot_count' => $result['screenshot_count'],
                    'asset_count'      => $result['asset_count'],
                    'total_files'      => $result['total_files'],
                    'duration_ms'      => $result['duration_ms'],
                    'phases'           => $result['phases'],
                    'theme_id'         => $result['theme']?->id,
                ]);
        } catch (\Throwable $e) {
            return back()
                ->withErrors(['zip_file' => 'خطا در پردازش ZIP: ' . $e->getMessage()])
                ->withInput();
        }
    }

    // ── Step 4: Result Page ───────────────────────────────────────────────────

    public function result(ThemePack $pack)
    {
        $importResult  = session('zip_import_result', []);
        $packSummary   = $pack->contentSummary();
        $allCategories = $this->categoryList();

        // Screenshots from pack_data
        $screenshots = collect($pack->pack_data['zip_import_screenshots'] ?? [])
            ->map(fn($path) => asset('storage/' . $path))
            ->all();

        return view('admin.theme-builder.zip-import-result', compact(
            'pack', 'importResult', 'packSummary', 'allCategories', 'screenshots'
        ));
    }

    // ── Step 5: Install as Theme (open Canvas) ────────────────────────────────

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
            'pages'         => $packData['pages']        ?? [],
            'global_styles' => $packData['global_styles'] ?? [],
            'header_blocks' => $packData['headers'][0]   ?? [],
            'footer_blocks' => $packData['footers'][0]   ?? [],
            'settings'      => [
                'imported_from_pack'   => $pack->uuid,
                'imported_from_zip'    => $packData['zip_import_original'] ?? '',
                'imported_from_origin' => $packData['zip_import_origin']   ?? '',
            ],
        ]);

        return redirect()
            ->route('admin.theme-builder.canvas', $theme->id)
            ->with('success', "قالب «{$theme->name}» از ZIP نصب شد و در ویرایشگر Canvas باز است.");
    }

    // ── Step 6: Export normalized JSON ────────────────────────────────────────

    public function export(ThemePack $pack)
    {
        $exportData = [
            'name'         => $pack->name,
            'description'  => $pack->description,
            'version'      => $pack->version,
            'author'       => $pack->author,
            'exported_at'  => now()->toISOString(),
            'import_meta'  => $pack->pack_data['import_meta'] ?? [],
            'zip_origin'   => $pack->pack_data['zip_import_origin'] ?? '',
            'normalized_structure' => [
                'global_styles' => ($pack->pack_data ?? [])['global_styles'] ?? [],
                'pages'         => ($pack->pack_data ?? [])['pages']         ?? [],
                'header_blocks' => ($pack->pack_data ?? [])['headers'][0]    ?? [],
                'footer_blocks' => ($pack->pack_data ?? [])['footers'][0]    ?? [],
            ],
        ];

        $filename = Str::slug($pack->name) . '-zip-import-normalized.json';

        return response()->json($exportData)
            ->header('Content-Disposition', "attachment; filename=\"{$filename}\"")
            ->header('Content-Type', 'application/json');
    }

    // ── Helper ────────────────────────────────────────────────────────────────

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
