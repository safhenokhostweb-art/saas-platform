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
use App\Services\ThemeBuilder\ThemeBuilderService;
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
        protected ThemeBuilderService $themeBuilder,
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
        $visualGlobalStyles = $this->visualGlobalStyles($theme);

        $sites = Site::orderBy('title')->get(['id', 'title']);

        return view('admin.theme-builder.canvas', compact(
            'theme', 'pages', 'registry', 'byCategory', 'catLabels', 'catIcons',
            'extensionSelections', 'extensionSelectionErrors', 'availableExtensions',
            'visualGlobalStyles', 'sites'
        ));
    }

    /**
     * Persist the explicit Theme-level visual Global Styles operation.
     * This deliberately does not pass through the page/header/footer target
     * contract because global styles are not a block tree.
     */
    public function saveGlobalStyles(Request $request, Theme $theme): JsonResponse
    {
        $data = $request->validate([
            'styles' => 'required|array:primary_color,secondary_color,accent_color,background_color,text_color,surface_color,muted_text_color,font_family,type_scale,spacing_unit,spacing_scale,radius_sm,radius_md,radius_lg,shadow_sm,shadow_md,shadow_lg|min:1',
            'styles.primary_color' => ['sometimes', 'required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'styles.secondary_color' => ['sometimes', 'required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'styles.accent_color' => ['sometimes', 'required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'styles.background_color' => ['sometimes', 'required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'styles.text_color' => ['sometimes', 'required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'styles.surface_color' => ['sometimes', 'required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'styles.muted_text_color' => ['sometimes', 'required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'styles.font_family' => ['sometimes', 'required', 'string', 'max:200', 'not_regex:/[{};<>]/u'],
            'styles.type_scale' => ['sometimes', 'required', 'numeric', 'between:0.5,3'],
            'styles.spacing_unit' => ['sometimes', 'required', 'regex:/^(?:0|\d+(?:\.\d+)?(?:px|rem|em))$/'],
            'styles.spacing_scale' => ['sometimes', 'required', 'numeric', 'between:0.5,3'],
            'styles.radius_sm' => ['sometimes', 'required', 'regex:/^(?:0|\d+(?:\.\d+)?(?:px|rem|em))$/'],
            'styles.radius_md' => ['sometimes', 'required', 'regex:/^(?:0|\d+(?:\.\d+)?(?:px|rem|em))$/'],
            'styles.radius_lg' => ['sometimes', 'required', 'regex:/^(?:0|\d+(?:\.\d+)?(?:px|rem|em))$/'],
            'styles.shadow_sm' => ['sometimes', 'required', 'string', 'max:200', 'not_regex:/[{};<>]/u'],
            'styles.shadow_md' => ['sometimes', 'required', 'string', 'max:200', 'not_regex:/[{};<>]/u'],
            'styles.shadow_lg' => ['sometimes', 'required', 'string', 'max:200', 'not_regex:/[{};<>]/u'],
        ]);

        $paths = [
            'primary_color' => 'primary_color',
            'secondary_color' => 'secondary_color',
            'accent_color' => 'accent_color',
            'background_color' => 'background_color',
            'text_color' => 'text_color',
            'surface_color' => 'colors.surface',
            'muted_text_color' => 'colors.text_muted',
            'font_family' => 'font_family',
            'type_scale' => 'typography.scale',
            'spacing_unit' => 'spacing.unit',
            'spacing_scale' => 'spacing.scale',
            'radius_sm' => 'border_radius.sm',
            'radius_md' => 'border_radius.md',
            'radius_lg' => 'border_radius.lg',
            'shadow_sm' => 'shadows.sm',
            'shadow_md' => 'shadows.md',
            'shadow_lg' => 'shadows.lg',
        ];

        $patch = [];
        foreach ($data['styles'] as $field => $value) {
            $patch[$paths[$field]] = $value;
        }

        $theme = $this->themeBuilder->patchGlobalStyles($theme, $patch);

        return response()->json([
            'success' => true,
            'message' => 'استایل‌های سراسری ذخیره شد.',
            'global_styles' => $theme->global_styles,
            'styles' => $this->visualGlobalStyles($theme),
            'css_variables' => $this->styleEngine->getCssVariables($theme->global_styles ?? [], $theme->site_id),
        ]);
    }

    /** @return array<string, string|float> */
    private function visualGlobalStyles(Theme $theme): array
    {
        $globalStyles = $theme->global_styles ?? [];
        $vars = $this->styleEngine->getCssVariables($globalStyles, $theme->site_id);

        return [
            'primary_color' => $vars['--tb-primary'],
            'secondary_color' => $vars['--tb-secondary'],
            'accent_color' => $vars['--tb-accent'],
            'background_color' => $vars['--tb-background'],
            'text_color' => $vars['--tb-text'],
            'surface_color' => $vars['--tb-surface'],
            'muted_text_color' => $vars['--tb-text-muted'],
            'font_family' => $vars['--tb-font-body'],
            'type_scale' => (float) $vars['--tb-scale'],
            'spacing_unit' => $vars['--tb-spacing-unit'] ?? '0.25rem',
            'spacing_scale' => (float) ($vars['--tb-spacing-scale'] ?? 1),
            'radius_sm' => $vars['--tb-radius-sm'],
            'radius_md' => $vars['--tb-radius-md'],
            'radius_lg' => $vars['--tb-radius-lg'],
            'shadow_sm' => $vars['--tb-shadow-sm'],
            'shadow_md' => $vars['--tb-shadow-md'],
            'shadow_lg' => $vars['--tb-shadow-lg'],
        ];
    }

    public function save(Request $request, Theme $theme)
    {
        $data = $request->validate([
            // An editor-boundary discriminator only: this never creates a
            // page/model concept or a second persisted representation.
            'target'   => 'nullable|in:page,header,footer',
            'page_key' => 'required_if:target,page|required_without:target|string|max:100',
            // An empty block tree is a valid Canvas state (especially for a
            // newly selected Header/Footer zone); require the field itself,
            // not at least one element.
            'blocks'   => 'present|array',
            'extension_selections' => 'nullable|array',
        ]);

        $pages = $theme->pages ?? $theme->getPageList();
        $target = $data['target'] ?? 'page';
        $key = $data['page_key'] ?? null;

        if ($target === 'page' && !isset($pages[$key])) {
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

        $settings = $theme->settings ?? [];
        $settings['extension_selections'] = $selections;

        // A zone save changes exactly its existing field plus the existing
        // Extension selection map contract. It cannot overwrite another zone.
        $updates = ['settings' => $settings];
        if ($target === 'page') {
            $pages[$key]['blocks'] = $data['blocks'];
            $updates['pages'] = $pages;
        } elseif ($target === 'header') {
            $updates['header_blocks'] = $data['blocks'];
        } else {
            $updates['footer_blocks'] = $data['blocks'];
        }

        $theme->update($updates);

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
        $target = $request->input('target', 'page');
        abort_unless(in_array($target, ['page', 'header', 'footer'], true), 404);
        $page = match ($target) {
            'header' => ['title' => 'Header', 'blocks' => $theme->header_blocks ?? []],
            'footer' => ['title' => 'Footer', 'blocks' => $theme->footer_blocks ?? []],
            default => $pages[$pageKey] ?? null,
        };

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
        $target = $request->input('target', 'page');
        abort_unless(in_array($target, ['page', 'header', 'footer'], true), 404);
        $pageKey  = $request->input('page', 'home');
        $pages    = $theme->getPageList();
        $blocks = match ($target) {
            'header' => $theme->header_blocks ?? [],
            'footer' => $theme->footer_blocks ?? [],
            default => ($pages[$pageKey]['blocks'] ?? []),
        };

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
     * نصب قالب روی سایت مشتری از پنل پلتفرم.
     *
     * Single install authority: this calls the customer-site's own
     * canonical /builder/api/theme/install endpoint, which writes through
     * ThemeInstanceRevisionService::record() — the exact same write path a
     * customer's own publish/rollback/import already use. There is no
     * second, raw-SQL install path any more: install now produces a real
     * ThemeInstanceRevision with provenance, and is subject to the same
     * CanvasReservedSlugs page-namespace collision guard as every other
     * way the active Canvas theme can change (see ADR-052).
     */
    public function installToSite(Request $request, Theme $theme)
    {
        $request->validate(['site_id' => 'required|integer|exists:sites,id']);

        $site = Site::findOrFail($request->site_id);

        $domain = $site->getDomain();
        if (!$domain) {
            return back()->with('error',
                'دامنه سایت "' . $site->title . '" تنظیم نشده است. از منوی سایت‌ها → ویرایش سایت، فیلد دامنه را پر کنید.'
            );
        }

        $baseUrl  = str_starts_with($domain, 'http') ? rtrim($domain, '/') : 'https://' . rtrim($domain, '/');
        $endpoint = $baseUrl . '/builder/api/theme/install';
        $secret   = config('services.customer_provision_secret', '');

        $currentRelease = $theme->currentRelease;

        try {
            $response = Http::withHeaders([
                'X-Provision-Secret' => $secret,
                'Accept'             => 'application/json',
            ])->timeout(15)->post($endpoint, [
                'theme_id'             => $theme->slug,
                'theme_name'           => $theme->name,
                'source_theme_id'      => (string) $theme->id,
                'source_theme_version' => $currentRelease?->version,
                'source_release_id'    => $currentRelease?->id,
                'header_blocks'        => $theme->header_blocks ?? [],
                'footer_blocks'        => $theme->footer_blocks ?? [],
                'pages'                => $theme->getPageList(),
                'global_styles'        => $theme->global_styles ?? [],
                // Manifest-declared Extension CSS/JS, projected to absolute
                // saas-platform URLs — see ExtensionThemeRuntimeAssetProjector's
                // own doc comment for why this is an absolute URL, not a copy.
                // Absent/empty for every Theme that selects no Extension.
                'extension_assets'     => ExtensionThemeRuntimeAssetProjector::project($theme),
            ]);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('ThemeInstall request failed', [
                'theme_id' => $theme->id,
                'site_id'  => $site->id,
                'error'    => $e->getMessage(),
            ]);
            return back()->with('error', 'اتصال به سایت "' . $site->title . '" ناموفق بود: ' . $e->getMessage());
        }

        if ($response->status() === 409) {
            $body = $response->json();
            return back()->with('error',
                'نصب قالب روی سایت "' . $site->title . '" به دلیل تداخل نام‌گذاری صفحات متوقف شد: '
                . ($body['message'] ?? 'یکی از صفحه‌های این قالب با صفحه‌ای موجود در سایت تداخل دارد.')
            );
        }

        if (!$response->successful()) {
            \Illuminate\Support\Facades\Log::error('ThemeInstall failed', [
                'theme_id' => $theme->id,
                'site_id'  => $site->id,
                'status'   => $response->status(),
                'body'     => $response->body(),
            ]);
            return back()->with('error',
                'نصب قالب روی سایت "' . $site->title . '" ناموفق بود (کد ' . $response->status() . ').'
            );
        }

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
