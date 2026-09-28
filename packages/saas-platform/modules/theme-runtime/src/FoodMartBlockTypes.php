<?php

namespace SaaSPlatform\ThemeRuntime;

/**
 * Canonical foodmart_* block type registry definitions (Phase 3).
 *
 * The 19 type names/categories below are copied verbatim from
 * saas-platform's Phase 2A App\Services\ThemeBuilder\FoodMart\FoodMartBlocks
 * (which registered them per-host via BlockRegistry::registerHostType()).
 * Phase 3 moves the DEFINITIONS here so both hosts register the same
 * canonical set through the same generic host-type seam — never by adding
 * FoodMart detection to any host's generic code. The block type itself is
 * the dispatch identity.
 *
 * Phase 2A shipped every type with an EMPTY schema/defaultProps: rendering
 * fidelity only. Phase 4B.5 begins the schema-driven editing foundation.
 * Phase 4B.6 (Elementor-like content model) completes it: every meaningful
 * customer-visible field on every content block is declared here — manual
 * content lives in schema-declared props, dynamic content comes from the
 * allowlisted binding registry. dataBinding.manual_prop names the prop that
 * carries manual items for a data-capable presentation: the SAME canonical
 * renderer consumes both sources. Renderers keep byte-exact no-props
 * fallbacks, so untouched output is unchanged.
 *
 * Call register() from host-side registration points (saas-platform's
 * FoodMartBlocks facade, customer-site's AppServiceProvider, tests). The
 * registry is process-global static state by design — hosts that
 * conditionally register must reset via BlockRegistry::resetHostTypes() in
 * tests, matching the Phase 2A convention.
 */
final class FoodMartBlockTypes
{
    /** @var array<string,array{name:string,category:string,schema:array,defaultProps:array,dataBinding?:array<string,mixed>,modulePresentation?:array<string,string>,schemaAuditReason?:string}> */
    /**
     * Field vocabulary (generic, host-agnostic):
     *   - repeater props carry selectable manual items; the renderer stamps
     *     data-tb-logical-group/index on each item so Canvas select-what-you-
     *     see can scroll/highlight the exact inspector fieldset.
     *   - dataBinding.manual_prop (when set) names the manual-items prop for
     *     a data-capable presentation; Canvas renders a generic Manual/
     *     Dynamic source selector from it. No FoodMart branch in Canvas.
     */
    public const TYPES = [
        'foodmart_svg_sprite' => ['name' => 'foodmart_svg_sprite', 'category' => 'foodmart', 'schema' => [], 'defaultProps' => [], 'schemaAuditReason' => 'Renderer-owned SVG symbol infrastructure has no customer content.', 'editor' => ['allowCustomCss' => false, 'lockedStructure' => true]],
        'foodmart_preloader' => ['name' => 'foodmart_preloader', 'category' => 'foodmart', 'schema' => [], 'defaultProps' => [], 'schemaAuditReason' => 'Renderer-owned loading infrastructure has no customer content.', 'editor' => ['allowCustomCss' => false, 'lockedStructure' => true]],
        'foodmart_offcanvas_cart' => ['name' => 'foodmart_offcanvas_cart', 'category' => 'foodmart', 'schema' => [], 'defaultProps' => [], 'schemaAuditReason' => 'Cart content and actions must come from the Cart domain; its FoodMart binding is not implemented in this slice.', 'editor' => ['allowCustomCss' => false, 'lockedStructure' => true]],
        'foodmart_offcanvas_search' => ['name' => 'foodmart_offcanvas_search', 'category' => 'foodmart', 'schema' => [], 'defaultProps' => [], 'schemaAuditReason' => 'Search behavior and copy require the Search capability contract; its binding is not implemented in this slice.', 'editor' => ['allowCustomCss' => false, 'lockedStructure' => true]],
        'foodmart_header' => ['name' => 'foodmart_header', 'category' => 'foodmart', 'schema' => [
            'logo_image' => ['type' => 'image', 'label' => 'لوگو', 'default' => ''],
            'logo_alt' => ['type' => 'string', 'label' => 'متن جایگزین لوگو', 'default' => ''],
            'search_placeholder' => ['type' => 'string', 'label' => 'متن راهنمای جستجو', 'default' => ''],
            'filter_label' => ['type' => 'string', 'label' => 'عنوان انتخاب دسته', 'default' => ''],
            'filter_options' => ['type' => 'string', 'label' => 'گزینه‌های دسته (با ویرگول جدا کنید)', 'default' => ''],
            'support_label' => ['type' => 'string', 'label' => 'برچسب پشتیبانی', 'default' => ''],
            'support_phone' => ['type' => 'string', 'label' => 'شماره تماس', 'default' => ''],
            'cart_label' => ['type' => 'string', 'label' => 'برچسب سبد خرید', 'default' => ''],
            'nav_items' => ['type' => 'repeater', 'label' => 'آیتم‌های منو', 'storage' => 'array', 'default' => [], 'fields' => [
                'label' => ['type' => 'string', 'label' => 'عنوان', 'default' => 'New menu item'],
                'url'   => ['type' => 'link',   'label' => 'لینک', 'default' => '#'],
            ]],
        ], 'defaultProps' => [], 'liveStructureUpdate' => 'document', 'editor' => ['allowCustomCss' => false, 'lockedStructure' => true]],
        'foodmart_hero' => ['name' => 'foodmart_hero', 'category' => 'foodmart', 'schema' => [
            'slides' => [
                'type' => 'repeater', 'label' => 'اسلایدهای بنر اصلی', 'storage' => 'array', 'default' => [],
                'fields' => [
                    'label'       => ['type' => 'string', 'label' => 'برچسب بالای عنوان', 'default' => 'New'],
                    'title'       => ['type' => 'string', 'label' => 'عنوان اسلاید', 'default' => 'New FoodMart slide'],
                    'description' => ['type' => 'string', 'label' => 'توضیح کوتاه', 'default' => 'Add your promotion details here.'],
                    'cta_label'   => ['type' => 'string', 'label' => 'متن دکمه', 'default' => 'Shop now'],
                    'cta_url'     => ['type' => 'string', 'label' => 'لینک دکمه', 'default' => '#'],
                    'image'       => ['type' => 'image',  'label' => 'تصویر اسلاید', 'default' => 'images/product-thumb-1.png'],
                    'image_alt'   => ['type' => 'string', 'label' => 'متن جایگزین تصویر', 'default' => 'FoodMart promotion'],
                ],
            ],
            'promos' => [
                'type' => 'repeater', 'label' => 'بنرهای کنار هم', 'storage' => 'array', 'default' => [],
                'fields' => [
                    'badge'   => ['type' => 'string', 'label' => 'برچسب تخفیف', 'default' => ''],
                    'title'   => ['type' => 'string', 'label' => 'عنوان', 'default' => ''],
                    'cta_url' => ['type' => 'string', 'label' => 'لینک', 'default' => ''],
                ],
            ],
        ], 'defaultProps' => [], 'liveStructureUpdate' => 'document', 'editor' => ['allowCustomCss' => false, 'lockedStructure' => true]],
        'foodmart_categories' => ['name' => 'foodmart_categories', 'category' => 'foodmart', 'schema' => [
            'section_title' => ['type' => 'string', 'label' => 'عنوان بخش', 'default' => ''],
            'view_all_label' => ['type' => 'string', 'label' => 'متن مشاهده همه', 'default' => ''],
            'view_all_url' => ['type' => 'link', 'label' => 'لینک مشاهده همه', 'default' => ''],
            'items' => ['type' => 'repeater', 'label' => 'کارت‌های دسته‌بندی', 'storage' => 'array', 'default' => [], 'fields' => [
                'title' => ['type' => 'string', 'label' => 'عنوان دسته', 'default' => 'New category'],
                'image' => ['type' => 'image', 'label' => 'تصویر/آیکون', 'default' => 'images/icon-vegetables-broccoli.png'],
                'image_alt' => ['type' => 'string', 'label' => 'متن جایگزین تصویر', 'default' => 'Category Thumbnail'],
                'url'  => ['type' => 'link', 'label' => 'لینک', 'default' => 'index.html'],
            ]],
        ], 'defaultProps' => [], 'dataBinding' => ['source' => 'product_categories', 'manual_prop' => 'items'], 'modulePresentation' => ['capability' => 'product_categories.collection', 'label' => 'FoodMart category carousel'], 'editor' => ['allowCustomCss' => false, 'lockedStructure' => true]],
        'foodmart_brands' => ['name' => 'foodmart_brands', 'category' => 'foodmart', 'schema' => [
            'section_title' => ['type' => 'string', 'label' => 'عنوان بخش', 'default' => ''],
            'view_all_label' => ['type' => 'string', 'label' => 'متن مشاهده همه', 'default' => ''],
            'items' => ['type' => 'repeater', 'label' => 'کارت‌های برند', 'storage' => 'array', 'default' => [], 'fields' => [
                'image' => ['type' => 'image', 'label' => 'تصویر', 'default' => 'images/product-thumb-11.jpg'],
                'name' => ['type' => 'string', 'label' => 'نام برند', 'default' => 'Amber Jar'],
                'title' => ['type' => 'string', 'label' => 'توضیح', 'default' => 'Honey best nectar you wish to get'],
            ]],
        ], 'defaultProps' => [], 'liveStructureUpdate' => 'document', 'editor' => ['allowCustomCss' => false, 'lockedStructure' => true]],
        'foodmart_trending_products' => ['name' => 'foodmart_trending_products', 'category' => 'foodmart', 'schema' => [
            'section_title' => ['type' => 'string', 'label' => 'عنوان بخش', 'default' => ''],
            'items' => ['type' => 'repeater', 'label' => 'کارت‌های محصول', 'storage' => 'array', 'default' => [], 'fields' => self::PRODUCT_CARD_FIELDS],
        ], 'defaultProps' => [], 'dataBinding' => ['source' => 'products', 'manual_prop' => 'items'], 'modulePresentation' => ['capability' => 'products.collection', 'label' => 'FoodMart product grid'], 'editor' => ['allowCustomCss' => false, 'lockedStructure' => true]],
        'foodmart_promo_banners' => ['name' => 'foodmart_promo_banners', 'category' => 'foodmart', 'schema' => [
            'section_title' => ['type' => 'string', 'label' => 'Promotion heading', 'default' => 'Featured promotions'],
            'items' => ['type' => 'repeater', 'label' => 'بنرهای تبلیغاتی', 'storage' => 'array', 'default' => [], 'fields' => [
                'badge' => ['type' => 'string', 'label' => 'برچسب تخفیف', 'default' => 'Upto 25% Off'],
                'title' => ['type' => 'string', 'label' => 'عنوان', 'default' => 'New promo banner'],
                'description' => ['type' => 'string', 'label' => 'توضیح', 'default' => 'Very tasty & creamy vanilla flavour creamy muffins.'],
                'cta_label' => ['type' => 'string', 'label' => 'متن دکمه', 'default' => 'Shop Now'],
                'cta_url' => ['type' => 'link', 'label' => 'لینک دکمه', 'default' => '#'],
                'image' => ['type' => 'image', 'label' => 'تصویر پس‌زمینه', 'default' => 'images/ad-image-3.png'],
            ]],
        ], 'defaultProps' => [], 'liveStructureUpdate' => 'document', 'editor' => ['allowCustomCss' => false, 'lockedStructure' => true]],
        'foodmart_product_carousel_best_selling' => ['name' => 'foodmart_product_carousel_best_selling', 'category' => 'foodmart', 'schema' => [
            'section_title' => ['type' => 'string', 'label' => 'Section heading', 'default' => ''],
            'view_all_label' => ['type' => 'string', 'label' => 'متن مشاهده همه', 'default' => ''],
            'items' => ['type' => 'repeater', 'label' => 'کارت‌های محصول', 'storage' => 'array', 'default' => [], 'fields' => self::PRODUCT_CARD_FIELDS],
        ], 'defaultProps' => [], 'dataBinding' => ['source' => 'products', 'manual_prop' => 'items', 'default_sort' => 'best_selling_30d'], 'modulePresentation' => ['capability' => 'products.collection', 'label' => 'FoodMart best-selling carousel'], 'editor' => ['allowCustomCss' => false, 'lockedStructure' => true]],
        'foodmart_newsletter' => ['name' => 'foodmart_newsletter', 'category' => 'foodmart', 'schema' => [
            'title' => ['type' => 'string', 'label' => 'Heading', 'default' => ''],
            'description' => ['type' => 'string', 'label' => 'Description', 'default' => ''],
            'name_label' => ['type' => 'string', 'label' => 'برچسب نام', 'default' => ''],
            'name_placeholder' => ['type' => 'string', 'label' => 'متن راهنمای نام', 'default' => ''],
            'email_label' => ['type' => 'string', 'label' => 'برچسب ایمیل', 'default' => ''],
            'email_placeholder' => ['type' => 'string', 'label' => 'متن راهنمای ایمیل', 'default' => ''],
            'checkbox_label' => ['type' => 'string', 'label' => 'متن چک‌باکس', 'default' => ''],
            'button_label' => ['type' => 'string', 'label' => 'Button label', 'default' => ''],
        ], 'defaultProps' => [], 'liveStructureUpdate' => 'document', 'editor' => ['allowCustomCss' => false, 'lockedStructure' => true]],
        'foodmart_product_carousel_most_popular' => ['name' => 'foodmart_product_carousel_most_popular', 'category' => 'foodmart', 'schema' => [
            'section_title' => ['type' => 'string', 'label' => 'Section heading', 'default' => ''],
            'view_all_label' => ['type' => 'string', 'label' => 'متن مشاهده همه', 'default' => ''],
            'items' => ['type' => 'repeater', 'label' => 'کارت‌های محصول', 'storage' => 'array', 'default' => [], 'fields' => self::PRODUCT_CARD_FIELDS],
        ], 'defaultProps' => [], 'dataBinding' => ['source' => 'products', 'manual_prop' => 'items', 'default_sort' => 'most_viewed'], 'modulePresentation' => ['capability' => 'products.collection', 'label' => 'FoodMart popular-products carousel'], 'editor' => ['allowCustomCss' => false, 'lockedStructure' => true]],
        'foodmart_product_carousel_just_arrived' => ['name' => 'foodmart_product_carousel_just_arrived', 'category' => 'foodmart', 'schema' => [
            'section_title' => ['type' => 'string', 'label' => 'Section heading', 'default' => ''],
            'view_all_label' => ['type' => 'string', 'label' => 'متن مشاهده همه', 'default' => ''],
            'items' => ['type' => 'repeater', 'label' => 'کارت‌های محصول', 'storage' => 'array', 'default' => [], 'fields' => self::PRODUCT_CARD_FIELDS],
        ], 'defaultProps' => [], 'dataBinding' => ['source' => 'products', 'manual_prop' => 'items', 'default_sort' => 'new_arrivals'], 'modulePresentation' => ['capability' => 'products.collection', 'label' => 'FoodMart new-arrivals carousel'], 'editor' => ['allowCustomCss' => false, 'lockedStructure' => true]],
        'foodmart_blog' => ['name' => 'foodmart_blog', 'category' => 'foodmart', 'schema' => [
            'section_title' => ['type' => 'string', 'label' => 'عنوان بخش', 'default' => ''],
            'view_all_label' => ['type' => 'string', 'label' => 'متن مشاهده همه', 'default' => ''],
            'view_all_url' => ['type' => 'link', 'label' => 'لینک مشاهده همه', 'default' => ''],
            'items' => ['type' => 'repeater', 'label' => 'کارت‌های وبلاگ', 'storage' => 'array', 'default' => [], 'fields' => [
                'image' => ['type' => 'image', 'label' => 'تصویر', 'default' => 'images/post-thumb-1.jpg'],
                'image_alt' => ['type' => 'string', 'label' => 'متن جایگزین تصویر', 'default' => 'post'],
                'url' => ['type' => 'link', 'label' => 'لینک کارت', 'default' => '#'],
                'date_text' => ['type' => 'string', 'label' => 'متن تاریخ', 'default' => '22 Aug 2021'],
                'category_label' => ['type' => 'string', 'label' => 'برچسب دسته', 'default' => 'tips & tricks'],
                'title' => ['type' => 'string', 'label' => 'عنوان', 'default' => 'New blog post title'],
                'excerpt' => ['type' => 'string', 'label' => 'خلاصه', 'default' => 'Short excerpt shown under the title.'],
            ]],
        ], 'defaultProps' => [], 'dataBinding' => ['source' => 'blog_posts', 'manual_prop' => 'items'], 'modulePresentation' => ['capability' => 'blog.posts', 'label' => 'FoodMart blog cards'], 'editor' => ['allowCustomCss' => false, 'lockedStructure' => true]],
        'foodmart_app_promo' => ['name' => 'foodmart_app_promo', 'category' => 'foodmart', 'schema' => [
            'title' => ['type' => 'string', 'label' => 'Heading', 'default' => ''],
            'description' => ['type' => 'string', 'label' => 'Description', 'default' => ''],
            'phone_image' => ['type' => 'image', 'label' => 'تصویر گوشی', 'default' => 'images/phone.png'],
            'store_image' => ['type' => 'image', 'label' => 'تصویر App Store', 'default' => 'images/app-store.jpg'],
            'store_url' => ['type' => 'link', 'label' => 'لینک App Store', 'default' => ''],
            'play_image' => ['type' => 'image', 'label' => 'تصویر Google Play', 'default' => 'images/google-play.jpg'],
            'play_url' => ['type' => 'link', 'label' => 'لینک Google Play', 'default' => ''],
        ], 'defaultProps' => [], 'liveStructureUpdate' => 'document', 'editor' => ['allowCustomCss' => false, 'lockedStructure' => true]],
        'foodmart_keywords' => ['name' => 'foodmart_keywords', 'category' => 'foodmart', 'schema' => [
            'section_title' => ['type' => 'string', 'label' => 'Section heading', 'default' => ''],
            'items' => ['type' => 'repeater', 'label' => 'کلیدواژه‌ها', 'storage' => 'array', 'default' => [], 'fields' => [
                'label' => ['type' => 'string', 'label' => 'متن', 'default' => 'New keyword'],
                'url' => ['type' => 'link', 'label' => 'لینک', 'default' => '#'],
            ]],
        ], 'defaultProps' => [], 'liveStructureUpdate' => 'document', 'editor' => ['allowCustomCss' => false, 'lockedStructure' => true]],
        'foodmart_trust_badges' => ['name' => 'foodmart_trust_badges', 'category' => 'foodmart', 'schema' => [
            'items' => ['type' => 'repeater', 'label' => 'Trust items', 'storage' => 'array', 'default' => [], 'fields' => [
                'title' => ['type' => 'string', 'label' => 'Title', 'default' => 'New trust item'],
                'description' => ['type' => 'string', 'label' => 'Description', 'default' => 'Describe this customer promise.'],
            ]],
        ], 'defaultProps' => [], 'liveStructureUpdate' => 'document', 'editor' => ['allowCustomCss' => false, 'lockedStructure' => true]],
        'foodmart_footer' => ['name' => 'foodmart_footer', 'category' => 'foodmart', 'schema' => [
            'logo_image' => ['type' => 'image', 'label' => 'لوگو', 'default' => ''],
            'logo_alt' => ['type' => 'string', 'label' => 'متن جایگزین لوگو', 'default' => ''],
            'columns' => ['type' => 'repeater', 'label' => 'ستون‌های فوتر', 'storage' => 'json', 'default' => [], 'fields' => [
                'heading' => ['type' => 'string', 'label' => 'عنوان ستون', 'default' => 'New column'],
                'links' => ['type' => 'repeater', 'label' => 'لینک‌ها', 'storage' => 'array', 'default' => [], 'fields' => [
                    'label' => ['type' => 'string', 'label' => 'عنوان لینک', 'default' => 'New link'],
                    'url' => ['type' => 'link', 'label' => 'آدرس', 'default' => '#'],
                ]],
            ]],
            'newsletter_heading' => ['type' => 'string', 'label' => 'عنوان خبرنامه', 'default' => ''],
            'newsletter_text' => ['type' => 'string', 'label' => 'متن خبرنامه', 'default' => ''],
            'newsletter_placeholder' => ['type' => 'string', 'label' => 'متن راهنمای ایمیل', 'default' => ''],
            'newsletter_button' => ['type' => 'string', 'label' => 'متن دکمه', 'default' => ''],
            'copyright' => ['type' => 'string', 'label' => 'متن کپی‌رایت', 'default' => ''],
            'credit_text' => ['type' => 'string', 'label' => 'متن اعتبار قالب', 'default' => ''],
        ], 'defaultProps' => [], 'liveStructureUpdate' => 'document', 'editor' => ['allowCustomCss' => false, 'lockedStructure' => true]],
    ];

    /**
     * Shared manual product-card field vocabulary — presentation-only card
     * content (no product-id/cart identity: manual cards never wire a fake
     * Add-to-Cart; they expose a safe CTA link instead).
     */
    public const PRODUCT_CARD_FIELDS = [
        'image' => ['type' => 'image', 'label' => 'تصویر', 'default' => 'images/thumb-bananas.png'],
        'image_alt' => ['type' => 'string', 'label' => 'متن جایگزین تصویر', 'default' => 'Product'],
        'url' => ['type' => 'link', 'label' => 'لینک', 'default' => '#'],
        'badge' => ['type' => 'string', 'label' => 'برچسب تخفیف', 'default' => ''],
        'title' => ['type' => 'string', 'label' => 'نام محصول', 'default' => 'New product'],
        'unit_label' => ['type' => 'string', 'label' => 'واحد/زیرعنوان', 'default' => '1 Unit'],
        'price_text' => ['type' => 'string', 'label' => 'متن قیمت', 'default' => '$0.00'],
        'old_price_text' => ['type' => 'string', 'label' => 'قیمت قبلی (خط خورده)', 'default' => ''],
        'cta_label' => ['type' => 'string', 'label' => 'متن دکمه', 'default' => 'Shop Now'],
    ];

    /** Register all canonical foodmart_* types on this host's registry, verbatim. */
    public static function register(): void
    {
        // Every definition carries its own generic 'editor' capability
        // metadata (allowCustomCss / lockedStructure) inline in TYPES, so
        // registration stays verbatim and replaces host-side theme name
        // checks. Imported fidelity structures keep their shape, while
        // every schema-declared content value remains editable.
        foreach (self::TYPES as $type => $definition) {
            BlockRegistry::registerHostType($type, $definition);
        }
    }
}
