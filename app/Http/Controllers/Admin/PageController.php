<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Helpers\PlatformSettings;
use App\Models\Page;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;

class PageController extends Controller
{
    public function index()
    {
        $pages = Page::orderBy('menu_order')->orderBy('title')->get();
        return view('admin.pages.index', compact('pages'));
    }

    public function create()
    {
        return view('admin.pages.create', ['page' => new Page()]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255|unique:pages,slug',
            'icon_default' => 'nullable|string|max:32',
            'icon_path' => 'nullable|string|max:500',
            'content' => 'nullable|string',
            'menu_position' => 'required|in:top,bottom,left,right',
            'menu_order' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
            'meta_description' => 'nullable|string|max:500',
        ]);

        $data['slug'] = $data['slug'] ?? Str::slug($data['title']);
        $data['menu_order'] = (int) ($data['menu_order'] ?? 0);
        $data['is_active'] = $request->boolean('is_active');
        $data['icon_default'] = trim((string)($data['icon_default'] ?? '')) ?: 'document';

        if ($request->has('content') && is_string($request->content)) {
            $decoded = json_decode($request->content, true);
            $data['content'] = is_array($decoded) ? $decoded : ['blocks' => []];
        } else {
            $data['content'] = ['blocks' => []];
        }

        if ($request->hasFile('icon')) {
            $maxKb = PlatformSettings::uploadImageMaxKb();
            $request->validate(['icon' => 'file|mimes:jpeg,jpg,png,gif,webp,svg|max:' . $maxKb]);
            $data['icon'] = $request->file('icon')->store('pages', 'public');
        } elseif ($request->filled('icon_path')) {
            $raw = trim($request->icon_path);
            if (preg_match('#^https?://#i', $raw) || str_starts_with($raw, '/')) {
                $data['icon'] = $raw;
            } else {
                $path = PlatformSettings::normalizeStoragePath($raw);
                if ($path !== '') {
                    $data['icon'] = $path;
                }
            }
        }

        Page::create($data);
        return redirect()->route('admin.pages.index')->with('success', 'صفحه ایجاد شد.');
    }

    public function edit(Page $page)
    {
        return view('admin.pages.edit', compact('page'));
    }

    public function update(Request $request, Page $page)
    {
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255|unique:pages,slug,' . $page->id,
            'icon_default' => 'nullable|string|max:32',
            'icon_path' => 'nullable|string|max:500',
            'content' => 'nullable|string',
            'menu_position' => 'required|in:top,bottom,left,right',
            'menu_order' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
            'meta_description' => 'nullable|string|max:500',
        ]);

        $data['menu_order'] = (int) ($data['menu_order'] ?? 0);
        $data['is_active'] = $request->boolean('is_active');
        $data['icon_default'] = trim((string)($data['icon_default'] ?? '')) ?: 'document';

        if ($request->has('content') && is_string($request->content)) {
            $decoded = json_decode($request->content, true);
            $data['content'] = is_array($decoded) ? $decoded : $page->content;
        }

        if ($request->hasFile('icon')) {
            $maxKb = PlatformSettings::uploadImageMaxKb();
            $request->validate(['icon' => 'file|mimes:jpeg,jpg,png,gif,webp,svg|max:' . $maxKb]);
            if ($page->icon && Storage::disk('public')->exists($page->icon)) {
                \Illuminate\Support\Facades\Storage::disk('public')->delete($page->icon);
            }
            $data['icon'] = $request->file('icon')->store('pages', 'public');
        }

        if ($request->has('remove_icon') && $request->boolean('remove_icon')) {
            if ($page->icon) {
                \Illuminate\Support\Facades\Storage::disk('public')->delete($page->icon);
            }
            $data['icon'] = null;
        } elseif (!$request->hasFile('icon') && $request->filled('icon_path')) {
            $raw = trim($request->icon_path);
            if (preg_match('#^https?://#i', $raw) || str_starts_with($raw, '/')) {
                $data['icon'] = $raw;
            } else {
                $path = PlatformSettings::normalizeStoragePath($raw);
                $data['icon'] = $path !== '' ? $path : $page->icon;
            }
        }

        $page->update($data);
        return redirect()->route('admin.pages.index')->with('success', 'صفحه به‌روزرسانی شد.');
    }

    public function destroy(Page $page)
    {
        if ($page->icon) {
            \Illuminate\Support\Facades\Storage::disk('public')->delete($page->icon);
        }
        $page->delete();
        return redirect()->route('admin.pages.index')->with('success', 'صفحه حذف شد.');
    }

    /** ذخیره موقعیت نمایش فهرست صفحات (برای کل سایت) */
    public function menuPosition(Request $request)
    {
        $pos = $request->input('position', 'top');
        if (in_array($pos, ['top', 'bottom', 'left', 'right'], true)) {
            Setting::updateOrCreate(['key' => 'page_menu_position'], ['value' => $pos]);
        }
        return back()->with('success', 'موقعیت فهرست صفحات ذخیره شد.');
    }

    /** تغییر ترتیب صفحات (درگ اند دراپ) */
    public function reorder(Request $request)
    {
        $order = $request->input('order', []);
        if (!is_array($order)) {
            return back()->with('error', 'ترتیب نامعتبر است.');
        }
        foreach ($order as $position => $id) {
            Page::where('id', (int) $id)->update(['menu_order' => (int) $position]);
        }
        return back()->with('success', 'ترتیب صفحات به‌روزرسانی شد.');
    }

    /** آپلود تصویر برای بلوک تصویر در صفحه‌ساز. حداکثر حجم از تنظیمات پنل خوانده می‌شود. */
    public function uploadImage(Request $request)
    {
        $maxKb = PlatformSettings::uploadImageMaxKb();
        $request->validate([
            'image' => [
                'required',
                'file',
                'max:' . $maxKb,
                'mimes:jpeg,jpg,png,gif,bmp,webp',
                'mimetypes:image/jpeg,image/png,image/gif,image/bmp,image/webp',
            ],
        ], [
            'image.required' => 'لطفاً یک فایل تصویر انتخاب کنید.',
            'image.mimes' => 'پسوندهای مجاز تصویر: JPG، JPEG، PNG، GIF، BMP، WEBP.',
            'image.mimetypes' => 'نوع فایل باید تصویر باشد (JPG، PNG، GIF، BMP، WEBP).',
            'image.max' => 'حداکثر حجم هر تصویر ' . round($maxKb / 1024, 1) . ' مگابایت است (از تنظیمات پنل).',
        ]);

        $path = $request->file('image')->store('pages/images', 'public');
        // آدرس نسبی تا در صفحه نمایش با دامنه فعلی درست لود شود
        $url = '/storage/' . $path;

        return response()->json(['url' => $url, 'path' => $path]);
    }
}
