<?php

namespace App\Services\ThemeBuilder\ReverseEngineering;

/**
 * Phase 4 — Commerce Analysis
 *
 * Detects e-commerce patterns:
 *   - Product Cards (layout, data fields)
 *   - Price Blocks (format, currency, discount)
 *   - Discount Badges (percentage, label)
 *   - Gallery Layouts (single, multi, zoom)
 *   - Reviews (rating, count, verified)
 *   - Specifications (table, accordion)
 *   - Related Products (grid, carousel)
 *   - Cross-Sell / Upsell sections
 */
class CommerceAnalysisService
{
    // Known commerce platform fingerprints
    private const PLATFORM_FINGERPRINTS = [
        'shopify'   => ['shopify', 'cdn.shopify.com', 'myshopify.com', 'shopify-section'],
        'woocommerce'=>['woocommerce', 'wc-', 'add_to_cart', 'woo-'],
        'magento'   => ['magento', 'mage/', 'Magento_', '_mage'],
        'opencart'  => ['opencart', 'route=product'],
        'digikala'  => ['digikala', 'dkp-', 'dk-product', 'digikala.com'],
        'amazon'    => ['amazon', 'a-price', 'a-color-price', 'a-carousel'],
        'malltina'  => ['malltina', 'mtproduct', 'mt-price'],
        'laravel'   => ['csrf-token', 'laravel_session', 'x-livewire'],
    ];

    // Persian price format patterns
    private const PERSIAN_PRICE_PATTERNS = [
        '/\d{1,3}(?:,\d{3})+\s*(?:تومان|ریال|T|R)/u',  // 1,500,000 تومان
        '/[\d٠-٩]{1,3}(?:,[\d٠-٩]{3})+\s*تومان/u',     // Persian numerals
        '/\d+\s*(?:هزار|میلیون)\s*تومان/u',             // 500 هزار تومان
    ];

    /**
     * Analyze commerce patterns from HTML.
     */
    public function analyze(string $html, array $domStructure = []): array
    {
        $cards        = $this->detectProductCards($html);
        $priceBlocks  = $this->detectPriceBlocks($html);
        $discounts    = $this->detectDiscountBadges($html);
        $gallery      = $this->detectGalleryLayouts($html);
        $reviews      = $this->detectReviews($html);
        $specs        = $this->detectSpecifications($html);
        $related      = $this->detectRelatedProducts($html);
        $crossSell    = $this->detectCrossSellUpsell($html);
        $platform     = $this->detectPlatform($html);
        $cartFlow     = $this->detectCartFlow($html);

        $isCommerce = ($cards['detected'] || $priceBlocks['detected'] || $platform !== 'unknown');

        return [
            'is_commerce'     => $isCommerce,
            'platform'        => $platform,
            'product_cards'   => $cards,
            'price_blocks'    => $priceBlocks,
            'discount_badges' => $discounts,
            'gallery'         => $gallery,
            'reviews'         => $reviews,
            'specifications'  => $specs,
            'related_products'=> $related,
            'cross_sell'      => $crossSell['cross_sell'],
            'upsell'          => $crossSell['upsell'],
            'cart_flow'       => $cartFlow,
            'commerce_score'  => $this->calcCommerceScore(compact('cards','priceBlocks','gallery','reviews','specs','cartFlow')),
        ];
    }

    /**
     * Analyze from AI Vision result.
     */
    public function analyzeFromVision(array $visionResult): array
    {
        $hasProducts = $visionResult['has_products'] ?? false;
        $siteType    = strtolower($visionResult['site_type'] ?? '');
        $isEcomm     = $hasProducts || str_contains($siteType, 'ecommerce') || str_contains($siteType, 'shop') || str_contains($siteType, 'store');

        return [
            'is_commerce'     => $isEcomm,
            'platform'        => 'unknown',
            'product_cards'   => ['detected' => $isEcomm, 'count' => $isEcomm ? 8 : 0, 'layout' => 'grid-4', 'fields' => ['image','title','price','rating','button']],
            'price_blocks'    => ['detected' => $isEcomm, 'format' => 'toman', 'has_original' => true, 'has_discount' => true],
            'discount_badges' => ['detected' => $isEcomm, 'style' => 'percentage', 'position' => 'top-right'],
            'gallery'         => ['detected' => $isEcomm, 'type' => 'multi-thumb', 'has_zoom' => true],
            'reviews'         => ['detected' => true, 'has_rating' => true, 'has_count' => true, 'style' => 'star'],
            'specifications'  => ['detected' => false, 'style' => 'table'],
            'related_products'=> ['detected' => $isEcomm, 'layout' => 'carousel', 'count' => 4],
            'cross_sell'      => ['detected' => false],
            'upsell'          => ['detected' => false],
            'cart_flow'       => ['has_cart' => $isEcomm, 'has_wishlist' => false, 'add_to_cart_style' => 'button'],
            'commerce_score'  => $isEcomm ? 70 : 10,
        ];
    }

    /**
     * Analyze from text prompt.
     */
    public function analyzeFromPrompt(string $prompt): array
    {
        $lower    = strtolower($prompt);
        $isStore  = str_contains($lower, 'فروشگاه') || str_contains($lower, 'shop') || str_contains($lower, 'store') || str_contains($lower, 'product') || str_contains($lower, 'محصول');

        return [
            'is_commerce'     => $isStore,
            'platform'        => 'custom',
            'product_cards'   => ['detected' => $isStore, 'count' => 8, 'layout' => 'grid-4', 'fields' => ['image','title','price','rating','button']],
            'price_blocks'    => ['detected' => $isStore, 'format' => 'toman', 'has_discount' => true],
            'discount_badges' => ['detected' => $isStore, 'style' => 'percentage', 'position' => 'top-right'],
            'gallery'         => ['detected' => $isStore, 'type' => 'multi-thumb', 'has_zoom' => true],
            'reviews'         => ['detected' => true, 'has_rating' => true, 'style' => 'star'],
            'specifications'  => ['detected' => $isStore, 'style' => 'table'],
            'related_products'=> ['detected' => $isStore, 'layout' => 'grid-4', 'count' => 4],
            'cross_sell'      => ['detected' => false],
            'upsell'          => ['detected' => false],
            'cart_flow'       => ['has_cart' => $isStore, 'has_wishlist' => true, 'add_to_cart_style' => 'button'],
            'commerce_score'  => $isStore ? 65 : 5,
        ];
    }

    // ── Private detection methods ─────────────────────────────────────────────

    private function detectProductCards(string $html): array
    {
        $patterns = [
            '/class="[^"]*product[^"]*card[^"]*"/i',
            '/class="[^"]*item[^"]*product[^"]*"/i',
            '/class="[^"]*product-item[^"]*"/i',
            '/class="[^"]*product-tile[^"]*"/i',
            '/class="[^"]*goods-item[^"]*"/i',
            '/class="[^"]*dk-product[^"]*"/i',       // Digikala
            '/class="[^"]*a-carousel-card[^"]*"/i',  // Amazon
        ];

        $count = 0;
        foreach ($patterns as $pattern) {
            preg_match_all($pattern, $html, $m);
            $count += count($m[0]);
        }

        $detected = $count > 0;

        // Infer fields present in product cards
        $fields = ['image', 'title', 'price'];
        if (preg_match('/rating|star|stars/i', $html)) $fields[] = 'rating';
        if (preg_match('/add.to.cart|btn.*cart|افزودن/i', $html)) $fields[] = 'button';
        if (preg_match('/wishlist|favorite|heart/i', $html)) $fields[] = 'wishlist';
        if (preg_match('/badge|label|tag/i', $html)) $fields[] = 'badge';
        if (preg_match('/brand|vendor|seller/i', $html)) $fields[] = 'brand';

        // Detect grid columns (product card density)
        $gridCols = 4;
        if (preg_match('/grid-cols-5|col-md-2\b/', $html)) $gridCols = 5;
        if (preg_match('/grid-cols-4|col-md-3\b/', $html)) $gridCols = 4;
        if (preg_match('/grid-cols-3|col-md-4\b/', $html)) $gridCols = 3;
        if (preg_match('/grid-cols-2|col-md-6\b/', $html)) $gridCols = 2;

        return [
            'detected' => $detected,
            'count'    => min($count, 50),
            'layout'   => "grid-{$gridCols}",
            'fields'   => $fields,
            'has_hover_zoom' => preg_match('/hover.*scale|transform.*scale|zoom/i', $html) > 0,
            'has_quick_view' => preg_match('/quick.view|quick-view|modal.*product/i', $html) > 0,
        ];
    }

    private function detectPriceBlocks(string $html): array
    {
        // Persian price patterns
        $hasPersianPrice = false;
        foreach (self::PERSIAN_PRICE_PATTERNS as $pattern) {
            if (preg_match($pattern, $html)) {
                $hasPersianPrice = true;
                break;
            }
        }

        // English/USD price patterns
        $hasUsdPrice = (bool) preg_match('/\$[\d,]+\.?\d*/', $html);

        // Detect price elements
        $hasPriceClass = (bool) preg_match('/class="[^"]*price[^"]*"/i', $html);
        $hasPriceData  = (bool) preg_match('/data-price|itemprop="price"/i', $html);

        $detected = $hasPersianPrice || $hasUsdPrice || $hasPriceClass || $hasPriceData;

        // Detect original/discounted price pair
        $hasOriginal = (bool) preg_match('/class="[^"]*(original|old|regular|before)[^"]*price[^"]*"/', $html);
        $hasSale     = (bool) preg_match('/class="[^"]*(sale|discount|special|after)[^"]*price[^"]*"/', $html);

        return [
            'detected'         => $detected,
            'format'           => $hasPersianPrice ? 'toman' : ($hasUsdPrice ? 'usd' : 'unknown'),
            'has_original'     => $hasOriginal,
            'has_discount'     => $hasSale,
            'has_free_shipping'=> (bool) preg_match('/free.shipping|ارسال.رایگان/i', $html),
            'has_installment'  => (bool) preg_match('/installment|اقساط|قسط/i', $html),
            'has_countdown'    => (bool) preg_match('/countdown|timer|تایمر/i', $html),
        ];
    }

    private function detectDiscountBadges(string $html): array
    {
        $hasBadge       = (bool) preg_match('/class="[^"]*(badge|ribbon|discount-tag|offer-badge)[^"]*"/i', $html);
        $hasPercentage  = (bool) preg_match('/\d+%\s*(?:off|تخفیف)/i', $html);
        $hasLabel       = (bool) preg_match('/(?:sale|new|hot|تخفیف|جدید|ویژه)/i', $html);

        $position = 'top-right'; // Most common
        if (preg_match('/top.left|badge-top-left/i', $html)) $position = 'top-left';
        if (preg_match('/top.right|badge-top-right/i', $html)) $position = 'top-right';

        $style = 'label';
        if ($hasPercentage) $style = 'percentage';
        if (preg_match('/ribbon/i', $html)) $style = 'ribbon';

        return [
            'detected'   => $hasBadge || $hasPercentage || $hasLabel,
            'style'      => $style,
            'position'   => $position,
            'colors'     => ['#ef4444', '#22c55e'],
            'has_timer'  => (bool) preg_match('/countdown.*badge|flash.sale/i', $html),
        ];
    }

    private function detectGalleryLayouts(string $html): array
    {
        $hasGallery   = (bool) preg_match('/class="[^"]*(?:gallery|product-images|image-slider|media)[^"]*"/i', $html);
        $hasThumbnails= (bool) preg_match('/class="[^"]*(?:thumbnail|thumb|gallery-thumb)[^"]*"/i', $html);
        $hasZoom      = (bool) preg_match('/zoom|magnify|lens/i', $html);
        $hasVideo     = (bool) preg_match('/product.video|360.*view/i', $html);
        $hasSwiper    = str_contains($html, 'swiper') || str_contains($html, 'slick') || str_contains($html, 'owl');

        $type = 'single';
        if ($hasThumbnails) $type = 'multi-thumb';
        if ($hasSwiper) $type = 'carousel';

        return [
            'detected'    => $hasGallery || $hasThumbnails,
            'type'        => $type,
            'has_zoom'    => $hasZoom,
            'has_video'   => $hasVideo,
            'has_fullscreen'=> (bool) preg_match('/fullscreen|lightbox|fancybox/i', $html),
            'thumb_position'=> preg_match('/thumb.*left|gallery-left/i', $html) ? 'left' : 'bottom',
        ];
    }

    private function detectReviews(string $html): array
    {
        $hasReviews  = (bool) preg_match('/class="[^"]*(?:review|comment|feedback|rating)[^"]*"/i', $html);
        $hasStars    = (bool) preg_match('/class="[^"]*star[^"]*"|★|☆|⭐/u', $html);
        $hasVerified = (bool) preg_match('/verified|تایید.شده|خریدار/i', $html);
        $hasCount    = (bool) preg_match('/\d+\s*(?:review|نظر|دیدگاه)/i', $html);

        return [
            'detected'     => $hasReviews || $hasStars,
            'has_rating'   => $hasStars,
            'has_count'    => $hasCount,
            'has_verified' => $hasVerified,
            'style'        => $hasStars ? 'star' : 'text',
            'has_filter'   => (bool) preg_match('/filter.*review|sort.*review/i', $html),
            'has_photo'    => (bool) preg_match('/review.*photo|review.*image/i', $html),
            'max_rating'   => 5,
        ];
    }

    private function detectSpecifications(string $html): array
    {
        $hasSpecTable = (bool) preg_match('/class="[^"]*(?:spec|specification|attributes|properties)[^"]*"/i', $html);
        $hasDlDt      = (bool) preg_match('/<dl>|<dt>/i', $html);
        $hasKeyValue  = (bool) preg_match('/<th>.*<\/th>.*<td>/si', $html);

        $style = 'none';
        if ($hasSpecTable) $style = 'card';
        if ($hasDlDt || $hasKeyValue) $style = 'table';
        if (preg_match('/accordion.*spec|spec.*accordion/i', $html)) $style = 'accordion';

        return [
            'detected' => $hasSpecTable || $hasDlDt || $hasKeyValue,
            'style'    => $style,
            'has_compare' => (bool) preg_match('/compare.*product|product.*compare/i', $html),
        ];
    }

    private function detectRelatedProducts(string $html): array
    {
        $hasRelated  = (bool) preg_match('/related.*product|محصولات.مشابه|similar.*product/i', $html);
        $hasCarousel = (bool) preg_match('/class="[^"]*(?:related|similar|recommended)[^"]*carousel[^"]*"/i', $html);
        $hasGrid     = (bool) preg_match('/related.*grid|class="[^"]*related[^"]*"/i', $html);

        return [
            'detected' => $hasRelated || $hasCarousel,
            'layout'   => $hasCarousel ? 'carousel' : 'grid-4',
            'count'    => 4,
            'has_price'=> true,
        ];
    }

    private function detectCrossSellUpsell(string $html): array
    {
        $crossSell = (bool) preg_match('/cross.sell|frequently.bought|همراه.با|customers.also/i', $html);
        $upsell    = (bool) preg_match('/upsell|upgrade|premium.version|you.might.also.like/i', $html);

        return [
            'cross_sell' => ['detected' => $crossSell, 'layout' => 'grid-3'],
            'upsell'     => ['detected' => $upsell,    'style'  => 'modal'],
        ];
    }

    private function detectPlatform(string $html): string
    {
        foreach (self::PLATFORM_FINGERPRINTS as $platform => $signals) {
            foreach ($signals as $signal) {
                if (str_contains($html, $signal)) return $platform;
            }
        }
        return 'unknown';
    }

    private function detectCartFlow(string $html): array
    {
        $hasCart      = (bool) preg_match('/add.to.cart|افزودن.به.سبد|btn.*cart/i', $html);
        $hasWishlist  = (bool) preg_match('/wishlist|favorite|علاقه.مندی/i', $html);
        $hasCompare   = (bool) preg_match('/compare|مقایسه/i', $html);
        $hasInstant   = (bool) preg_match('/buy.now|خرید.فوری|instant.buy/i', $html);

        return [
            'has_cart'          => $hasCart,
            'has_wishlist'      => $hasWishlist,
            'has_compare'       => $hasCompare,
            'has_instant_buy'   => $hasInstant,
            'add_to_cart_style' => $hasCart ? 'button' : 'none',
            'cart_type'         => (bool) preg_match('/mini.cart|cart.*sidebar/i', $html) ? 'sidebar' : 'page',
        ];
    }

    private function calcCommerceScore(array $data): int
    {
        $score = 0;
        if ($data['cards']['detected'] ?? false)      $score += 25;
        if ($data['priceBlocks']['detected'] ?? false) $score += 20;
        if ($data['gallery']['detected'] ?? false)    $score += 15;
        if ($data['reviews']['detected'] ?? false)    $score += 15;
        if ($data['specs']['detected'] ?? false)      $score += 10;
        if ($data['cartFlow']['has_cart'] ?? false)   $score += 15;
        return min($score, 100);
    }
}
