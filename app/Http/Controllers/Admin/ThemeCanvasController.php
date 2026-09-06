<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Site;
use App\Models\Theme;
use App\Services\ThemeBuilder\BlockRegistry;
use App\Services\ThemeBuilder\BlockRenderer;
use App\Services\ThemeBuilder\Dependency\ThemeExtensionBlockTypeExtractor;
use App\Services\ThemeBuilder\Extension\ExtensionCanvasPaletteAdapter;
use App\Services\ThemeBuilder\Extension\ExtensionThemeRuntimeAssetProjector;
use App\Services\ThemeBuilder\GlobalStyleEngine;
use App\Services\ThemeBuilder\ThemeTemplateService;
use App\Models\ThemeTemplate;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Http;

class ThemeCanvasController extends Controller
{
    public function __construct(
        protected BlockRenderer $renderer,
        protected GlobalStyleEngine $styleEngine,
        protected ThemeTemplateService $templateSvc,
    ) {}

    public function show(Theme $theme)
    {
        $pages     = $theme->getPageList();
        $registry  = BlockRegistry::all();
        $byCategory = BlockRegistry::byCategory();
        $catLabels  = BlockRegistry::categoryLabels();
        $catIcons   = BlockRegistry::categoryIcons();

        // Phase 3G-B-6: merge the Theme's currently-selected Extension
        // release manifests into the palette at request time only — never
        // via BlockRegistry::registerHostType(), which is process-global and
        // shared with customer-site (see ExtensionCanvasPaletteAdapter's own
        // doc comment). Extension entries never override a canonical type:
        // `+` keeps the left (canonical) operand's value on key collision.
        //
        // Closure (§5-§6): the INSERTION picker only ever offers ownership-
        // canonical types (authorablePaletteFor), never the raw, unfiltered
        // paletteFor() — a legacy/direct-DB ownership collision must not
        // become newly selectable here, even though it stays fully
        // resolvable for content already authored against it (see
        // extContainerTypesFor() below, which intentionally still uses the
        // unfiltered paletteFor() for rendering that historical content).
        $extensionPalette = ExtensionCanvasPaletteAdapter::authorablePaletteFor($theme);
        $registry = $registry + $extensionPalette;
        foreach ($extensionPalette as $type => $def) {
            $byCategory[$def['category']][$type] = $def;
        }
        $catLabels = $catLabels + [ExtensionCanvasPaletteAdapter::FALLBACK_CATEGORY => 'افزونه‌ها'];
        $catIcons  = $catIcons + [ExtensionCanvasPaletteAdapter::FALLBACK_CATEGORY => '🧩'];

        $extensionSelections = $theme->settings['extension_selections'] ?? [];
        $extensionSelectionErrors = ExtensionCanvasPaletteAdapter::resolveSelections($extensionSelections)['errors'];
        $availableExtensions = ExtensionCanvasPaletteAdapter::selectableProducts();

        return view('admin.theme-builder.canvas', compact(
            'theme', 'pages', 'registry', 'byCategory', 'catLabels', 'catIcons',
            'extensionSelections', 'extensionSelectionErrors', 'availableExtensions'
        ));
    }

    public function save(Request $request, Theme $theme)
    {
        $data = $request->validate([
            'page_key' => 'required|string|max:100',
            'blocks'   => 'required|array',
            'extension_selections' => 'nullable|array',
        ]);

        $pages = $theme->pages ?? $theme->getPageList();
        $key   = $data['page_key'];

        if (!isset($pages[$key])) {
            return response()->json(['error' => 'صفحه یافت نشد'], 404);
        }

        // Phase 3G-B-6 (§12, §21): the client always sends the full current
        // extension_selections map alongside the page being saved (never a
        // partial patch), so merging it into settings never needs to guess
        // at unrelated keys — only the `extension_selections` key is ever
        // replaced, every other Theme.settings key is spread through as-is.
        $selections = array_key_exists('extension_selections', $data)
            ? $data['extension_selections']
            : ($theme->settings['extension_selections'] ?? []);

        // Closure (§11-§17): "historical" is INSTANCE-, not type-, scoped —
        // the Theme's ENTIRE prior snapshot (header/footer/every page,
        // captured BEFORE $pages[$key]['blocks'] is overwritten below) is
        // read at the (id, type) level, so a NEW instance of a type that
        // merely has *some* historical instance elsewhere is still
        // ownership-gated (one historical ambiguous block must never
        // blanket-authorize unlimited new ones of the same type).
        $previousInstances = (new ThemeExtensionBlockTypeExtractor())->extractInstancesFromSnapshot(
            $theme->header_blocks ?? [],
            $theme->footer_blocks ?? [],
            $pages
        );

        $validation = ExtensionCanvasPaletteAdapter::validateBlocksAgainstSelections($data['blocks'], $selections, $previousInstances);
        if (!$validation['ok']) {
            return response()->json(['error' => $validation['error']], 422);
        }

        $pages[$key]['blocks'] = $data['blocks'];

        $settings = $theme->settings ?? [];
        $settings['extension_selections'] = $selections;

        $theme->update(['pages' => $pages, 'settings' => $settings]);

        return response()->json(['success' => true, 'message' => 'ذخیره شد.']);
    }

    public function addPage(Request $request, Theme $theme)
    {
        $data = $request->validate([
            'title' => 'required|string|max:100',
            'slug'  => 'nullable|string|max:100',
        ]);

        $pages = $theme->pages ?? $theme->getPageList();
        $key   = Str::slug($data['title']) ?: 'page-' . uniqid();

        if (isset($pages[$key])) {
            $key .= '-' . Str::random(4);
        }

        $pages[$key] = [
            'title'  => $data['title'],
            'slug'   => $data['slug'] ?? $key,
            'blocks' => [],
        ];

        $theme->update(['pages' => $pages]);

        return response()->json(['success' => true, 'key' => $key, 'page' => $pages[$key]]);
    }

    public function removePage(Request $request, Theme $theme, string $key)
    {
        if ($key === 'home') {
            return response()->json(['error' => 'صفحه اصلی حذف نمی‌شود'], 422);
        }

        $pages = $theme->pages ?? $theme->getPageList();
        unset($pages[$key]);
        $theme->update(['pages' => $pages]);

        return response()->json(['success' => true]);
    }

    public function preview(Request $request, Theme $theme, string $pageKey)
    {
        $pages = $theme->getPageList();
        $page  = $pages[$pageKey] ?? null;

        if (!$page) {
            abort(404, 'صفحه یافت نشد');
        }

        $blocks     = $page['blocks'] ?? [];
        $this->renderer->setContext(['ext_container_types' => $this->extContainerTypesFor($theme)]);
        $html       = $this->renderer->renderBlocks($blocks);
        $css        = $this->styleEngine->renderCssBlock($theme->global_styles ?? []);
        $hideChrome = $request->boolean('screenshot');
        $extAssets  = ExtensionCanvasPaletteAdapter::assetsUsedIn($blocks, $theme);

        return view('admin.theme-builder.canvas-preview', compact('theme', 'page', 'html', 'css', 'pageKey', 'hideChrome', 'extAssets'));
    }

    public function previewAjax(Request $request, Theme $theme)
    {
        $data = $request->validate([
            'page_key' => 'required|string',
            'blocks'   => 'required|array',
        ]);

        $this->renderer->setContext(['editor_mode' => true, 'ext_container_types' => $this->extContainerTypesFor($theme)]);
        $html = $this->renderer->renderBlocks($data['blocks']);
        $css  = $this->styleEngine->renderCssBlock($theme->global_styles ?? []);

        return response()->json(['html' => $html, 'css' => $css]);
    }

    /**
     * Phase 3G-B-6 — the set of `ext:*` types this Theme's currently-selected
     * Extension release manifests declare as containers, keyed by type =>
     * true. Fed to BlockRenderer::setContext() so its container-detection
     * (App\Services\ThemeBuilder\BlockRenderer::isStructuralContainer())
     * can recognize Extension containers declaratively — never a second
     * hardcoded list.
     */
    private function extContainerTypesFor(Theme $theme): array
    {
        $containerTypes = [];
        foreach (ExtensionCanvasPaletteAdapter::paletteFor($theme) as $type => $def) {
            if (!empty($def['is_container'])) {
                $containerTypes[$type] = true;
            }
        }

        return $containerTypes;
    }

    /**
     * TB3-02: Live iframe canvas endpoint.
     * Renders the current saved page blocks into a standalone HTML page.
     * Called as iframe src: GET /admin/theme-builder/{theme}/canvas/live-preview?page={key}
     */
    public function livePreview(Request $request, Theme $theme)
    {
        $pageKey  = $request->input('page', 'home');
        $pages    = $theme->getPageList();
        $pageData = $pages[$pageKey] ?? null;
        $blocks   = $pageData['blocks'] ?? [];

        $globalStyles = $theme->global_styles ?? [];
        $css = $this->styleEngine->renderCssBlock($globalStyles, $theme->site_id);

        $this->renderer->setContext(['editor_mode' => true, 'ext_container_types' => $this->extContainerTypesFor($theme)]);

        $renderedBlocks = [];
        foreach ($blocks as $block) {
            $bid = $block['id'] ?? '';
            if ($bid) {
                $renderedBlocks[$bid] = $this->renderer->renderBlock($block);
            }
        }

        $extAssets = ExtensionCanvasPaletteAdapter::assetsUsedIn($blocks, $theme);

        return response()
            ->view('admin.theme-builder.canvas-live-preview', compact('theme', 'css', 'blocks', 'renderedBlocks', 'extAssets'))
            ->header('Content-Type', 'text/html; charset=UTF-8');
    }

    /**
     * TB3-02: Per-block re-render for live prop updates.
     * POST body: { block: {id, type, props, ...} }
     * Returns: { html: "..." }
     */
    public function renderBlockAjax(Request $request, Theme $theme)
    {
        $blockData = $request->input('block', []);

        if (!is_array($blockData) || empty($blockData['type'])) {
            return response()->json(['html' => '']);
        }

        // Normalize block.data → block.props (TB3-01 compat)
        if (isset($blockData['data']) && !isset($blockData['props'])) {
            $blockData['props'] = $blockData['data'];
            unset($blockData['data']);
        }

        $this->renderer->setContext(['editor_mode' => true, 'ext_container_types' => $this->extContainerTypesFor($theme)]);
        $html = $this->renderer->renderBlock($blockData);

        return response()->json(['html' => $html]);
    }

    /**
     * نصب مستقیم قالب روی دیتابیس سایت مشتری از پنل پلتفرم.
     * Platform admin can assign any canvas theme to any site directly.
     */
    public function installToSite(Request $request, Theme $theme)
    {
        $request->validate(['site_id' => 'required|integer|exists:sites,id']);

        $site = Site::findOrFail($request->site_id);

        if (!$site->db_host || !$site->db_name) {
            return back()->with('error',
                'اطلاعات دیتابیس سایت "' . $site->title . '" تنظیم نشده است. ' .
                'از منوی سایت‌ها → ویرایش سایت، فیلدهای db_host، db_name، db_user و db_password را پر کنید.'
            );
        }

        $connKey = 'theme_install_' . $site->id;

        try {
            Config::set("database.connections.{$connKey}", [
                'driver'    => 'mysql',
                'host'      => $site->db_host,
                'port'      => '3306',
                'database'  => $site->db_name,
                'username'  => $site->db_user,
                'password'  => $site->getDecryptedDbPassword() ?? '',
                'charset'   => 'utf8mb4',
                'collation' => 'utf8mb4_unicode_ci',
                'prefix'    => '',
                'strict'    => false,
            ]);
            DB::purge($connKey);
            $conn = DB::connection($connKey);
            $conn->getPdo(); // force connection — throws if credentials wrong
        } catch (\Throwable $e) {
            DB::purge($connKey);
            return back()->with('error',
                'اتصال به دیتابیس سایت "' . $site->title . '" ناموفق بود: ' . $e->getMessage()
            );
        }

        try {
            $themeJson = json_encode([
                'name'             => $theme->name,
                'slug'             => $theme->slug,
                'header_blocks'    => $theme->header_blocks ?? [],
                'footer_blocks'    => $theme->footer_blocks ?? [],
                'pages'            => $theme->pages ?? [],
                'global_styles'    => $theme->global_styles ?? [],
                // Manifest-declared Extension CSS/JS, projected to absolute
                // saas-platform URLs — see ExtensionThemeRuntimeAssetProjector's
                // own doc comment for why this is an absolute URL, not a copy.
                // Absent/empty for every Theme that selects no Extension.
                'extension_assets' => ExtensionThemeRuntimeAssetProjector::project($theme),
            ], JSON_UNESCAPED_UNICODE);

            // upsert تمام ۴ کلید canvas theme در جدول settings سایت مشتری
            $themeSettings = [
                'active_canvas_theme_data' => $themeJson,
                'active_canvas_theme_name' => $theme->name,
                'active_canvas_theme_slug' => $theme->slug,
                'active_canvas_theme_id'   => (string) $theme->id,
            ];

            foreach ($themeSettings as $key => $value) {
                $existing = $conn->table('settings')->where('key', $key)->first();
                if ($existing) {
                    $conn->table('settings')
                        ->where('key', $key)
                        ->update(['value' => $value, 'updated_at' => now()]);
                } else {
                    $conn->table('settings')->insert([
                        'key'        => $key,
                        'value'      => $value,
                        'group'      => 'theme',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }

            // پاکسازی کش قالب (برای driver=database — Redis توسط customer-site در 60 ثانیه expire می‌شود)
            try {
                $conn->table('cache')->where('key', 'like', '%canvas_theme%')->delete();
                $conn->table('cache')->where('key', 'like', '%settings%')->delete();
            } catch (\Throwable) {}

            // Provision theme pages into customer site's pages table
            $this->provisionThemePagesIntoDb($conn, $theme);

        } catch (\Throwable $e) {
            DB::purge($connKey);
            \Illuminate\Support\Facades\Log::error('ThemeInstall failed', [
                'theme_id' => $theme->id,
                'site_id'  => $site->id,
                'error'    => $e->getMessage(),
            ]);
            return back()->with('error', 'خطا در نصب قالب: ' . $e->getMessage());
        }

        DB::purge($connKey);

        return redirect()->route('admin.theme-builder.index')
            ->with('success', 'قالب "' . $theme->name . '" با موفقیت روی سایت "' . $site->title . '" نصب شد.');
    }

    /**
     * همگام‌سازی صفحات قالب برای سایتی که قبلاً قالب رویش نصب شده بود
     * (re-provision without overwriting theme settings).
     */
    public function syncPagesForSite(Request $request, Theme $theme)
    {
        $request->validate(['site_id' => 'required|integer|exists:sites,id']);

        $site = Site::findOrFail($request->site_id);

        if (!$site->db_host || !$site->db_name) {
            return back()->with('error', 'اطلاعات دیتابیس سایت "' . $site->title . '" تنظیم نشده است.');
        }

        $connKey = 'theme_sync_' . $site->id . '_' . time();
        Config::set("database.connections.{$connKey}", [
            'driver'    => 'mysql',
            'host'      => $site->db_host,
            'port'      => '3306',
            'database'  => $site->db_name,
            'username'  => $site->db_user,
            'password'  => $site->getDecryptedDbPassword() ?? '',
            'charset'   => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix'    => '',
            'strict'    => false,
        ]);
        DB::purge($connKey);
        $conn = DB::connection($connKey);

        $this->provisionThemePagesIntoDb($conn, $theme);

        DB::purge($connKey);

        return redirect()->route('admin.theme-builder.index')
            ->with('success', 'صفحات قالب "' . $theme->name . '" با موفقیت در سایت "' . $site->title . '" همگام‌سازی شدند.');
    }

    public function canvasTemplates(Request $request): JsonResponse
    {
        $templates = $this->templateSvc->listForCanvas(
            type:     $request->input('type'),
            category: $request->input('category'),
            term:     $request->input('search'),
            siteId:   null,
        );

        return response()->json(['templates' => $templates]);
    }

    public function canvasSaveAsTemplate(Request $request, Theme $theme): JsonResponse
    {
        $data = $request->validate([
            'name'       => 'required|string|max:200',
            'type'       => 'required|in:' . implode(',', array_keys(ThemeTemplate::TYPES)),
            'category'   => 'required|in:' . implode(',', array_keys(ThemeTemplate::CATEGORIES)),
            'scope'      => 'required|in:block,page,theme',
            'blocks'     => 'required|array',
            'page_key'   => 'nullable|string',
            'is_public'  => 'nullable|boolean',
        ]);

        $template = $this->templateSvc->saveFromCanvas($data, null);

        return response()->json(['success' => true, 'template' => ['id' => $template->id, 'name' => $template->name]]);
    }

    public function canvasInsertTemplate(Request $request, Theme $theme): JsonResponse
    {
        $data = $request->validate([
            'template_id' => 'required|integer|exists:theme_templates,id',
        ]);

        $blocks = $this->templateSvc->insertIntoPage((int) $data['template_id'], null);

        return response()->json(['success' => true, 'blocks' => $blocks]);
    }

    /**
     * Directly write theme pages into the customer site's pages table via DB connection.
     * Pages are marked as is_protected=1, theme_id=slug so they cannot be accidentally deleted.
     */
    protected function provisionThemePagesIntoDb($conn, \App\Models\Theme $theme): void
    {
        $pages   = $theme->getPageList();
        $themeId = $theme->slug ?? (string) $theme->id;

        try {
            // Check if pages table has the is_protected column
            $hasProtection = \Illuminate\Support\Facades\Schema::connection($conn->getName())->hasColumn('pages', 'is_protected');
        } catch (\Throwable) {
            $hasProtection = false;
        }

        foreach ($pages as $pageKey => $pageDef) {
            $title  = $pageDef['title'] ?? $pageKey;
            $slug   = $pageDef['slug'] ?? Str::slug($title) ?: $pageKey;
            $blocks = json_encode(['blocks' => $pageDef['blocks'] ?? []], JSON_UNESCAPED_UNICODE);
            $now    = now()->toDateTimeString();

            // Skip if already provisioned for this theme (only safe when columns exist)
            if ($hasProtection) {
                try {
                    $exists = $conn->table('pages')
                        ->where('theme_page_key', $pageKey)
                        ->where('theme_id', $themeId)
                        ->exists();
                    if ($exists) continue;
                } catch (\Throwable) {}
            }

            // Ensure unique slug
            $baseSlug = $slug;
            $i = 1;
            while ($conn->table('pages')->where('slug', $slug)->exists()) {
                $slug = $baseSlug . '-' . $i++;
            }

            $row = [
                'title'              => $title,
                'slug'               => $slug,
                'is_published'       => 1,
                'show_in_menu'       => ($pageKey === 'home') ? 1 : 0,
                'builder_type'       => 'builder',
                'builder_content_v2' => $blocks,
                'order'              => ($conn->table('pages')->max('order') ?? 0) + 1,
                'created_at'         => $now,
                'updated_at'         => $now,
            ];

            if ($hasProtection) {
                $row['is_protected']    = 1;
                $row['theme_id']        = $themeId;
                $row['theme_page_key']  = $pageKey;
            }

            try {
                $conn->table('pages')->insert($row);
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('ThemeInstall: page insert failed', [
                    'key'     => $pageKey,
                    'message' => $e->getMessage(),
                ]);
            }
        }
    }
}
