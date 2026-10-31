<?php

namespace SaaSPlatform\ThemeRuntime;

/** Shared SleepShiman renderer used by platform preview and tenant runtime. */
trait SleepShimanBlockRendererTrait
{
    private function ssEscape(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    private function ssLabel(string $key): string
    {
        $locale = strtolower((string) ($this->context['locale'] ?? (function_exists('app') ? app()->getLocale() : 'fa')));
        $language = str_starts_with($locale, 'ar') ? 'ar' : (str_starts_with($locale, 'en') ? 'en' : 'fa');
        $labels = [
            'fa' => ['weight'=>'وزن','material'=>'جنس','size'=>'اندازه','sales'=>'فروش','satisfaction'=>'رضایت','view_product'=>'مشاهده محصول','view'=>'مشاهده','add_to_cart'=>'افزودن به سبد','available'=>'موجود','empty_products'=>'محصولی برای نمایش وجود ندارد.','helpful'=>'مفید','not_helpful'=>'نامفید','carousel'=>'کنترل اسلایدر','previous'=>'موارد قبلی','next'=>'موارد بعدی'],
            'ar' => ['weight'=>'الوزن','material'=>'الخامة','size'=>'المقاس','sales'=>'المبيعات','satisfaction'=>'الرضا','view_product'=>'عرض المنتج','view'=>'عرض','add_to_cart'=>'أضف إلى السلة','available'=>'متوفر','empty_products'=>'لا توجد منتجات للعرض.','helpful'=>'مفيد','not_helpful'=>'غير مفيد','carousel'=>'عناصر العرض','previous'=>'السابق','next'=>'التالي'],
            'en' => ['weight'=>'Weight','material'=>'Material','size'=>'Size','sales'=>'Sales','satisfaction'=>'Satisfaction','view_product'=>'View product','view'=>'View','add_to_cart'=>'Add to cart','available'=>'Available','empty_products'=>'No products to display.','helpful'=>'Helpful','not_helpful'=>'Not helpful','carousel'=>'Carousel controls','previous'=>'Previous items','next'=>'Next items'],
        ];

        return $labels[$language][$key] ?? $labels['fa'][$key] ?? $key;
    }

    private function ssUrl(mixed $value): string
    {
        $url = trim($this->linkValue($value));
        if ($url === '' || str_starts_with($url, '#') || str_starts_with($url, '/') || preg_match('/^(https?:|mailto:|tel:)/i', $url) === 1
            || (preg_match('#^[a-zA-Z0-9][a-zA-Z0-9._/-]*$#', $url) === 1 && !str_contains($url, '..'))) {
            return $this->ssEscape($url === '' ? '#' : $url);
        }
        return '#';
    }

    /** @return list<array<string,mixed>> */
    private function ssItems(array $props, string $key = 'items'): array
    {
        return array_values(array_filter(is_array($props[$key] ?? null) ? $props[$key] : [], 'is_array'));
    }

    /** @return array<string,mixed>|null */
    private function ssBinding(string $id, string $source): ?array
    {
        $binding = $this->context['theme_bindings'][$id] ?? null;
        if (!is_array($binding) || ($binding['status'] ?? null) !== 'resolved' || ($binding['source'] ?? null) !== $source) {
            return null;
        }
        // The explicit manual policy keeps a complete page when an optional
        // module is disabled or its current query has no records.
        if (($binding['items'] ?? []) === [] && ($binding['empty'] ?? null) === 'manual') {
            return null;
        }
        return $binding;
    }

    /** @return list<array<string,mixed>> */
    private function ssDataItems(array $props, string $id, string $source): array
    {
        $binding = $this->ssBinding($id, $source);
        return $binding !== null ? array_values(array_filter($binding['items'] ?? [], 'is_array')) : $this->ssItems($props);
    }

    private function ssIcon(string $name): string
    {
        $paths = [
            'bed' => '<path d="M3 7v10m18 0V9a2 2 0 0 0-2-2H9v10M3 13h18M7 7V5h5a2 2 0 0 1 2 2"/>',
            'layers' => '<path d="m12 2 9 5-9 5-9-5 9-5Zm-9 10 9 5 9-5M3 17l9 5 9-5"/>',
            'blanket' => '<path d="M5 3h14a2 2 0 0 1 2 2v14H7a4 4 0 0 1-4-4V5a2 2 0 0 1 2-2Zm2 12h14"/>',
            'moon' => '<path d="M21 12.8A9 9 0 1 1 11.2 3 7 7 0 0 0 21 12.8Z"/>',
            'support' => '<path d="M4 14v-2a8 8 0 0 1 16 0v2M4 14h3v6H5a1 1 0 0 1-1-1v-5Zm16 0h-3v6h2a1 1 0 0 0 1-1v-5Z"/>',
            'badge' => '<path d="m12 2 3 3 4 .5.5 4 2.5 2.5-2.5 3-.5 4-4 .5-3 3-3-3-4-.5-.5-4L2 12l2.5-2.5.5-4 4-.5 3-3Z"/><path d="m9 12 2 2 4-4"/>',
            'card' => '<rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20"/>',
            'return' => '<path d="M3 12a9 9 0 1 0 3-6.7L3 8"/><path d="M3 3v5h5"/>',
            'search' => '<circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/>',
            'user' => '<circle cx="12" cy="8" r="4"/><path d="M4 22a8 8 0 0 1 16 0"/>',
            'cart' => '<path d="M3 3h2l2 12h11l2-8H6M9 21h.01M18 21h.01"/>',
            'pin' => '<path d="M20 10c0 5-8 12-8 12S4 15 4 10a8 8 0 1 1 16 0Z"/><circle cx="12" cy="10" r="2"/>',
            'arrow' => '<path d="M19 12H5m6-6-6 6 6 6"/>',
        ];
        $path = $paths[$name] ?? $paths['arrow'];
        return '<svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">'.$path.'</svg>';
    }

    private function ssImage(mixed $src, mixed $alt, string $class = ''): string
    {
        $src = $this->mediaSrc($src);
        if ($src === '') return '';
        return '<img'.($class !== '' ? ' class="'.$this->ssEscape($class).'"' : '').' src="'.$this->ssUrl($src).'" alt="'.$this->ssEscape($alt).'" loading="lazy">';
    }

    private function ssCarouselId(string $id): string
    {
        return 'ss-carousel-'.trim((string) preg_replace('/[^a-zA-Z0-9_-]+/', '-', $id), '-');
    }

    private function ssCarouselControls(string $trackId): string
    {
        return '<span class="ss-carousel-controls" role="group" aria-label="'.$this->ssEscape($this->ssLabel('carousel')).'"><button type="button" data-ss-scroll="#'.$this->ssEscape($trackId).'" data-direction="1" aria-label="'.$this->ssEscape($this->ssLabel('previous')).'">'.$this->ssIcon('arrow').'</button><button type="button" data-ss-scroll="#'.$this->ssEscape($trackId).'" data-direction="-1" aria-label="'.$this->ssEscape($this->ssLabel('next')).'">'.$this->ssIcon('arrow').'</button></span>';
    }

    private function ssSectionHead(array $props, ?string $trackId = null): string
    {
        $title = $this->ssEscape($props['title'] ?? '');
        $subtitle = $this->ssEscape($props['subtitle'] ?? '');
        $url = $this->ssUrl($props['view_all_url'] ?? '#');
        $label = $this->ssEscape($props['view_all_label'] ?? '');
        return '<header class="ss-section-head"><span><h2>'.$title.'</h2>'.($subtitle !== '' ? '<small>'.$subtitle.'</small>' : '').'</span><span class="ss-section-actions">'
            .($trackId !== null ? $this->ssCarouselControls($trackId) : '')
            .($label !== '' ? '<a href="'.$url.'">'.$label.$this->ssIcon('arrow').'</a>' : '').'</span></header>';
    }

    private function ssProductCard(array $item, int $index, bool $bound): string
    {
        $title = $this->ssEscape($item['title'] ?? '');
        $image = $bound ? ($item['image_url'] ?? '') : ($item['image'] ?? '');
        $alt = $bound ? $title : ($item['image_alt'] ?? $title);
        $url = $this->ssUrl($item['url'] ?? '#');
        $badge = $bound ? ($item['discount_percent'] ?? null) : ($item['badge'] ?? '');
        $badge = is_numeric($badge) ? '٪'.\SaaSPlatform\ThemeRuntime\Support\PersianNumber::digits((int) $badge).' تخفیف' : (string) $badge;
        $brand = $bound ? ($item['category'] ?? '') : ($item['brand'] ?? '');
        $price = $bound ? ($item['price_html'] ?? '') : ($item['price_text'] ?? '');
        $old = $bound ? ($item['regular_price_html'] ?? '') : ($item['old_price_text'] ?? '');
        $action = '<a class="ss-product-cta" href="'.$url.'">'.$this->ssEscape($item['cta_label'] ?? $this->ssLabel('view')).$this->ssIcon('arrow').'</a>';
        if ($bound && !($item['requires_variant'] ?? false) && isset($item['cart_url'], $item['id'])) {
            $disabled = ($item['in_stock'] ?? false) ? '' : ' disabled';
            $action = '<form method="POST" action="'.$this->ssUrl($item['cart_url']).'" class="ss-add-to-cart"><input type="hidden" name="_token" value="'.$this->ssEscape($item['csrf_token'] ?? '').'"><input type="hidden" name="product_id" value="'.(int) $item['id'].'"><input type="hidden" name="quantity" value="'.max(1, (int) ($item['min_order_qty'] ?? 1)).'"><button type="submit"'.$disabled.'>'.$this->ssEscape($this->ssLabel('add_to_cart')).'</button></form>';
        }
        return '<article class="ss-product" data-tb-logical-group="items" data-tb-logical-index="'.$index.'"><a class="ss-product-image" href="'.$url.'">'.$this->ssImage($image, $alt).($badge !== '' ? '<b>'.$this->ssEscape($badge).'</b>' : '').'</a><div class="ss-product-body"><a class="ss-product-title" href="'.$url.'">'.$title.'</a><small>'.$this->ssEscape($brand).'</small><div class="ss-specs"><span>'.$this->ssEscape($item['weight'] ?? $this->ssLabel('available')).'</span><span>'.$this->ssEscape($item['size'] ?? '').'</span></div><div class="ss-price"><span>★ '.$this->ssEscape($item['rating'] ?? '').'</span><strong>'.$this->ssEscape($price).'</strong>'.($old !== '' && $old !== $price ? '<del>'.$this->ssEscape($old).'</del>' : '').'</div>'.$action.'</div></article>';
    }

    private function ssProductSection(array $props, string $id, string $class = ''): string
    {
        $binding = $this->ssBinding($id, 'products');
        $items = $binding !== null ? array_values(array_filter($binding['items'] ?? [], 'is_array')) : $this->ssItems($props);
        if ($items === [] && $binding !== null && ($binding['empty'] ?? 'hide') === 'hide') return '';
        $cards = '';
        foreach ($items as $index => $item) $cards .= $this->ssProductCard($item, $index, $binding !== null);
        if ($cards === '') $cards = '<p class="ss-empty">'.$this->ssEscape($this->ssLabel('empty_products')).'</p>';
        $trackId = $this->ssCarouselId($id);
        return '<section class="ss-wrap ss-section '.$this->ssEscape($class).'">'.$this->ssSectionHead($props, $trackId).'<div id="'.$this->ssEscape($trackId).'" class="ss-product-grid ss-carousel-track" data-ss-carousel>'.$cards.'</div></section>';
    }

    protected function renderSleepshimanHeader(array $props, string $inner, string $id): string
    {
        return '<header class="ss-header ss-wrap"><a class="ss-brand" href="/" aria-label="'.$this->ssEscape($props['logo_alt'] ?? 'SleepShiman').'">'.$this->ssImage($props['logo'] ?? '', '', 'ss-logo').'<span><b>'.$this->ssEscape($props['brand'] ?? '').'</b><small>'.$this->ssEscape($props['tagline'] ?? '').'</small></span></a><form class="ss-search" action="'.$this->ssUrl($props['search_url'] ?? '/shop').'" method="GET"><button aria-label="جست‌وجو">'.$this->ssIcon('search').'</button><input name="q" placeholder="'.$this->ssEscape($props['search_placeholder'] ?? '').'"></form><nav class="ss-header-actions"><a href="'.$this->ssUrl($props['account_url'] ?? '/login').'">'.$this->ssIcon('user').'<span>'.$this->ssEscape($props['account_label'] ?? '').'</span></a><a href="'.$this->ssUrl($props['cart_url'] ?? '/cart').'">'.$this->ssIcon('cart').'<span>'.$this->ssEscape($props['cart_label'] ?? '').'</span></a></nav></header>';
    }

    protected function renderSleepshimanNavigation(array $props, string $inner, string $id): string
    {
        $items=''; foreach($this->ssItems($props) as $i=>$item){$items.='<a data-tb-logical-group="items" data-tb-logical-index="'.$i.'" href="'.$this->ssUrl($item['url']??'#').'">'.$this->ssEscape($item['label']??'').(!empty($item['badge'])?'<em>'.$this->ssEscape($item['badge']).'</em>':'').'</a>';}
        return '<div class="ss-nav ss-wrap"><span class="ss-location">'.$this->ssIcon('pin').'<b>'.$this->ssEscape($props['location_label']??'').'</b><small>'.$this->ssEscape($props['location_text']??'').'</small></span><nav>'.$items.'</nav></div>';
    }

    protected function renderSleepshimanQuickCategories(array $props, string $inner, string $id): string
    {
        $binding=$this->ssBinding($id,'product_categories'); $items=$binding!==null?array_values(array_filter($binding['items']??[],'is_array')):$this->ssItems($props); if($items===[] && $binding!==null && ($binding['empty']??'hide')==='hide') return ''; $html='';
        foreach($items as $i=>$item){$html.='<a class="ss-quick-card" data-tb-logical-group="items" data-tb-logical-index="'.$i.'" href="'.$this->ssUrl($item['url']??'#').'">'.$this->ssIcon($item['icon']??'bed').'<b>'.$this->ssEscape($item['title']??'').'</b><small>'.$this->ssEscape($item['subtitle']??(($item['product_count']??'').' محصول')).'</small><i>'.$this->ssIcon('arrow').'</i></a>';}
        return '<section class="ss-wrap ss-quick-grid">'.$html.'</section>';
    }

    protected function renderSleepshimanFeaturedProduct(array $props, string $inner, string $id): string
    {
        $manual=$this->ssItems($props)[0]??$props;
        $binding=$this->ssBinding($id,'products');
        if($binding!==null && ($binding['items']??[])===[]) return ($binding['empty']??'hide')==='empty_state' ? '<section class="ss-wrap ss-featured ss-featured-empty"><p>'.$this->ssEscape($this->ssLabel('empty_products')).'</p></section>' : '';
        $bound=$binding!==null && is_array($binding['items'][0]??null);
        $item=$bound?array_merge($manual,$binding['items'][0]):$manual;
        if(!is_array($item)) return '';
        $title=$this->ssEscape($item['title']??''); $image=$bound?($item['image_url']??$item['image']??''):($item['image']??''); $price=$bound?($item['price_html']??''):($item['price_text']??''); $old=$bound?($item['regular_price_html']??''):($item['old_price_text']??'');
        $secondSpec=trim((string)($item['material']??''))!=='' ? $this->ssLabel('material').': '.$this->ssEscape($item['material']) : $this->ssLabel('size').': '.$this->ssEscape($item['size']??'');
        return '<section class="ss-wrap ss-featured" data-ss-content-source="'.($bound?'dynamic':'manual').'"><div><b class="ss-badge">'.$this->ssEscape($item['badge']??(($item['discount_percent']??'')!==''?'٪'.$item['discount_percent']:'')).'</b><span class="ss-rating">★ '.$this->ssEscape($item['rating']??'').'</span><h1>'.$title.'</h1><p>'.$this->ssEscape($item['subtitle']??'').'</p><ul><li>'.$this->ssEscape($this->ssLabel('weight')).': '.$this->ssEscape($item['weight']??'').'</li><li>'.$secondSpec.'</li><li>'.$this->ssEscape($this->ssLabel('sales')).': '.$this->ssEscape($item['sales']??'').'</li><li>'.$this->ssEscape($this->ssLabel('satisfaction')).': '.$this->ssEscape($item['satisfaction']??'').'</li></ul><strong>'.$this->ssEscape($price).'</strong> <del>'.$this->ssEscape($old).'</del><a class="ss-primary" href="'.$this->ssUrl($item['url']??'#').'">'.$this->ssEscape($item['cta_label']??$this->ssLabel('view_product')).$this->ssIcon('arrow').'</a></div>'.$this->ssImage($image,$item['image_alt']??$title).'<span class="ss-feature-dots" aria-hidden="true"><i></i><i></i><i></i></span></section>';
    }

    protected function renderSleepshimanServices(array $props, string $inner, string $id): string
    {
        $html=''; foreach($this->ssItems($props) as $i=>$item){$html.='<article data-tb-logical-group="items" data-tb-logical-index="'.$i.'">'.$this->ssIcon($item['icon']??'support').'<span><b>'.$this->ssEscape($item['title']??'').'</b><small>'.$this->ssEscape($item['subtitle']??'').'</small></span></article>';}
        return '<section class="ss-wrap ss-services">'.$html.'</section>';
    }

    protected function renderSleepshimanAmazingOffers(array $props, string $inner, string $id): string
    {
        $binding=$this->ssBinding($id,'products'); $items=$binding!==null?array_values(array_filter($binding['items']??[],'is_array')):$this->ssItems($props); if($items===[] && $binding!==null && ($binding['empty']??'hide')==='hide') return ''; $cards=''; foreach($items as $i=>$item)$cards.=$this->ssProductCard($item,$i,$binding!==null);
        $trackId=$this->ssCarouselId($id);
        return '<section class="ss-amazing"><div class="ss-wrap"><header><h2>'.$this->ssEscape($props['title']??'').'</h2><p>'.$this->ssEscape($props['subtitle']??'').'</p>'.$this->ssImage($props['illustration']??'','تصویر خواب آرام').$this->ssCarouselControls($trackId).'</header><div id="'.$this->ssEscape($trackId).'" class="ss-product-grid ss-carousel-track" data-ss-carousel>'.$cards.'</div></div></section>';
    }

    protected function renderSleepshimanMattresses(array $props,string $inner,string $id): string { return $this->ssProductSection($props,$id,'ss-mattresses'); }
    protected function renderSleepshimanBeddingProducts(array $props,string $inner,string $id): string { return $this->ssMixedProductSection($props,$id,'ss-bedding',2); }
    protected function renderSleepshimanBlanketProducts(array $props,string $inner,string $id): string { return $this->ssMixedProductSection($props,$id,'ss-blankets',4); }
    protected function renderSleepshimanPublishedProducts(array $props,string $inner,string $id): string { return $this->ssProductSection($props,$id,'ss-published'); }

    private function ssMixedProductSection(array $props,string $id,string $class,int $cardCount): string
    {
        $binding=$this->ssBinding($id,'products');
        $items=$binding!==null?array_values(array_filter($binding['items']??[],'is_array')):$this->ssItems($props);
        if($items===[] && $binding!==null && ($binding['empty']??'hide')==='hide') return '';
        $cards=''; foreach(array_slice($items,0,$cardCount) as $i=>$item)$cards.=$this->ssProductCard($item,$i,$binding!==null);
        if($cards==='')$cards='<p class="ss-empty">'.$this->ssEscape($this->ssLabel('empty_products')).'</p>';
        $feature='<a class="ss-mixed-feature" href="'.$this->ssUrl($props['feature_url']??'/shop').'">'.$this->ssImage($props['feature_image']??'',$props['feature_image_alt']??$props['feature_title']??'').'<span><b>'.$this->ssEscape($props['feature_title']??'').'</b><small>'.$this->ssEscape($props['feature_subtitle']??'').'</small></span></a>';
        return '<section class="ss-wrap ss-section '.$this->ssEscape($class).'">'.$this->ssSectionHead($props).'<div class="ss-mixed-products"><div class="ss-mixed-grid">'.$cards.'</div>'.$feature.'</div></section>';
    }

    protected function renderSleepshimanPromoBanners(array $props,string $inner,string $id): string
    {
        $html=''; foreach($this->ssItems($props) as $i=>$item){$html.='<a class="ss-promo '.$this->ssEscape($item['tone']??'blue').'" data-tb-logical-group="items" data-tb-logical-index="'.$i.'" href="'.$this->ssUrl($item['url']??'#').'">'.$this->ssImage($item['image']??'',$item['image_alt']??'').'<span><b>'.$this->ssEscape($item['title']??'').'</b><small>'.$this->ssEscape($item['subtitle']??'').'</small></span></a>';}
        return '<section class="ss-wrap ss-promos">'.$html.'</section>';
    }

    protected function renderSleepshimanSleepBanner(array $props,string $inner,string $id): string
    { return '<a class="ss-wrap ss-sleep-banner" href="'.$this->ssUrl($props['url']??'#').'">'.$this->ssImage($props['image']??'',$props['image_alt']??'').'<span><b>'.$this->ssEscape($props['title']??'').'</b><small>'.$this->ssEscape($props['subtitle']??'').'</small></span>'.$this->ssIcon('arrow').'</a>'; }

    protected function renderSleepshimanBrands(array $props,string $inner,string $id): string
    { $html=''; foreach($this->ssItems($props) as $i=>$item)$html.='<a data-tb-logical-group="items" data-tb-logical-index="'.$i.'" href="'.$this->ssUrl($item['url']??'#').'">'.$this->ssImage($item['image']??'',$item['image_alt']??'').'</a>'; return '<section class="ss-wrap ss-brands">'.$html.'</section>'; }

    protected function renderSleepshimanTopicCards(array $props,string $inner,string $id): string
    { $html=''; foreach($this->ssItems($props) as $i=>$item)$html.='<a data-tb-logical-group="items" data-tb-logical-index="'.$i.'" href="'.$this->ssUrl($item['url']??'#').'">'.$this->ssImage($item['image']??'',$item['image_alt']??'').'<span><b>'.$this->ssEscape($item['title']??'').'</b><small>'.$this->ssEscape($item['description']??'').'</small></span></a>'; return '<section class="ss-wrap ss-section">'.$this->ssSectionHead($props).'<div class="ss-topics">'.$html.'</div></section>'; }

    protected function renderSleepshimanArticles(array $props,string $inner,string $id): string
    { $binding=$this->ssBinding($id,'blog_posts'); $items=$binding!==null?array_values(array_filter($binding['items']??[],'is_array')):$this->ssItems($props); if($items===[] && $binding!==null && ($binding['empty']??'hide')==='hide') return ''; $html=''; foreach(array_slice($items,0,4) as $i=>$item){$image=$binding!==null?($item['image_url']??''):($item['image']??'');$html.='<article data-tb-logical-group="items" data-tb-logical-index="'.$i.'"><a href="'.$this->ssUrl($item['url']??'#').'">'.$this->ssImage($image,$item['image_alt']??$item['title']??'').'</a><span><small>'.$this->ssEscape($item['date_text']??'').'</small><h3>'.$this->ssEscape($item['title']??'').'</h3><p>'.$this->ssEscape($item['excerpt']??'').'</p></span></article>'; } $feature='<a class="ss-article-feature" href="'.$this->ssUrl($props['feature_url']??'/blog').'">'.$this->ssImage($props['feature_image']??'',$props['feature_image_alt']??$props['feature_title']??'').'<span><small>'.$this->ssEscape($props['feature_kicker']??'').'</small><b>'.$this->ssEscape($props['feature_title']??'').'</b><i aria-hidden="true">● ● ●</i></span></a>'; return '<section class="ss-wrap ss-section ss-article-section">'.$this->ssSectionHead($props).'<div class="ss-article-layout"><div class="ss-articles">'.$html.'</div>'.$feature.'</div></section>'; }

    protected function renderSleepshimanEditorial(array $props,string $inner,string $id): string
    { return '<section class="ss-wrap ss-editorial"><div><p>'.$this->ssEscape($props['body']??'').'</p><a class="ss-primary" href="'.$this->ssUrl($props['cta_url']??'#').'">'.$this->ssEscape($props['cta_label']??'').$this->ssIcon('arrow').'</a></div>'.$this->ssImage($props['image']??'',$props['image_alt']??'').'</section>'; }

    protected function renderSleepshimanReviews(array $props,string $inner,string $id): string
    {
        $binding=$this->ssBinding($id,'reviews'); $items=$binding!==null?array_values(array_filter($binding['items']??[],'is_array')):$this->ssItems($props); if($items===[] && $binding!==null && ($binding['empty']??'hide')==='hide') return ''; $html='';
        foreach($items as $i=>$item){$avatar=$binding!==null?($item['avatar_url']??''):($item['avatar']??'');$html.='<article data-tb-logical-group="items" data-tb-logical-index="'.$i.'"><header>'.$this->ssImage($avatar,$item['avatar_alt']??$item['author']??'').'<span><b>'.$this->ssEscape($item['author']??'').'</b><small>'.$this->ssEscape($item['role']??'').'</small></span><strong>★ '.$this->ssEscape($item['rating']??'').'</strong></header><p>'.$this->ssEscape($item['body']??'').'</p><footer class="ss-review-votes"><span>'.$this->ssEscape($this->ssLabel('helpful')).' · '.$this->ssEscape($item['helpful_count']??'۰').'</span><span>'.$this->ssEscape($this->ssLabel('not_helpful')).' · '.$this->ssEscape($item['not_helpful_count']??'۰').'</span></footer></article>'; }
        $trackId=$this->ssCarouselId($id);
        return '<section class="ss-wrap ss-section">'.$this->ssSectionHead($props,$trackId).'<div id="'.$this->ssEscape($trackId).'" class="ss-reviews ss-carousel-track" data-ss-carousel>'.$html.'</div></section>';
    }

    protected function renderSleepshimanGuideIntro(array $props,string $inner,string $id): string
    { return '<section class="ss-wrap ss-guide-intro"><span aria-hidden="true">'.$this->ssIcon('moon').'</span><h2>'.$this->ssEscape($props['title']??'').'</h2><p>'.$this->ssEscape($props['subtitle']??'').'</p></section>'; }

    protected function renderSleepshimanGuideCards(array $props,string $inner,string $id): string
    { $html='';foreach($this->ssItems($props) as $i=>$item)$html.='<a data-tb-logical-group="items" data-tb-logical-index="'.$i.'" href="'.$this->ssUrl($item['url']??'#').'">'.$this->ssImage($item['image']??'',$item['image_alt']??'').'<b>'.$this->ssEscape($item['title']??'').'</b></a>';return '<section class="ss-wrap ss-guides">'.$html.'</section>'; }

    protected function renderSleepshimanProductFilters(array $props,string $inner,string $id): string
    { $groups='';foreach($this->ssItems($props,'groups') as $i=>$group){$options='';foreach(array_values(array_filter($group['options']??[],'is_array')) as $j=>$option)$options.='<a data-tb-logical-group="groups.'.$i.'.options" data-tb-logical-index="'.$j.'" href="'.$this->ssUrl($option['url']??'#').'">'.$this->ssEscape($option['label']??'').'</a>';$groups.='<div data-tb-logical-group="groups" data-tb-logical-index="'.$i.'"><b>'.$this->ssEscape($group['title']??'').'</b><div>'.$options.'</div></div>'; }return '<section class="ss-wrap ss-section ss-filters">'.$this->ssSectionHead($props).'<div class="ss-filter-groups">'.$groups.'</div></section>'; }

    protected function renderSleepshimanStats(array $props,string $inner,string $id): string
    { $html='';foreach($this->ssItems($props) as $i=>$item)$html.='<article data-tb-logical-group="items" data-tb-logical-index="'.$i.'"><b>'.$this->ssEscape($item['value']??'').'</b><small>'.$this->ssEscape($item['label']??'').'</small></article>';return '<section class="ss-wrap ss-stats">'.$html.'</section>'; }

    protected function renderSleepshimanFooter(array $props,string $inner,string $id): string
    { $columns='';foreach(array_values(array_filter($props['columns']??[],'is_array')) as $i=>$column){$links='';foreach(array_values(array_filter($column['links']??[],'is_array')) as $j=>$link)$links.='<a data-tb-logical-group="columns.'.$i.'.links" data-tb-logical-index="'.$j.'" href="'.$this->ssUrl($link['url']??'#').'">'.$this->ssEscape($link['label']??'').'</a>';$columns.='<nav data-tb-logical-group="columns" data-tb-logical-index="'.$i.'"><b>'.$this->ssEscape($column['heading']??'').'</b>'.$links.'</nav>'; }$trust='';foreach(array_values(array_filter($props['trust_marks']??[],'is_array')) as $i=>$mark){$trust.='<a data-tb-logical-group="trust_marks" data-tb-logical-index="'.$i.'" href="'.$this->ssUrl($mark['url']??'#').'">'.$this->ssImage($mark['image']??'',$mark['image_alt']??$mark['label']??'').'<span>'.$this->ssEscape($mark['label']??'').'</span></a>';}return '<footer class="ss-footer ss-wrap"><div class="ss-footer-main"><div class="ss-trust-marks">'.$trust.'</div><div class="ss-footer-links">'.$columns.'</div><section class="ss-footer-copy"><h2>'.$this->ssEscape($props['brand']??'').'</h2><p>'.$this->ssEscape($props['description']??'').'</p><address>'.$this->ssEscape($props['address']??'').' · <a href="'.$this->ssUrl($props['phone_url']??'#').'">'.$this->ssEscape($props['phone']??'').'</a></address><div class="ss-newsletter"><b>'.$this->ssEscape($props['newsletter_heading']??'').'</b><small>'.$this->ssEscape($props['newsletter_placeholder']??'').'</small><a href="'.$this->ssUrl($props['newsletter_url']??'/contact').'">'.$this->ssEscape($props['newsletter_button']??'').'</a></div></section></div><small class="ss-copyright">'.$this->ssEscape($props['copyright']??'').'</small></footer>'; }
}
