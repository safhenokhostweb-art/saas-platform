<?php

namespace App\Services\ThemeBuilder;

use App\Models\Site;
use Illuminate\Support\Facades\DB;

/**
 * Resolves demo-data placeholders inside template blocks.
 *
 * Placeholder tokens inside block props/style values:
 *   {{site.name}}         → tenant site name
 *   {{site.tagline}}      → site tagline / description
 *   {{site.logo_url}}     → site logo URL
 *   {{site.primary_color}}→ theme primary colour
 *   {{placeholder.image}} → default placeholder image URL
 *   {{placeholder.avatar}}→ avatar placeholder
 *   {{first.product.name}}→ first product in tenant DB
 *   {{first.product.price}}
 *   {{first.post.title}}  → first blog post title
 *   {{first.post.excerpt}}
 *   {{first.course.title}}
 */
class ThemeTemplateDemoDataService
{
    public function resolveForSite(array $blocks, ?int $siteId): array
    {
        if ($siteId === null) return $blocks;

        $map = $this->buildMap($siteId);
        return $this->resolveBlocks($blocks, $map);
    }

    private function buildMap(int $siteId): array
    {
        $site = Site::find($siteId);

        $map = [
            '{{site.name}}'          => $site?->name ?? 'نام سایت',
            '{{site.tagline}}'       => $site?->tagline ?? 'توضیح کوتاه سایت',
            '{{site.logo_url}}'      => $site?->logo_url ?? '',
            '{{site.primary_color}}' => '#4f46e5',
            '{{placeholder.image}}' => 'https://placehold.co/800x450/e2e8f0/94a3b8?text=تصویر',
            '{{placeholder.avatar}}'=> 'https://placehold.co/80x80/e2e8f0/94a3b8?text=عکس',
            '{{placeholder.logo}}'  => 'https://placehold.co/200x60/e2e8f0/94a3b8?text=لوگو',
        ];

        // Try to grab first product (if table exists)
        try {
            $product = DB::connection("site_{$siteId}")
                ->table('products')->where('is_active', true)->first();
            $map['{{first.product.name}}']  = $product?->name  ?? 'نام محصول نمونه';
            $map['{{first.product.price}}'] = $product?->price ?? '۰';
        } catch (\Throwable) {
            $map['{{first.product.name}}']  = 'نام محصول نمونه';
            $map['{{first.product.price}}'] = '۰';
        }

        // Try to grab first post
        try {
            $post = DB::connection("site_{$siteId}")
                ->table('posts')->where('status', 'published')->first();
            $map['{{first.post.title}}']   = $post?->title   ?? 'عنوان مقاله نمونه';
            $map['{{first.post.excerpt}}'] = $post?->excerpt ?? 'خلاصه مقاله نمونه…';
        } catch (\Throwable) {
            $map['{{first.post.title}}']   = 'عنوان مقاله نمونه';
            $map['{{first.post.excerpt}}'] = 'خلاصه مقاله نمونه…';
        }

        // Try to grab first course
        try {
            $course = DB::connection("site_{$siteId}")
                ->table('education_courses')->where('is_published', true)->first();
            $map['{{first.course.title}}'] = $course?->title ?? 'عنوان دوره نمونه';
        } catch (\Throwable) {
            $map['{{first.course.title}}'] = 'عنوان دوره نمونه';
        }

        return $map;
    }

    private function resolveBlocks(array $blocks, array $map): array
    {
        return array_map(fn($b) => $this->resolveBlock($b, $map), $blocks);
    }

    private function resolveBlock(array $block, array $map): array
    {
        if (!empty($block['props']) && is_array($block['props'])) {
            $block['props'] = $this->resolveValues($block['props'], $map);
        }
        if (!empty($block['children']) && is_array($block['children'])) {
            $block['children'] = $this->resolveBlocks($block['children'], $map);
        }
        return $block;
    }

    private function resolveValues(array $values, array $map): array
    {
        foreach ($values as $k => $v) {
            if (is_string($v)) {
                $values[$k] = strtr($v, $map);
            } elseif (is_array($v)) {
                $values[$k] = $this->resolveValues($v, $map);
            }
        }
        return $values;
    }
}
