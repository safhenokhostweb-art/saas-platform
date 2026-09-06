<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Site;
use App\Models\Theme;
use App\Models\ThemeRevision;
use App\Services\ThemeBuilder\BlockRegistry;
use App\Services\ThemeBuilder\BlockRenderer;
use App\Services\ThemeBuilder\Dependency\ThemeExtensionBlockTypeExtractor;
use App\Services\ThemeBuilder\Extension\ExtensionCanvasPaletteAdapter;
use App\Services\ThemeBuilder\GlobalStyleEngine;
use App\Services\ThemeBuilder\QualityGate\ThemeQualityGateFailedException;
use App\Services\ThemeBuilder\ThemeBuilderService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ThemeBuilderController extends Controller
{
    public function __construct(
        protected ThemeBuilderService $themeService,
        protected BlockRenderer $renderer,
        protected GlobalStyleEngine $styleEngine
    ) {}

    public function index(Request $request)
    {
        $siteId = $request->integer('site_id');
        $themes = Theme::query()
            ->when($siteId > 0, fn ($q) => $q->where('site_id', $siteId))
            ->when($siteId <= 0, fn ($q) => $q->whereNull('site_id'))
            ->orderByDesc('updated_at')
            ->paginate(20);
        $sites = Site::orderBy('title')->get(['id', 'title']);
        return view('admin.theme-builder.index', compact('themes', 'sites', 'siteId'));
    }

    public function create(Request $request)
    {
        $siteId = $request->integer('site_id');
        $sites = Site::orderBy('title')->get(['id', 'title']);
        $blockRegistry = [
            'blocks' => BlockRegistry::all(),
            'byCategory' => BlockRegistry::byCategory(),
            'categoryLabels' => BlockRegistry::categoryLabels(),
        ];
        return view('admin.theme-builder.create', compact('sites', 'siteId', 'blockRegistry'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255',
            'description' => 'nullable|string|max:500',
            'site_id' => 'nullable|exists:sites,id',
        ]);
        $data['slug'] = $data['slug'] ?? Str::slug($data['name']) . '-' . uniqid();
        $theme = $data['site_id']
            ? $this->themeService->createForSite(Site::findOrFail($data['site_id']), $data)
            : $this->themeService->createTemplate($data);
        return redirect()->route('admin.theme-builder.canvas', $theme)
            ->with('success', 'تم ایجاد شد. اکنون می‌توانید آن را در قالب‌ساز طراحی کنید.');
    }

    public function edit(Theme $theme_builder)
    {
        $theme = $theme_builder;
        $blockRegistry = [
            'blocks' => BlockRegistry::all(),
            'byCategory' => BlockRegistry::byCategory(),
            'categoryLabels' => BlockRegistry::categoryLabels(),
        ];
        $defaultStyles = $this->themeService->defaultGlobalStyles();
        return view('admin.theme-builder.edit', compact('theme', 'blockRegistry', 'defaultStyles'));
    }

    public function update(Request $request, Theme $theme_builder)
    {
        $theme = $theme_builder;
        $data = $request->validate([
            'name' => 'sometimes|string|max:255',
            'slug' => 'sometimes|string|max:255',
            'description' => 'nullable|string|max:500',
            // در ادیتور، این فیلدها به صورت JSON string ارسال می‌شوند
            'header_blocks' => 'nullable',
            'footer_blocks' => 'nullable',
            'global_styles' => 'nullable',
            'settings' => 'nullable',
        ]);

        foreach (['header_blocks', 'footer_blocks', 'global_styles', 'settings'] as $key) {
            if (array_key_exists($key, $data) && is_string($data[$key]) && $data[$key] !== '') {
                $decoded = json_decode($data[$key], true);
                if (is_array($decoded)) {
                    $data[$key] = $decoded;
                } else {
                    $data[$key] = $key === 'global_styles' ? $this->themeService->defaultGlobalStyles() : [];
                }
            }
        }

        // Phase 3H-B — this raw editor is the only authoring path for
        // header_blocks/footer_blocks besides canonical Canvas, so it must
        // reuse the exact same B-6 save-boundary ownership gate Canvas's
        // own save() uses (ExtensionCanvasPaletteAdapter::
        // validateBlocksAgainstSelections()) — never a second validator.
        // global_styles/settings are not block trees and are deliberately
        // left out of this gate.
        if (array_key_exists('header_blocks', $data) || array_key_exists('footer_blocks', $data)) {
            $headerToValidate = array_key_exists('header_blocks', $data) ? $data['header_blocks'] : ($theme->header_blocks ?? []);
            $footerToValidate = array_key_exists('footer_blocks', $data) ? $data['footer_blocks'] : ($theme->footer_blocks ?? []);

            $previousInstances = (new ThemeExtensionBlockTypeExtractor())->extractInstancesFromSnapshot(
                $theme->header_blocks ?? [],
                $theme->footer_blocks ?? [],
                $theme->pages ?? $theme->getPageList()
            );

            $selections = $theme->settings['extension_selections'] ?? [];

            $validation = ExtensionCanvasPaletteAdapter::validateBlocksAgainstSelections(
                array_merge($headerToValidate, $footerToValidate),
                $selections,
                $previousInstances
            );

            if (!$validation['ok']) {
                if ($request->wantsJson()) {
                    return response()->json(['error' => $validation['error']], 422);
                }
                return back()->withInput()->with('error', $validation['error']);
            }
        }

        $this->themeService->updateTheme($theme, $data);
        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'theme' => $theme->fresh()]);
        }
        return redirect()->route('admin.theme-builder.edit', $theme)->with('success', 'تم ذخیره شد.');
    }

    public function destroy(Theme $theme_builder)
    {
        $theme_builder->delete();
        return redirect()->route('admin.theme-builder.index')->with('success', 'تم حذف شد.');
    }

    /**
     * Phase 2c — Mandatory Commercial Theme Publish Gate. No quality logic
     * lives here: ThemeBuilderService::publish() already ran the Commercial
     * Quality Gate before this method regains control, and either succeeded
     * (a real ThemeRelease now exists) or threw ThemeQualityGateFailedException
     * (nothing was written). This method only formats that already-decided
     * outcome for the caller — the structured diagnostics come straight from
     * the persisted ThemeQualityReport the gate produced.
     */
    public function publish(Request $request, Theme $theme_builder)
    {
        $data = $request->validate([
            'version' => 'nullable|string|max:32',
            'changelog' => 'nullable|string|max:2000',
            'release_notes' => 'nullable|string|max:5000',
        ]);

        try {
            $this->themeService->publish(
                $theme_builder,
                $data['version'] ?? null,
                $data['changelog'] ?? null,
                $data['release_notes'] ?? null,
                $request->user()?->id
            );
        } catch (ThemeQualityGateFailedException $e) {
            $failures = array_map(
                fn (array $f) => ['code' => $f['code'], 'category' => $f['category'], 'message' => $f['message']],
                $e->report->blockingFailures()
            );

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'قالب منتشر نشد: دروازه کیفیت تجاری رد شد.',
                    'quality_report_id' => $e->report->id,
                    'blocking_failures' => $failures,
                ], 422);
            }

            return redirect()->route('admin.theme-builder.edit', $theme_builder)
                ->with('error', 'انتشار انجام نشد — دروازه کیفیت تجاری رد شد: ' . implode('، ', array_column($failures, 'message')));
        }

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'message' => 'قالب منتشر شد.']);
        }
        return redirect()->route('admin.theme-builder.edit', $theme_builder)->with('success', 'تم منتشر شد.');
    }

    public function unpublish(Request $request, Theme $theme_builder)
    {
        $this->themeService->unpublish($theme_builder);
        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'message' => 'قالب از انتشار خارج شد.']);
        }
        return redirect()->route('admin.theme-builder.edit', $theme_builder)->with('success', 'تم از حالت انتشار خارج شد.');
    }

    public function saveRevision(Request $request, Theme $theme_builder)
    {
        $label = $request->input('label', 'نسخه ' . now()->format('Y/m/d H:i'));
        $rev = $this->themeService->saveRevision($theme_builder, $label, $request->user()?->id);
        return response()->json(['success' => true, 'revision' => $rev]);
    }

    public function restoreRevision(ThemeRevision $revision)
    {
        $theme = $revision->theme;
        $this->themeService->restoreRevision($revision);
        return redirect()->route('admin.theme-builder.edit', $theme)->with('success', 'نسخه بازیابی شد.');
    }

    public function cloneTheme(Request $request, Theme $theme_builder)
    {
        $newName = $request->validate(['name' => 'required|string|max:255'])['name'];
        $siteId = $request->integer('site_id') ?: null;
        $newTheme = $this->themeService->cloneTheme($theme_builder, $newName, $siteId ?: null);
        return redirect()->route('admin.theme-builder.edit', $newTheme)->with('success', 'تم کپی شد.');
    }

    public function export(Theme $theme_builder)
    {
        $json = $this->themeService->exportTheme($theme_builder);
        return response()->json($json, 200, [
            'Content-Disposition' => 'attachment; filename="theme-' . $theme_builder->slug . '.json"',
            'Content-Type' => 'application/json',
        ]);
    }

    public function import(Request $request)
    {
        $request->validate(['file' => 'required|file|mimes:json|max:10240']);
        $content = file_get_contents($request->file('file')->getRealPath());
        $data = json_decode($content, true);
        if (!is_array($data)) {
            return redirect()->route('admin.theme-builder.index')->with('error', 'فایل JSON نامعتبر است.');
        }
        $siteId = $request->integer('site_id') ?: null;
        $theme = $this->themeService->importTheme($data, $siteId);
        return redirect()->route('admin.theme-builder.edit', $theme)->with('success', 'تم با موفقیت وارد شد.');
    }

    /** Preview: render header/footer HTML for iframe. */
    public function preview(Request $request, Theme $theme_builder)
    {
        $zone = $request->input('zone', 'header');
        $blocks = $zone === 'footer' ? ($theme_builder->footer_blocks ?? []) : ($theme_builder->header_blocks ?? []);
        $menuHtml = '';
        if ($theme_builder->site_id && $zone === 'header') {
            $menus = \App\Models\Menu::where('site_id', $theme_builder->site_id)
                ->with(['items' => fn ($q) => $q->where('is_active', true)->orderBy('order')])
                ->where('location', 'header')
                ->first();
            if ($menus && $menus->items->isNotEmpty()) {
                $items = $menus->items->map(fn ($i) => '<a href="' . e($i->url ?? '#') . '">' . e($i->title) . '</a>')->implode(' ');
                $menuHtml = $items;
            }
        }
        $this->renderer->setContext(['menu_html' => $menuHtml]);
        $html = $this->renderer->renderBlocks($blocks);
        $globalStyles = $theme_builder->global_styles ?? $this->themeService->defaultGlobalStyles();
        $css = $this->styleEngine->renderCssBlock($globalStyles, $theme_builder->site_id);
        return response()->view('admin.theme-builder.preview-frame', [
            'html' => $html,
            'css' => $css,
            'zone' => $zone,
        ])->header('Content-Type', 'text/html; charset=UTF-8');
    }

    /** Assign theme to site. */
    public function assign(Request $request)
    {
        $data = $request->validate([
            'site_id' => 'required|exists:sites,id',
            'theme_id' => 'nullable|exists:themes,id',
        ]);
        $site = Site::findOrFail($data['site_id']);
        $site->update(['theme_id' => $data['theme_id'] ?: null]);
        return redirect()->back()->with('success', 'تم برای سایت انتخاب شد.');
    }
}
