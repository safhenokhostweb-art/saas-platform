<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Site;
use App\Models\Theme;
use App\Models\ThemeRevision;
use App\Services\ThemeBuilder\BlockRegistry;
use App\Services\ThemeBuilder\BlockRenderer;
use App\Services\ThemeBuilder\GlobalStyleEngine;
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
        return redirect()->route('admin.theme-builder.edit', $theme)
            ->with('success', 'تم ایجاد شد. اکنون می‌توانید هدر، فوتر و استایل‌ها را ویرایش کنید.');
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

    public function publish(Theme $theme_builder)
    {
        $this->themeService->publish($theme_builder);
        return redirect()->route('admin.theme-builder.edit', $theme_builder)->with('success', 'تم منتشر شد.');
    }

    public function unpublish(Theme $theme_builder)
    {
        $this->themeService->unpublish($theme_builder);
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
