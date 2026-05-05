<?php

namespace App\Observers;

use App\Models\Product;
use App\Services\Seo\SeoEngine;
use App\Services\Seo\SitemapService;

class ProductObserver
{
    public function saved(Product $product): void
    {
        if (!method_exists($product, 'seoMeta')) {
            return;
        }
        app(SeoEngine::class)->generate($product);
        app(SitemapService::class)->generate();
    }

    public function deleted(Product $product): void
    {
        if (!method_exists($product, 'seoMeta')) {
            return;
        }
        app(SitemapService::class)->generate();
    }
}
