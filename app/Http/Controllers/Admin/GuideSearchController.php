<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Page;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * جستجو در راهنما برای ویجت راهنما در پنل ادمین پلتفرم.
 * ترکیب: ورودی‌های پیش‌فرض (لینک به بخش‌های ادمین) + صفحات ثابت فعال.
 */
class GuideSearchController extends Controller
{
    /** ورودی‌های پیش‌فرض راهنما (بخش‌های پنل ادمین پلتفرم) — قابل جستجو */
    protected function defaultAdminGuideEntries(): array
    {
        return [
            ['title' => 'داشبورد پلتفرم', 'url' => route('admin.dashboard')],
            ['title' => 'سایت‌ها و مدیریت سایت‌های مشتری', 'url' => route('admin.sites.index')],
            ['title' => 'ماژول‌های هر سایت', 'url' => route('admin.sites.index')],
            ['title' => 'مشتریان', 'url' => route('admin.customers.index')],
            ['title' => 'تنظیمات عمومی پلتفرم (نام، لوگو، رنگ، فونت، ویجت راهنما)', 'url' => route('admin.platform-settings.index')],
            ['title' => 'صفحات ثابت و راهنما', 'url' => route('admin.pages.index')],
            ['title' => 'فرم‌ها و ارسال‌ها', 'url' => route('admin.forms.index')],
            ['title' => 'محصولات فروشگاه پلتفرم', 'url' => route('admin.products.index')],
        ];
    }

    public function index(Request $request): JsonResponse
    {
        try {
            $q = trim((string) $request->input('q', ''));
            $query = Page::where('is_active', true);

            if ($q !== '') {
                $query->where(function ($qb) use ($q) {
                    $qb->where('title', 'like', '%' . $q . '%');
                    $driver = $qb->getConnection()->getDriverName();
                    if ($driver === 'mysql' || $driver === 'mariadb') {
                        $qb->orWhereRaw('(content IS NOT NULL AND CAST(content AS CHAR) LIKE ?)', ['%' . $q . '%']);
                    } else {
                        $qb->orWhereRaw('(content::text LIKE ?)', ['%' . $q . '%']);
                    }
                });
            }

            $pages = $query->orderBy('menu_order')->orderBy('title')->limit(100)->get(['id', 'title', 'slug']);

            $items = $pages->map(function ($page) {
                return [
                    'title' => $page->title,
                    'slug' => $page->slug,
                    'url' => route('page.show', $page->slug),
                ];
            })->values()->all();

            $defaults = $this->defaultAdminGuideEntries();
            if ($q !== '') {
                $qLower = mb_strtolower($q);
                $defaults = array_values(array_filter($defaults, function ($e) use ($qLower) {
                    return mb_strpos(mb_strtolower($e['title']), $qLower) !== false;
                }));
            }
            $items = array_merge($defaults, $items);

            return response()->json(['pages' => $items]);
        } catch (\Throwable $e) {
            report($e);
            return response()->json(['pages' => []]);
        }
    }
}
