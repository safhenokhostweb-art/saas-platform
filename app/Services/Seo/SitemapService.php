<?php
namespace App\Services\Seo;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use App\Models\Site;

class SitemapService
{
    /**
     * ساخت فایل sitemap.xml برای کل سایت
     */
    public function generate(): void
    {
        $urls = [];

        // صفحه اصلی
        $urls[] = [
            'loc' => url('/'),
            'lastmod' => now()->toAtomString(),
            'priority' => '1.0',
        ];

        // محصولات (فقط اگر جدول وجود دارد)
        if (Schema::hasTable('products')) {
            $productClass = 'App\\Models\\Product';

            if (class_exists($productClass)) {
                $productClass::latest()->chunk(500, function ($products) use (&$urls) {
                    foreach ($products as $product) {
                        $urls[] = [
                            'loc' => url('/products/' . $product->id),
                            'lastmod' => optional($product->updated_at)->toAtomString(),
                            'priority' => '0.9',
                        ];
                    }
                });
            }
        }

        // مقالات / بلاگ
        if (Schema::hasTable('posts')) {
            $postClass = 'App\\Models\\Post';

            if (class_exists($postClass)) {
                $postClass::latest()->chunk(500, function ($posts) use (&$urls) {
                    foreach ($posts as $post) {
                        $urls[] = [
                            'loc' => url('/blog/' . $post->slug),
                            'lastmod' => optional($post->updated_at)->toAtomString(),
                            'priority' => '0.7',
                        ];
                    }
                });
            }
        }

        // دامنه سایت‌های مشتری (مولتی‌سایت)
        if (Schema::hasTable('sites')) {
            Site::latest()->chunk(500, function ($sites) use (&$urls) {
                foreach ($sites as $site) {
                    if ($site->domain) {
                        $urls[] = [
                            'loc' => 'https://' . ltrim($site->domain, '/'),
                            'lastmod' => optional($site->updated_at)->toAtomString(),
                            'priority' => '0.6',
                        ];
                    }
                }
            });
        }

        $xml = view('seo.sitemap', compact('urls'))->render();

        Storage::disk('public')->put('sitemap.xml', $xml);

        Cache::put('seo:sitemap_last_generated', now()->toDateTimeString(), now()->addDay());
    }
}
