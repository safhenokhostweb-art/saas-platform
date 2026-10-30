<?php

namespace SaaSPlatform\ThemeRuntime;

/**
 * FoodMart block rendering — THE canonical, single-source implementation
 * (Phase 3). The 19 renderFoodmart*() methods below are the byte-exact
 * Golden Reference markup extracted during Phase 2A and rendered through
 * every FoodMart phase since (2A prototype, 2B collision analysis, 2C
 * capabilities, 2D real-shell acceptance). They previously lived in
 * saas-platform's App\Services\ThemeBuilder\FoodMart\FoodMartBlockRenderer;
 * they moved here verbatim (no markup edited) so BOTH hosts consume one
 * implementation:
 *   - the shared base BlockRenderer below consumes this trait, so
 *     saas-platform's Builder preview AND customer-site's tenant runtime
 *     (which extends the same base) can render foodmart_* types;
 *   - saas-platform's old FoodMartBlockRenderer class remains as a thin
 *     subclass of App\Services\ThemeBuilder\BlockRenderer with NO markup
 *     of its own (compatibility shim for Phase 2A/2B/2D test support).
 *
 * Registry: the foodmart_* type definitions are registered per-host via
 * BlockRegistry::registerHostType() (see FoodMartBlockTypes::register()),
 * the same generic extension seam used in Phase 2A — no slug detection
 * anywhere, the block type itself is the dispatch identity.
 *
 * This trait calls render/escape helper methods inherited from
 * BlockRenderer; it can only be used inside a BlockRenderer hierarchy.
 */
trait FoodMartBlockRendererTrait
{
    /** @return array<string,mixed>|null */
    private function foodmartResolvedBinding(string $id, string $source): ?array
    {
        $bindings = $this->context['theme_bindings'] ?? [];
        if (!array_key_exists($id, $bindings) || !is_array($bindings[$id])) {
            return null;
        }

        $binding = $bindings[$id];
        if (($binding['source'] ?? null) !== $source) {
            return ['source' => $source, 'status' => 'invalid', 'items' => [], 'empty' => 'hide'];
        }

        return $binding;
    }

    /**
     * Phase 4B.6 — manual category cards through the SAME category
     * presentation. Item cards carry logical-selection metadata so Canvas
     * can open the exact item's inspector fieldset.
     *
     * @param list<array<string,mixed>> $items
     */
    private function renderFoodmartManualCategories(array $items, ?string $sectionTitle = null, string $viewAllLabel = '', string $viewAllUrl = ''): string
    {
        $cards = '';
        foreach ($items as $index => $item) {
            $url = $this->foodmartEditableHrefValue((string) ($item['url'] ?? 'index.html'));
            $title = $this->foodmartEscape((string) ($item['title'] ?? ''));
            $image = $this->foodmartEditableSrc((string) ($item['image'] ?? ''));
            $imageAlt = $this->foodmartEscape((string) ($item['image_alt'] ?? 'Category Thumbnail'));
            $imageHtml = $image !== '' ? '<img src="'.$image.'" alt="'.$imageAlt.'">' : '';
            $cards .= '<a href="'.$url.'" class="nav-link category-item swiper-slide" data-tb-logical-group="items" data-tb-logical-index="'.(int) $index.'">'
                .$imageHtml.'<h3 class="category-title">'.$title.'</h3></a>';
        }
        if ($cards === '') {
            $cards = '<p class="foodmart-empty-state">No categories available.</p>';
        }

        $header = '<h2 class="section-title">'.$this->foodmartEscape($sectionTitle ?? 'Category').'</h2>';
        $viewAll = $viewAllLabel !== ''
            ? '<a href="'.$this->foodmartEditableHrefValue($viewAllUrl).'" class="btn-link text-decoration-none">'.$this->foodmartEscape($viewAllLabel).' →</a>'
            : '<a href="#" class="btn-link text-decoration-none">View All Categories →</a>';
        $markup = '<section class="py-5 overflow-hidden"><div class="container-fluid"><div class="row"><div class="col-md-12">'
            .'<div class="section-header d-flex flex-wrap justify-content-between mb-5">'.$header
            .'<div class="d-flex align-items-center">'.$viewAll
            .'<div class="swiper-buttons"><button class="swiper-prev category-carousel-prev btn btn-yellow">❮</button><button class="swiper-next category-carousel-next btn btn-yellow">❯</button></div></div></div>'
            .'</div></div><div class="row"><div class="col-md-12"><div class="category-carousel swiper"><div class="swiper-wrapper">'.$cards.'</div></div></div></div></div></section>';

        return $this->decorateAccessibility($markup);
    }

    /** @param array<string,mixed> $binding */
    private function renderFoodmartBoundCategories(array $binding, ?string $sectionTitle = null): string
    {
        $items = is_array($binding['items'] ?? null) ? $binding['items'] : [];
        if ($items === [] && ($binding['empty'] ?? 'hide') === 'hide') {
            return '';
        }

        $cards = '';
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            $url = $this->foodmartEscape((string) ($item['url'] ?? '#'));
            $title = $this->foodmartEscape((string) ($item['title'] ?? ''));
            $image = $this->foodmartEscape((string) ($item['image_url'] ?? ''));
            $count = (int) ($item['product_count'] ?? 0);
            $imageHtml = $image !== '' ? '<img src="'.$image.'" alt="'.$title.'">' : '';
            $cards .= '<a href="'.$url.'" class="nav-link category-item swiper-slide">'.$imageHtml
                .'<h3 class="category-title">'.$title.'</h3><span class="category-count">'.$count.'</span></a>';
        }

        $empty = $cards === '' ? '<p class="foodmart-empty-state">No categories available.</p>' : $cards;
        $markup = '<section class="py-5 overflow-hidden"><div class="container-fluid"><div class="row"><div class="col-md-12">'
            .'<div class="section-header d-flex flex-wrap justify-content-between mb-5"><h2 class="section-title">'.$this->foodmartEscape($sectionTitle ?? 'Category').'</h2>'
            .'<div class="d-flex align-items-center"><a href="'. $this->foodmartEscape((string) ($binding['collection_url'] ?? '#')) .'" class="btn-link text-decoration-none">View All Categories →</a>'
            .'<div class="swiper-buttons"><button class="swiper-prev category-carousel-prev btn btn-yellow">❮</button><button class="swiper-next category-carousel-next btn btn-yellow">❯</button></div></div></div>'
            .'</div></div><div class="row"><div class="col-md-12"><div class="category-carousel swiper"><div class="swiper-wrapper">'.$empty.'</div></div></div></div></div></section>';

        return $this->decorateAccessibility($markup);
    }

    /** @param array<string,mixed> $binding */
    private function renderFoodmartBoundProducts(array $binding, string $title, bool $carousel, ?string $sectionTitle = null): string
    {
        $items = is_array($binding['items'] ?? null) ? $binding['items'] : [];
        if ($items === [] && ($binding['empty'] ?? 'hide') === 'hide') {
            return '';
        }

        $cards = '';
        foreach ($items as $item) {
            if (is_array($item)) {
                $card = $this->renderFoodmartParityProductCard($item);
                $cards .= $carousel ? $card : '<div class="col">'.$card.'</div>';
            }
        }
        if ($cards === '') {
            $cards = '<p class="foodmart-empty-state">No products available.</p>';
        }

        $escapedTitle = $this->foodmartEscape($sectionTitle ?? $title);
        if (!$carousel) {
            $markup = '<section class="py-5"><div class="container-fluid"><div class="row"><div class="col-md-12"><div class="bootstrap-tabs product-tabs">'
                .'<div class="tabs-header d-flex justify-content-between border-bottom my-5"><h3>'.$escapedTitle.'</h3></div>'
                .'<div class="tab-content"><div class="tab-pane fade show active" role="tabpanel"><div class="product-grid row row-cols-1 row-cols-sm-2 row-cols-md-3 row-cols-lg-4 row-cols-xl-5">'
                .$cards.'</div></div></div></div></div></div></div></section>';
        } else {
            $markup = '<section class="py-5 overflow-hidden"><div class="container-fluid"><div class="row"><div class="col-md-12">'
                .'<div class="section-header d-flex flex-wrap justify-content-between my-5"><h2 class="section-title">'.$escapedTitle.'</h2>'
                .'<div class="d-flex align-items-center"><a href="'.$this->foodmartEscape((string) ($binding['collection_url'] ?? '#')).'" class="btn-link text-decoration-none">View All Products →</a>'
                .'<div class="swiper-buttons"><button class="swiper-prev products-carousel-prev btn btn-primary">❮</button><button class="swiper-next products-carousel-next btn btn-primary">❯</button></div></div></div>'
                .'<div class="products-carousel swiper"><div class="swiper-wrapper">'.$cards.'</div></div></div></div></div></section>';
        }

        return $this->decorateAccessibility($markup);
    }

    /** @param array<string,mixed> $item */
    private function renderFoodmartBoundProductCard(array $item): string
    {
        $title = $this->foodmartEscape((string) ($item['title'] ?? ''));
        $url = $this->foodmartEscape((string) ($item['url'] ?? '#'));
        $image = $this->foodmartEscape((string) ($item['image_url'] ?? ''));
        $imageHtml = $image !== '' ? '<img src="'.$image.'" class="tab-image" alt="'.$title.'">' : '';
        $badge = isset($item['discount_percent']) && (int) $item['discount_percent'] > 0
            ? '<span class="badge bg-success position-absolute m-3">-'.(int) $item['discount_percent'].'%</span>'
            : '';
        $category = $this->foodmartEscape((string) ($item['category'] ?? ''));
        $stock = ($item['in_stock'] ?? false) ? 'In stock' : 'Out of stock';
        $price = (string) ($item['price_html'] ?? '');
        $regular = (string) ($item['regular_price_html'] ?? '');
        $priceHtml = $regular !== '' && $regular !== $price
            ? '<span class="price"><del>'.$regular.'</del> '.$price.'</span>'
            : '<span class="price">'.$price.'</span>';
        $disabled = ($item['in_stock'] ?? false) ? '' : ' disabled';
        $csrf = $this->foodmartEscape((string) ($item['csrf_token'] ?? ''));
        $cartUrl = $this->foodmartEscape((string) ($item['cart_url'] ?? '#'));
        $wishlistUrl = $this->foodmartEscape((string) ($item['wishlist_url'] ?? '#'));
        $productId = (int) ($item['id'] ?? 0);

        $min = max(1, (int) ($item['min_order_qty'] ?? 1));
        $max = isset($item['max_order_qty']) ? ' max="'.(int) $item['max_order_qty'].'"' : '';
        $action = ($item['requires_variant'] ?? false)
            ? '<a href="'.$url.'" class="nav-link">Choose options</a>'
            : '<form method="POST" action="'.$cartUrl.'" class="d-flex align-items-center justify-content-between foodmart-add-to-cart">'
                .'<input type="hidden" name="_token" value="'.$csrf.'"><input type="hidden" name="product_id" value="'.$productId.'">'
                .'<div class="input-group product-qty"><input type="number" name="quantity" class="form-control input-number" value="'.$min.'" min="'.$min.'"'.$max.'></div>'
                .'<button type="submit" class="nav-link btn btn-link"'.$disabled.'>Add to Cart <iconify-icon icon="uil:shopping-cart"></iconify-icon></button></form>';

        return '<div class="product-item swiper-slide">'.$badge
            .'<form method="POST" action="'.$wishlistUrl.'" class="foodmart-wishlist-form"><input type="hidden" name="_token" value="'.$csrf.'"><button type="submit" class="btn-wishlist" aria-label="Wishlist"><svg width="24" height="24"><use xlink:href="#heart"></use></svg></button></form>'
            .'<figure><a href="'.$url.'" title="'.$title.'">'.$imageHtml.'</a></figure><h3>'.$title.'</h3>'
            .'<span class="qty">'.($category !== '' ? $category : $stock).'</span>'.$priceHtml.$action.'</div>';
    }

    /**
     * Phase 4B.6 — presentation-parity bound card. Dynamic product data is
     * normalized into the SAME Golden card anatomy the static markup uses
     * (badge, wishlist, figure, title, qty, rating, price, quantity stepper
     * + Add-to-Cart row). Structural wrappers/classes NEVER depend on which
     * fields a domain record carries — missing optional values render safe
     * fallbacks instead of collapsing the section.
     *
     * @param array<string,mixed> $item semantic contract from the binding
     */
    private function renderFoodmartParityProductCard(array $item): string
    {
        $title = $this->foodmartEscape((string) ($item['title'] ?? ''));
        $url = $this->foodmartEscape((string) ($item['url'] ?? '#'));
        $image = $this->foodmartEscape((string) ($item['image_url'] ?? ''));
        $imageAlt = $this->foodmartEscape((string) ($item['image_alt'] ?? ($item['title'] ?? 'Product')));
        $imageHtml = $image !== '' ? '<img src="'.$image.'" class="tab-image" alt="'.$imageAlt.'">' : '';
        $badge = isset($item['discount_percent']) && (int) $item['discount_percent'] > 0
            ? '<span class="badge bg-success position-absolute m-3">-'.(int) $item['discount_percent'].'%</span>'
            : '';
        $category = $this->foodmartEscape((string) ($item['category'] ?? ''));
        $stock = ($item['in_stock'] ?? false) ? 'In stock' : 'Out of stock';
        $rating = (float) ($item['rating'] ?? 0);
        $ratingHtml = $rating > 0
            ? '<span class="rating"><svg width="24" height="24" class="text-primary"><use xlink:href="#star-solid"></use></svg> '.number_format($rating, 1).'</span>'
            : '';
        $price = (string) ($item['price_html'] ?? '');
        $regular = (string) ($item['regular_price_html'] ?? '');
        $priceHtml = $regular !== '' && $regular !== $price
            ? '<span class="price"><del>'.$regular.'</del> '.$price.'</span>'
            : '<span class="price">'.$price.'</span>';
        $disabled = ($item['in_stock'] ?? false) ? '' : ' disabled';
        $csrf = $this->foodmartEscape((string) ($item['csrf_token'] ?? ''));
        $cartUrl = $this->foodmartEscape((string) ($item['cart_url'] ?? '#'));
        $wishlistUrl = $this->foodmartEscape((string) ($item['wishlist_url'] ?? '#'));
        $productId = (int) ($item['id'] ?? 0);

        $min = max(1, (int) ($item['min_order_qty'] ?? 1));
        $max = isset($item['max_order_qty']) ? ' max="'.(int) $item['max_order_qty'].'"' : '';
        $action = ($item['requires_variant'] ?? false)
            ? '<a href="'.$url.'" class="nav-link">Choose options</a>'
            : '<form method="POST" action="'.$cartUrl.'" class="d-flex align-items-center justify-content-between foodmart-add-to-cart">'
                .'<input type="hidden" name="_token" value="'.$csrf.'"><input type="hidden" name="product_id" value="'.$productId.'">'
                .'<div class="input-group product-qty"><input type="number" name="quantity" class="form-control input-number" value="'.$min.'" min="'.$min.'"'.$max.'></div>'
                .'<button type="submit" class="nav-link btn btn-link"'.$disabled.'>Add to Cart <iconify-icon icon="uil:shopping-cart"></iconify-icon></button></form>';

        return '<div class="product-item swiper-slide">'.$badge
            .'<form method="POST" action="'.$wishlistUrl.'" class="foodmart-wishlist-form"><input type="hidden" name="_token" value="'.$csrf.'"><button type="submit" class="btn-wishlist" aria-label="Wishlist"><svg width="24" height="24"><use xlink:href="#heart"></use></svg></button></form>'
            .'<figure><a href="'.$url.'" title="'.$title.'">'.$imageHtml.'</a></figure><h3>'.$title.'</h3>'
            .'<span class="qty">'.($category !== '' ? $category : $stock).'</span>'.$ratingHtml.$priceHtml.$action.'</div>';
    }

    /**
     * Phase 4B.6 — MANUAL product-style card. Presentation content only:
     * NO product id, no cart POST action, no fabricated domain identity.
     * Emits the same Golden card anatomy with a safe configurable CTA in
     * place of the cart form.
     *
     * @param array<string,mixed> $item
     */
    private function renderFoodmartManualProductCard(array $item, ?int $logicalIndex = null): string
    {
        $title = $this->foodmartEscape((string) ($item['title'] ?? ''));
        $url = $this->foodmartEditableHrefValue((string) ($item['url'] ?? '#'));
        $image = $this->foodmartEditableSrc((string) ($item['image'] ?? ''));
        $imageAlt = $this->foodmartEscape((string) ($item['image_alt'] ?? 'Product'));
        $imageHtml = $image !== '' ? '<img src="'.$image.'" class="tab-image" alt="'.$imageAlt.'">' : '';
        $badge = trim((string) ($item['badge'] ?? '')) !== ''
            ? '<span class="badge bg-success position-absolute m-3">'.$this->foodmartEscape((string) $item['badge']).'</span>'
            : '';
        $unit = $this->foodmartEscape((string) ($item['unit_label'] ?? ''));
        $price = $this->foodmartEscape((string) ($item['price_text'] ?? ''));
        $oldPrice = trim((string) ($item['old_price_text'] ?? ''));
        $priceHtml = $oldPrice !== ''
            ? '<span class="price"><del>'.$this->foodmartEscape($oldPrice).'</del> '.$price.'</span>'
            : '<span class="price">'.$price.'</span>';
        $cta = $this->foodmartEscape((string) ($item['cta_label'] ?? ''));
        $ctaHtml = $cta !== '' ? '<a href="'.$url.'" class="nav-link">'.$cta.' <iconify-icon icon="uil:shopping-cart"></iconify-icon></a>' : '';
        $logical = $logicalIndex !== null
            ? ' data-tb-logical-group="items" data-tb-logical-index="'.(int) $logicalIndex.'"'
            : '';

        return '<div class="product-item swiper-slide"'.$logical.'>'.$badge
            .'<a href="'.$url.'" class="btn-wishlist" aria-label="Wishlist"><svg width="24" height="24"><use xlink:href="#heart"></use></svg></a>'
            .'<figure><a href="'.$url.'" title="'.$title.'">'.$imageHtml.'</a></figure><h3>'.$title.'</h3>'
            .($unit !== '' ? '<span class="qty">'.$unit.'</span>' : '').$priceHtml
            .'<div class="d-flex align-items-center justify-content-between">'.$ctaHtml.'</div></div>';
    }

    /**
     * Phase 4B.6 — manual/dynamic source contract. Manual items are
     * ACTIVE only when explicitly non-empty; the prop never silently
     * overrides a resolved binding (binding wins, mirroring Phase 4B).
     *
     * @param array<string,mixed> $props
     * @return list<array<string,mixed>>|null
     */
    private function foodmartManualItems(array $props, string $key): ?array
    {
        $value = $props[$key] ?? null;
        if (!is_array($value) || $value === []) {
            return null;
        }

        return array_values(array_filter($value, 'is_array'));
    }

    /**
     * Manual-mode product section: SAME Golden section/chrome wrappers as
     * the static fallback and the bound path — only the card renderer and
     * data source differ. @see renderFoodmartBoundProducts for the wrapper
     * contract (kept byte-identical here).
     *
     * @param list<array<string,mixed>> $items
     */
    private function renderFoodmartManualProducts(array $items, string $title, bool $carousel, ?string $sectionTitle = null): string
    {
        $cards = '';
        foreach ($items as $index => $item) {
            $card = $this->renderFoodmartManualProductCard($item, $index);
            $cards .= $carousel ? $card : '<div class="col">'.$card.'</div>';
        }
        if ($cards === '') {
            $cards = '<p class="foodmart-empty-state">No products available.</p>';
        }

        $escapedTitle = $this->foodmartEscape($sectionTitle ?? $title);
        if (!$carousel) {
            $markup = '<section class="py-5"><div class="container-fluid"><div class="row"><div class="col-md-12"><div class="bootstrap-tabs product-tabs">'
                .'<div class="tabs-header d-flex justify-content-between border-bottom my-5"><h3>'.$escapedTitle.'</h3></div>'
                .'<div class="tab-content"><div class="tab-pane fade show active" role="tabpanel"><div class="product-grid row row-cols-1 row-cols-sm-2 row-cols-md-3 row-cols-lg-4 row-cols-xl-5">'
                .$cards.'</div></div></div></div></div></div></div></section>';
        } else {
            $markup = '<section class="py-5 overflow-hidden"><div class="container-fluid"><div class="row"><div class="col-md-12">'
                .'<div class="section-header d-flex flex-wrap justify-content-between my-5"><h2 class="section-title">'.$escapedTitle.'</h2>'
                .'<div class="d-flex align-items-center"><a href="#" class="btn-link text-decoration-none">View All Products →</a>'
                .'<div class="swiper-buttons"><button class="swiper-prev products-carousel-prev btn btn-primary">❮</button><button class="swiper-next products-carousel-next btn btn-primary">❯</button></div></div></div>'
                .'<div class="products-carousel swiper"><div class="swiper-wrapper">'.$cards.'</div></div></div></div></div></section>'
                ."\n";
        }

        return $this->decorateAccessibility($markup);
    }

    /** Safe href value (no markup), used by manual-card renderers. */
    private function foodmartEditableHrefValue(string $url): string
    {
        $url = trim($url);
        if ($url === '' || preg_match('/[\"\'<>\\s]/', $url) === 1) {
            return '#';
        }
        if (preg_match('/\Ahttps?:\/\//i', $url) !== 1 && !str_starts_with($url, '/')) {
            return '#';
        }

        return $this->foodmartEscape($url);
    }

    private function foodmartEscape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /**
     * Byte-exact Golden Reference markup (Phase 0/2A) — see
     * _reference-themes/FoodMart-main/FoodMart-main/index.html. Extracted
     * once during Phase 2A authoring; never re-read at runtime. Zero prop
     * usage by design (Phase 2A is static-only, see Step 8).
     */
    protected function renderFoodmartSvgSprite(array $props, string $inner, string $id): string
    {
        $markup = <<<'__FOODMART_RAW_EOF__'
<svg xmlns="http://www.w3.org/2000/svg" style="display: none;">
      <defs>
        <symbol xmlns="http://www.w3.org/2000/svg" id="link" viewBox="0 0 24 24">
          <path fill="currentColor" d="M12 19a1 1 0 1 0-1-1a1 1 0 0 0 1 1Zm5 0a1 1 0 1 0-1-1a1 1 0 0 0 1 1Zm0-4a1 1 0 1 0-1-1a1 1 0 0 0 1 1Zm-5 0a1 1 0 1 0-1-1a1 1 0 0 0 1 1Zm7-12h-1V2a1 1 0 0 0-2 0v1H8V2a1 1 0 0 0-2 0v1H5a3 3 0 0 0-3 3v14a3 3 0 0 0 3 3h14a3 3 0 0 0 3-3V6a3 3 0 0 0-3-3Zm1 17a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1v-9h16Zm0-11H4V6a1 1 0 0 1 1-1h1v1a1 1 0 0 0 2 0V5h8v1a1 1 0 0 0 2 0V5h1a1 1 0 0 1 1 1ZM7 15a1 1 0 1 0-1-1a1 1 0 0 0 1 1Zm0 4a1 1 0 1 0-1-1a1 1 0 0 0 1 1Z"/>
        </symbol>
        <symbol xmlns="http://www.w3.org/2000/svg" id="arrow-right" viewBox="0 0 24 24">
          <path fill="currentColor" d="M17.92 11.62a1 1 0 0 0-.21-.33l-5-5a1 1 0 0 0-1.42 1.42l3.3 3.29H7a1 1 0 0 0 0 2h7.59l-3.3 3.29a1 1 0 0 0 0 1.42a1 1 0 0 0 1.42 0l5-5a1 1 0 0 0 .21-.33a1 1 0 0 0 0-.76Z"/>
        </symbol>
        <symbol xmlns="http://www.w3.org/2000/svg" id="category" viewBox="0 0 24 24">
          <path fill="currentColor" d="M19 5.5h-6.28l-.32-1a3 3 0 0 0-2.84-2H5a3 3 0 0 0-3 3v13a3 3 0 0 0 3 3h14a3 3 0 0 0 3-3v-10a3 3 0 0 0-3-3Zm1 13a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1v-13a1 1 0 0 1 1-1h4.56a1 1 0 0 1 .95.68l.54 1.64a1 1 0 0 0 .95.68h7a1 1 0 0 1 1 1Z"/>
        </symbol>
        <symbol xmlns="http://www.w3.org/2000/svg" id="calendar" viewBox="0 0 24 24">
          <path fill="currentColor" d="M19 4h-2V3a1 1 0 0 0-2 0v1H9V3a1 1 0 0 0-2 0v1H5a3 3 0 0 0-3 3v12a3 3 0 0 0 3 3h14a3 3 0 0 0 3-3V7a3 3 0 0 0-3-3Zm1 15a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1v-7h16Zm0-9H4V7a1 1 0 0 1 1-1h2v1a1 1 0 0 0 2 0V6h6v1a1 1 0 0 0 2 0V6h2a1 1 0 0 1 1 1Z"/>
        </symbol>
        <symbol xmlns="http://www.w3.org/2000/svg" id="heart" viewBox="0 0 24 24">
          <path fill="currentColor" d="M20.16 4.61A6.27 6.27 0 0 0 12 4a6.27 6.27 0 0 0-8.16 9.48l7.45 7.45a1 1 0 0 0 1.42 0l7.45-7.45a6.27 6.27 0 0 0 0-8.87Zm-1.41 7.46L12 18.81l-6.75-6.74a4.28 4.28 0 0 1 3-7.3a4.25 4.25 0 0 1 3 1.25a1 1 0 0 0 1.42 0a4.27 4.27 0 0 1 6 6.05Z"/>
        </symbol>
        <symbol xmlns="http://www.w3.org/2000/svg" id="plus" viewBox="0 0 24 24">
          <path fill="currentColor" d="M19 11h-6V5a1 1 0 0 0-2 0v6H5a1 1 0 0 0 0 2h6v6a1 1 0 0 0 2 0v-6h6a1 1 0 0 0 0-2Z"/>
        </symbol>
        <symbol xmlns="http://www.w3.org/2000/svg" id="minus" viewBox="0 0 24 24">
          <path fill="currentColor" d="M19 11H5a1 1 0 0 0 0 2h14a1 1 0 0 0 0-2Z"/>
        </symbol>
        <symbol xmlns="http://www.w3.org/2000/svg" id="cart" viewBox="0 0 24 24">
          <path fill="currentColor" d="M8.5 19a1.5 1.5 0 1 0 1.5 1.5A1.5 1.5 0 0 0 8.5 19ZM19 16H7a1 1 0 0 1 0-2h8.491a3.013 3.013 0 0 0 2.885-2.176l1.585-5.55A1 1 0 0 0 19 5H6.74a3.007 3.007 0 0 0-2.82-2H3a1 1 0 0 0 0 2h.921a1.005 1.005 0 0 1 .962.725l.155.545v.005l1.641 5.742A3 3 0 0 0 7 18h12a1 1 0 0 0 0-2Zm-1.326-9l-1.22 4.274a1.005 1.005 0 0 1-.963.726H8.754l-.255-.892L7.326 7ZM16.5 19a1.5 1.5 0 1 0 1.5 1.5a1.5 1.5 0 0 0-1.5-1.5Z"/>
        </symbol>
        <symbol xmlns="http://www.w3.org/2000/svg" id="check" viewBox="0 0 24 24">
          <path fill="currentColor" d="M18.71 7.21a1 1 0 0 0-1.42 0l-7.45 7.46l-3.13-3.14A1 1 0 1 0 5.29 13l3.84 3.84a1 1 0 0 0 1.42 0l8.16-8.16a1 1 0 0 0 0-1.47Z"/>
        </symbol>
        <symbol xmlns="http://www.w3.org/2000/svg" id="trash" viewBox="0 0 24 24">
          <path fill="currentColor" d="M10 18a1 1 0 0 0 1-1v-6a1 1 0 0 0-2 0v6a1 1 0 0 0 1 1ZM20 6h-4V5a3 3 0 0 0-3-3h-2a3 3 0 0 0-3 3v1H4a1 1 0 0 0 0 2h1v11a3 3 0 0 0 3 3h8a3 3 0 0 0 3-3V8h1a1 1 0 0 0 0-2ZM10 5a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1v1h-4Zm7 14a1 1 0 0 1-1 1H8a1 1 0 0 1-1-1V8h10Zm-3-1a1 1 0 0 0 1-1v-6a1 1 0 0 0-2 0v6a1 1 0 0 0 1 1Z"/>
        </symbol>
        <symbol xmlns="http://www.w3.org/2000/svg" id="star-outline" viewBox="0 0 15 15">
          <path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" d="M7.5 9.804L5.337 11l.413-2.533L4 6.674l2.418-.37L7.5 4l1.082 2.304l2.418.37l-1.75 1.793L9.663 11L7.5 9.804Z"/>
        </symbol>
        <symbol xmlns="http://www.w3.org/2000/svg" id="star-solid" viewBox="0 0 15 15">
          <path fill="currentColor" d="M7.953 3.788a.5.5 0 0 0-.906 0L6.08 5.85l-2.154.33a.5.5 0 0 0-.283.843l1.574 1.613l-.373 2.284a.5.5 0 0 0 .736.518l1.92-1.063l1.921 1.063a.5.5 0 0 0 .736-.519l-.373-2.283l1.574-1.613a.5.5 0 0 0-.283-.844L8.921 5.85l-.968-2.062Z"/>
        </symbol>
        <symbol xmlns="http://www.w3.org/2000/svg" id="search" viewBox="0 0 24 24">
          <path fill="currentColor" d="M21.71 20.29L18 16.61A9 9 0 1 0 16.61 18l3.68 3.68a1 1 0 0 0 1.42 0a1 1 0 0 0 0-1.39ZM11 18a7 7 0 1 1 7-7a7 7 0 0 1-7 7Z"/>
        </symbol>
        <symbol xmlns="http://www.w3.org/2000/svg" id="user" viewBox="0 0 24 24">
          <path fill="currentColor" d="M15.71 12.71a6 6 0 1 0-7.42 0a10 10 0 0 0-6.22 8.18a1 1 0 0 0 2 .22a8 8 0 0 1 15.9 0a1 1 0 0 0 1 .89h.11a1 1 0 0 0 .88-1.1a10 10 0 0 0-6.25-8.19ZM12 12a4 4 0 1 1 4-4a4 4 0 0 1-4 4Z"/>
        </symbol>
        <symbol xmlns="http://www.w3.org/2000/svg" id="close" viewBox="0 0 15 15">
          <path fill="currentColor" d="M7.953 3.788a.5.5 0 0 0-.906 0L6.08 5.85l-2.154.33a.5.5 0 0 0-.283.843l1.574 1.613l-.373 2.284a.5.5 0 0 0 .736.518l1.92-1.063l1.921 1.063a.5.5 0 0 0 .736-.519l-.373-2.283l1.574-1.613a.5.5 0 0 0-.283-.844L8.921 5.85l-.968-2.062Z"/>
        </symbol>
      </defs>
    </svg>
__FOODMART_RAW_EOF__;

        return $this->decorateAccessibility($markup);
    }

    /**
     * Byte-exact Golden Reference markup (Phase 0/2A) — see
     * _reference-themes/FoodMart-main/FoodMart-main/index.html. Extracted
     * once during Phase 2A authoring; never re-read at runtime. Zero prop
     * usage by design (Phase 2A is static-only, see Step 8).
     */
    protected function renderFoodmartPreloader(array $props, string $inner, string $id): string
    {
        $markup = <<<'__FOODMART_RAW_EOF__'
<div class="preloader-wrapper">
      <div class="preloader">
      </div>
    </div>
__FOODMART_RAW_EOF__;

        return $this->decorateAccessibility($markup);
    }

    /**
     * Byte-exact Golden Reference markup (Phase 0/2A) — see
     * _reference-themes/FoodMart-main/FoodMart-main/index.html. Extracted
     * once during Phase 2A authoring; never re-read at runtime. Zero prop
     * usage by design (Phase 2A is static-only, see Step 8).
     */
    protected function renderFoodmartOffcanvasCart(array $props, string $inner, string $id): string
    {
        $markup = <<<'__FOODMART_RAW_EOF__'
<div class="offcanvas offcanvas-end" data-bs-scroll="true" tabindex="-1" id="offcanvasCart" aria-labelledby="My Cart">
      <div class="offcanvas-header justify-content-center">
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
      </div>
      <div class="offcanvas-body">
        <div class="order-md-last">
          <h4 class="d-flex justify-content-between align-items-center mb-3">
            <span class="text-primary">Your cart</span>
            <span class="badge bg-primary rounded-pill">3</span>
          </h4>
          <ul class="list-group mb-3">
            <li class="list-group-item d-flex justify-content-between lh-sm">
              <div>
                <h6 class="my-0">Growers cider</h6>
                <small class="text-body-secondary">Brief description</small>
              </div>
              <span class="text-body-secondary">$12</span>
            </li>
            <li class="list-group-item d-flex justify-content-between lh-sm">
              <div>
                <h6 class="my-0">Fresh grapes</h6>
                <small class="text-body-secondary">Brief description</small>
              </div>
              <span class="text-body-secondary">$8</span>
            </li>
            <li class="list-group-item d-flex justify-content-between lh-sm">
              <div>
                <h6 class="my-0">Heinz tomato ketchup</h6>
                <small class="text-body-secondary">Brief description</small>
              </div>
              <span class="text-body-secondary">$5</span>
            </li>
            <li class="list-group-item d-flex justify-content-between">
              <span>Total (USD)</span>
              <strong>$20</strong>
            </li>
          </ul>
  
          <button class="w-100 btn btn-primary btn-lg" type="submit">Continue to checkout</button>
        </div>
      </div>
    </div>
__FOODMART_RAW_EOF__;

        return $this->decorateAccessibility($markup);
    }

    /**
     * Byte-exact Golden Reference markup (Phase 0/2A) — see
     * _reference-themes/FoodMart-main/FoodMart-main/index.html. Extracted
     * once during Phase 2A authoring; never re-read at runtime. Zero prop
     * usage by design (Phase 2A is static-only, see Step 8).
     */
    protected function renderFoodmartOffcanvasSearch(array $props, string $inner, string $id): string
    {
        $markup = <<<'__FOODMART_RAW_EOF__'
<div class="offcanvas offcanvas-end" data-bs-scroll="true" tabindex="-1" id="offcanvasSearch" aria-labelledby="Search">
      <div class="offcanvas-header justify-content-center">
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
      </div>
      <div class="offcanvas-body">
        <div class="order-md-last">
          <h4 class="d-flex justify-content-between align-items-center mb-3">
            <span class="text-primary">Search</span>
          </h4>
          <form role="search" action="index.html" method="get" class="d-flex mt-3 gap-0">
            <input class="form-control rounded-start rounded-0 bg-light" type="email" placeholder="What are you looking for?" aria-label="What are you looking for?">
            <button class="btn btn-dark rounded-end rounded-0" type="submit">Search</button>
          </form>
        </div>
      </div>
    </div>
__FOODMART_RAW_EOF__;

        return $this->decorateAccessibility($markup);
    }

    /**
     * Byte-exact Golden Reference markup (Phase 0/2A) — see
     * _reference-themes/FoodMart-main/FoodMart-main/index.html. Extracted
     * once during Phase 2A authoring; never re-read at runtime. Zero prop
     * usage by design (Phase 2A is static-only, see Step 8).
     */
    protected function renderFoodmartHeader(array $props, string $inner, string $id): string
    {
        if ($this->foodmartHasEditableProps($props, ['nav_items'])) {
            return $this->renderFoodmartEditableHeader($props);
        }

        $markup = <<<'__FOODMART_RAW_EOF__'
<header>
      <div class="container-fluid">
        <div class="row py-3 border-bottom">
          
          <div class="col-sm-4 col-lg-3 text-center text-sm-start">
            <div class="main-logo">
              <a href="index.html">
                <img src="images/logo.png" alt="logo" class="img-fluid">
              </a>
            </div>
          </div>
          
          <div class="col-sm-6 offset-sm-2 offset-md-0 col-lg-5 d-none d-lg-block">
            <div class="search-bar row bg-light p-2 my-2 rounded-4">
              <div class="col-md-4 d-none d-md-block">
                <select class="form-select border-0 bg-transparent">
                  <option>All Categories</option>
                  <option>Groceries</option>
                  <option>Drinks</option>
                  <option>Chocolates</option>
                </select>
              </div>
              <div class="col-11 col-md-7">
                <form id="search-form" class="text-center" action="index.html" method="post">
                  <input type="text" class="form-control border-0 bg-transparent" placeholder="Search for more than 20,000 products" />
                </form>
              </div>
              <div class="col-1">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><path fill="currentColor" d="M21.71 20.29L18 16.61A9 9 0 1 0 16.61 18l3.68 3.68a1 1 0 0 0 1.42 0a1 1 0 0 0 0-1.39ZM11 18a7 7 0 1 1 7-7a7 7 0 0 1-7 7Z"/></svg>
              </div>
            </div>
          </div>
          
          <div class="col-sm-8 col-lg-4 d-flex justify-content-end gap-5 align-items-center mt-4 mt-sm-0 justify-content-center justify-content-sm-end">
            <div class="support-box text-end d-none d-xl-block">
              <span class="fs-6 text-muted">For Support?</span>
              <h5 class="mb-0">+980-34984089</h5>
            </div>

            <ul class="d-flex justify-content-end list-unstyled m-0">
              <li>
                <a href="#" class="rounded-circle bg-light p-2 mx-1">
                  <svg width="24" height="24" viewBox="0 0 24 24"><use xlink:href="#user"></use></svg>
                </a>
              </li>
              <li>
                <a href="#" class="rounded-circle bg-light p-2 mx-1">
                  <svg width="24" height="24" viewBox="0 0 24 24"><use xlink:href="#heart"></use></svg>
                </a>
              </li>
              <li class="d-lg-none">
                <a href="#" class="rounded-circle bg-light p-2 mx-1" data-bs-toggle="offcanvas" data-bs-target="#offcanvasCart" aria-controls="offcanvasCart">
                  <svg width="24" height="24" viewBox="0 0 24 24"><use xlink:href="#cart"></use></svg>
                </a>
              </li>
              <li class="d-lg-none">
                <a href="#" class="rounded-circle bg-light p-2 mx-1" data-bs-toggle="offcanvas" data-bs-target="#offcanvasSearch" aria-controls="offcanvasSearch">
                  <svg width="24" height="24" viewBox="0 0 24 24"><use xlink:href="#search"></use></svg>
                </a>
              </li>
            </ul>

            <div class="cart text-end d-none d-lg-block dropdown">
              <button class="border-0 bg-transparent d-flex flex-column gap-2 lh-1" type="button" data-bs-toggle="offcanvas" data-bs-target="#offcanvasCart" aria-controls="offcanvasCart">
                <span class="fs-6 text-muted dropdown-toggle">Your Cart</span>
                <span class="cart-total fs-5 fw-bold">$1290.00</span>
              </button>
            </div>
          </div>

        </div>
      </div>
      <div class="container-fluid">
        <div class="row py-3">
          <div class="d-flex  justify-content-center justify-content-sm-between align-items-center">
            <nav class="main-menu d-flex navbar navbar-expand-lg">

              <button class="navbar-toggler" type="button" data-bs-toggle="offcanvas" data-bs-target="#offcanvasNavbar"
                aria-controls="offcanvasNavbar">
                <span class="navbar-toggler-icon"></span>
              </button>

              <div class="offcanvas offcanvas-end" tabindex="-1" id="offcanvasNavbar" aria-labelledby="offcanvasNavbarLabel">

                <div class="offcanvas-header justify-content-center">
                  <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
                </div>

                <div class="offcanvas-body">
              
                  <select class="filter-categories border-0 mb-0 me-5">
                    <option>Shop by Departments</option>
                    <option>Groceries</option>
                    <option>Drinks</option>
                    <option>Chocolates</option>
                  </select>
              
                  <ul class="navbar-nav justify-content-end menu-list list-unstyled d-flex gap-md-3 mb-0">
                    <li class="nav-item active">
                      <a href="#women" class="nav-link">Women</a>
                    </li>
                    <li class="nav-item dropdown">
                      <a href="#men" class="nav-link">Men</a>
                    </li>
                    <li class="nav-item">
                      <a href="#kids" class="nav-link">Kids</a>
                    </li>
                    <li class="nav-item">
                      <a href="#accessories" class="nav-link">Accessories</a>
                    </li>
                    <li class="nav-item dropdown">
                      <a class="nav-link dropdown-toggle" role="button" id="pages" data-bs-toggle="dropdown" aria-expanded="false">Pages</a>
                      <ul class="dropdown-menu" aria-labelledby="pages">
                        <li><a href="index.html" class="dropdown-item">About Us </a></li>
                        <li><a href="index.html" class="dropdown-item">Shop </a></li>
                        <li><a href="index.html" class="dropdown-item">Single Product </a></li>
                        <li><a href="index.html" class="dropdown-item">Cart </a></li>
                        <li><a href="index.html" class="dropdown-item">Checkout </a></li>
                        <li><a href="index.html" class="dropdown-item">Blog </a></li>
                        <li><a href="index.html" class="dropdown-item">Single Post </a></li>
                        <li><a href="index.html" class="dropdown-item">Styles </a></li>
                        <li><a href="index.html" class="dropdown-item">Contact </a></li>
                        <li><a href="index.html" class="dropdown-item">Thank You </a></li>
                        <li><a href="index.html" class="dropdown-item">My Account </a></li>
                        <li><a href="index.html" class="dropdown-item">404 Error </a></li>
                      </ul>
                    </li>
                    <li class="nav-item">
                      <a href="#brand" class="nav-link">Brand</a>
                    </li>
                    <li class="nav-item">
                      <a href="#sale" class="nav-link">Sale</a>
                    </li>
                    <li class="nav-item">
                      <a href="#blog" class="nav-link">Blog</a>
                    </li>
                  </ul>
                
                </div>

              </div>
          </div>
        </div>
      </div>
    </header>
__FOODMART_RAW_EOF__;

        return $this->decorateAccessibility($markup);
    }

    /**
     * Phase 4B.6 — editable header. Same FoodMart header DOM (logo/search/
     * support/cart row + offcanvas navbar) with theme-owned visible strings
     * and nav items from schema props. Static rows keep canonical defaults;
     * each nav item carries logical-selection metadata. Cart/search/offcanvas
     * wiring (data-bs-toggle, required ids) stays structural — never props.
     *
     * @param array<string,mixed> $props
     */
    private function renderFoodmartEditableHeader(array $props): string
    {
        $logo = $this->foodmartEditableSrc((string) ($props['logo_image'] ?? ''));
        $logoAlt = $this->foodmartEscape((string) ($props['logo_alt'] ?? 'logo'));
        $logoHtml = '<a href="index.html"><img src="'.($logo !== '' ? $logo : 'images/logo.png').'" alt="'.$logoAlt.'" class="img-fluid"></a>';
        $searchPlaceholder = $this->foodmartEscape((string) ($props['search_placeholder'] ?? 'Search for more than 20,000 products'));
        $filterLabel = $this->foodmartEscape((string) ($props['filter_label'] ?? 'Shop by Departments'));
        $filterOptions = array_values(array_filter(array_map('trim', explode(',', (string) ($props['filter_options'] ?? 'Groceries,Drinks,Chocolates'))), fn (string $option) => $option !== ''));
        $filterOptionsHtml = '';
        foreach (array_merge([$filterLabel], $filterOptions) as $filterOption) {
            $filterOptionsHtml .= '<option>'.$this->foodmartEscape($filterOption).'</option>';
        }
        $supportLabel = $this->foodmartEscape((string) ($props['support_label'] ?? 'For Support?'));
        $supportPhone = $this->foodmartEscape((string) ($props['support_phone'] ?? '+980-34984089'));
        $cartLabel = $this->foodmartEscape((string) ($props['cart_label'] ?? 'Your Cart'));

        $navItems = $this->foodmartEditableList($props['nav_items'] ?? null);
        $navHtml = '';
        foreach ($navItems as $index => $item) {
            $label = $this->foodmartEscape((string) ($item['label'] ?? ''));
            $url = $this->foodmartEditableHrefValue((string) ($item['url'] ?? '#'));
            $navHtml .= '<li class="nav-item" data-tb-logical-group="nav_items" data-tb-logical-index="'.(int) $index.'">'
                .'<a href="'.$url.'" class="nav-link">'.$label.'</a></li>';
        }
        if ($navHtml === '') {
            $navHtml = '<li class="nav-item active"><a href="#women" class="nav-link">Women</a></li>'
                .'<li class="nav-item"><a href="#men" class="nav-link">Men</a></li>'
                .'<li class="nav-item"><a href="#kids" class="nav-link">Kids</a></li>'
                .'<li class="nav-item"><a href="#accessories" class="nav-link">Accessories</a></li>'
                .'<li class="nav-item"><a href="#brand" class="nav-link">Brand</a></li>'
                .'<li class="nav-item"><a href="#sale" class="nav-link">Sale</a></li>'
                .'<li class="nav-item"><a href="#blog" class="nav-link">Blog</a></li>';
        }

        $markup = '<header><div class="container-fluid"><div class="row py-3 border-bottom">'
            .'<div class="col-sm-4 col-lg-3 text-center text-sm-start"><div class="main-logo">'.$logoHtml.'</div></div>'
            .'<div class="col-sm-6 offset-sm-2 offset-md-0 col-lg-5 d-none d-lg-block"><div class="search-bar row bg-light p-2 my-2 rounded-4">'
            .'<div class="col-md-4 d-none d-md-block"><select class="form-select border-0 bg-transparent">'.$filterOptionsHtml.'</select></div>'
            .'<div class="col-11 col-md-7"><form id="search-form" class="text-center" action="index.html" method="post">'
            .'<input type="text" class="form-control border-0 bg-transparent" placeholder="'.$searchPlaceholder.'" /></form></div>'
            .'<div class="col-1"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><path fill="currentColor" d="M21.71 20.29L18 16.61A9 9 0 1 0 16.61 18l3.68 3.68a1 1 0 0 0 0-1.39ZM11 18a7 7 0 1 1 7-7a7 7 0 0 1-7 7Z"/></svg></div>'
            .'</div></div>'
            .'<div class="col-sm-8 col-lg-4 d-flex justify-content-end gap-5 align-items-center mt-4 mt-sm-0 justify-content-center justify-content-sm-end">'
            .'<div class="support-box text-end d-none d-xl-block"><span class="fs-6 text-muted">'.$supportLabel.'</span><h5 class="mb-0">'.$supportPhone.'</h5></div>'
            .'<ul class="d-flex justify-content-end list-unstyled m-0">'
            .'<li><a href="#" class="rounded-circle bg-light p-2 mx-1"><svg width="24" height="24" viewBox="0 0 24 24"><use xlink:href="#user"></use></svg></a></li>'
            .'<li><a href="#" class="rounded-circle bg-light p-2 mx-1"><svg width="24" height="24" viewBox="0 0 24 24"><use xlink:href="#heart"></use></svg></a></li>'
            .'<li class="d-lg-none"><a href="#" class="rounded-circle bg-light p-2 mx-1" data-bs-toggle="offcanvas" data-bs-target="#offcanvasCart" aria-controls="offcanvasCart"><svg width="24" height="24" viewBox="0 0 24 24"><use xlink:href="#cart"></use></svg></a></li>'
            .'<li class="d-lg-none"><a href="#" class="rounded-circle bg-light p-2 mx-1" data-bs-toggle="offcanvas" data-bs-target="#offcanvasSearch" aria-controls="offcanvasSearch"><svg width="24" height="24" viewBox="0 0 24 24"><use xlink:href="#search"></use></svg></a></li>'
            .'</ul>'
            .'<div class="cart text-end d-none d-lg-block dropdown"><button class="border-0 bg-transparent d-flex flex-column gap-2 lh-1" type="button" data-bs-toggle="offcanvas" data-bs-target="#offcanvasCart" aria-controls="offcanvasCart">'
            .'<span class="fs-6 text-muted dropdown-toggle">'.$cartLabel.'</span><span class="cart-total fs-5 fw-bold">$1290.00</span></button></div>'
            .'</div></div>'
            .'<div class="container-fluid"><div class="row py-3"><div class="d-flex  justify-content-center justify-content-sm-between align-items-center">'
            .'<nav class="main-menu d-flex navbar navbar-expand-lg">'
            .'<button class="navbar-toggler" type="button" data-bs-toggle="offcanvas" data-bs-target="#offcanvasNavbar" aria-controls="offcanvasNavbar"><span class="navbar-toggler-icon"></span></button>'
            .'<div class="offcanvas offcanvas-end" tabindex="-1" id="offcanvasNavbar" aria-labelledby="offcanvasNavbarLabel">'
            .'<div class="offcanvas-header justify-content-center"><button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button></div>'
            .'<div class="offcanvas-body">'
            .'<select class="filter-categories border-0 mb-0 me-5">'.$filterOptionsHtml.'</select>'
            .'<ul class="navbar-nav justify-content-end menu-list list-unstyled d-flex gap-md-3 mb-0">'.$navHtml.'</ul>'
            .'</div></div></nav></div></div></div></div></header>';

        return $this->decorateAccessibility($markup);
    }

    /**
     * Byte-exact Golden Reference markup (Phase 0/2A) — see
     * _reference-themes/FoodMart-main/FoodMart-main/index.html. Extracted
     * once during Phase 2A authoring; never read at runtime. Zero prop
     * usage by design (Phase 2A is static-only, see Step 8).
     */
    protected function renderFoodmartHero(array $props, string $inner, string $id): string
    {
        if ($this->foodmartHasEditableProps($props, ['slides', 'promos'])) {
            return $this->renderFoodmartEditableHero($props);
        }

        $markup = <<<'__FOODMART_RAW_EOF__'
<section class="py-3" style="background-image: url('images/background-pattern.jpg');background-repeat: no-repeat;background-size: cover;">
      <div class="container-fluid">
        <div class="row">
          <div class="col-md-12">

            <div class="banner-blocks">
            
              <div class="banner-ad large bg-info block-1">

                <div class="swiper main-swiper">
                  <div class="swiper-wrapper">
                    
                    <div class="swiper-slide">
                      <div class="row banner-content p-5">
                        <div class="content-wrapper col-md-7">
                          <div class="categories my-3">100% natural</div>
                          <h3 class="display-4">Fresh Smoothie & Summer Juice</h3>
                          <p>Lorem ipsum dolor sit amet, consectetur adipiscing elit. Dignissim massa diam elementum.</p>
                          <a href="#" class="btn btn-outline-dark btn-lg text-uppercase fs-6 rounded-1 px-4 py-3 mt-3">Shop Now</a>
                        </div>
                        <div class="img-wrapper col-md-5">
                          <img src="images/product-thumb-1.png" class="img-fluid">
                        </div>
                      </div>
                    </div>
                    
                    <div class="swiper-slide">
                      <div class="row banner-content p-5">
                        <div class="content-wrapper col-md-7">
                          <div class="categories mb-3 pb-3">100% natural</div>
                          <h3 class="banner-title">Fresh Smoothie & Summer Juice</h3>
                          <p>Lorem ipsum dolor sit amet, consectetur adipiscing elit. Dignissim massa diam elementum.</p>
                          <a href="#" class="btn btn-outline-dark btn-lg text-uppercase fs-6 rounded-1">Shop Collection</a>
                        </div>
                        <div class="img-wrapper col-md-5">
                          <img src="images/product-thumb-1.png" class="img-fluid">
                        </div>
                      </div>
                    </div>
                    
                    <div class="swiper-slide">
                      <div class="row banner-content p-5">
                        <div class="content-wrapper col-md-7">
                          <div class="categories mb-3 pb-3">100% natural</div>
                          <h3 class="banner-title">Heinz Tomato Ketchup</h3>
                          <p>Lorem ipsum dolor sit amet, consectetur adipiscing elit. Dignissim massa diam elementum.</p>
                          <a href="#" class="btn btn-outline-dark btn-lg text-uppercase fs-6 rounded-1">Shop Collection</a>
                        </div>
                        <div class="img-wrapper col-md-5">
                          <img src="images/product-thumb-2.png" class="img-fluid">
                        </div>
                      </div>
                    </div>
                  </div>
                  
                  <div class="swiper-pagination"></div>

                </div>
              </div>
              
              <div class="banner-ad bg-success-subtle block-2" style="background:url('images/ad-image-1.png') no-repeat;background-position: right bottom">
                <div class="row banner-content p-5">

                  <div class="content-wrapper col-md-7">
                    <div class="categories sale mb-3 pb-3">20% off</div>
                    <h3 class="banner-title">Fruits & Vegetables</h3>
                    <a href="#" class="d-flex align-items-center nav-link">Shop Collection <svg width="24" height="24"><use xlink:href="#arrow-right"></use></svg></a>
                  </div>

                </div>
              </div>

              <div class="banner-ad bg-danger block-3" style="background:url('images/ad-image-2.png') no-repeat;background-position: right bottom">
                <div class="row banner-content p-5">

                  <div class="content-wrapper col-md-7">
                    <div class="categories sale mb-3 pb-3">15% off</div>
                    <h3 class="item-title">Baked Products</h3>
                    <a href="#" class="d-flex align-items-center nav-link">Shop Collection <svg width="24" height="24"><use xlink:href="#arrow-right"></use></svg></a>
                  </div>

                </div>
              </div>

            </div>
            <!-- / Banner Blocks -->
              
          </div>
        </div>
      </div>
    </section>
__FOODMART_RAW_EOF__;

        return $this->decorateAccessibility($markup);
    }

    /**
     * Phase 4B.5 editable hero — same FoodMart DOM skeleton and classes as
     * the byte-exact fallback below, populated from schema-declared props
     * (repeater `slides` + `promos`). Text is escaped; URLs fail closed
     * through the shared safe-URL gate. Never used when props are absent,
     * so the unbound/static output stays byte-identical.
     */
    private function renderFoodmartEditableHero(array $props): string
    {
        $slides = $this->foodmartEditableList($props['slides'] ?? null);
        $promos = $this->foodmartEditableList($props['promos'] ?? null);

        $slideHtml = '';
        foreach ($slides as $slideIndex => $slide) {
            $label = $this->foodmartEscape((string) ($slide['label'] ?? ''));
            $title = $this->foodmartEscape((string) ($slide['title'] ?? ''));
            $desc = $this->foodmartEscape((string) ($slide['description'] ?? ''));
            $cta = $this->foodmartEditableHref($this->linkValue($slide['cta_url'] ?? ''), 'btn btn-outline-dark btn-lg text-uppercase fs-6 rounded-1 px-4 py-3 mt-3');
            $image = $this->foodmartEditableSrc((string) ($slide['image'] ?? ''));
            $imageAlt = $this->foodmartEscape((string) ($slide['image_alt'] ?? 'Fruit'));
            $imgHtml = $image === '' ? '' : '<img src="'.$image.'" class="img-fluid" alt="'.$imageAlt.'">';
            $slideHtml .= '<div class="swiper-slide" data-tb-logical-group="slides" data-tb-logical-index="'.(int) $slideIndex.'"><div class="row banner-content p-5"><div class="content-wrapper col-md-7">'
                .($label !== '' ? '<div class="categories my-3">'.$label.'</div>' : '')
                .'<h3 class="banner-title">'.$title.'</h3>'
                .($desc !== '' ? '<p>'.$desc.'</p>' : '')
                .$cta.'</div><div class="img-wrapper col-md-5">'.$imgHtml.'</div></div></div>';
        }
        if ($slideHtml === '') {
            $slideHtml = '<div class="swiper-slide"><div class="row banner-content p-5"></div></div>';
        }
        $promoHtml = '';
        foreach ($promos as $promoIndex => $promo) {
            $badge = $this->foodmartEscape((string) ($promo['badge'] ?? ''));
            $title = $this->foodmartEscape((string) ($promo['title'] ?? ''));
            $url = $this->foodmartEditableHref($this->linkValue($promo['cta_url'] ?? ''), 'd-flex align-items-center nav-link');
            $promoHtml .= '<div class="banner-ad bg-success-subtle block-2" data-tb-logical-group="promos" data-tb-logical-index="'.(int) $promoIndex.'"><div class="row banner-content p-5"><div class="content-wrapper col-md-7">'
                .($badge !== '' ? '<div class="categories sale mb-3 pb-3">'.$badge.'</div>' : '')
                .'<h3 class="banner-title">'.$title.'</h3>'
                .$url.'</div></div></div>';
        }

        if ($promoHtml === '') {
            $promoHtml = '<div class="banner-ad bg-success-subtle block-2"><div class="row banner-content p-5"></div></div>';
        }

        $markup = '<section class="py-3"><div class="container-fluid"><div class="row"><div class="col-md-12"><div class="banner-blocks">'
            .'<div class="banner-ad large bg-info block-1"><div class="swiper main-swiper"><div class="swiper-wrapper">'
            .$slideHtml
            .'</div><div class="swiper-pagination"></div></div></div>'
            .$promoHtml
            .'</div></div></div></div></section>';

        return $this->decorateAccessibility($markup);
    }

    /**
     * True when any of the given prop keys actually carries editable
     * content. Empty/missing props keep the byte-exact static output —
     * the editability switch is strictly additive.
     *
     * @param array<int,string> $keys
     * @param array<string,mixed> $props
     */
    private function foodmartHasEditableProps(array $props, array $keys): bool
    {
        foreach ($keys as $key) {
            $value = $props[$key] ?? null;
            if (is_array($value) && $value !== []) {
                return true;
            }
            if (is_string($value) && trim($value) !== '') {
                return true;
            }
        }

        return false;
    }

    private function foodmartEditableList(mixed $value): array
    {
        // Phase 4B.6: a repeater declared with storage='json' (footer.columns)
        // is persisted by the editor — and accepted by the tenant validator —
        // as BOTH a real PHP array and a JSON-encoded array string. Dropping
        // the string shape silently discarded every edited footer column, so
        // decode it exactly like the editor's structuredItems() does.
        if (is_string($value) && trim($value) !== '') {
            $decoded = json_decode($value, true);
            $value = is_array($decoded) ? $decoded : null;
        }
        if (!is_array($value)) {
            return [];
        }

        return array_values(array_filter($value, 'is_array'));
    }

    private function foodmartEditableHref(string $url, string $class): string
    {
        $url = trim($url);
        if ($url === '') {
            return '';
        }
        if (preg_match('/\Ahttps?:\/\//i', $url) !== 1 && !str_starts_with($url, '/')) {
            return '';
        }
        if (preg_match('/[\"\'<>\\s]/', $url) === 1) {
            return '';
        }

        return '<a href="'.$this->foodmartEscape($url).'" class="'.$class.'">Shop Collection</a>';
    }

    private function foodmartEditableSrc(string $path): string
    {
        $path = trim($path);
        if ($path === '' || preg_match('/[\"\'<>\\s]/', $path) === 1 || str_contains($path, '..') || str_contains($path, '\\') || str_contains($path, "\0")) {
            return '';
        }

        return $this->foodmartEscape($path);
    }

    /**
     * Byte-exact Golden Reference markup (Phase 0/2A) — see
     * _reference-themes/FoodMart-main/FoodMart-main/index.html. Extracted
     * once during Phase 2A authoring; never read at runtime.
     */
    protected function renderFoodmartCategories(array $props, string $inner, string $id): string
    {
        $sectionTitle = is_string($props['section_title'] ?? null) && trim($props['section_title']) !== '' ? $props['section_title'] : null;
        if (($binding = $this->foodmartResolvedBinding($id, 'product_categories')) !== null) {
            return $this->renderFoodmartBoundCategories($binding, $sectionTitle);
        }
        if (($manual = $this->foodmartManualItems($props, 'items')) !== null) {
            return $this->renderFoodmartManualCategories(
                $manual,
                $sectionTitle,
                (string) ($props['view_all_label'] ?? ''),
                (string) ($props['view_all_url'] ?? '')
            );
        }

        $markup = <<<'__FOODMART_RAW_EOF__'
<section class="py-5 overflow-hidden">
      <div class="container-fluid">
        <div class="row">
          <div class="col-md-12">

            <div class="section-header d-flex flex-wrap justify-content-between mb-5">
              <h2 class="section-title">Category</h2>

              <div class="d-flex align-items-center">
                <a href="#" class="btn-link text-decoration-none">View All Categories →</a>
                <div class="swiper-buttons">
                  <button class="swiper-prev category-carousel-prev btn btn-yellow">❮</button>
                  <button class="swiper-next category-carousel-next btn btn-yellow">❯</button>
                </div>
              </div>
            </div>
            
          </div>
        </div>
        <div class="row">
          <div class="col-md-12">

            <div class="category-carousel swiper">
              <div class="swiper-wrapper">
                <a href="index.html" class="nav-link category-item swiper-slide">
                  <img src="images/icon-vegetables-broccoli.png" alt="Category Thumbnail">
                  <h3 class="category-title">Fruits & Veges</h3>
                </a>
                <a href="index.html" class="nav-link category-item swiper-slide">
                  <img src="images/icon-bread-baguette.png" alt="Category Thumbnail">
                  <h3 class="category-title">Breads & Sweets</h3>
                </a>
                <a href="index.html" class="nav-link category-item swiper-slide">
                  <img src="images/icon-soft-drinks-bottle.png" alt="Category Thumbnail">
                  <h3 class="category-title">Fruits & Veges</h3>
                </a>
                <a href="index.html" class="nav-link category-item swiper-slide">
                  <img src="images/icon-wine-glass-bottle.png" alt="Category Thumbnail">
                  <h3 class="category-title">Fruits & Veges</h3>
                </a>
                <a href="index.html" class="nav-link category-item swiper-slide">
                  <img src="images/icon-animal-products-drumsticks.png" alt="Category Thumbnail">
                  <h3 class="category-title">Fruits & Veges</h3>
                </a>
                <a href="index.html" class="nav-link category-item swiper-slide">
                  <img src="images/icon-bread-herb-flour.png" alt="Category Thumbnail">
                  <h3 class="category-title">Fruits & Veges</h3>
                </a>
                <a href="index.html" class="nav-link category-item swiper-slide">
                  <img src="images/icon-vegetables-broccoli.png" alt="Category Thumbnail">
                  <h3 class="category-title">Fruits & Veges</h3>
                </a>
                <a href="index.html" class="nav-link category-item swiper-slide">
                  <img src="images/icon-vegetables-broccoli.png" alt="Category Thumbnail">
                  <h3 class="category-title">Fruits & Veges</h3>
                </a>
                <a href="index.html" class="nav-link category-item swiper-slide">
                  <img src="images/icon-vegetables-broccoli.png" alt="Category Thumbnail">
                  <h3 class="category-title">Fruits & Veges</h3>
                </a>
                <a href="index.html" class="nav-link category-item swiper-slide">
                  <img src="images/icon-vegetables-broccoli.png" alt="Category Thumbnail">
                  <h3 class="category-title">Fruits & Veges</h3>
                </a>
                <a href="index.html" class="nav-link category-item swiper-slide">
                  <img src="images/icon-vegetables-broccoli.png" alt="Category Thumbnail">
                  <h3 class="category-title">Fruits & Veges</h3>
                </a>
                <a href="index.html" class="nav-link category-item swiper-slide">
                  <img src="images/icon-vegetables-broccoli.png" alt="Category Thumbnail">
                  <h3 class="category-title">Fruits & Veges</h3>
                </a>
                
              </div>
            </div>

          </div>
        </div>
      </div>
    </section>
__FOODMART_RAW_EOF__;

        if ($sectionTitle !== null) {
            $markup = str_replace('<h2 class="section-title">Category</h2>', '<h2 class="section-title">'.$this->foodmartEscape($sectionTitle).'</h2>', $markup);
        }

        return $this->decorateAccessibility($markup);
    }

    /**
     * Byte-exact Golden Reference markup (Phase 0/2A) — see
     * _reference-themes/FoodMart-main/FoodMart-main/index.html. Extracted
     * once during Phase 2A authoring; never re-read at runtime.
     */
    protected function renderFoodmartBrands(array $props, string $inner, string $id): string
    {
        if (($manual = $this->foodmartManualItems($props, 'items')) !== null) {
            return $this->renderFoodmartEditableBrands($props, $manual);
        }

        $markup = <<<'__FOODMART_RAW_EOF__'
<section class="py-5 overflow-hidden">
      <div class="container-fluid">
        <div class="row">
          <div class="col-md-12">

            <div class="section-header d-flex flex-wrap flex-wrap justify-content-between mb-5">
              
              <h2 class="section-title">Newly Arrived Brands</h2>

              <div class="d-flex align-items-center">
                <a href="#" class="btn-link text-decoration-none">View All Categories →</a>
                <div class="swiper-buttons">
                  <button class="swiper-prev brand-carousel-prev btn btn-yellow">❮</button>
                  <button class="swiper-next brand-carousel-next btn btn-yellow">❯</button>
                </div>  
              </div>
            </div>
            
          </div>
        </div>
        <div class="row">
          <div class="col-md-12">

            <div class="brand-carousel swiper">
              <div class="swiper-wrapper">
                
                <div class="swiper-slide">
                  <div class="card mb-3 p-3 rounded-4 shadow border-0">
                    <div class="row g-0">
                      <div class="col-md-4">
                        <img src="images/product-thumb-11.jpg" class="img-fluid rounded" alt="Card title">
                      </div>
                      <div class="col-md-8">
                        <div class="card-body py-0">
                          <p class="text-muted mb-0">Amber Jar</p>
                          <h5 class="card-title">Honey best nectar you wish to get</h5>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
                <div class="swiper-slide">
                  <div class="card mb-3 p-3 rounded-4 shadow border-0">
                    <div class="row g-0">
                      <div class="col-md-4">
                        <img src="images/product-thumb-12.jpg" class="img-fluid rounded" alt="Card title">
                      </div>
                      <div class="col-md-8">
                        <div class="card-body py-0">
                          <p class="text-muted mb-0">Amber Jar</p>
                          <h5 class="card-title">Honey best nectar you wish to get</h5>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
                <div class="swiper-slide">
                  <div class="card mb-3 p-3 rounded-4 shadow border-0">
                    <div class="row g-0">
                      <div class="col-md-4">
                        <img src="images/product-thumb-13.jpg" class="img-fluid rounded" alt="Card title">
                      </div>
                      <div class="col-md-8">
                        <div class="card-body py-0">
                          <p class="text-muted mb-0">Amber Jar</p>
                          <h5 class="card-title">Honey best nectar you wish to get</h5>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
                <div class="swiper-slide">
                  <div class="card mb-3 p-3 rounded-4 shadow border-0">
                    <div class="row g-0">
                      <div class="col-md-4">
                        <img src="images/product-thumb-14.jpg" class="img-fluid rounded" alt="Card title">
                      </div>
                      <div class="col-md-8">
                        <div class="card-body py-0">
                          <p class="text-muted mb-0">Amber Jar</p>
                          <h5 class="card-title">Honey best nectar you wish to get</h5>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
                <div class="swiper-slide">
                  <div class="card mb-3 p-3 rounded-4 shadow border-0">
                    <div class="row g-0">
                      <div class="col-md-4">
                        <img src="images/product-thumb-11.jpg" class="img-fluid rounded" alt="Card title">
                      </div>
                      <div class="col-md-8">
                        <div class="card-body py-0">
                          <p class="text-muted mb-0">Amber Jar</p>
                          <h5 class="card-title">Honey best nectar you wish to get</h5>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
                <div class="swiper-slide">
                  <div class="card mb-3 p-3 rounded-4 shadow border-0">
                    <div class="row g-0">
                      <div class="col-md-4">
                        <img src="images/product-thumb-12.jpg" class="img-fluid rounded" alt="Card title">
                      </div>
                      <div class="col-md-8">
                        <div class="card-body py-0">
                          <p class="text-muted mb-0">Amber Jar</p>
                          <h5 class="card-title">Honey best nectar you wish to get</h5>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
                
              </div>
            </div>

          </div>
        </div>
      </div>
    </section>
__FOODMART_RAW_EOF__;

        return $this->decorateAccessibility($markup);
    }

    /**
     * Byte-exact Golden Reference markup (Phase 0/2A) — see
     * _reference-themes/FoodMart-main/FoodMart-main/index.html. Extracted
     * once during Phase 2A authoring; never re-read at runtime. Zero prop
     * usage by design (Phase 2A is static-only, see Step 8).
     */
    protected function renderFoodmartTrendingProducts(array $props, string $inner, string $id): string
    {
        $sectionTitle = is_string($props['section_title'] ?? null) && trim($props['section_title']) !== '' ? $props['section_title'] : null;
        if (($binding = $this->foodmartResolvedBinding($id, 'products')) !== null) {
            return $this->renderFoodmartBoundProducts($binding, 'Trending Products', false, $sectionTitle);
        }
        if (($manual = $this->foodmartManualItems($props, 'items')) !== null) {
            return $this->renderFoodmartManualProducts($manual, 'Trending Products', false, $sectionTitle);
        }

        $markup = <<<'__FOODMART_RAW_EOF__'
<section class="py-5">
      <div class="container-fluid">
        
        <div class="row">
          <div class="col-md-12">

            <div class="bootstrap-tabs product-tabs">
              <div class="tabs-header d-flex justify-content-between border-bottom my-5">
                <h3>Trending Products</h3>
                <nav>
                  <div class="nav nav-tabs" id="nav-tab" role="tablist">
                    <a href="#" class="nav-link text-uppercase fs-6 active" id="nav-all-tab" data-bs-toggle="tab" data-bs-target="#nav-all">All</a>
                    <a href="#" class="nav-link text-uppercase fs-6" id="nav-fruits-tab" data-bs-toggle="tab" data-bs-target="#nav-fruits">Fruits & Veges</a>
                    <a href="#" class="nav-link text-uppercase fs-6" id="nav-juices-tab" data-bs-toggle="tab" data-bs-target="#nav-juices">Juices</a>
                  </div>
                </nav>
              </div>
              <div class="tab-content" id="nav-tabContent">
                <div class="tab-pane fade show active" id="nav-all" role="tabpanel" aria-labelledby="nav-all-tab">

                  <div class="product-grid row row-cols-1 row-cols-sm-2 row-cols-md-3 row-cols-lg-4 row-cols-xl-5">
                  
                    <div class="col">
                      <div class="product-item">
                        <span class="badge bg-success position-absolute m-3">-30%</span>
                        <a href="#" class="btn-wishlist"><svg width="24" height="24"><use xlink:href="#heart"></use></svg></a>
                        <figure>
                          <a href="index.html" title="Product Title">
                            <img src="images/thumb-bananas.png"  class="tab-image">
                          </a>
                        </figure>
                        <h3>Sunstar Fresh Melon Juice</h3>
                        <span class="qty">1 Unit</span><span class="rating"><svg width="24" height="24" class="text-primary"><use xlink:href="#star-solid"></use></svg> 4.5</span>
                        <span class="price">$18.00</span>
                        <div class="d-flex align-items-center justify-content-between">
                          <div class="input-group product-qty">
                              <span class="input-group-btn">
                                  <button type="button" class="quantity-left-minus btn btn-danger btn-number" data-type="minus">
                                    <svg width="16" height="16"><use xlink:href="#minus"></use></svg>
                                  </button>
                              </span>
                              <input type="text" id="quantity" name="quantity" class="form-control input-number" value="1">
                              <span class="input-group-btn">
                                  <button type="button" class="quantity-right-plus btn btn-success btn-number" data-type="plus">
                                      <svg width="16" height="16"><use xlink:href="#plus"></use></svg>
                                  </button>
                              </span>
                          </div>
                          <a href="#" class="nav-link">Add to Cart <iconify-icon icon="uil:shopping-cart"></a>
                        </div>
                      </div>
                    </div>

                    <div class="col">
                      <div class="product-item">
                        <span class="badge bg-success position-absolute m-3">-30%</span>
                        <a href="#" class="btn-wishlist"><svg width="24" height="24"><use xlink:href="#heart"></use></svg></a>
                        <figure>
                          <a href="index.html" title="Product Title">
                            <img src="images/thumb-biscuits.png"  class="tab-image">
                          </a>
                        </figure>
                        <h3>Sunstar Fresh Melon Juice</h3>
                        <span class="qty">1 Unit</span><span class="rating"><svg width="24" height="24" class="text-primary"><use xlink:href="#star-solid"></use></svg> 4.5</span>
                        <span class="price">$18.00</span>
                        <div class="d-flex align-items-center justify-content-between">
                          <div class="input-group product-qty">
                              <span class="input-group-btn">
                                  <button type="button" class="quantity-left-minus btn btn-danger btn-number" data-type="minus">
                                    <svg width="16" height="16"><use xlink:href="#minus"></use></svg>
                                  </button>
                              </span>
                              <input type="text" id="quantity" name="quantity" class="form-control input-number" value="1">
                              <span class="input-group-btn">
                                  <button type="button" class="quantity-right-plus btn btn-success btn-number" data-type="plus">
                                      <svg width="16" height="16"><use xlink:href="#plus"></use></svg>
                                  </button>
                              </span>
                          </div>
                          <a href="#" class="nav-link">Add to Cart <iconify-icon icon="uil:shopping-cart"></a>
                        </div>
                      </div>
                    </div>

                    <div class="col">
                      <div class="product-item">
                        <a href="#" class="btn-wishlist"><svg width="24" height="24"><use xlink:href="#heart"></use></svg></a>
                        <figure>
                          <a href="index.html" title="Product Title">
                            <img src="images/thumb-cucumber.png"  class="tab-image">
                          </a>
                        </figure>
                        <h3>Sunstar Fresh Melon Juice</h3>
                        <span class="qty">1 Unit</span><span class="rating"><svg width="24" height="24" class="text-primary"><use xlink:href="#star-solid"></use></svg> 4.5</span>
                        <span class="price">$18.00</span>
                        <div class="d-flex align-items-center justify-content-between">
                          <div class="input-group product-qty">
                              <span class="input-group-btn">
                                  <button type="button" class="quantity-left-minus btn btn-danger btn-number" data-type="minus">
                                    <svg width="16" height="16"><use xlink:href="#minus"></use></svg>
                                  </button>
                              </span>
                              <input type="text" id="quantity" name="quantity" class="form-control input-number" value="1">
                              <span class="input-group-btn">
                                  <button type="button" class="quantity-right-plus btn btn-success btn-number" data-type="plus">
                                      <svg width="16" height="16"><use xlink:href="#plus"></use></svg>
                                  </button>
                              </span>
                          </div>
                          <a href="#" class="nav-link">Add to Cart <iconify-icon icon="uil:shopping-cart"></a>
                        </div>
                      </div>
                    </div>

                    <div class="col">
                      <div class="product-item">
                        <a href="#" class="btn-wishlist"><svg width="24" height="24"><use xlink:href="#heart"></use></svg></a>
                        <figure>
                          <a href="index.html" title="Product Title">
                            <img src="images/thumb-milk.png"  class="tab-image">
                          </a>
                        </figure>
                        <h3>Sunstar Fresh Melon Juice</h3>
                        <span class="qty">1 Unit</span><span class="rating"><svg width="24" height="24" class="text-primary"><use xlink:href="#star-solid"></use></svg> 4.5</span>
                        <span class="price">$18.00</span>
                        <div class="d-flex align-items-center justify-content-between">
                          <div class="input-group product-qty">
                              <span class="input-group-btn">
                                  <button type="button" class="quantity-left-minus btn btn-danger btn-number" data-type="minus">
                                    <svg width="16" height="16"><use xlink:href="#minus"></use></svg>
                                  </button>
                              </span>
                              <input type="text" id="quantity" name="quantity" class="form-control input-number" value="1">
                              <span class="input-group-btn">
                                  <button type="button" class="quantity-right-plus btn btn-success btn-number" data-type="plus">
                                      <svg width="16" height="16"><use xlink:href="#plus"></use></svg>
                                  </button>
                              </span>
                          </div>
                          <a href="#" class="nav-link">Add to Cart <iconify-icon icon="uil:shopping-cart"></a>
                        </div>
                      </div>
                    </div>

                    <div class="col">
                      <div class="product-item">
                        <a href="#" class="btn-wishlist"><svg width="24" height="24"><use xlink:href="#heart"></use></svg></a>
                        <figure>
                          <a href="index.html" title="Product Title">
                            <img src="images/thumb-bananas.png"  class="tab-image">
                          </a>
                        </figure>
                        <h3>Sunstar Fresh Melon Juice</h3>
                        <span class="qty">1 Unit</span><span class="rating"><svg width="24" height="24" class="text-primary"><use xlink:href="#star-solid"></use></svg> 4.5</span>
                        <span class="price">$18.00</span>
                        <div class="d-flex align-items-center justify-content-between">
                          <div class="input-group product-qty">
                              <span class="input-group-btn">
                                  <button type="button" class="quantity-left-minus btn btn-danger btn-number" data-type="minus">
                                    <svg width="16" height="16"><use xlink:href="#minus"></use></svg>
                                  </button>
                              </span>
                              <input type="text" id="quantity" name="quantity" class="form-control input-number" value="1">
                              <span class="input-group-btn">
                                  <button type="button" class="quantity-right-plus btn btn-success btn-number" data-type="plus">
                                      <svg width="16" height="16"><use xlink:href="#plus"></use></svg>
                                  </button>
                              </span>
                          </div>
                          <a href="#" class="nav-link">Add to Cart <iconify-icon icon="uil:shopping-cart"></a>
                        </div>
                      </div>
                    </div>

                    <div class="col">
                      <div class="product-item">
                        <a href="#" class="btn-wishlist"><svg width="24" height="24"><use xlink:href="#heart"></use></svg></a>
                        <figure>
                          <a href="index.html" title="Product Title">
                            <img src="images/thumb-biscuits.png"  class="tab-image">
                          </a>
                        </figure>
                        <h3>Sunstar Fresh Melon Juice</h3>
                        <span class="qty">1 Unit</span><span class="rating"><svg width="24" height="24" class="text-primary"><use xlink:href="#star-solid"></use></svg> 4.5</span>
                        <span class="price">$18.00</span>
                        <div class="d-flex align-items-center justify-content-between">
                          <div class="input-group product-qty">
                              <span class="input-group-btn">
                                  <button type="button" class="quantity-left-minus btn btn-danger btn-number" data-type="minus">
                                    <svg width="16" height="16"><use xlink:href="#minus"></use></svg>
                                  </button>
                              </span>
                              <input type="text" id="quantity" name="quantity" class="form-control input-number" value="1">
                              <span class="input-group-btn">
                                  <button type="button" class="quantity-right-plus btn btn-success btn-number" data-type="plus">
                                      <svg width="16" height="16"><use xlink:href="#plus"></use></svg>
                                  </button>
                              </span>
                          </div>
                          <a href="#" class="nav-link">Add to Cart <iconify-icon icon="uil:shopping-cart"></a>
                        </div>
                      </div>
                    </div>

                    <div class="col">
                      <div class="product-item">
                        <a href="#" class="btn-wishlist"><svg width="24" height="24"><use xlink:href="#heart"></use></svg></a>
                        <figure>
                          <a href="index.html" title="Product Title">
                            <img src="images/thumb-cucumber.png"  class="tab-image">
                          </a>
                        </figure>
                        <h3>Sunstar Fresh Melon Juice</h3>
                        <span class="qty">1 Unit</span><span class="rating"><svg width="24" height="24" class="text-primary"><use xlink:href="#star-solid"></use></svg> 4.5</span>
                        <span class="price">$18.00</span>
                        <div class="d-flex align-items-center justify-content-between">
                          <div class="input-group product-qty">
                              <span class="input-group-btn">
                                  <button type="button" class="quantity-left-minus btn btn-danger btn-number" data-type="minus">
                                    <svg width="16" height="16"><use xlink:href="#minus"></use></svg>
                                  </button>
                              </span>
                              <input type="text" id="quantity" name="quantity" class="form-control input-number" value="1">
                              <span class="input-group-btn">
                                  <button type="button" class="quantity-right-plus btn btn-success btn-number" data-type="plus">
                                      <svg width="16" height="16"><use xlink:href="#plus"></use></svg>
                                  </button>
                              </span>
                          </div>
                          <a href="#" class="nav-link">Add to Cart <iconify-icon icon="uil:shopping-cart"></a>
                        </div>
                      </div>
                    </div>

                    <div class="col">
                      <div class="product-item">
                        <a href="#" class="btn-wishlist"><svg width="24" height="24"><use xlink:href="#heart"></use></svg></a>
                        <figure>
                          <a href="index.html" title="Product Title">
                            <img src="images/thumb-milk.png"  class="tab-image">
                          </a>
                        </figure>
                        <h3>Sunstar Fresh Melon Juice</h3>
                        <span class="qty">1 Unit</span><span class="rating"><svg width="24" height="24" class="text-primary"><use xlink:href="#star-solid"></use></svg> 4.5</span>
                        <span class="price">$18.00</span>
                        <div class="d-flex align-items-center justify-content-between">
                          <div class="input-group product-qty">
                              <span class="input-group-btn">
                                  <button type="button" class="quantity-left-minus btn btn-danger btn-number" data-type="minus">
                                    <svg width="16" height="16"><use xlink:href="#minus"></use></svg>
                                  </button>
                              </span>
                              <input type="text" id="quantity" name="quantity" class="form-control input-number" value="1">
                              <span class="input-group-btn">
                                  <button type="button" class="quantity-right-plus btn btn-success btn-number" data-type="plus">
                                      <svg width="16" height="16"><use xlink:href="#plus"></use></svg>
                                  </button>
                              </span>
                          </div>
                          <a href="#" class="nav-link">Add to Cart <iconify-icon icon="uil:shopping-cart"></a>
                        </div>
                      </div>
                    </div>

                    <div class="col">
                      <div class="product-item">
                        <a href="#" class="btn-wishlist"><svg width="24" height="24"><use xlink:href="#heart"></use></svg></a>
                        <figure>
                          <a href="index.html" title="Product Title">
                            <img src="images/thumb-bananas.png"  class="tab-image">
                          </a>
                        </figure>
                        <h3>Sunstar Fresh Melon Juice</h3>
                        <span class="qty">1 Unit</span><span class="rating"><svg width="24" height="24" class="text-primary"><use xlink:href="#star-solid"></use></svg> 4.5</span>
                        <span class="price">$18.00</span>
                        <div class="d-flex align-items-center justify-content-between">
                          <div class="input-group product-qty">
                              <span class="input-group-btn">
                                  <button type="button" class="quantity-left-minus btn btn-danger btn-number" data-type="minus">
                                    <svg width="16" height="16"><use xlink:href="#minus"></use></svg>
                                  </button>
                              </span>
                              <input type="text" id="quantity" name="quantity" class="form-control input-number" value="1">
                              <span class="input-group-btn">
                                  <button type="button" class="quantity-right-plus btn btn-success btn-number" data-type="plus">
                                      <svg width="16" height="16"><use xlink:href="#plus"></use></svg>
                                  </button>
                              </span>
                          </div>
                          <a href="#" class="nav-link">Add to Cart <iconify-icon icon="uil:shopping-cart"></a>
                        </div>
                      </div>
                    </div>

                    <div class="col">
                      <div class="product-item">
                        <a href="#" class="btn-wishlist"><svg width="24" height="24"><use xlink:href="#heart"></use></svg></a>
                        <figure>
                          <a href="index.html" title="Product Title">
                            <img src="images/thumb-biscuits.png"  class="tab-image">
                          </a>
                        </figure>
                        <h3>Sunstar Fresh Melon Juice</h3>
                        <span class="qty">1 Unit</span><span class="rating"><svg width="24" height="24" class="text-primary"><use xlink:href="#star-solid"></use></svg> 4.5</span>
                        <span class="price">$18.00</span>
                        <div class="d-flex align-items-center justify-content-between">
                          <div class="input-group product-qty">
                              <span class="input-group-btn">
                                  <button type="button" class="quantity-left-minus btn btn-danger btn-number" data-type="minus">
                                    <svg width="16" height="16"><use xlink:href="#minus"></use></svg>
                                  </button>
                              </span>
                              <input type="text" id="quantity" name="quantity" class="form-control input-number" value="1">
                              <span class="input-group-btn">
                                  <button type="button" class="quantity-right-plus btn btn-success btn-number" data-type="plus">
                                      <svg width="16" height="16"><use xlink:href="#plus"></use></svg>
                                  </button>
                              </span>
                          </div>
                          <a href="#" class="nav-link">Add to Cart <iconify-icon icon="uil:shopping-cart"></a>
                        </div>
                      </div>
                    </div>

                  </div>
                  <!-- / product-grid -->
                  
                </div>

                <div class="tab-pane fade" id="nav-fruits" role="tabpanel" aria-labelledby="nav-fruits-tab">
                  
                  <div class="product-grid row row-cols-1 row-cols-sm-2 row-cols-md-3 row-cols-lg-4 row-cols-xl-5">

                    <div class="col">
                      <div class="product-item">
                        <span class="badge bg-success position-absolute m-3">-30%</span>
                        <a href="#" class="btn-wishlist"><svg width="24" height="24"><use xlink:href="#heart"></use></svg></a>
                        <figure>
                          <a href="index.html" title="Product Title">
                            <img src="images/thumb-cucumber.png"  class="tab-image">
                          </a>
                        </figure>
                        <h3>Sunstar Fresh Melon Juice</h3>
                        <span class="qty">1 Unit</span><span class="rating"><svg width="24" height="24" class="text-primary"><use xlink:href="#star-solid"></use></svg> 4.5</span>
                        <span class="price">$18.00</span>
                        <div class="d-flex align-items-center justify-content-between">
                          <div class="input-group product-qty">
                              <span class="input-group-btn">
                                  <button type="button" class="quantity-left-minus btn btn-danger btn-number" data-type="minus">
                                    <svg width="16" height="16"><use xlink:href="#minus"></use></svg>
                                  </button>
                              </span>
                              <input type="text" id="quantity" name="quantity" class="form-control input-number" value="1">
                              <span class="input-group-btn">
                                  <button type="button" class="quantity-right-plus btn btn-success btn-number" data-type="plus">
                                      <svg width="16" height="16"><use xlink:href="#plus"></use></svg>
                                  </button>
                              </span>
                          </div>
                          <a href="#" class="nav-link">Add to Cart <iconify-icon icon="uil:shopping-cart"></a>
                        </div>
                      </div>
                    </div>

                    <div class="col">
                      <div class="product-item">
                        <span class="badge bg-success position-absolute m-3">-30%</span>
                        <a href="#" class="btn-wishlist"><svg width="24" height="24"><use xlink:href="#heart"></use></svg></a>
                        <figure>
                          <a href="index.html" title="Product Title">
                            <img src="images/thumb-milk.png"  class="tab-image">
                          </a>
                        </figure>
                        <h3>Sunstar Fresh Melon Juice</h3>
                        <span class="qty">1 Unit</span><span class="rating"><svg width="24" height="24" class="text-primary"><use xlink:href="#star-solid"></use></svg> 4.5</span>
                        <span class="price">$18.00</span>
                        <div class="d-flex align-items-center justify-content-between">
                          <div class="input-group product-qty">
                              <span class="input-group-btn">
                                  <button type="button" class="quantity-left-minus btn btn-danger btn-number" data-type="minus">
                                    <svg width="16" height="16"><use xlink:href="#minus"></use></svg>
                                  </button>
                              </span>
                              <input type="text" id="quantity" name="quantity" class="form-control input-number" value="1">
                              <span class="input-group-btn">
                                  <button type="button" class="quantity-right-plus btn btn-success btn-number" data-type="plus">
                                      <svg width="16" height="16"><use xlink:href="#plus"></use></svg>
                                  </button>
                              </span>
                          </div>
                          <a href="#" class="nav-link">Add to Cart <iconify-icon icon="uil:shopping-cart"></a>
                        </div>
                      </div>
                    </div>
                  
                    <div class="col">
                      <div class="product-item">
                        <span class="badge bg-success position-absolute m-3">-30%</span>
                        <a href="#" class="btn-wishlist"><svg width="24" height="24"><use xlink:href="#heart"></use></svg></a>
                        <figure>
                          <a href="index.html" title="Product Title">
                            <img src="images/thumb-orange-juice.png"  class="tab-image">
                          </a>
                        </figure>
                        <h3>Sunstar Fresh Melon Juice</h3>
                        <span class="qty">1 Unit</span><span class="rating"><svg width="24" height="24" class="text-primary"><use xlink:href="#star-solid"></use></svg> 4.5</span>
                        <span class="price">$18.00</span>
                        <div class="d-flex align-items-center justify-content-between">
                          <div class="input-group product-qty">
                              <span class="input-group-btn">
                                  <button type="button" class="quantity-left-minus btn btn-danger btn-number" data-type="minus">
                                    <svg width="16" height="16"><use xlink:href="#minus"></use></svg>
                                  </button>
                              </span>
                              <input type="text" id="quantity" name="quantity" class="form-control input-number" value="1">
                              <span class="input-group-btn">
                                  <button type="button" class="quantity-right-plus btn btn-success btn-number" data-type="plus">
                                      <svg width="16" height="16"><use xlink:href="#plus"></use></svg>
                                  </button>
                              </span>
                          </div>
                          <a href="#" class="nav-link">Add to Cart <iconify-icon icon="uil:shopping-cart"></a>
                        </div>
                      </div>
                    </div>

                    <div class="col">
                      <div class="product-item">
                        <a href="#" class="btn-wishlist"><svg width="24" height="24"><use xlink:href="#heart"></use></svg></a>
                        <figure>
                          <a href="index.html" title="Product Title">
                            <img src="images/thumb-raspberries.png"  class="tab-image">
                          </a>
                        </figure>
                        <h3>Sunstar Fresh Melon Juice</h3>
                        <span class="qty">1 Unit</span><span class="rating"><svg width="24" height="24" class="text-primary"><use xlink:href="#star-solid"></use></svg> 4.5</span>
                        <span class="price">$18.00</span>
                        <div class="d-flex align-items-center justify-content-between">
                          <div class="input-group product-qty">
                              <span class="input-group-btn">
                                  <button type="button" class="quantity-left-minus btn btn-danger btn-number" data-type="minus">
                                    <svg width="16" height="16"><use xlink:href="#minus"></use></svg>
                                  </button>
                              </span>
                              <input type="text" id="quantity" name="quantity" class="form-control input-number" value="1">
                              <span class="input-group-btn">
                                  <button type="button" class="quantity-right-plus btn btn-success btn-number" data-type="plus">
                                      <svg width="16" height="16"><use xlink:href="#plus"></use></svg>
                                  </button>
                              </span>
                          </div>
                          <a href="#" class="nav-link">Add to Cart <iconify-icon icon="uil:shopping-cart"></a>
                        </div>
                      </div>
                    </div>

                    <div class="col">
                      <div class="product-item">
                        <a href="#" class="btn-wishlist"><svg width="24" height="24"><use xlink:href="#heart"></use></svg></a>
                        <figure>
                          <a href="index.html" title="Product Title">
                            <img src="images/thumb-bananas.png"  class="tab-image">
                          </a>
                        </figure>
                        <h3>Sunstar Fresh Melon Juice</h3>
                        <span class="qty">1 Unit</span><span class="rating"><svg width="24" height="24" class="text-primary"><use xlink:href="#star-solid"></use></svg> 4.5</span>
                        <span class="price">$18.00</span>
                        <div class="d-flex align-items-center justify-content-between">
                          <div class="input-group product-qty">
                              <span class="input-group-btn">
                                  <button type="button" class="quantity-left-minus btn btn-danger btn-number" data-type="minus">
                                    <svg width="16" height="16"><use xlink:href="#minus"></use></svg>
                                  </button>
                              </span>
                              <input type="text" id="quantity" name="quantity" class="form-control input-number" value="1">
                              <span class="input-group-btn">
                                  <button type="button" class="quantity-right-plus btn btn-success btn-number" data-type="plus">
                                      <svg width="16" height="16"><use xlink:href="#plus"></use></svg>
                                  </button>
                              </span>
                          </div>
                          <a href="#" class="nav-link">Add to Cart <iconify-icon icon="uil:shopping-cart"></a>
                        </div>
                      </div>
                    </div>

                    <div class="col">
                      <div class="product-item">
                        <a href="#" class="btn-wishlist"><svg width="24" height="24"><use xlink:href="#heart"></use></svg></a>
                        <figure>
                          <a href="index.html" title="Product Title">
                            <img src="images/thumb-bananas.png"  class="tab-image">
                          </a>
                        </figure>
                        <h3>Sunstar Fresh Melon Juice</h3>
                        <span class="qty">1 Unit</span><span class="rating"><svg width="24" height="24" class="text-primary"><use xlink:href="#star-solid"></use></svg> 4.5</span>
                        <span class="price">$18.00</span>
                        <div class="d-flex align-items-center justify-content-between">
                          <div class="input-group product-qty">
                              <span class="input-group-btn">
                                  <button type="button" class="quantity-left-minus btn btn-danger btn-number" data-type="minus">
                                    <svg width="16" height="16"><use xlink:href="#minus"></use></svg>
                                  </button>
                              </span>
                              <input type="text" id="quantity" name="quantity" class="form-control input-number" value="1">
                              <span class="input-group-btn">
                                  <button type="button" class="quantity-right-plus btn btn-success btn-number" data-type="plus">
                                      <svg width="16" height="16"><use xlink:href="#plus"></use></svg>
                                  </button>
                              </span>
                          </div>
                          <a href="#" class="nav-link">Add to Cart <iconify-icon icon="uil:shopping-cart"></a>
                        </div>
                      </div>
                    </div>

                  </div>
                  <!-- / product-grid -->

                </div>
                <div class="tab-pane fade" id="nav-juices" role="tabpanel" aria-labelledby="nav-juices-tab">

                  <div class="product-grid row row-cols-1 row-cols-sm-2 row-cols-md-3 row-cols-lg-4 row-cols-xl-5">

                    <div class="col">
                      <div class="product-item">
                        <a href="#" class="btn-wishlist"><svg width="24" height="24"><use xlink:href="#heart"></use></svg></a>
                        <figure>
                          <a href="index.html" title="Product Title">
                            <img src="images/thumb-cucumber.png"  class="tab-image">
                          </a>
                        </figure>
                        <h3>Sunstar Fresh Melon Juice</h3>
                        <span class="qty">1 Unit</span><span class="rating"><svg width="24" height="24" class="text-primary"><use xlink:href="#star-solid"></use></svg> 4.5</span>
                        <span class="price">$18.00</span>
                        <div class="d-flex align-items-center justify-content-between">
                          <div class="input-group product-qty">
                              <span class="input-group-btn">
                                  <button type="button" class="quantity-left-minus btn btn-danger btn-number" data-type="minus">
                                    <svg width="16" height="16"><use xlink:href="#minus"></use></svg>
                                  </button>
                              </span>
                              <input type="text" id="quantity" name="quantity" class="form-control input-number" value="1">
                              <span class="input-group-btn">
                                  <button type="button" class="quantity-right-plus btn btn-success btn-number" data-type="plus">
                                      <svg width="16" height="16"><use xlink:href="#plus"></use></svg>
                                  </button>
                              </span>
                          </div>
                          <a href="#" class="nav-link">Add to Cart <iconify-icon icon="uil:shopping-cart"></a>
                        </div>
                      </div>
                    </div>

                    <div class="col">
                      <div class="product-item">
                        <a href="#" class="btn-wishlist"><svg width="24" height="24"><use xlink:href="#heart"></use></svg></a>
                        <figure>
                          <a href="index.html" title="Product Title">
                            <img src="images/thumb-milk.png"  class="tab-image">
                          </a>
                        </figure>
                        <h3>Sunstar Fresh Melon Juice</h3>
                        <span class="qty">1 Unit</span><span class="rating"><svg width="24" height="24" class="text-primary"><use xlink:href="#star-solid"></use></svg> 4.5</span>
                        <span class="price">$18.00</span>
                        <div class="d-flex align-items-center justify-content-between">
                          <div class="input-group product-qty">
                              <span class="input-group-btn">
                                  <button type="button" class="quantity-left-minus btn btn-danger btn-number" data-type="minus">
                                    <svg width="16" height="16"><use xlink:href="#minus"></use></svg>
                                  </button>
                              </span>
                              <input type="text" id="quantity" name="quantity" class="form-control input-number" value="1">
                              <span class="input-group-btn">
                                  <button type="button" class="quantity-right-plus btn btn-success btn-number" data-type="plus">
                                      <svg width="16" height="16"><use xlink:href="#plus"></use></svg>
                                  </button>
                              </span>
                          </div>
                          <a href="#" class="nav-link">Add to Cart <iconify-icon icon="uil:shopping-cart"></a>
                        </div>
                      </div>
                    </div>
                  
                    <div class="col">
                      <div class="product-item">
                        <a href="#" class="btn-wishlist"><svg width="24" height="24"><use xlink:href="#heart"></use></svg></a>
                        <figure>
                          <a href="index.html" title="Product Title">
                            <img src="images/thumb-tomatoes.png"  class="tab-image">
                          </a>
                        </figure>
                        <h3>Sunstar Fresh Melon Juice</h3>
                        <span class="qty">1 Unit</span><span class="rating"><svg width="24" height="24" class="text-primary"><use xlink:href="#star-solid"></use></svg> 4.5</span>
                        <span class="price">$18.00</span>
                        <div class="d-flex align-items-center justify-content-between">
                          <div class="input-group product-qty">
                              <span class="input-group-btn">
                                  <button type="button" class="quantity-left-minus btn btn-danger btn-number" data-type="minus">
                                    <svg width="16" height="16"><use xlink:href="#minus"></use></svg>
                                  </button>
                              </span>
                              <input type="text" id="quantity" name="quantity" class="form-control input-number" value="1">
                              <span class="input-group-btn">
                                  <button type="button" class="quantity-right-plus btn btn-success btn-number" data-type="plus">
                                      <svg width="16" height="16"><use xlink:href="#plus"></use></svg>
                                  </button>
                              </span>
                          </div>
                          <a href="#" class="nav-link">Add to Cart <iconify-icon icon="uil:shopping-cart"></a>
                        </div>
                      </div>
                    </div>

                    <div class="col">
                      <div class="product-item">
                        <a href="#" class="btn-wishlist"><svg width="24" height="24"><use xlink:href="#heart"></use></svg></a>
                        <figure>
                          <a href="index.html" title="Product Title">
                            <img src="images/thumb-tomatoketchup.png"  class="tab-image">
                          </a>
                        </figure>
                        <h3>Sunstar Fresh Melon Juice</h3>
                        <span class="qty">1 Unit</span><span class="rating"><svg width="24" height="24" class="text-primary"><use xlink:href="#star-solid"></use></svg> 4.5</span>
                        <span class="price">$18.00</span>
                        <div class="d-flex align-items-center justify-content-between">
                          <div class="input-group product-qty">
                              <span class="input-group-btn">
                                  <button type="button" class="quantity-left-minus btn btn-danger btn-number" data-type="minus">
                                    <svg width="16" height="16"><use xlink:href="#minus"></use></svg>
                                  </button>
                              </span>
                              <input type="text" id="quantity" name="quantity" class="form-control input-number" value="1">
                              <span class="input-group-btn">
                                  <button type="button" class="quantity-right-plus btn btn-success btn-number" data-type="plus">
                                      <svg width="16" height="16"><use xlink:href="#plus"></use></svg>
                                  </button>
                              </span>
                          </div>
                          <a href="#" class="nav-link">Add to Cart <iconify-icon icon="uil:shopping-cart"></a>
                        </div>
                      </div>
                    </div>

                    <div class="col">
                      <div class="product-item">
                        <a href="#" class="btn-wishlist"><svg width="24" height="24"><use xlink:href="#heart"></use></svg></a>
                        <figure>
                          <a href="index.html" title="Product Title">
                            <img src="images/thumb-bananas.png"  class="tab-image">
                          </a>
                        </figure>
                        <h3>Sunstar Fresh Melon Juice</h3>
                        <span class="qty">1 Unit</span><span class="rating"><svg width="24" height="24" class="text-primary"><use xlink:href="#star-solid"></use></svg> 4.5</span>
                        <span class="price">$18.00</span>
                        <div class="d-flex align-items-center justify-content-between">
                          <div class="input-group product-qty">
                              <span class="input-group-btn">
                                  <button type="button" class="quantity-left-minus btn btn-danger btn-number" data-type="minus">
                                    <svg width="16" height="16"><use xlink:href="#minus"></use></svg>
                                  </button>
                              </span>
                              <input type="text" id="quantity" name="quantity" class="form-control input-number" value="1">
                              <span class="input-group-btn">
                                  <button type="button" class="quantity-right-plus btn btn-success btn-number" data-type="plus">
                                      <svg width="16" height="16"><use xlink:href="#plus"></use></svg>
                                  </button>
                              </span>
                          </div>
                          <a href="#" class="nav-link">Add to Cart <iconify-icon icon="uil:shopping-cart"></a>
                        </div>
                      </div>
                    </div>

                    <div class="col">
                      <div class="product-item">
                        <a href="#" class="btn-wishlist"><svg width="24" height="24"><use xlink:href="#heart"></use></svg></a>
                        <figure>
                          <a href="index.html" title="Product Title">
                            <img src="images/thumb-bananas.png"  class="tab-image">
                          </a>
                        </figure>
                        <h3>Sunstar Fresh Melon Juice</h3>
                        <span class="qty">1 Unit</span><span class="rating"><svg width="24" height="24" class="text-primary"><use xlink:href="#star-solid"></use></svg> 4.5</span>
                        <span class="price">$18.00</span>
                        <div class="d-flex align-items-center justify-content-between">
                          <div class="input-group product-qty">
                              <span class="input-group-btn">
                                  <button type="button" class="quantity-left-minus btn btn-danger btn-number" data-type="minus">
                                    <svg width="16" height="16"><use xlink:href="#minus"></use></svg>
                                  </button>
                              </span>
                              <input type="text" id="quantity" name="quantity" class="form-control input-number" value="1">
                              <span class="input-group-btn">
                                  <button type="button" class="quantity-right-plus btn btn-success btn-number" data-type="plus">
                                      <svg width="16" height="16"><use xlink:href="#plus"></use></svg>
                                  </button>
                              </span>
                          </div>
                          <a href="#" class="nav-link">Add to Cart <iconify-icon icon="uil:shopping-cart"></a>
                        </div>
                      </div>
                    </div>

                  </div>
                  <!-- / product-grid -->
                  
                </div>
                
              </div>
            </div>

          </div>
        </div>
      </div>
    </section>
__FOODMART_RAW_EOF__;

        if ($sectionTitle !== null) {
            $markup = str_replace('<h3>Trending Products</h3>', '<h3>'.$this->foodmartEscape($sectionTitle).'</h3>', $markup);
        }

        return $this->decorateAccessibility($markup);
    }

    /**
     * Byte-exact Golden Reference markup (Phase 0/2A) — see
     * _reference-themes/FoodMart-main/FoodMart-main/index.html. Extracted
     * once during Phase 2A authoring; never re-read at runtime.
     */
    protected function renderFoodmartPromoBanners(array $props, string $inner, string $id): string
    {
        $markup = <<<'__FOODMART_RAW_EOF__'
<section class="py-5">
      <div class="container-fluid">
        <div class="row">
          
          <div class="col-md-6">
            <div class="banner-ad bg-danger mb-3" style="background: url('images/ad-image-3.png');background-repeat: no-repeat;background-position: right bottom;">
              <div class="banner-content p-5">

                <div class="categories text-primary fs-3 fw-bold">Upto 25% Off</div>
                <h3 class="banner-title">Luxa Dark Chocolate</h3>
                <p>Very tasty & creamy vanilla flavour creamy muffins.</p>
                <a href="#" class="btn btn-dark text-uppercase">Show Now</a>

              </div>
            
            </div>
          </div>
          <div class="col-md-6">
            <div class="banner-ad bg-info" style="background: url('images/ad-image-4.png');background-repeat: no-repeat;background-position: right bottom;">
              <div class="banner-content p-5">

                <div class="categories text-primary fs-3 fw-bold">Upto 25% Off</div>
                <h3 class="banner-title">Creamy Muffins</h3>
                <p>Very tasty & creamy vanilla flavour creamy muffins.</p>
                <a href="#" class="btn btn-dark text-uppercase">Show Now</a>

              </div>
            
            </div>
          </div>
             
        </div>
      </div>
    </section>
__FOODMART_RAW_EOF__;

        if (is_string($props['section_title'] ?? null) && trim($props['section_title']) !== '') {
            $heading = '<h2 class="section-title mb-4">'.$this->foodmartEscape($props['section_title']).'</h2>';
            $markup = str_replace('<div class="container-fluid">', '<div class="container-fluid">'.$heading, $markup);
        }

        // Phase 4B.6 — manual promo items: same two-banner anatomy, all
        // visible content prop-driven. One item renders as a single banner;
        // two render as the canonical col-md-6 pair.
        $items = $this->foodmartManualItems($props, 'items');
        if ($items !== null) {
            $banners = '';
            $spans = ['col-md-6', 'col-md-6'];
            foreach ($items as $index => $item) {
                $span = $spans[$index] ?? 'col-md-6';
                $bg = $this->foodmartEditableSrc((string) ($item['image'] ?? ''));
                $badge = $this->foodmartEscape((string) ($item['badge'] ?? ''));
                $title = $this->foodmartEscape((string) ($item['title'] ?? ''));
                $description = $this->foodmartEscape((string) ($item['description'] ?? ''));
                $ctaLabel = $this->foodmartEscape((string) ($item['cta_label'] ?? ''));
                $ctaUrl = $this->foodmartEditableHrefValue((string) ($item['cta_url'] ?? '#'));
                $banners .= '<div class="'.$span.'" data-tb-logical-group="items" data-tb-logical-index="'.(int) $index.'">'
                    .'<div class="banner-ad bg-danger mb-3" style="background: url(\''.$bg.'\');background-repeat: no-repeat;background-position: right bottom;">'
                    .'<div class="banner-content p-5">'
                    .($badge !== '' ? '<div class="categories text-primary fs-3 fw-bold">'.$badge.'</div>' : '')
                    .'<h3 class="banner-title">'.$title.'</h3>'
                    .($description !== '' ? '<p>'.$description.'</p>' : '')
                    .($ctaLabel !== '' ? '<a href="'.$ctaUrl.'" class="btn btn-dark text-uppercase">'.$ctaLabel.'</a>' : '')
                    .'</div></div></div>';
            }
            $markup = '<section class="py-5"><div class="container-fluid">'
                .(is_string($props['section_title'] ?? null) && trim($props['section_title']) !== '' ? $heading : '')
                .'<div class="row">'.$banners.'</div></div></section>';
        }

        return $this->decorateAccessibility($markup);
    }

    /**
     * Byte-exact Golden Reference markup (Phase 0/2A) — see
     * _reference-themes/FoodMart-main/FoodMart-main/index.html. Extracted
     * once during Phase 2A authoring; never re-read at runtime.
     */
    protected function renderFoodmartProductCarouselBestSelling(array $props, string $inner, string $id): string
    {
        $sectionTitle = is_string($props['section_title'] ?? null) && trim($props['section_title']) !== '' ? $props['section_title'] : null;
        if (($binding = $this->foodmartResolvedBinding($id, 'products')) !== null) {
            return $this->renderFoodmartBoundProducts($binding, 'Best Selling Products', true, $sectionTitle);
        }
        if (($manual = $this->foodmartManualItems($props, 'items')) !== null) {
            return $this->renderFoodmartManualProducts($manual, 'Best Selling Products', true, $sectionTitle);
        }

        $markup = <<<'__FOODMART_RAW_EOF__'
<section class="py-5 overflow-hidden">
      <div class="container-fluid">
        <div class="row">
          <div class="col-md-12">

            <div class="section-header d-flex flex-wrap justify-content-between my-5">
              
              <h2 class="section-title">Best selling products</h2>

              <div class="d-flex align-items-center">
                <a href="#" class="btn-link text-decoration-none">View All Categories →</a>
                <div class="swiper-buttons">
                  <button class="swiper-prev products-carousel-prev btn btn-primary">❮</button>
                  <button class="swiper-next products-carousel-next btn btn-primary">❯</button>
                </div>  
              </div>
            </div>
            
          </div>
        </div>
        <div class="row">
          <div class="col-md-12">

            <div class="products-carousel swiper">
              <div class="swiper-wrapper">
                
                <div class="product-item swiper-slide">
                  <span class="badge bg-success position-absolute m-3">-15%</span>
                  <a href="#" class="btn-wishlist"><svg width="24" height="24"><use xlink:href="#heart"></use></svg></a>
                  <figure>
                    <a href="index.html" title="Product Title">
                      <img src="images/thumb-tomatoes.png"  class="tab-image">
                    </a>
                  </figure>
                  <h3>Sunstar Fresh Melon Juice</h3>
                  <span class="qty">1 Unit</span><span class="rating"><svg width="24" height="24" class="text-primary"><use xlink:href="#star-solid"></use></svg> 4.5</span>
                  <span class="price">$18.00</span>
                  <div class="d-flex align-items-center justify-content-between">
                    <div class="input-group product-qty">
                        <span class="input-group-btn">
                            <button type="button" class="quantity-left-minus btn btn-danger btn-number" data-type="minus">
                              <svg width="16" height="16"><use xlink:href="#minus"></use></svg>
                            </button>
                        </span>
                        <input type="text" id="quantity" name="quantity" class="form-control input-number" value="1">
                        <span class="input-group-btn">
                            <button type="button" class="quantity-right-plus btn btn-success btn-number" data-type="plus">
                                <svg width="16" height="16"><use xlink:href="#plus"></use></svg>
                            </button>
                        </span>
                    </div>
                    <a href="#" class="nav-link">Add to Cart <iconify-icon icon="uil:shopping-cart"></a>
                  </div>
                </div>

                <div class="product-item swiper-slide">
                  <span class="badge bg-success position-absolute m-3">-15%</span>
                  <a href="#" class="btn-wishlist"><svg width="24" height="24"><use xlink:href="#heart"></use></svg></a>
                  <figure>
                    <a href="index.html" title="Product Title">
                      <img src="images/thumb-tomatoketchup.png"  class="tab-image">
                    </a>
                  </figure>
                  <h3>Sunstar Fresh Melon Juice</h3>
                  <span class="qty">1 Unit</span><span class="rating"><svg width="24" height="24" class="text-primary"><use xlink:href="#star-solid"></use></svg> 4.5</span>
                  <span class="price">$18.00</span>
                  <div class="d-flex align-items-center justify-content-between">
                    <div class="input-group product-qty">
                        <span class="input-group-btn">
                            <button type="button" class="quantity-left-minus btn btn-danger btn-number" data-type="minus">
                              <svg width="16" height="16"><use xlink:href="#minus"></use></svg>
                            </button>
                        </span>
                        <input type="text" id="quantity" name="quantity" class="form-control input-number" value="1">
                        <span class="input-group-btn">
                            <button type="button" class="quantity-right-plus btn btn-success btn-number" data-type="plus">
                                <svg width="16" height="16"><use xlink:href="#plus"></use></svg>
                            </button>
                        </span>
                    </div>
                    <a href="#" class="nav-link">Add to Cart <iconify-icon icon="uil:shopping-cart"></a>
                  </div>
                </div>

                <div class="product-item swiper-slide">
                  <span class="badge bg-success position-absolute m-3">-15%</span>
                  <a href="#" class="btn-wishlist"><svg width="24" height="24"><use xlink:href="#heart"></use></svg></a>
                  <figure>
                    <a href="index.html" title="Product Title">
                      <img src="images/thumb-bananas.png"  class="tab-image">
                    </a>
                  </figure>
                  <h3>Sunstar Fresh Melon Juice</h3>
                  <span class="qty">1 Unit</span><span class="rating"><svg width="24" height="24" class="text-primary"><use xlink:href="#star-solid"></use></svg> 4.5</span>
                  <span class="price">$18.00</span>
                  <div class="d-flex align-items-center justify-content-between">
                    <div class="input-group product-qty">
                        <span class="input-group-btn">
                            <button type="button" class="quantity-left-minus btn btn-danger btn-number" data-type="minus">
                              <svg width="16" height="16"><use xlink:href="#minus"></use></svg>
                            </button>
                        </span>
                        <input type="text" id="quantity" name="quantity" class="form-control input-number" value="1">
                        <span class="input-group-btn">
                            <button type="button" class="quantity-right-plus btn btn-success btn-number" data-type="plus">
                                <svg width="16" height="16"><use xlink:href="#plus"></use></svg>
                            </button>
                        </span>
                    </div>
                    <a href="#" class="nav-link">Add to Cart <iconify-icon icon="uil:shopping-cart"></a>
                  </div>
                </div>

                <div class="product-item swiper-slide">
                  <span class="badge bg-success position-absolute m-3">-15%</span>
                  <a href="#" class="btn-wishlist"><svg width="24" height="24"><use xlink:href="#heart"></use></svg></a>
                  <figure>
                    <a href="index.html" title="Product Title">
                      <img src="images/thumb-bananas.png"  class="tab-image">
                    </a>
                  </figure>
                  <h3>Sunstar Fresh Melon Juice</h3>
                  <span class="qty">1 Unit</span><span class="rating"><svg width="24" height="24" class="text-primary"><use xlink:href="#star-solid"></use></svg> 4.5</span>
                  <span class="price">$18.00</span>
                  <div class="d-flex align-items-center justify-content-between">
                    <div class="input-group product-qty">
                        <span class="input-group-btn">
                            <button type="button" class="quantity-left-minus btn btn-danger btn-number" data-type="minus">
                              <svg width="16" height="16"><use xlink:href="#minus"></use></svg>
                            </button>
                        </span>
                        <input type="text" id="quantity" name="quantity" class="form-control input-number" value="1">
                        <span class="input-group-btn">
                            <button type="button" class="quantity-right-plus btn btn-success btn-number" data-type="plus">
                                <svg width="16" height="16"><use xlink:href="#plus"></use></svg>
                            </button>
                        </span>
                    </div>
                    <a href="#" class="nav-link">Add to Cart <iconify-icon icon="uil:shopping-cart"></a>
                  </div>
                </div>
                <div class="product-item swiper-slide">
                  <a href="#" class="btn-wishlist"><svg width="24" height="24"><use xlink:href="#heart"></use></svg></a>
                  <figure>
                    <a href="index.html" title="Product Title">
                      <img src="images/thumb-tomatoes.png"  class="tab-image">
                    </a>
                  </figure>
                  <h3>Sunstar Fresh Melon Juice</h3>
                  <span class="qty">1 Unit</span><span class="rating"><svg width="24" height="24" class="text-primary"><use xlink:href="#star-solid"></use></svg> 4.5</span>
                  <span class="price">$18.00</span>
                  <div class="d-flex align-items-center justify-content-between">
                    <div class="input-group product-qty">
                        <span class="input-group-btn">
                            <button type="button" class="quantity-left-minus btn btn-danger btn-number" data-type="minus">
                              <svg width="16" height="16"><use xlink:href="#minus"></use></svg>
                            </button>
                        </span>
                        <input type="text" id="quantity" name="quantity" class="form-control input-number" value="1">
                        <span class="input-group-btn">
                            <button type="button" class="quantity-right-plus btn btn-success btn-number" data-type="plus">
                                <svg width="16" height="16"><use xlink:href="#plus"></use></svg>
                            </button>
                        </span>
                    </div>
                    <a href="#" class="nav-link">Add to Cart <iconify-icon icon="uil:shopping-cart"></a>
                  </div>
                </div>

                <div class="product-item swiper-slide">
                  <a href="#" class="btn-wishlist"><svg width="24" height="24"><use xlink:href="#heart"></use></svg></a>
                  <figure>
                    <a href="index.html" title="Product Title">
                      <img src="images/thumb-tomatoketchup.png"  class="tab-image">
                    </a>
                  </figure>
                  <h3>Sunstar Fresh Melon Juice</h3>
                  <span class="qty">1 Unit</span><span class="rating"><svg width="24" height="24" class="text-primary"><use xlink:href="#star-solid"></use></svg> 4.5</span>
                  <span class="price">$18.00</span>
                  <div class="d-flex align-items-center justify-content-between">
                    <div class="input-group product-qty">
                        <span class="input-group-btn">
                            <button type="button" class="quantity-left-minus btn btn-danger btn-number" data-type="minus">
                              <svg width="16" height="16"><use xlink:href="#minus"></use></svg>
                            </button>
                        </span>
                        <input type="text" id="quantity" name="quantity" class="form-control input-number" value="1">
                        <span class="input-group-btn">
                            <button type="button" class="quantity-right-plus btn btn-success btn-number" data-type="plus">
                                <svg width="16" height="16"><use xlink:href="#plus"></use></svg>
                            </button>
                        </span>
                    </div>
                    <a href="#" class="nav-link">Add to Cart <iconify-icon icon="uil:shopping-cart"></a>
                  </div>
                </div>

                <div class="product-item swiper-slide">
                  <a href="#" class="btn-wishlist"><svg width="24" height="24"><use xlink:href="#heart"></use></svg></a>
                  <figure>
                    <a href="index.html" title="Product Title">
                      <img src="images/thumb-bananas.png"  class="tab-image">
                    </a>
                  </figure>
                  <h3>Sunstar Fresh Melon Juice</h3>
                  <span class="qty">1 Unit</span><span class="rating"><svg width="24" height="24" class="text-primary"><use xlink:href="#star-solid"></use></svg> 4.5</span>
                  <span class="price">$18.00</span>
                  <div class="d-flex align-items-center justify-content-between">
                    <div class="input-group product-qty">
                        <span class="input-group-btn">
                            <button type="button" class="quantity-left-minus btn btn-danger btn-number" data-type="minus">
                              <svg width="16" height="16"><use xlink:href="#minus"></use></svg>
                            </button>
                        </span>
                        <input type="text" id="quantity" name="quantity" class="form-control input-number" value="1">
                        <span class="input-group-btn">
                            <button type="button" class="quantity-right-plus btn btn-success btn-number" data-type="plus">
                                <svg width="16" height="16"><use xlink:href="#plus"></use></svg>
                            </button>
                        </span>
                    </div>
                    <a href="#" class="nav-link">Add to Cart <iconify-icon icon="uil:shopping-cart"></a>
                  </div>
                </div>

                <div class="product-item swiper-slide">
                  <a href="#" class="btn-wishlist"><svg width="24" height="24"><use xlink:href="#heart"></use></svg></a>
                  <figure>
                    <a href="index.html" title="Product Title">
                      <img src="images/thumb-bananas.png"  class="tab-image">
                    </a>
                  </figure>
                  <h3>Sunstar Fresh Melon Juice</h3>
                  <span class="qty">1 Unit</span><span class="rating"><svg width="24" height="24" class="text-primary"><use xlink:href="#star-solid"></use></svg> 4.5</span>
                  <span class="price">$18.00</span>
                  <div class="d-flex align-items-center justify-content-between">
                    <div class="input-group product-qty">
                        <span class="input-group-btn">
                            <button type="button" class="quantity-left-minus btn btn-danger btn-number" data-type="minus">
                              <svg width="16" height="16"><use xlink:href="#minus"></use></svg>
                            </button>
                        </span>
                        <input type="text" id="quantity" name="quantity" class="form-control input-number" value="1">
                        <span class="input-group-btn">
                            <button type="button" class="quantity-right-plus btn btn-success btn-number" data-type="plus">
                                <svg width="16" height="16"><use xlink:href="#plus"></use></svg>
                            </button>
                        </span>
                    </div>
                    <a href="#" class="nav-link">Add to Cart <iconify-icon icon="uil:shopping-cart"></a>
                  </div>
                </div>
                
              </div>
            </div>
            <!-- / products-carousel -->

          </div>
        </div>
      </div>
    </section>
__FOODMART_RAW_EOF__;

        if ($sectionTitle !== null) {
            $markup = str_replace('<h2 class="section-title">Best selling products</h2>', '<h2 class="section-title">'.$this->foodmartEscape($sectionTitle).'</h2>', $markup);
        }

        return $this->decorateAccessibility($markup);
    }

    /**
     * Byte-exact Golden Reference markup (Phase 0/2A) — see
     * _reference-themes/FoodMart-main/FoodMart-main/index.html. Extracted
     * once during Phase 2A authoring; never re-read at runtime. Zero prop
     * usage by design (Phase 2A is static-only, see Step 8).
     */
    protected function renderFoodmartNewsletter(array $props, string $inner, string $id): string
    {
        $markup = <<<'__FOODMART_RAW_EOF__'
<section class="py-5">
      <div class="container-fluid">

        <div class="bg-secondary py-5 my-5 rounded-5" style="background: url('images/bg-leaves-img-pattern.png') no-repeat;">
          <div class="container my-5">
            <div class="row">
              <div class="col-md-6 p-5">
                <div class="section-header">
                  <h2 class="section-title display-4">Get <span class="text-primary">25% Discount</span> on your first purchase</h2>
                </div>
                <p>Lorem ipsum dolor sit amet, consectetur adipiscing elit. Dictumst amet, metus, sit massa posuere maecenas. At tellus ut nunc amet vel egestas.</p>
              </div>
              <div class="col-md-6 p-5">
                <form>
                  <div class="mb-3">
                    <label for="name" class="form-label">Name</label>
                    <input type="text"
                      class="form-control form-control-lg" name="name" id="name" placeholder="Name">
                  </div>
                  <div class="mb-3">
                    <label for="" class="form-label">Email</label>
                    <input type="email" class="form-control form-control-lg" name="email" id="email" placeholder="abc@mail.com">
                  </div>
                  <div class="form-check form-check-inline mb-3">
                    <label class="form-check-label" for="subscribe">
                    <input class="form-check-input" type="checkbox" id="subscribe" value="subscribe">
                    Subscribe to the newsletter</label>
                  </div>
                  <div class="d-grid gap-2">
                    <button type="submit" class="btn btn-dark btn-lg">Submit</button>
                  </div>
                </form>
                
              </div>
              
            </div>
            
          </div>
        </div>
        
      </div>
    </section>
__FOODMART_RAW_EOF__;

        $replacements = [
            'title' => ['<h2 class="section-title display-4">Get <span class="text-primary">25% Discount</span> on your first purchase</h2>', '<h2 class="section-title display-4">%s</h2>'],
            'description' => ['<p>Lorem ipsum dolor sit amet, consectetur adipiscing elit. Dictumst amet, metus, sit massa posuere maecenas. At tellus ut nunc amet vel egestas.</p>', '<p>%s</p>'],
            'button_label' => ['<button type="submit" class="btn btn-dark btn-lg">Submit</button>', '<button type="submit" class="btn btn-dark btn-lg">%s</button>'],
            'name_label' => ['<label for="name" class="form-label">Name</label>', '<label for="name" class="form-label">%s</label>'],
            'name_placeholder' => ['placeholder="Name"', 'placeholder="%s"'],
            'email_label' => ['<label for="" class="form-label">Email</label>', '<label for="" class="form-label">%s</label>'],
            'email_placeholder' => ['placeholder="abc@mail.com"', 'placeholder="%s"'],
            'checkbox_label' => ['Subscribe to the newsletter</label>', '%s</label>'],
        ];
        foreach ($replacements as $key => [$needle, $format]) {
            if (is_string($props[$key] ?? null) && trim($props[$key]) !== '') {
                $markup = str_replace($needle, sprintf($format, $this->foodmartEscape($props[$key])), $markup);
            }
        }

        return $this->decorateAccessibility($markup);
    }

    /**
     * Byte-exact Golden Reference markup (Phase 0/2A) — see
     * _reference-themes/FoodMart-main/FoodMart-main/index.html. Extracted
     * once during Phase 2A authoring; never re-read at runtime. Zero prop
     * usage by design (Phase 2A is static-only, see Step 8).
     */
    protected function renderFoodmartProductCarouselMostPopular(array $props, string $inner, string $id): string
    {
        $sectionTitle = is_string($props['section_title'] ?? null) && trim($props['section_title']) !== '' ? $props['section_title'] : null;
        if (($binding = $this->foodmartResolvedBinding($id, 'products')) !== null) {
            return $this->renderFoodmartBoundProducts($binding, 'Most Popular Products', true, $sectionTitle);
        }
        if (($manual = $this->foodmartManualItems($props, 'items')) !== null) {
            return $this->renderFoodmartManualProducts($manual, 'Most Popular Products', true, $sectionTitle);
        }

        $markup = <<<'__FOODMART_RAW_EOF__'
<section class="py-5 overflow-hidden">
      <div class="container-fluid">
        <div class="row">
          <div class="col-md-12">

            <div class="section-header d-flex justify-content-between">
              
              <h2 class="section-title">Most popular products</h2>

              <div class="d-flex align-items-center">
                <a href="#" class="btn-link text-decoration-none">View All Categories →</a>
                <div class="swiper-buttons">
                  <button class="swiper-prev products-carousel-prev btn btn-primary">❮</button>
                  <button class="swiper-next products-carousel-next btn btn-primary">❯</button>
                </div>  
              </div>
            </div>
            
          </div>
        </div>
        <div class="row">
          <div class="col-md-12">

            <div class="products-carousel swiper">
              <div class="swiper-wrapper">
                
                <div class="product-item swiper-slide">
                  <a href="#" class="btn-wishlist"><svg width="24" height="24"><use xlink:href="#heart"></use></svg></a>
                  <figure>
                    <a href="index.html" title="Product Title">
                      <img src="images/thumb-tomatoes.png"  class="tab-image">
                    </a>
                  </figure>
                  <h3>Sunstar Fresh Melon Juice</h3>
                  <span class="qty">1 Unit</span><span class="rating"><svg width="24" height="24" class="text-primary"><use xlink:href="#star-solid"></use></svg> 4.5</span>
                  <span class="price">$18.00</span>
                  <div class="d-flex align-items-center justify-content-between">
                    <div class="input-group product-qty">
                        <span class="input-group-btn">
                            <button type="button" class="quantity-left-minus btn btn-danger btn-number" data-type="minus">
                              <svg width="16" height="16"><use xlink:href="#minus"></use></svg>
                            </button>
                        </span>
                        <input type="text" id="quantity" name="quantity" class="form-control input-number" value="1">
                        <span class="input-group-btn">
                            <button type="button" class="quantity-right-plus btn btn-success btn-number" data-type="plus">
                                <svg width="16" height="16"><use xlink:href="#plus"></use></svg>
                            </button>
                        </span>
                    </div>
                    <a href="#" class="nav-link">Add to Cart <iconify-icon icon="uil:shopping-cart"></a>
                  </div>
                </div>

                <div class="product-item swiper-slide">
                  <a href="#" class="btn-wishlist"><svg width="24" height="24"><use xlink:href="#heart"></use></svg></a>
                  <figure>
                    <a href="index.html" title="Product Title">
                      <img src="images/thumb-tomatoketchup.png"  class="tab-image">
                    </a>
                  </figure>
                  <h3>Sunstar Fresh Melon Juice</h3>
                  <span class="qty">1 Unit</span><span class="rating"><svg width="24" height="24" class="text-primary"><use xlink:href="#star-solid"></use></svg> 4.5</span>
                  <span class="price">$18.00</span>
                  <div class="d-flex align-items-center justify-content-between">
                    <div class="input-group product-qty">
                        <span class="input-group-btn">
                            <button type="button" class="quantity-left-minus btn btn-danger btn-number" data-type="minus">
                              <svg width="16" height="16"><use xlink:href="#minus"></use></svg>
                            </button>
                        </span>
                        <input type="text" id="quantity" name="quantity" class="form-control input-number" value="1">
                        <span class="input-group-btn">
                            <button type="button" class="quantity-right-plus btn btn-success btn-number" data-type="plus">
                                <svg width="16" height="16"><use xlink:href="#plus"></use></svg>
                            </button>
                        </span>
                    </div>
                    <a href="#" class="nav-link">Add to Cart <iconify-icon icon="uil:shopping-cart"></a>
                  </div>
                </div>

                <div class="product-item swiper-slide">
                  <a href="#" class="btn-wishlist"><svg width="24" height="24"><use xlink:href="#heart"></use></svg></a>
                  <figure>
                    <a href="index.html" title="Product Title">
                      <img src="images/thumb-bananas.png"  class="tab-image">
                    </a>
                  </figure>
                  <h3>Sunstar Fresh Melon Juice</h3>
                  <span class="qty">1 Unit</span><span class="rating"><svg width="24" height="24" class="text-primary"><use xlink:href="#star-solid"></use></svg> 4.5</span>
                  <span class="price">$18.00</span>
                  <div class="d-flex align-items-center justify-content-between">
                    <div class="input-group product-qty">
                        <span class="input-group-btn">
                            <button type="button" class="quantity-left-minus btn btn-danger btn-number" data-type="minus">
                              <svg width="16" height="16"><use xlink:href="#minus"></use></svg>
                            </button>
                        </span>
                        <input type="text" id="quantity" name="quantity" class="form-control input-number" value="1">
                        <span class="input-group-btn">
                            <button type="button" class="quantity-right-plus btn btn-success btn-number" data-type="plus">
                                <svg width="16" height="16"><use xlink:href="#plus"></use></svg>
                            </button>
                        </span>
                    </div>
                    <a href="#" class="nav-link">Add to Cart <iconify-icon icon="uil:shopping-cart"></a>
                  </div>
                </div>

                <div class="product-item swiper-slide">
                  <a href="#" class="btn-wishlist"><svg width="24" height="24"><use xlink:href="#heart"></use></svg></a>
                  <figure>
                    <a href="index.html" title="Product Title">
                      <img src="images/thumb-bananas.png"  class="tab-image">
                    </a>
                  </figure>
                  <h3>Sunstar Fresh Melon Juice</h3>
                  <span class="qty">1 Unit</span><span class="rating"><svg width="24" height="24" class="text-primary"><use xlink:href="#star-solid"></use></svg> 4.5</span>
                  <span class="price">$18.00</span>
                  <div class="d-flex align-items-center justify-content-between">
                    <div class="input-group product-qty">
                        <span class="input-group-btn">
                            <button type="button" class="quantity-left-minus btn btn-danger btn-number" data-type="minus">
                              <svg width="16" height="16"><use xlink:href="#minus"></use></svg>
                            </button>
                        </span>
                        <input type="text" id="quantity" name="quantity" class="form-control input-number" value="1">
                        <span class="input-group-btn">
                            <button type="button" class="quantity-right-plus btn btn-success btn-number" data-type="plus">
                                <svg width="16" height="16"><use xlink:href="#plus"></use></svg>
                            </button>
                        </span>
                    </div>
                    <a href="#" class="nav-link">Add to Cart <iconify-icon icon="uil:shopping-cart"></a>
                  </div>
                </div>
                <div class="product-item swiper-slide">
                  <a href="#" class="btn-wishlist"><svg width="24" height="24"><use xlink:href="#heart"></use></svg></a>
                  <figure>
                    <a href="index.html" title="Product Title">
                      <img src="images/thumb-tomatoes.png"  class="tab-image">
                    </a>
                  </figure>
                  <h3>Sunstar Fresh Melon Juice</h3>
                  <span class="qty">1 Unit</span><span class="rating"><svg width="24" height="24" class="text-primary"><use xlink:href="#star-solid"></use></svg> 4.5</span>
                  <span class="price">$18.00</span>
                  <div class="d-flex align-items-center justify-content-between">
                    <div class="input-group product-qty">
                        <span class="input-group-btn">
                            <button type="button" class="quantity-left-minus btn btn-danger btn-number" data-type="minus">
                              <svg width="16" height="16"><use xlink:href="#minus"></use></svg>
                            </button>
                        </span>
                        <input type="text" id="quantity" name="quantity" class="form-control input-number" value="1">
                        <span class="input-group-btn">
                            <button type="button" class="quantity-right-plus btn btn-success btn-number" data-type="plus">
                                <svg width="16" height="16"><use xlink:href="#plus"></use></svg>
                            </button>
                        </span>
                    </div>
                    <a href="#" class="nav-link">Add to Cart <iconify-icon icon="uil:shopping-cart"></a>
                  </div>
                </div>

                <div class="product-item swiper-slide">
                  <a href="#" class="btn-wishlist"><svg width="24" height="24"><use xlink:href="#heart"></use></svg></a>
                  <figure>
                    <a href="index.html" title="Product Title">
                      <img src="images/thumb-tomatoketchup.png"  class="tab-image">
                    </a>
                  </figure>
                  <h3>Sunstar Fresh Melon Juice</h3>
                  <span class="qty">1 Unit</span><span class="rating"><svg width="24" height="24" class="text-primary"><use xlink:href="#star-solid"></use></svg> 4.5</span>
                  <span class="price">$18.00</span>
                  <div class="d-flex align-items-center justify-content-between">
                    <div class="input-group product-qty">
                        <span class="input-group-btn">
                            <button type="button" class="quantity-left-minus btn btn-danger btn-number" data-type="minus">
                              <svg width="16" height="16"><use xlink:href="#minus"></use></svg>
                            </button>
                        </span>
                        <input type="text" id="quantity" name="quantity" class="form-control input-number" value="1">
                        <span class="input-group-btn">
                            <button type="button" class="quantity-right-plus btn btn-success btn-number" data-type="plus">
                                <svg width="16" height="16"><use xlink:href="#plus"></use></svg>
                            </button>
                        </span>
                    </div>
                    <a href="#" class="nav-link">Add to Cart <iconify-icon icon="uil:shopping-cart"></a>
                  </div>
                </div>

                <div class="product-item swiper-slide">
                  <a href="#" class="btn-wishlist"><svg width="24" height="24"><use xlink:href="#heart"></use></svg></a>
                  <figure>
                    <a href="index.html" title="Product Title">
                      <img src="images/thumb-bananas.png"  class="tab-image">
                    </a>
                  </figure>
                  <h3>Sunstar Fresh Melon Juice</h3>
                  <span class="qty">1 Unit</span><span class="rating"><svg width="24" height="24" class="text-primary"><use xlink:href="#star-solid"></use></svg> 4.5</span>
                  <span class="price">$18.00</span>
                  <div class="d-flex align-items-center justify-content-between">
                    <div class="input-group product-qty">
                        <span class="input-group-btn">
                            <button type="button" class="quantity-left-minus btn btn-danger btn-number" data-type="minus">
                              <svg width="16" height="16"><use xlink:href="#minus"></use></svg>
                            </button>
                        </span>
                        <input type="text" id="quantity" name="quantity" class="form-control input-number" value="1">
                        <span class="input-group-btn">
                            <button type="button" class="quantity-right-plus btn btn-success btn-number" data-type="plus">
                                <svg width="16" height="16"><use xlink:href="#plus"></use></svg>
                            </button>
                        </span>
                    </div>
                    <a href="#" class="nav-link">Add to Cart <iconify-icon icon="uil:shopping-cart"></a>
                  </div>
                </div>

                <div class="product-item swiper-slide">
                  <a href="#" class="btn-wishlist"><svg width="24" height="24"><use xlink:href="#heart"></use></svg></a>
                  <figure>
                    <a href="index.html" title="Product Title">
                      <img src="images/thumb-bananas.png"  class="tab-image">
                    </a>
                  </figure>
                  <h3>Sunstar Fresh Melon Juice</h3>
                  <span class="qty">1 Unit</span><span class="rating"><svg width="24" height="24" class="text-primary"><use xlink:href="#star-solid"></use></svg> 4.5</span>
                  <span class="price">$18.00</span>
                  <div class="d-flex align-items-center justify-content-between">
                    <div class="input-group product-qty">
                        <span class="input-group-btn">
                            <button type="button" class="quantity-left-minus btn btn-danger btn-number" data-type="minus">
                              <svg width="16" height="16"><use xlink:href="#minus"></use></svg>
                            </button>
                        </span>
                        <input type="text" id="quantity" name="quantity" class="form-control input-number" value="1">
                        <span class="input-group-btn">
                            <button type="button" class="quantity-right-plus btn btn-success btn-number" data-type="plus">
                                <svg width="16" height="16"><use xlink:href="#plus"></use></svg>
                            </button>
                        </span>
                    </div>
                    <a href="#" class="nav-link">Add to Cart <iconify-icon icon="uil:shopping-cart"></a>
                  </div>
                </div>
                
              </div>
            </div>
            <!-- / products-carousel -->

          </div>
        </div>
      </div>
    </section>
__FOODMART_RAW_EOF__;

        if ($sectionTitle !== null) {
            $markup = str_replace('<h2 class="section-title">Most popular products</h2>', '<h2 class="section-title">'.$this->foodmartEscape($sectionTitle).'</h2>', $markup);
        }

        return $this->decorateAccessibility($markup);
    }

    /**
     * Byte-exact Golden Reference markup (Phase 0/2A) — see
     * _reference-themes/FoodMart-main/FoodMart-main/index.html. Extracted
     * once during Phase 2A authoring; never re-read at runtime. Zero prop
     * usage by design (Phase 2A is static-only, see Step 8).
     */
    protected function renderFoodmartProductCarouselJustArrived(array $props, string $inner, string $id): string
    {
        $sectionTitle = is_string($props['section_title'] ?? null) && trim($props['section_title']) !== '' ? $props['section_title'] : null;
        if (($binding = $this->foodmartResolvedBinding($id, 'products')) !== null) {
            return $this->renderFoodmartBoundProducts($binding, 'Just Arrived', true, $sectionTitle);
        }
        if (($manual = $this->foodmartManualItems($props, 'items')) !== null) {
            return $this->renderFoodmartManualProducts($manual, 'Just Arrived', true, $sectionTitle);
        }

        $markup = <<<'__FOODMART_RAW_EOF__'
<section class="py-5 overflow-hidden">
      <div class="container-fluid">
        <div class="row">
          <div class="col-md-12">

            <div class="section-header d-flex justify-content-between">
              
              <h2 class="section-title">Just arrived</h2>

              <div class="d-flex align-items-center">
                <a href="#" class="btn-link text-decoration-none">View All Categories →</a>
                <div class="swiper-buttons">
                  <button class="swiper-prev products-carousel-prev btn btn-primary">❮</button>
                  <button class="swiper-next products-carousel-next btn btn-primary">❯</button>
                </div>  
              </div>
            </div>
            
          </div>
        </div>
        <div class="row">
          <div class="col-md-12">

            <div class="products-carousel swiper">
              <div class="swiper-wrapper">
                
                <div class="product-item swiper-slide">
                  <a href="#" class="btn-wishlist"><svg width="24" height="24"><use xlink:href="#heart"></use></svg></a>
                  <figure>
                    <a href="index.html" title="Product Title">
                      <img src="images/thumb-tomatoes.png"  class="tab-image">
                    </a>
                  </figure>
                  <h3>Sunstar Fresh Melon Juice</h3>
                  <span class="qty">1 Unit</span><span class="rating"><svg width="24" height="24" class="text-primary"><use xlink:href="#star-solid"></use></svg> 4.5</span>
                  <span class="price">$18.00</span>
                  <div class="d-flex align-items-center justify-content-between">
                    <div class="input-group product-qty">
                        <span class="input-group-btn">
                            <button type="button" class="quantity-left-minus btn btn-danger btn-number" data-type="minus">
                              <svg width="16" height="16"><use xlink:href="#minus"></use></svg>
                            </button>
                        </span>
                        <input type="text" id="quantity" name="quantity" class="form-control input-number" value="1">
                        <span class="input-group-btn">
                            <button type="button" class="quantity-right-plus btn btn-success btn-number" data-type="plus">
                                <svg width="16" height="16"><use xlink:href="#plus"></use></svg>
                            </button>
                        </span>
                    </div>
                    <a href="#" class="nav-link">Add to Cart <iconify-icon icon="uil:shopping-cart"></a>
                  </div>
                </div>

                <div class="product-item swiper-slide">
                  <a href="#" class="btn-wishlist"><svg width="24" height="24"><use xlink:href="#heart"></use></svg></a>
                  <figure>
                    <a href="index.html" title="Product Title">
                      <img src="images/thumb-tomatoketchup.png"  class="tab-image">
                    </a>
                  </figure>
                  <h3>Sunstar Fresh Melon Juice</h3>
                  <span class="qty">1 Unit</span><span class="rating"><svg width="24" height="24" class="text-primary"><use xlink:href="#star-solid"></use></svg> 4.5</span>
                  <span class="price">$18.00</span>
                  <div class="d-flex align-items-center justify-content-between">
                    <div class="input-group product-qty">
                        <span class="input-group-btn">
                            <button type="button" class="quantity-left-minus btn btn-danger btn-number" data-type="minus">
                              <svg width="16" height="16"><use xlink:href="#minus"></use></svg>
                            </button>
                        </span>
                        <input type="text" id="quantity" name="quantity" class="form-control input-number" value="1">
                        <span class="input-group-btn">
                            <button type="button" class="quantity-right-plus btn btn-success btn-number" data-type="plus">
                                <svg width="16" height="16"><use xlink:href="#plus"></use></svg>
                            </button>
                        </span>
                    </div>
                    <a href="#" class="nav-link">Add to Cart <iconify-icon icon="uil:shopping-cart"></a>
                  </div>
                </div>

                <div class="product-item swiper-slide">
                  <a href="#" class="btn-wishlist"><svg width="24" height="24"><use xlink:href="#heart"></use></svg></a>
                  <figure>
                    <a href="index.html" title="Product Title">
                      <img src="images/thumb-bananas.png"  class="tab-image">
                    </a>
                  </figure>
                  <h3>Sunstar Fresh Melon Juice</h3>
                  <span class="qty">1 Unit</span><span class="rating"><svg width="24" height="24" class="text-primary"><use xlink:href="#star-solid"></use></svg> 4.5</span>
                  <span class="price">$18.00</span>
                  <div class="d-flex align-items-center justify-content-between">
                    <div class="input-group product-qty">
                        <span class="input-group-btn">
                            <button type="button" class="quantity-left-minus btn btn-danger btn-number" data-type="minus">
                              <svg width="16" height="16"><use xlink:href="#minus"></use></svg>
                            </button>
                        </span>
                        <input type="text" id="quantity" name="quantity" class="form-control input-number" value="1">
                        <span class="input-group-btn">
                            <button type="button" class="quantity-right-plus btn btn-success btn-number" data-type="plus">
                                <svg width="16" height="16"><use xlink:href="#plus"></use></svg>
                            </button>
                        </span>
                    </div>
                    <a href="#" class="nav-link">Add to Cart <iconify-icon icon="uil:shopping-cart"></a>
                  </div>
                </div>

                <div class="product-item swiper-slide">
                  <a href="#" class="btn-wishlist"><svg width="24" height="24"><use xlink:href="#heart"></use></svg></a>
                  <figure>
                    <a href="index.html" title="Product Title">
                      <img src="images/thumb-bananas.png"  class="tab-image">
                    </a>
                  </figure>
                  <h3>Sunstar Fresh Melon Juice</h3>
                  <span class="qty">1 Unit</span><span class="rating"><svg width="24" height="24" class="text-primary"><use xlink:href="#star-solid"></use></svg> 4.5</span>
                  <span class="price">$18.00</span>
                  <div class="d-flex align-items-center justify-content-between">
                    <div class="input-group product-qty">
                        <span class="input-group-btn">
                            <button type="button" class="quantity-left-minus btn btn-danger btn-number" data-type="minus">
                              <svg width="16" height="16"><use xlink:href="#minus"></use></svg>
                            </button>
                        </span>
                        <input type="text" id="quantity" name="quantity" class="form-control input-number" value="1">
                        <span class="input-group-btn">
                            <button type="button" class="quantity-right-plus btn btn-success btn-number" data-type="plus">
                                <svg width="16" height="16"><use xlink:href="#plus"></use></svg>
                            </button>
                        </span>
                    </div>
                    <a href="#" class="nav-link">Add to Cart <iconify-icon icon="uil:shopping-cart"></a>
                  </div>
                </div>
                <div class="product-item swiper-slide">
                  <a href="#" class="btn-wishlist"><svg width="24" height="24"><use xlink:href="#heart"></use></svg></a>
                  <figure>
                    <a href="index.html" title="Product Title">
                      <img src="images/thumb-tomatoes.png"  class="tab-image">
                    </a>
                  </figure>
                  <h3>Sunstar Fresh Melon Juice</h3>
                  <span class="qty">1 Unit</span><span class="rating"><svg width="24" height="24" class="text-primary"><use xlink:href="#star-solid"></use></svg> 4.5</span>
                  <span class="price">$18.00</span>
                  <div class="d-flex align-items-center justify-content-between">
                    <div class="input-group product-qty">
                        <span class="input-group-btn">
                            <button type="button" class="quantity-left-minus btn btn-danger btn-number" data-type="minus">
                              <svg width="16" height="16"><use xlink:href="#minus"></use></svg>
                            </button>
                        </span>
                        <input type="text" id="quantity" name="quantity" class="form-control input-number" value="1">
                        <span class="input-group-btn">
                            <button type="button" class="quantity-right-plus btn btn-success btn-number" data-type="plus">
                                <svg width="16" height="16"><use xlink:href="#plus"></use></svg>
                            </button>
                        </span>
                    </div>
                    <a href="#" class="nav-link">Add to Cart <iconify-icon icon="uil:shopping-cart"></a>
                  </div>
                </div>

                <div class="product-item swiper-slide">
                  <a href="#" class="btn-wishlist"><svg width="24" height="24"><use xlink:href="#heart"></use></svg></a>
                  <figure>
                    <a href="index.html" title="Product Title">
                      <img src="images/thumb-tomatoketchup.png"  class="tab-image">
                    </a>
                  </figure>
                  <h3>Sunstar Fresh Melon Juice</h3>
                  <span class="qty">1 Unit</span><span class="rating"><svg width="24" height="24" class="text-primary"><use xlink:href="#star-solid"></use></svg> 4.5</span>
                  <span class="price">$18.00</span>
                  <div class="d-flex align-items-center justify-content-between">
                    <div class="input-group product-qty">
                        <span class="input-group-btn">
                            <button type="button" class="quantity-left-minus btn btn-danger btn-number" data-type="minus">
                              <svg width="16" height="16"><use xlink:href="#minus"></use></svg>
                            </button>
                        </span>
                        <input type="text" id="quantity" name="quantity" class="form-control input-number" value="1">
                        <span class="input-group-btn">
                            <button type="button" class="quantity-right-plus btn btn-success btn-number" data-type="plus">
                                <svg width="16" height="16"><use xlink:href="#plus"></use></svg>
                            </button>
                        </span>
                    </div>
                    <a href="#" class="nav-link">Add to Cart <iconify-icon icon="uil:shopping-cart"></a>
                  </div>
                </div>

                <div class="product-item swiper-slide">
                  <a href="#" class="btn-wishlist"><svg width="24" height="24"><use xlink:href="#heart"></use></svg></a>
                  <figure>
                    <a href="index.html" title="Product Title">
                      <img src="images/thumb-bananas.png"  class="tab-image">
                    </a>
                  </figure>
                  <h3>Sunstar Fresh Melon Juice</h3>
                  <span class="qty">1 Unit</span><span class="rating"><svg width="24" height="24" class="text-primary"><use xlink:href="#star-solid"></use></svg> 4.5</span>
                  <span class="price">$18.00</span>
                  <div class="d-flex align-items-center justify-content-between">
                    <div class="input-group product-qty">
                        <span class="input-group-btn">
                            <button type="button" class="quantity-left-minus btn btn-danger btn-number" data-type="minus">
                              <svg width="16" height="16"><use xlink:href="#minus"></use></svg>
                            </button>
                        </span>
                        <input type="text" id="quantity" name="quantity" class="form-control input-number" value="1">
                        <span class="input-group-btn">
                            <button type="button" class="quantity-right-plus btn btn-success btn-number" data-type="plus">
                                <svg width="16" height="16"><use xlink:href="#plus"></use></svg>
                            </button>
                        </span>
                    </div>
                    <a href="#" class="nav-link">Add to Cart <iconify-icon icon="uil:shopping-cart"></a>
                  </div>
                </div>

                <div class="product-item swiper-slide">
                  <a href="#" class="btn-wishlist"><svg width="24" height="24"><use xlink:href="#heart"></use></svg></a>
                  <figure>
                    <a href="index.html" title="Product Title">
                      <img src="images/thumb-bananas.png"  class="tab-image">
                    </a>
                  </figure>
                  <h3>Sunstar Fresh Melon Juice</h3>
                  <span class="qty">1 Unit</span><span class="rating"><svg width="24" height="24" class="text-primary"><use xlink:href="#star-solid"></use></svg> 4.5</span>
                  <span class="price">$18.00</span>
                  <div class="d-flex align-items-center justify-content-between">
                    <div class="input-group product-qty">
                        <span class="input-group-btn">
                            <button type="button" class="quantity-left-minus btn btn-danger btn-number" data-type="minus">
                              <svg width="16" height="16"><use xlink:href="#minus"></use></svg>
                            </button>
                        </span>
                        <input type="text" id="quantity" name="quantity" class="form-control input-number" value="1">
                        <span class="input-group-btn">
                            <button type="button" class="quantity-right-plus btn btn-success btn-number" data-type="plus">
                                <svg width="16" height="16"><use xlink:href="#plus"></use></svg>
                            </button>
                        </span>
                    </div>
                    <a href="#" class="nav-link">Add to Cart <iconify-icon icon="uil:shopping-cart"></a>
                  </div>
                </div>
                
              </div>
            </div>
            <!-- / products-carousel -->

          </div>
        </div>
      </div>
    </section>
__FOODMART_RAW_EOF__;

        if ($sectionTitle !== null) {
            $markup = str_replace('<h2 class="section-title">Just arrived</h2>', '<h2 class="section-title">'.$this->foodmartEscape($sectionTitle).'</h2>', $markup);
        }

        return $this->decorateAccessibility($markup);
    }

    /**
     * Byte-exact Golden Reference markup (Phase 0/2A) — see
     * _reference-themes/FoodMart-main/FoodMart-main/index.html. Extracted
     * once during Phase 2A authoring; never re-read at runtime. Zero prop
     * usage by design (Phase 2A is static-only, see Step 8).
     */
    protected function renderFoodmartBlog(array $props, string $inner, string $id): string
    {
        if (($manual = $this->foodmartManualItems($props, 'items')) !== null
            || ($bound = $this->foodmartResolvedBinding($id, 'blog_posts')) !== null) {
            return $this->renderFoodmartEditableBlog(
                $props,
                $manual ?? array_map(fn ($item) => is_array($item) ? [
                    'image' => (string) ($item['image_url'] ?? ''),
                    'image_alt' => (string) ($item['image_alt'] ?? 'post'),
                    'url' => (string) ($item['url'] ?? '#'),
                    'date_text' => (string) ($item['date_text'] ?? ''),
                    'category_label' => (string) ($item['category_label'] ?? ''),
                    'title' => (string) ($item['title'] ?? ''),
                    'excerpt' => (string) ($item['excerpt'] ?? ''),
                ] : [], is_array($bound['items'] ?? null) ? $bound['items'] : []),
                (string) ($props['section_title'] ?? ''),
                (string) ($props['view_all_label'] ?? ''),
                (string) ($props['view_all_url'] ?? '')
            );
        }

        $markup = <<<'__FOODMART_RAW_EOF__'
<section id="latest-blog" class="py-5">
      <div class="container-fluid">
        <div class="row">
          <div class="section-header d-flex align-items-center justify-content-between my-5">
            <h2 class="section-title">Our Recent Blog</h2>
            <div class="btn-wrap align-right">
              <a href="#" class="d-flex align-items-center nav-link">Read All Articles <svg width="24" height="24"><use xlink:href="#arrow-right"></use></svg></a>
            </div>
          </div>
        </div>
        <div class="row">
          <div class="col-md-4">
            <article class="post-item card border-0 shadow-sm p-3">
              <div class="image-holder zoom-effect">
                <a href="#">
                  <img src="images/post-thumb-1.jpg" alt="post" class="card-img-top">
                </a>
              </div>
              <div class="card-body">
                <div class="post-meta d-flex text-uppercase gap-3 my-2 align-items-center">
                  <div class="meta-date"><svg width="16" height="16"><use xlink:href="#calendar"></use></svg>22 Aug 2021</div>
                  <div class="meta-categories"><svg width="16" height="16"><use xlink:href="#category"></use></svg>tips & tricks</div>
                </div>
                <div class="post-header">
                  <h3 class="post-title">
                    <a href="#" class="text-decoration-none">Top 10 casual look ideas to dress up your kids</a>
                  </h3>
                  <p>Lorem ipsum dolor sit amet, consectetur adipi elit. Aliquet eleifend viverra enim tincidunt donec quam. A in arcu, hendrerit neque dolor morbi...</p>
                </div>
              </div>
            </article>
          </div>
          <div class="col-md-4">
            <article class="post-item card border-0 shadow-sm p-3">
              <div class="image-holder zoom-effect">
                <a href="#">
                  <img src="images/post-thumb-2.jpg" alt="post" class="card-img-top">
                </a>
              </div>
              <div class="card-body">
                <div class="post-meta d-flex text-uppercase gap-3 my-2 align-items-center">
                  <div class="meta-date"><svg width="16" height="16"><use xlink:href="#calendar"></use></svg>25 Aug 2021</div>
                  <div class="meta-categories"><svg width="16" height="16"><use xlink:href="#category"></use></svg>trending</div>
                </div>
                <div class="post-header">
                  <h3 class="post-title">
                    <a href="#" class="text-decoration-none">Latest trends of wearing street wears supremely</a>
                  </h3>
                  <p>Lorem ipsum dolor sit amet, consectetur adipi elit. Aliquet eleifend viverra enim tincidunt donec quam. A in arcu, hendrerit neque dolor morbi...</p>
                </div>
              </div>
            </article>
          </div>
          <div class="col-md-4">
            <article class="post-item card border-0 shadow-sm p-3">
              <div class="image-holder zoom-effect">
                <a href="#">
                  <img src="images/post-thumb-3.jpg" alt="post" class="card-img-top">
                </a>
              </div>
              <div class="card-body">
                <div class="post-meta d-flex text-uppercase gap-3 my-2 align-items-center">
                  <div class="meta-date"><svg width="16" height="16"><use xlink:href="#calendar"></use></svg>28 Aug 2021</div>
                  <div class="meta-categories"><svg width="16" height="16"><use xlink:href="#category"></use></svg>inspiration</div>
                </div>
                <div class="post-header">
                  <h3 class="post-title">
                    <a href="#" class="text-decoration-none">10 Different Types of comfortable clothes ideas for women</a>
                  </h3>
                  <p>Lorem ipsum dolor sit amet, consectetur adipi elit. Aliquet eleifend viverra enim tincidunt donec quam. A in arcu, hendrerit neque dolor morbi...</p>
                </div>
              </div>
            </article>
          </div>
        </div>
      </div>
    </section>
__FOODMART_RAW_EOF__;

        return $this->decorateAccessibility($markup);
    }

    /**
     * Byte-exact Golden Reference markup (Phase 0/2A) — see
     * _reference-themes/FoodMart-main/FoodMart-main/index.html. Extracted
     * once during Phase 2A authoring; never re-read at runtime. Zero prop
     * usage by design (Phase 2A is static-only, see Step 8).
     */
    protected function renderFoodmartAppPromo(array $props, string $inner, string $id): string
    {
        $markup = <<<'__FOODMART_RAW_EOF__'
<section class="py-5 my-5">
      <div class="container-fluid">

        <div class="bg-warning py-5 rounded-5" style="background-image: url('images/bg-pattern-2.png') no-repeat;">
          <div class="container">
            <div class="row">
              <div class="col-md-4">
                <img src="images/phone.png" alt="phone" class="image-float img-fluid">
              </div>
              <div class="col-md-8">
                <h2 class="my-5">Shop faster with foodmart App</h2>
                <p>Lorem ipsum dolor sit amet, consectetur adipiscing elit. Sagittis sed ptibus liberolectus nonet psryroin. Amet sed lorem posuere sit iaculis amet, ac urna. Adipiscing fames semper erat ac in suspendisse iaculis. Amet blandit tortor praesent ante vitae. A, enim pretiummi senectus magna. Sagittis sed ptibus liberolectus non et psryroin.</p>
                <div class="d-flex gap-2 flex-wrap">
                  <img src="images/app-store.jpg" alt="app-store">
                  <img src="images/google-play.jpg" alt="google-play">
                </div>
              </div>
            </div>
          </div>
        </div>
        
      </div>
    </section>
__FOODMART_RAW_EOF__;

        if (is_string($props['title'] ?? null) && trim($props['title']) !== '') {
            $markup = str_replace('Shop faster with foodmart App', $this->foodmartEscape($props['title']), $markup);
        }
        if (is_string($props['description'] ?? null) && trim($props['description']) !== '') {
            $markup = preg_replace('/<p>Lorem ipsum dolor sit amet, consectetur adipiscing elit\..*?<\/p>/s', '<p>'.$this->foodmartEscape($props['description']).'</p>', $markup, 1) ?? $markup;
        }

        // Phase 4B.6 — editable media/link content (phone + store badges).
        // All structural wrappers stay canonical; only src/href values move.
        $phone = $this->foodmartEditableSrc((string) ($props['phone_image'] ?? ''));
        if ($phone !== '') {
            $markup = str_replace('src="images/phone.png"', 'src="'.$phone.'"', $markup);
        }
        $store = $this->foodmartEditableSrc((string) ($props['store_image'] ?? ''));
        if ($store !== '') {
            $markup = str_replace('src="images/app-store.jpg"', 'src="'.$store.'"', $markup);
        }
        $play = $this->foodmartEditableSrc((string) ($props['play_image'] ?? ''));
        if ($play !== '') {
            $markup = str_replace('src="images/google-play.jpg"', 'src="'.$play.'"', $markup);
        }
        $storeUrl = trim((string) ($props['store_url'] ?? ''));
        $playUrl = trim((string) ($props['play_url'] ?? ''));
        if ($storeUrl !== '' || $playUrl !== '') {
            $storeSafe = $this->foodmartEditableHrefValue($storeUrl !== '' ? $storeUrl : '#');
            $playSafe = $this->foodmartEditableHrefValue($playUrl !== '' ? $playUrl : '#');
            $markup = preg_replace(
                '/<img src="'.($store !== '' ? preg_quote($store, '/') : 'images\/app-store\.jpg').'" alt="app-store">/',
                '<a href="'.$storeSafe.'"><img src="'.($store !== '' ? $store : 'images/app-store.jpg').'" alt="app-store"></a>',
                $markup, 1
            ) ?? $markup;
            $markup = preg_replace(
                '/<img src="'.($play !== '' ? preg_quote($play, '/') : 'images\/google-play\.jpg').'" alt="google-play">/',
                '<a href="'.$playSafe.'"><img src="'.($play !== '' ? $play : 'images/google-play.jpg').'" alt="google-play"></a>',
                $markup, 1
            ) ?? $markup;
        }

        return $this->decorateAccessibility($markup);
    }

    /**
     * Byte-exact Golden Reference markup (Phase 0/2A) — see
     * _reference-themes/FoodMart-main/FoodMart-main/index.html. Extracted
     * once during Phase 2A authoring; never re-read at runtime. Zero prop
     * usage by design (Phase 2A is static-only, see Step 8).
     */
    protected function renderFoodmartKeywords(array $props, string $inner, string $id): string
    {
        $markup = <<<'__FOODMART_RAW_EOF__'
<section class="py-5">
      <div class="container-fluid">
        <h2 class="my-5">People are also looking for</h2>
        <a href="#" class="btn btn-warning me-2 mb-2">Blue diamon almonds</a>
        <a href="#" class="btn btn-warning me-2 mb-2">Angie’s Boomchickapop Corn</a>
        <a href="#" class="btn btn-warning me-2 mb-2">Salty kettle Corn</a>
        <a href="#" class="btn btn-warning me-2 mb-2">Chobani Greek Yogurt</a>
        <a href="#" class="btn btn-warning me-2 mb-2">Sweet Vanilla Yogurt</a>
        <a href="#" class="btn btn-warning me-2 mb-2">Foster Farms Takeout Crispy wings</a>
        <a href="#" class="btn btn-warning me-2 mb-2">Warrior Blend Organic</a>
        <a href="#" class="btn btn-warning me-2 mb-2">Chao Cheese Creamy</a>
        <a href="#" class="btn btn-warning me-2 mb-2">Chicken meatballs</a>
        <a href="#" class="btn btn-warning me-2 mb-2">Blue diamon almonds</a>
        <a href="#" class="btn btn-warning me-2 mb-2">Angie’s Boomchickapop Corn</a>
        <a href="#" class="btn btn-warning me-2 mb-2">Salty kettle Corn</a>
        <a href="#" class="btn btn-warning me-2 mb-2">Chobani Greek Yogurt</a>
        <a href="#" class="btn btn-warning me-2 mb-2">Sweet Vanilla Yogurt</a>
        <a href="#" class="btn btn-warning me-2 mb-2">Foster Farms Takeout Crispy wings</a>
        <a href="#" class="btn btn-warning me-2 mb-2">Warrior Blend Organic</a>
        <a href="#" class="btn btn-warning me-2 mb-2">Chao Cheese Creamy</a>
        <a href="#" class="btn btn-warning me-2 mb-2">Chicken meatballs</a>
      </div>
    </section>
__FOODMART_RAW_EOF__;

        if (is_string($props['section_title'] ?? null) && trim($props['section_title']) !== '') {
            $markup = str_replace('<h2 class="my-5">People are also looking for</h2>', '<h2 class="my-5">'.$this->foodmartEscape($props['section_title']).'</h2>', $markup);
        }

        // Phase 4B.6 — manual keyword chips (label + safe link each),
        // replacing the canonical demo chips with prop-driven ones.
        $keywords = $this->foodmartManualItems($props, 'items');
        if ($keywords !== null) {
            $chips = '';
            foreach ($keywords as $index => $item) {
                $label = $this->foodmartEscape((string) ($item['label'] ?? ''));
                $url = $this->foodmartEditableHrefValue((string) ($item['url'] ?? '#'));
                $chips .= '<a href="'.$url.'" class="btn btn-warning me-2 mb-2" data-tb-logical-group="items" data-tb-logical-index="'.(int) $index.'">'.$label.'</a>';
            }
            $markup = preg_replace('/<a href="#" class="btn btn-warning me-2 mb-2">.*?<\/a>/s', '', $markup) ?? $markup;
            $markup = str_replace('</section>', $chips.'
    </section>', $markup);
        }

        return $this->decorateAccessibility($markup);
    }

    /**
     * Phase 4B.6 — editable brand cards through the SAME brand-carousel
     * presentation (card mb-3 p-3 rounded-4 shadow border-0 anatomy).
     *
     * @param array<string,mixed> $props
     * @param list<array<string,mixed>> $items
     */
    private function renderFoodmartEditableBrands(array $props, array $items): string
    {
        $cards = '';
        foreach ($items as $index => $item) {
            $image = $this->foodmartEditableSrc((string) ($item['image'] ?? ''));
            $name = $this->foodmartEscape((string) ($item['name'] ?? ''));
            $title = $this->foodmartEscape((string) ($item['title'] ?? ''));
            $cards .= '<div class="swiper-slide" data-tb-logical-group="items" data-tb-logical-index="'.(int) $index.'">'
                .'<div class="card mb-3 p-3 rounded-4 shadow border-0"><div class="row g-0">'
                .'<div class="col-md-4"><img src="'.($image !== '' ? $image : 'images/product-thumb-11.jpg').'" class="img-fluid rounded" alt="'.$name.'"></div>'
                .'<div class="col-md-8"><div class="card-body py-0"><p class="text-muted mb-0">'.$name.'</p>'
                .'<h5 class="card-title">'.$title.'</h5></div></div></div></div></div>';
        }
        if ($cards === '') {
            $cards = '<p class="foodmart-empty-state">No brands available.</p>';
        }

        $heading = is_string($props['section_title'] ?? null) && trim($props['section_title']) !== ''
            ? $this->foodmartEscape($props['section_title'])
            : 'Newly Arrived Brands';
        $viewAllLabel = (string) ($props['view_all_label'] ?? '');
        $viewAll = $viewAllLabel !== ''
            ? '<a href="'.$this->foodmartEditableHrefValue((string) ($props['view_all_url'] ?? '#')).'" class="btn-link text-decoration-none">'.$this->foodmartEscape($viewAllLabel).' →</a>'
            : '<a href="#" class="btn-link text-decoration-none">View All Categories →</a>';

        $markup = '<section class="py-5 overflow-hidden"><div class="container-fluid"><div class="row"><div class="col-md-12">'
            .'<div class="section-header d-flex flex-wrap flex-wrap justify-content-between mb-5"><h2 class="section-title">'.$heading.'</h2>'
            .'<div class="d-flex align-items-center">'.$viewAll
            .'<div class="swiper-buttons"><button class="swiper-prev brand-carousel-prev btn btn-yellow">❮</button><button class="swiper-next brand-carousel-next btn btn-yellow">❯</button></div></div></div>'
            .'</div></div><div class="row"><div class="col-md-12"><div class="brand-carousel swiper"><div class="swiper-wrapper">'.$cards.'</div></div></div></div></div></section>';

        return $this->decorateAccessibility($markup);
    }

    /**
     * Phase 4B.6 — blog cards fed by MANUAL items or the (host-provided)
     * blog_posts binding — both normalized to the same semantic item shape
     * and rendered through the SAME Golden card anatomy (image-holder →
     * card-body → post-meta → post-header). Empty section_title/view_all
     * keep the canonical literals.
     *
     * @param array<string,mixed> $props
     * @param list<array<string,mixed>> $items
     */
    private function renderFoodmartEditableBlog(array $props, array $items, string $sectionTitle, string $viewAllLabel, string $viewAllUrl): string
    {
        $cards = '';
        foreach ($items as $index => $item) {
            $url = $this->foodmartEditableHrefValue((string) ($item['url'] ?? '#'));
            $image = $this->foodmartEditableSrc((string) ($item['image'] ?? ''));
            $imageAlt = $this->foodmartEscape((string) ($item['image_alt'] ?? 'post'));
            $imageHtml = $image !== '' ? '<img src="'.$image.'" alt="'.$imageAlt.'" class="card-img-top">' : '';
            $date = $this->foodmartEscape((string) ($item['date_text'] ?? ''));
            $category = $this->foodmartEscape((string) ($item['category_label'] ?? ''));
            $title = $this->foodmartEscape((string) ($item['title'] ?? ''));
            $excerpt = $this->foodmartEscape((string) ($item['excerpt'] ?? ''));
            $cards .= '<div class="col-md-4" data-tb-logical-group="items" data-tb-logical-index="'.(int) $index.'">'
                .'<article class="post-item card border-0 shadow-sm p-3">'
                .'<div class="image-holder zoom-effect"><a href="'.$url.'">'.$imageHtml.'</a></div>'
                .'<div class="card-body">'
                .'<div class="post-meta d-flex text-uppercase gap-3 my-2 align-items-center">'
                .($date !== '' ? '<div class="meta-date"><svg width="16" height="16"><use xlink:href="#calendar"></use></svg>'.$date.'</div>' : '')
                .($category !== '' ? '<div class="meta-categories"><svg width="16" height="16"><use xlink:href="#category"></use></svg>'.$category.'</div>' : '')
                .'</div>'
                .'<div class="post-header"><h3 class="post-title"><a href="'.$url.'" class="text-decoration-none">'.$title.'</a></h3>'
                .($excerpt !== '' ? '<p>'.$excerpt.'</p>' : '')
                .'</div></div></article></div>';
        }
        if ($cards === '') {
            $cards = '<p class="foodmart-empty-state">No blog posts available.</p>';
        }

        $heading = $sectionTitle !== ''
            ? '<h2 class="section-title">'.$this->foodmartEscape($sectionTitle).'</h2>'
            : '<h2 class="section-title">Our Recent Blog</h2>';
        $viewAllHtml = $viewAllLabel !== ''
            ? '<a href="'.$this->foodmartEditableHrefValue($viewAllUrl).'" class="d-flex align-items-center nav-link">'.$this->foodmartEscape($viewAllLabel).' <svg width="24" height="24"><use xlink:href="#arrow-right"></use></svg></a>'
            : '<a href="#" class="d-flex align-items-center nav-link">Read All Articles <svg width="24" height="24"><use xlink:href="#arrow-right"></use></svg></a>';

        $markup = '<section id="latest-blog" class="py-5"><div class="container-fluid"><div class="row">'
            .'<div class="section-header d-flex align-items-center justify-content-between my-5">'.$heading
            .'<div class="btn-wrap align-right">'.$viewAllHtml.'</div></div></div>'
            .'<div class="row">'.$cards.'</div></div></section>';

        return $this->decorateAccessibility($markup);
    }

    /**
     * Byte-exact Golden Reference markup (Phase 0/2A) — see
     * _reference-themes/FoodMart-main/FoodMart-main/index.html. Extracted
     * once during Phase 2A authoring; never re-read at runtime. Zero prop
     * usage by design (Phase 2A is static-only, see Step 8).
     */
    protected function renderFoodmartTrustBadges(array $props, string $inner, string $id): string
    {
        $items = $this->foodmartEditableList($props['items'] ?? null);
        if ($items !== []) {
            $cards = '';
            foreach ($items as $item) {
                $title = $this->foodmartEscape((string) ($item['title'] ?? ''));
                $description = $this->foodmartEscape((string) ($item['description'] ?? ''));
                $cards .= '<div class="col"><div class="card mb-3 border-0"><div class="row"><div class="col-md-2 text-dark" aria-hidden="true">&#10003;</div>'
                    .'<div class="col-md-10"><div class="card-body p-0"><h5>'.$title.'</h5><p class="card-text">'.$description.'</p></div></div></div></div></div>';
            }

            return $this->decorateAccessibility('<section class="py-5"><div class="container-fluid"><div class="row row-cols-1 row-cols-sm-3 row-cols-lg-5">'.$cards.'</div></div></section>');
        }

        $markup = <<<'__FOODMART_RAW_EOF__'
<section class="py-5">
      <div class="container-fluid">
        <div class="row row-cols-1 row-cols-sm-3 row-cols-lg-5">
          <div class="col">
            <div class="card mb-3 border-0">
              <div class="row">
                <div class="col-md-2 text-dark">
                  <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24"><path fill="currentColor" d="M21.5 15a3 3 0 0 0-1.9-2.78l1.87-7a1 1 0 0 0-.18-.87A1 1 0 0 0 20.5 4H6.8l-.33-1.26A1 1 0 0 0 5.5 2h-2v2h1.23l2.48 9.26a1 1 0 0 0 1 .74H18.5a1 1 0 0 1 0 2h-13a1 1 0 0 0 0 2h1.18a3 3 0 1 0 5.64 0h2.36a3 3 0 1 0 5.82 1a2.94 2.94 0 0 0-.4-1.47A3 3 0 0 0 21.5 15Zm-3.91-3H9L7.34 6H19.2ZM9.5 20a1 1 0 1 1 1-1a1 1 0 0 1-1 1Zm8 0a1 1 0 1 1 1-1a1 1 0 0 1-1 1Z"/></svg>
                </div>
                <div class="col-md-10">
                  <div class="card-body p-0">
                    <h5>Free delivery</h5>
                    <p class="card-text">Lorem ipsum dolor sit amet, consectetur adipi elit.</p>
                  </div>
                </div>
              </div>
              </div>
          </div>
          <div class="col">
            <div class="card mb-3 border-0">
              <div class="row">
                <div class="col-md-2 text-dark">
                  <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24"><path fill="currentColor" d="M19.63 3.65a1 1 0 0 0-.84-.2a8 8 0 0 1-6.22-1.27a1 1 0 0 0-1.14 0a8 8 0 0 1-6.22 1.27a1 1 0 0 0-.84.2a1 1 0 0 0-.37.78v7.45a9 9 0 0 0 3.77 7.33l3.65 2.6a1 1 0 0 0 1.16 0l3.65-2.6A9 9 0 0 0 20 11.88V4.43a1 1 0 0 0-.37-.78ZM18 11.88a7 7 0 0 1-2.93 5.7L12 19.77l-3.07-2.19A7 7 0 0 1 6 11.88v-6.3a10 10 0 0 0 6-1.39a10 10 0 0 0 6 1.39Zm-4.46-2.29l-2.69 2.7l-.89-.9a1 1 0 0 0-1.42 1.42l1.6 1.6a1 1 0 0 0 1.42 0L15 11a1 1 0 0 0-1.42-1.42Z"/></svg>
                </div>
                <div class="col-md-10">
                  <div class="card-body p-0">
                    <h5>100% secure payment</h5>
                    <p class="card-text">Lorem ipsum dolor sit amet, consectetur adipi elit.</p>
                  </div>
                </div>
              </div>
              </div>
          </div>
          <div class="col">
            <div class="card mb-3 border-0">
              <div class="row">
                <div class="col-md-2 text-dark">
                  <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24"><path fill="currentColor" d="M22 5H2a1 1 0 0 0-1 1v4a3 3 0 0 0 2 2.82V22a1 1 0 0 0 1 1h16a1 1 0 0 0 1-1v-9.18A3 3 0 0 0 23 10V6a1 1 0 0 0-1-1Zm-7 2h2v3a1 1 0 0 1-2 0Zm-4 0h2v3a1 1 0 0 1-2 0ZM7 7h2v3a1 1 0 0 1-2 0Zm-3 4a1 1 0 0 1-1-1V7h2v3a1 1 0 0 1-1 1Zm10 10h-4v-2a2 2 0 0 1 4 0Zm5 0h-3v-2a4 4 0 0 0-8 0v2H5v-8.18a3.17 3.17 0 0 0 1-.6a3 3 0 0 0 4 0a3 3 0 0 0 4 0a3 3 0 0 0 4 0a3.17 3.17 0 0 0 1 .6Zm2-11a1 1 0 0 1-2 0V7h2ZM4.3 3H20a1 1 0 0 0 0-2H4.3a1 1 0 0 0 0 2Z"/></svg>
                </div>
                <div class="col-md-10">
                  <div class="card-body p-0">
                    <h5>Quality guarantee</h5>
                    <p class="card-text">Lorem ipsum dolor sit amet, consectetur adipi elit.</p>
                  </div>
                </div>
              </div>
              </div>
          </div>
          <div class="col">
            <div class="card mb-3 border-0">
              <div class="row">
                <div class="col-md-2 text-dark">
                  <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24"><path fill="currentColor" d="M12 8.35a3.07 3.07 0 0 0-3.54.53a3 3 0 0 0 0 4.24L11.29 16a1 1 0 0 0 1.42 0l2.83-2.83a3 3 0 0 0 0-4.24A3.07 3.07 0 0 0 12 8.35Zm2.12 3.36L12 13.83l-2.12-2.12a1 1 0 0 1 0-1.42a1 1 0 0 1 1.41 0a1 1 0 0 0 1.42 0a1 1 0 0 1 1.41 0a1 1 0 0 1 0 1.42ZM12 2A10 10 0 0 0 2 12a9.89 9.89 0 0 0 2.26 6.33l-2 2a1 1 0 0 0-.21 1.09A1 1 0 0 0 3 22h9a10 10 0 0 0 0-20Zm0 18H5.41l.93-.93a1 1 0 0 0 0-1.41A8 8 0 1 1 12 20Z"/></svg>
                </div>
                <div class="col-md-10">
                  <div class="card-body p-0">
                    <h5>guaranteed savings</h5>
                    <p class="card-text">Lorem ipsum dolor sit amet, consectetur adipi elit.</p>
                  </div>
                </div>
              </div>
              </div>
          </div>
          <div class="col">
            <div class="card mb-3 border-0">
              <div class="row">
                <div class="col-md-2 text-dark">
                  <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24"><path fill="currentColor" d="M18 7h-.35A3.45 3.45 0 0 0 18 5.5a3.49 3.49 0 0 0-6-2.44A3.49 3.49 0 0 0 6 5.5A3.45 3.45 0 0 0 6.35 7H6a3 3 0 0 0-3 3v2a1 1 0 0 0 1 1h1v6a3 3 0 0 0 3 3h8a3 3 0 0 0 3-3v-6h1a1 1 0 0 0 1-1v-2a3 3 0 0 0-3-3Zm-7 13H8a1 1 0 0 1-1-1v-6h4Zm0-9H5v-1a1 1 0 0 1 1-1h5Zm0-4H9.5A1.5 1.5 0 1 1 11 5.5Zm2-1.5A1.5 1.5 0 1 1 14.5 7H13ZM17 19a1 1 0 0 1-1 1h-3v-7h4Zm2-8h-6V9h5a1 1 0 0 1 1 1Z"/></svg>
                </div>
                <div class="col-md-10">
                  <div class="card-body p-0">
                    <h5>Daily offers</h5>
                    <p class="card-text">Lorem ipsum dolor sit amet, consectetur adipi elit.</p>
                  </div>
                </div>
              </div>
              </div>
          </div>
        </div>
      </div>
    </section>
__FOODMART_RAW_EOF__;

        return $this->decorateAccessibility($markup);
    }

    /**
     * Byte-exact Golden Reference markup (Phase 0/2A) — see
     * _reference-themes/FoodMart-main/FoodMart-main/index.html. Extracted
     * once during Phase 2A authoring; never re-read at runtime. Zero prop
     * usage by design (Phase 2A is static-only, see Step 8).
     */
    protected function renderFoodmartFooter(array $props, string $inner, string $id): string
    {
        if ($this->foodmartHasEditableProps($props, ['columns', 'newsletter_heading', 'newsletter_text', 'newsletter_placeholder', 'newsletter_button', 'copyright', 'credit_text', 'logo_image', 'logo_alt'])) {
            return $this->renderFoodmartEditableFooter($props);
        }

        $markup = <<<'__FOODMART_RAW_EOF__'
<footer class="py-5">
      <div class="container-fluid">
        <div class="row">

          <div class="col-lg-3 col-md-6 col-sm-6">
            <div class="footer-menu">
              <img src="images/logo.png" alt="logo">
              <div class="social-links mt-5">
                <ul class="d-flex list-unstyled gap-2">
                  <li>
                    <a href="#" class="btn btn-outline-light">
                      <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"><path fill="currentColor" d="M15.12 5.32H17V2.14A26.11 26.11 0 0 0 14.26 2c-2.72 0-4.58 1.66-4.58 4.7v2.62H6.61v3.56h3.07V22h3.68v-9.12h3.06l.46-3.56h-3.52V7.05c0-1.05.28-1.73 1.76-1.73Z"/></svg>
                    </a>
                  </li>
                  <li>
                    <a href="#" class="btn btn-outline-light">
                      <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"><path fill="currentColor" d="M22.991 3.95a1 1 0 0 0-1.51-.86a7.48 7.48 0 0 1-1.874.794a5.152 5.152 0 0 0-3.374-1.242a5.232 5.232 0 0 0-5.223 5.063a11.032 11.032 0 0 1-6.814-3.924a1.012 1.012 0 0 0-.857-.365a.999.999 0 0 0-.785.5a5.276 5.276 0 0 0-.242 4.769l-.002.001a1.041 1.041 0 0 0-.496.89a3.042 3.042 0 0 0 .027.439a5.185 5.185 0 0 0 1.568 3.312a.998.998 0 0 0-.066.77a5.204 5.204 0 0 0 2.362 2.922a7.465 7.465 0 0 1-3.59.448A1 1 0 0 0 1.45 19.3a12.942 12.942 0 0 0 7.01 2.061a12.788 12.788 0 0 0 12.465-9.363a12.822 12.822 0 0 0 .535-3.646l-.001-.2a5.77 5.77 0 0 0 1.532-4.202Zm-3.306 3.212a.995.995 0 0 0-.234.702c.01.165.009.331.009.488a10.824 10.824 0 0 1-.454 3.08a10.685 10.685 0 0 1-10.546 7.93a10.938 10.938 0 0 1-2.55-.301a9.48 9.48 0 0 0 2.942-1.564a1 1 0 0 0-.602-1.786a3.208 3.208 0 0 1-2.214-.935q.224-.042.445-.105a1 1 0 0 0-.08-1.943a3.198 3.198 0 0 1-2.25-1.726a5.3 5.3 0 0 0 .545.046a1.02 1.02 0 0 0 .984-.696a1 1 0 0 0-.4-1.137a3.196 3.196 0 0 1-1.425-2.673c0-.066.002-.133.006-.198a13.014 13.014 0 0 0 8.21 3.48a1.02 1.02 0 0 0 .817-.36a1 1 0 0 0 .206-.867a3.157 3.157 0 0 1-.087-.729a3.23 3.23 0 0 1 3.226-3.226a3.184 3.184 0 0 1 2.345 1.02a.993.993 0 0 0 .921.298a9.27 9.27 0 0 0 1.212-.322a6.681 6.681 0 0 1-1.026 1.524Z"/></svg>
                    </a>
                  </li>
                  <li>
                    <a href="#" class="btn btn-outline-light">
                      <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"><path fill="currentColor" d="M23 9.71a8.5 8.5 0 0 0-.91-4.13a2.92 2.92 0 0 0-1.72-1A78.36 78.36 0 0 0 12 4.27a78.45 78.45 0 0 0-8.34.3a2.87 2.87 0 0 0-1.46.74c-.9.83-1 2.25-1.1 3.45a48.29 48.29 0 0 0 0 6.48a9.55 9.55 0 0 0 .3 2a3.14 3.14 0 0 0 .71 1.36a2.86 2.86 0 0 0 1.49.78a45.18 45.18 0 0 0 6.5.33c3.5.05 6.57 0 10.2-.28a2.88 2.88 0 0 0 1.53-.78a2.49 2.49 0 0 0 .61-1a10.58 10.58 0 0 0 .52-3.4c.04-.56.04-3.94.04-4.54ZM9.74 14.85V8.66l5.92 3.11c-1.66.92-3.85 1.96-5.92 3.08Z"/></svg>
                    </a>
                  </li>
                  <li>
                    <a href="#" class="btn btn-outline-light">
                      <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"><path fill="currentColor" d="M17.34 5.46a1.2 1.2 0 1 0 1.2 1.2a1.2 1.2 0 0 0-1.2-1.2Zm4.6 2.42a7.59 7.59 0 0 0-.46-2.43a4.94 4.94 0 0 0-1.16-1.77a4.7 4.7 0 0 0-1.77-1.15a7.3 7.3 0 0 0-2.43-.47C15.06 2 14.72 2 12 2s-3.06 0-4.12.06a7.3 7.3 0 0 0-2.43.47a4.78 4.78 0 0 0-1.77 1.15a4.7 4.7 0 0 0-1.15 1.77a7.3 7.3 0 0 0-.47 2.43C2 8.94 2 9.28 2 12s0 3.06.06 4.12a7.3 7.3 0 0 0 .47 2.43a4.7 4.7 0 0 0 1.15 1.77a4.78 4.78 0 0 0 1.77 1.15a7.3 7.3 0 0 0 2.43.47C8.94 22 9.28 22 12 22s3.06 0 4.12-.06a7.3 7.3 0 0 0 2.43-.47a4.7 4.7 0 0 0 1.77-1.15a4.85 4.85 0 0 0 1.16-1.77a7.59 7.59 0 0 0 .46-2.43c0-1.06.06-1.4.06-4.12s0-3.06-.06-4.12ZM20.14 16a5.61 5.61 0 0 1-.34 1.86a3.06 3.06 0 0 1-.75 1.15a3.19 3.19 0 0 1-1.15.75a5.61 5.61 0 0 1-1.86.34c-1 .05-1.37.06-4 .06s-3 0-4-.06a5.73 5.73 0 0 1-1.94-.3a3.27 3.27 0 0 1-1.1-.75a3 3 0 0 1-.74-1.15a5.54 5.54 0 0 1-.4-1.9c0-1-.06-1.37-.06-4s0-3 .06-4a5.54 5.54 0 0 1 .35-1.9A3 3 0 0 1 5 5a3.14 3.14 0 0 1 1.1-.8A5.73 5.73 0 0 1 8 3.86c1 0 1.37-.06 4-.06s3 0 4 .06a5.61 5.61 0 0 1 1.86.34a3.06 3.06 0 0 1 1.19.8a3.06 3.06 0 0 1 .75 1.1a5.61 5.61 0 0 1 .34 1.9c.05 1 .06 1.37.06 4s-.01 3-.06 4ZM12 6.87A5.13 5.13 0 1 0 17.14 12A5.12 5.12 0 0 0 12 6.87Zm0 8.46A3.33 3.33 0 1 1 15.33 12A3.33 3.33 0 0 1 12 15.33Z"/></svg>
                    </a>
                  </li>
                  <li>
                    <a href="#" class="btn btn-outline-light">
                      <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"><path fill="currentColor" d="M1.04 17.52q.1-.16.32-.02a21.308 21.308 0 0 0 10.88 2.9a21.524 21.524 0 0 0 7.74-1.46q.1-.04.29-.12t.27-.12a.356.356 0 0 1 .47.12q.17.24-.11.44q-.36.26-.92.6a14.99 14.99 0 0 1-3.84 1.58A16.175 16.175 0 0 1 12 22a16.017 16.017 0 0 1-5.9-1.09a16.246 16.246 0 0 1-4.98-3.07a.273.273 0 0 1-.12-.2a.215.215 0 0 1 .04-.12Zm6.02-5.7a4.036 4.036 0 0 1 .68-2.36A4.197 4.197 0 0 1 9.6 7.98a10.063 10.063 0 0 1 2.66-.66q.54-.06 1.76-.16v-.34a3.562 3.562 0 0 0-.28-1.72a1.5 1.5 0 0 0-1.32-.6h-.16a2.189 2.189 0 0 0-1.14.42a1.64 1.64 0 0 0-.62 1a.508.508 0 0 1-.4.46L7.8 6.1q-.34-.08-.34-.36a.587.587 0 0 1 .02-.14a3.834 3.834 0 0 1 1.67-2.64A6.268 6.268 0 0 1 12.26 2h.5a5.054 5.054 0 0 1 3.56 1.18a3.81 3.81 0 0 1 .37.43a3.875 3.875 0 0 1 .27.41a2.098 2.098 0 0 1 .18.52q.08.34.12.47a2.856 2.856 0 0 1 .06.56q.02.43.02.51v4.84a2.868 2.868 0 0 0 .15.95a2.475 2.475 0 0 0 .29.62q.14.19.46.61a.599.599 0 0 1 .12.32a.346.346 0 0 1-.16.28q-1.66 1.44-1.8 1.56a.557.557 0 0 1-.58.04q-.28-.24-.49-.46t-.3-.32a4.466 4.466 0 0 1-.29-.39q-.2-.29-.28-.39a4.91 4.91 0 0 1-2.2 1.52a6.038 6.038 0 0 1-1.68.2a3.505 3.505 0 0 1-2.53-.95a3.553 3.553 0 0 1-.99-2.69Zm3.44-.4a1.895 1.895 0 0 0 .39 1.25a1.294 1.294 0 0 0 1.05.47a1.022 1.022 0 0 0 .17-.02a1.022 1.022 0 0 1 .15-.02a2.033 2.033 0 0 0 1.3-1.08a3.13 3.13 0 0 0 .33-.83a3.8 3.8 0 0 0 .12-.73q.01-.28.01-.92v-.5a7.287 7.287 0 0 0-1.76.16a2.144 2.144 0 0 0-1.76 2.22Zm8.4 6.44a.626.626 0 0 1 .12-.16a3.14 3.14 0 0 1 .96-.46a6.52 6.52 0 0 1 1.48-.22a1.195 1.195 0 0 1 .38.02q.9.08 1.08.3a.655.655 0 0 1 .08.36v.14a4.56 4.56 0 0 1-.38 1.65a3.84 3.84 0 0 1-1.06 1.53a.302.302 0 0 1-.18.08a.177.177 0 0 1-.08-.02q-.12-.06-.06-.22a7.632 7.632 0 0 0 .74-2.42a.513.513 0 0 0-.08-.32q-.2-.24-1.12-.24q-.34 0-.8.04q-.5.06-.92.12a.232.232 0 0 1-.16-.04a.065.065 0 0 1-.02-.08a.153.153 0 0 1 .02-.06Z"/></svg>
                    </a>
                  </li>
                </ul>
              </div>
            </div>
          </div>

          <div class="col-md-2 col-sm-6">
            <div class="footer-menu">
              <h5 class="widget-title">Ultras</h5>
              <ul class="menu-list list-unstyled">
                <li class="menu-item">
                  <a href="#" class="nav-link">About us</a>
                </li>
                <li class="menu-item">
                  <a href="#" class="nav-link">Conditions </a>
                </li>
                <li class="menu-item">
                  <a href="#" class="nav-link">Our Journals</a>
                </li>
                <li class="menu-item">
                  <a href="#" class="nav-link">Careers</a>
                </li>
                <li class="menu-item">
                  <a href="#" class="nav-link">Affiliate Programme</a>
                </li>
                <li class="menu-item">
                  <a href="#" class="nav-link">Ultras Press</a>
                </li>
              </ul>
            </div>
          </div>
          <div class="col-md-2 col-sm-6">
            <div class="footer-menu">
              <h5 class="widget-title">Customer Service</h5>
              <ul class="menu-list list-unstyled">
                <li class="menu-item">
                  <a href="#" class="nav-link">FAQ</a>
                </li>
                <li class="menu-item">
                  <a href="#" class="nav-link">Contact</a>
                </li>
                <li class="menu-item">
                  <a href="#" class="nav-link">Privacy Policy</a>
                </li>
                <li class="menu-item">
                  <a href="#" class="nav-link">Returns & Refunds</a>
                </li>
                <li class="menu-item">
                  <a href="#" class="nav-link">Cookie Guidelines</a>
                </li>
                <li class="menu-item">
                  <a href="#" class="nav-link">Delivery Information</a>
                </li>
              </ul>
            </div>
          </div>
          <div class="col-md-2 col-sm-6">
            <div class="footer-menu">
              <h5 class="widget-title">Customer Service</h5>
              <ul class="menu-list list-unstyled">
                <li class="menu-item">
                  <a href="#" class="nav-link">FAQ</a>
                </li>
                <li class="menu-item">
                  <a href="#" class="nav-link">Contact</a>
                </li>
                <li class="menu-item">
                  <a href="#" class="nav-link">Privacy Policy</a>
                </li>
                <li class="menu-item">
                  <a href="#" class="nav-link">Returns & Refunds</a>
                </li>
                <li class="menu-item">
                  <a href="#" class="nav-link">Cookie Guidelines</a>
                </li>
                <li class="menu-item">
                  <a href="#" class="nav-link">Delivery Information</a>
                </li>
              </ul>
            </div>
          </div>
          <div class="col-lg-3 col-md-6 col-sm-6">
            <div class="footer-menu">
              <h5 class="widget-title">Subscribe Us</h5>
              <p>Subscribe to our newsletter to get updates about our grand offers.</p>
              <form class="d-flex mt-3 gap-0" role="newsletter">
                <input class="form-control rounded-start rounded-0 bg-light" type="email" placeholder="Email Address" aria-label="Email Address">
                <button class="btn btn-dark rounded-end rounded-0" type="submit">Subscribe</button>
              </form>
            </div>
          </div>
          
        </div>
      </div>
    </footer>
<div id="footer-bottom">
      <div class="container-fluid">
        <div class="row">
          <div class="col-md-6 copyright">
            <p>© 2023 Foodmart. All rights reserved.</p>
          </div>
          <div class="col-md-6 credit-link text-start text-md-end">
            <p>Free HTML Template by <a href="https://templatesjungle.com/">TemplatesJungle</a> Distributed by <a href="https://themewagon">ThemeWagon</a></p>
          </div>
        </div>
      </div>
    </div>
__FOODMART_RAW_EOF__;

        return $this->decorateAccessibility($markup);
    }
    /**
     * Phase 4B.6 — editable footer. Same Golden footer anatomy (logo+social
     * column, link columns, subscribe column, footer-bottom) with theme-
     * owned text/links from schema props. Columns keep the Golden grid
     * classes (col-lg-3/col-md-2…); every link item carries logical-
     * selection metadata for the Canvas inspector.
     *
     * @param array<string,mixed> $props
     */
    private function renderFoodmartEditableFooter(array $props): string
    {
        $logo = $this->foodmartEditableSrc((string) ($props['logo_image'] ?? ''));
        $logoAlt = $this->foodmartEscape((string) ($props['logo_alt'] ?? 'logo'));
        $logoHtml = '<img src="'.($logo !== '' ? $logo : 'images/logo.png').'" alt="'.$logoAlt.'">';
        $socials = [
            ['Facebook', 'M15.12 5.32H17V2.14A26.11 26.11 0 0 0 14.26 2c-2.72 0-4.58 1.66-4.58 4.7v2.62H6.61v3.56h3.07V22h3.68v-9.12h3.06l.46-3.56h-3.52V7.05c0-1.05.28-1.73 1.76-1.73Z'],
            ['Twitter', 'M22.991 3.95a1 1 0 0 0-1.51-.86a7.48 7.48 0 0 1-1.874.794a5.152 5.152 0 0 0-3.374-1.242a5.232 5.232 0 0 0-5.223 5.063a11.032 11.032 0 0 1-6.814-3.924a1.012 1.012 0 0 0-.857-.365a.999.999 0 0 0-.785.5a5.276 5.276 0 0 0-.242 4.769l-.002.001a1.041 1.041 0 0 0-.496.89a3.042 3.042 0 0 0 .027.439a5.185 5.185 0 0 0 1.568 3.312a.998.998 0 0 0-.066.77a5.204 5.204 0 0 0 2.362 2.922a7.465 7.465 0 0 1-3.59.448A1 1 0 0 0 1.45 19.3a12.942 12.942 0 0 0 7.01 2.061a12.788 12.788 0 0 0 12.465-9.363a12.822 12.822 0 0 0 .535-3.646l-.001-.2a5.77 5.77 0 0 0 1.532-4.202Zm-3.306 3.212a.995.995 0 0 0-.234.702c.01.165.009.331.009.488a10.824 10.824 0 0 1-.454 3.08a10.685 10.685 0 0 1-10.546 7.93a10.938 10.938 0 0 1-2.55-.301a9.48 9.48 0 0 0 2.942-1.564a1 1 0 0 0-.602-1.786a3.208 3.208 0 0 1-2.214-.935q.224-.042.445-.105a1 1 0 0 0-.08-1.943a3.198 3.198 0 0 1-2.25-1.726a5.3 5.3 0 0 0 .545.046a1.02 1.02 0 0 0 .984-.696a1 1 0 0 0-.4-1.137a3.196 3.196 0 0 1-1.425-2.673c0-.066.002-.133.006-.198a13.014 13.014 0 0 0 8.21 3.48a1.02 1.02 0 0 0 .817-.36a1 1 0 0 0 .206-.867a3.157 3.157 0 0 1-.087-.729a3.23 3.23 0 0 1 3.226-3.226a3.184 3.184 0 0 1 2.345 1.02a.993.993 0 0 0 .921.298a9.27 9.27 0 0 0 1.212-.322a6.681 6.681 0 0 1-1.026 1.524Z'],
            ['YouTube', 'M23 9.71a8.5 8.5 0 0 0-.91-4.13a2.92 2.92 0 0 0-1.72-1A78.36 78.36 0 0 0 12 4.27a78.45 78.45 0 0 0-8.34.3a2.87 2.87 0 0 0-1.46.74c-.9.83-1 2.25-1.1 3.45a48.29 48.29 0 0 0 0 6.48a9.55 9.55 0 0 0 .3 2a3.14 3.14 0 0 0 .71 1.36a2.86 2.86 0 0 0 1.49.78a45.18 45.18 0 0 0 6.5.33c3.5.05 6.57 0 10.2-.28a2.88 2.88 0 0 0 1.53-.78a2.49 2.49 0 0 0 .61-1a10.58 10.58 0 0 0 .52-3.4c.04-.56.04-3.94.04-4.54ZM9.74 14.85V8.66l5.92 3.11c-1.66.92-3.85 1.96-5.92 3.08Z'],
            ['Instagram', 'M17.34 5.46a1.2 1.2 0 1 0 1.2 1.2a1.2 1.2 0 0 0-1.2-1.2Zm4.6 2.42a7.59 7.59 0 0 0-.46-2.43a4.94 4.94 0 0 0-1.16-1.77a4.7 4.7 0 0 0-1.77-1.15a7.3 7.3 0 0 0-2.43-.47C15.06 2 14.72 2 12 2s-3.06 0-4.12.06a7.3 7.3 0 0 0-2.43.47a4.78 4.78 0 0 0-1.77 1.15a4.7 4.7 0 0 0-1.15 1.77a7.3 7.3 0 0 0-.47 2.43C2 8.94 2 9.28 2 12s0 3.06.06 4.12a7.3 7.3 0 0 0 .47 2.43a4.7 4.7 0 0 0 1.15 1.77a4.78 4.78 0 0 0 1.77 1.15a7.3 7.3 0 0 0 2.43.47C8.94 22 9.28 22 12 22s3.06 0 4.12-.06a7.3 7.3 0 0 0 2.43-.47a4.7 4.7 0 0 0 1.77-1.15a4.85 4.85 0 0 0 1.16-1.77a7.59 7.59 0 0 0 .46-2.43c0-1.06.06-1.4.06-4.12s0-3.06-.06-4.12ZM20.14 16a5.61 5.61 0 0 1-.34 1.86a3.06 3.06 0 0 1-.75 1.15a3.19 3.19 0 0 1-1.15.75a5.61 5.61 0 0 1-1.86.34c-1 .05-1.37.06-4 .06s-3 0-4-.06a5.73 5.73 0 0 1-1.94-.3a3.27 3.27 0 0 1-1.1-.75a3 3 0 0 1-.74-1.15a5.54 5.54 0 0 1-.4-1.9c0-1-.06-1.37-.06-4s0-3 .06-4a5.54 5.54 0 0 1 .35-1.9A3 3 0 0 1 5 5a3.14 3.14 0 0 1 1.1-.8A5.73 5.73 0 0 1 8 3.86c1 0 1.37-.06 4-.06s3 0 4 .06a5.61 5.61 0 0 1 1.86.34a3.06 3.06 0 0 1 1.19.8a3.06 3.06 0 0 1 .75 1.1a5.61 5.61 0 0 1 .34 1.9c.05 1 .06 1.37.06 4s-.01 3-.06 4ZM12 6.87A5.13 5.13 0 1 0 17.14 12A5.12 5.12 0 0 0 12 6.87Zm0 8.46A3.33 3.33 0 1 1 15.33 12A3.33 3.33 0 0 1 12 15.33Z'],
            ['TikTok', 'M1.04 17.52q.1-.16.32-.02a21.308 21.308 0 0 0 10.88 2.9a21.524 21.524 0 0 0 7.74-1.46q.1-.04.29-.12t.27-.12a.356.356 0 0 1 .47.12q.17.24-.11.44q-.36.26-.92.6a14.99 14.99 0 0 1-3.84 1.58A16.175 16.175 0 0 1 12 22a16.017 16.017 0 0 1-5.9-1.09a16.246 16.246 0 0 1-4.98-3.07a.273.273 0 0 1-.12-.2a.215.215 0 0 1 .04-.12Zm6.02-5.7a4.036 4.036 0 0 1 .68-2.36A4.197 4.197 0 0 1 9.6 7.98a10.063 10.063 0 0 1 2.66-.66q.54-.06 1.76-.16v-.34a3.562 3.562 0 0 0-.28-1.72a1.5 1.5 0 0 0-1.32-.6h-.16a2.189 2.189 0 0 0-1.14.42a1.64 1.64 0 0 0-.62 1a.508.508 0 0 1-.4.46L7.8 6.1q-.34-.08-.34-.36a.587.587 0 0 1 .02-.14a3.834 3.834 0 0 1 1.67-2.64A6.268 6.268 0 0 1 12.26 2h.5a5.054 5.054 0 0 1 3.56 1.18a3.81 3.81 0 0 1 .37.43a3.875 3.875 0 0 1 .27.41a2.098 2.098 0 0 1 .18.52q.08.34.12.47a2.856 2.856 0 0 1 .06.56q.02.43.02.51v4.84a2.868 2.868 0 0 0 .15.95a2.475 2.475 0 0 0 .29.62q.14.19.46.61a.599.599 0 0 1 .12.32a.346.346 0 0 1-.16.28q-1.66 1.44-1.8 1.56a.557.557 0 0 1-.58.04q-.28-.24-.49-.46t-.3-.32a4.466 4.466 0 0 1-.29-.39q-.2-.29-.28-.39a4.91 4.91 0 0 1-2.2 1.52a6.038 6.038 0 0 1-1.68.2a3.505 3.505 0 0 1-2.53-.95a3.553 3.553 0 0 1-.99-2.69Zm3.44-.4a1.895 1.895 0 0 0 .39 1.25a1.294 1.294 0 0 0 1.05.47a1.022 1.022 0 0 0 .17-.02a1.022 1.022 0 0 1 .15-.02a2.033 2.033 0 0 0 1.3-1.08a3.13 3.13 0 0 0 .33-.83a3.8 3.8 0 0 0 .12-.73q.01-.28.01-.92v-.5a7.287 7.287 0 0 0-1.76.16a2.144 2.144 0 0 0-1.76 2.22Zm8.4 6.44a.626.626 0 0 1 .12-.16a3.14 3.14 0 0 1 .96-.46a6.52 6.52 0 0 1 1.48-.22a1.195 1.195 0 0 1 .38.02q.9.08 1.08.3a.655.655 0 0 1 .08.36v.14a4.56 4.56 0 0 1-.38 1.65a3.84 3.84 0 0 1-1.06 1.53a.302.302 0 0 1-.18.08a.177.177 0 0 1-.08-.02q-.12-.06-.06-.22a7.632 7.632 0 0 0 .74-2.42a.513.513 0 0 0-.08-.32q-.2-.24-1.12-.24q-.34 0-.8.04q-.5.06-.92.12a.232.232 0 0 1-.16-.04a.065.065 0 0 1-.02-.08a.153.153 0 0 1 .02-.06Z'],
        ];
        $socialHtml = '';
        foreach ($socials as [$socialLabel, $socialPath]) {
            $socialHtml .= '<li><a href="#" aria-label="'.$socialLabel.'" class="btn btn-outline-light">'
                .'<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"><path fill="currentColor" d="'.$socialPath.'"/></svg></a></li>';
        }

        $columns = $this->foodmartEditableList($props['columns'] ?? null);
        $gridClasses = ['col-lg-3 col-md-6 col-sm-6', 'col-md-2 col-sm-6', 'col-md-2 col-sm-6', 'col-md-2 col-sm-6', 'col-lg-3 col-md-6 col-sm-6'];
        $columnsHtml = '';
        $brandHtml = '<div class="col-lg-3 col-md-6 col-sm-6"><div class="footer-menu">'.$logoHtml
            .'<div class="social-links mt-5"><ul class="d-flex list-unstyled gap-2">'.$socialHtml.'</ul></div></div></div>';
        foreach ($columns as $columnIndex => $column) {
            $heading = $this->foodmartEscape((string) ($column['heading'] ?? ''));
            $links = $this->foodmartEditableList($column['links'] ?? null);
            $linksHtml = '';
            foreach ($links as $linkIndex => $link) {
                $label = $this->foodmartEscape((string) ($link['label'] ?? ''));
                $url = $this->foodmartEditableHrefValue((string) ($link['url'] ?? '#'));
                $linksHtml .= '<li class="menu-item" data-tb-logical-group="columns" data-tb-logical-index="'.(int) $columnIndex.'" data-tb-logical-child="links" data-tb-logical-child-index="'.(int) $linkIndex.'">'
                    .'<a href="'.$url.'" class="nav-link">'.$label.'</a></li>';
            }
            if ($linksHtml === '') {
                $linksHtml = '<li class="menu-item"><a href="#" class="nav-link">New link</a></li>';
            }
            $class = $gridClasses[$columnIndex] ?? 'col-md-2 col-sm-6';
            $columnsHtml .= '<div class="'.$class.'"><div class="footer-menu"><h5 class="widget-title">'.$heading.'</h5>'
                .'<ul class="menu-list list-unstyled">'.$linksHtml.'</ul></div></div>';
        }
        if ($columnsHtml === '') {
            $columnsHtml = '<div class="col-md-2 col-sm-6"><div class="footer-menu"><h5 class="widget-title">New column</h5>'
                .'<ul class="menu-list list-unstyled"><li class="menu-item"><a href="#" class="nav-link">New link</a></li></ul></div></div>';
        }

        $newsletterHeading = $this->foodmartEscape((string) ($props['newsletter_heading'] ?? 'Subscribe Us'));
        $newsletterText = $this->foodmartEscape((string) ($props['newsletter_text'] ?? 'Subscribe to our newsletter to get updates about our grand offers.'));
        $newsletterPlaceholder = $this->foodmartEscape((string) ($props['newsletter_placeholder'] ?? 'Email Address'));
        $newsletterButton = $this->foodmartEscape((string) ($props['newsletter_button'] ?? 'Subscribe'));
        $copyright = $this->foodmartEscape((string) ($props['copyright'] ?? '© 2023 Foodmart. All rights reserved.'));
        $creditText = $this->foodmartEscape((string) ($props['credit_text'] ?? 'Free HTML Template by TemplatesJungle Distributed by ThemeWagon'));

        $markup = '<footer class="py-5"><div class="container-fluid"><div class="row">'
            .$brandHtml.$columnsHtml
            .'<div class="col-lg-3 col-md-6 col-sm-6"><div class="footer-menu"><h5 class="widget-title">'.$newsletterHeading.'</h5>'
            .'<p>'.$newsletterText.'</p>'
            .'<form class="d-flex mt-3 gap-0" role="newsletter">'
            .'<input class="form-control rounded-start rounded-0 bg-light" type="email" placeholder="'.$newsletterPlaceholder.'" aria-label="'.$newsletterPlaceholder.'">'
            .'<button class="btn btn-dark rounded-end rounded-0" type="submit">'.$newsletterButton.'</button></form></div></div>'
            .'</div></div></footer>'
            .'<div id="footer-bottom"><div class="container-fluid"><div class="row">'
            .'<div class="col-md-6 copyright"><p>'.$copyright.'</p></div>'
            .'<div class="col-md-6 credit-link text-start text-md-end"><p>'.$creditText.'</p></div>'
            .'</div></div></div>';

        return $this->decorateAccessibility($markup);
    }

    /**
     * Phase 3 (user-approved): accessibility attribute decoration.
     *
     * The Golden Reference markup legitimately lacks alt/label attributes
     * (26/75 imgs carry alt; most form fields carry none), which the real
     * ThemeCommercialQualityGate blocks on. Rather than weakening the gate
     * or hand-editing Golden markup, this decoration adds, at RENDER time,
     * deterministic generic attributes:
     *   - <img> without alt -> alt derived from the src file name
     *   - input/select/textarea without label/aria-label/aria-labelledby/
     *     title -> aria-label derived from placeholder, then name, then type
     * Attribute-only and visually inert: rendered pixels are unchanged
     * (alt text shows only while an image fails to load; aria-labels are
     * never rendered). Idempotent: tags already carrying the attribute are
     * never touched. ONE canonical implementation: the saas Builder
     * preview and the customer-site tenant runtime decorate identically
     * through this shared trait.
     */
    protected function decorateAccessibility(string $html): string
    {
        $html = preg_replace_callback(
            '/<img\b(?![^>]*\balt=)([^>]*)>/i',
            function (array $m): string {
                $attrs = $m[1];
                $label = '';
                if (preg_match('/\bsrc="([^"]+)"/i', $attrs, $src)) {
                    $label = ucwords(str_replace(['-', '_'], ' ', pathinfo($src[1], PATHINFO_FILENAME)));
                }

                return '<img'.$attrs.' alt="'.htmlspecialchars($label, ENT_QUOTES, 'UTF-8').'">';
            },
            $html
        ) ?? $html;

        $html = preg_replace_callback(
            '/<(input|select|textarea)\b(?![^>]*\b(?:aria-label|aria-labelledby|title)=)([^>]*)>/i',
            function (array $m): string {
                $tag = strtolower($m[1]);
                $attrs = $m[2];
                if (preg_match('/\bplaceholder="([^"]+)"/i', $attrs, $p)) {
                    $label = $p[1];
                } elseif (preg_match('/\bname="([^"]+)"/i', $attrs, $n)) {
                    $label = ucwords(str_replace(['-', '_'], ' ', $n[1]));
                } elseif (preg_match('/\btype="([^"]+)"/i', $attrs, $t)) {
                    $label = $t[1].' field';
                } else {
                    $label = $tag === 'select' ? 'options selector' : $tag.' field';
                }

                return '<'.$tag.$attrs.' aria-label="'.htmlspecialchars($label, ENT_QUOTES, 'UTF-8').'">';
            },
            $html
        ) ?? $html;

        return $html;
    }
}
