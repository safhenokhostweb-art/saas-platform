<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Theme;
use App\Services\ThemeBuilder\BlockRegistry;
use App\Services\ThemeBuilder\BlockRenderer;
use App\Services\ThemeBuilder\GlobalStyleEngine;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ThemeCanvasController extends Controller
{
    public function __construct(
        protected BlockRenderer $renderer,
        protected GlobalStyleEngine $styleEngine
    ) {}

    public function show(Theme $theme)
    {
        $pages     = $theme->getPageList();
        $registry  = BlockRegistry::all();
        $byCategory = BlockRegistry::byCategory();
        $catLabels  = BlockRegistry::categoryLabels();
        $catIcons   = BlockRegistry::categoryIcons();

        return view('admin.theme-builder.canvas', compact(
            'theme', 'pages', 'registry', 'byCategory', 'catLabels', 'catIcons'
        ));
    }

    public function save(Request $request, Theme $theme)
    {
        $data = $request->validate([
            'page_key' => 'required|string|max:100',
            'blocks'   => 'required|array',
        ]);

        $pages = $theme->pages ?? $theme->getPageList();
        $key   = $data['page_key'];

        if (!isset($pages[$key])) {
            return response()->json(['error' => 'صفحه یافت نشد'], 404);
        }

        $pages[$key]['blocks'] = $data['blocks'];
        $theme->update(['pages' => $pages]);

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

    public function preview(Theme $theme, string $pageKey)
    {
        $pages = $theme->getPageList();
        $page  = $pages[$pageKey] ?? null;

        if (!$page) {
            abort(404, 'صفحه یافت نشد');
        }

        $blocks = $page['blocks'] ?? [];
        $html   = $this->renderer->renderBlocks($blocks);
        $css    = $this->styleEngine->generate($theme->global_styles ?? []);

        return view('admin.theme-builder.canvas-preview', compact('theme', 'page', 'html', 'css', 'pageKey'));
    }

    public function previewAjax(Request $request, Theme $theme)
    {
        $data = $request->validate([
            'page_key' => 'required|string',
            'blocks'   => 'required|array',
        ]);

        $html = $this->renderer->renderBlocks($data['blocks']);
        $css  = $this->styleEngine->generate($theme->global_styles ?? []);

        return response()->json(['html' => $html, 'css' => $css]);
    }
}
