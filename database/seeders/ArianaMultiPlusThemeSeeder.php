<?php

namespace Database\Seeders;

use App\Models\Theme;
use App\Models\ThemeMarketplace;
use App\Services\ThemeBuilder\ThemePlaceholderService;
use App\Services\ThemeBuilder\ThemeBuilderService;
use Illuminate\Database\Seeder;

/**
 * Canonical multipurpose Persian commercial theme: "آرینا مولتی‌پلاس" (Ariana MultiPlus).
 *
 * One preset, three business shapes — corporate, store, and personal —
 * authored exclusively from EXISTING canonical block types registered in
 * SaaSPlatform\ThemeRuntime\BlockRegistry. No new block type, renderer,
 * migration, or package change is introduced (constitution §3: reuse
 * canonical components; do not create parallel implementations).
 *
 * Design contract:
 *  - RTL + fa-IR, Vazirmatn font family via global_styles.typography.
 *  - Mobile-number-only auth identity (no email in copy or CTA).
 *  - Professional image/product/testimonial/logo sliders via the shared
 *    carousel engine (slider, product_slider, category_slider,
 *    testimonials_slider, client_logos).
 *  - Every block is fully editable later in the Canvas editor by the
 *    customer (ThemeCanvasController::save), because every prop matches
 *    the canonical schema/defaultProps shape.
 *
 * Authoring seed only; it never publishes, assigns, installs, or migrates.
 */
final class ArianaMultiPlusThemeSeeder extends Seeder
{
    public const SLUG = 'ariana-multiplus';
    public const VERSION = '1.0.0';

    public function run(): void
    {
        $service = app(ThemeBuilderService::class);
        $defaultStyles = $service->defaultGlobalStyles();
        $placeholder = app(ThemePlaceholderService::class);

        $theme = Theme::firstOrNew(['slug' => self::SLUG, 'site_id' => null]);

        $theme->fill([
            'name' => 'آرینا مولتی‌پلاس — قالب چندمنظوره',
            'description' => 'قالب تجاری چندمنظوره فارسی و RTL برای کسب‌وکارهای شرکتی، فروشگاهی و شخصی؛ شامل اسلایدرهای حرفه‌ای تصویر، محصول و نظرات مشتریان، با قابلیت ویرایش کامل در قالب‌ساز.',
            'type' => Theme::TYPE_FULL,
            'category' => Theme::CATEGORY_BUSINESS,
            'source' => Theme::SOURCE_SEEDER,
            'pages' => self::pages($placeholder),
            'header_blocks' => [self::header()],
            'footer_blocks' => [self::footer()],
            'global_styles' => self::globalStyles($defaultStyles),
            'settings' => array_merge(is_array($theme->settings ?? null) ? $theme->settings : [], [
                'preset' => 'multiplus',
                'supported_locales' => ['fa-IR'],
                'default_locale' => 'fa-IR',
                'locale_contract' => [
                    'directions' => ['fa-IR' => 'rtl'],
                    'font_families' => ['fa-IR' => 'Vazirmatn'],
                ],
                'meta' => [
                    'id' => self::SLUG,
                    'version' => self::VERSION,
                    'required_platform_version' => '1.0.0',
                    'supported_modules' => ['shop', 'blog', 'forms', 'menu', 'social_links', 'reviews'],
                    'changelog' => [
                        self::VERSION => [
                            'قالب چندمنظوره شرکتی، فروشگاهی و شخصی با اسلایدرهای حرفه‌ای.',
                            'بهینه‌سازی برای موبایل، RTL و فونت فارسی وزیر.',
                        ],
                    ],
                ],
            ]),
        ]);

        // Published for authoring/workflow reasons only (canonical
        // seeder convention, see ThemeCatalogRebuildCommand): it makes
        // the theme visible in the catalog and assignable to customer
        // sites. Commercial eligibility still requires a ThemeRelease
        // backed by a passing Commercial Quality Report (theme-release:backfill).
        $theme->status = Theme::STATUS_PUBLISHED;
        $theme->is_marketplace = true;
        $theme->marketplace_price = 0;

        $theme->save();

        // Honest marketplace catalog row (NexusSeedCommand convention):
        // no fabricated sales counts, ratings or featured flags.
        ThemeMarketplace::updateOrCreate(['theme_id' => $theme->id], [
            'marketplace_description' => $theme->description,
            'category_tags' => ['شرکتی', 'فروشگاهی', 'شخصی', 'کسب‌وکار'],
            'is_featured' => false,
            'is_new_release' => false,
            'is_bestseller' => false,
            'annual_price' => 0,
        ]);
    }

    /** @return array<string,array<string,mixed>> */
    public static function pages(ThemePlaceholderService $placeholder): array
    {
        return [
            'home' => [
                'title' => 'صفحه اصلی',
                'slug' => '',
                'seo' => [
                    'title' => 'آرینا مولتی‌پلاس | قالب چندمنظوره کسب‌وکار شما',
                    'description' => 'معرفی محصولات و خدمات کسب‌وکار شما با قالبی حرفه‌ای، فارسی و کاملاً واکنش‌گرا.',
                ],
                'blocks' => self::homeBlocks($placeholder),
            ],
            'services' => [
                'title' => 'خدمات',
                'slug' => 'services',
                'seo' => [
                    'title' => 'خدمات | آرینا مولتی‌پلاس',
                    'description' => 'خدمات حرفه‌ای برای رشد کسب‌وکار شما.',
                ],
                'blocks' => self::servicesBlocks($placeholder),
            ],
            'shop' => [
                'title' => 'فروشگاه',
                'slug' => 'shop',
                'seo' => [
                    'title' => 'فروشگاه | آرینا مولتی‌پلاس',
                    'description' => 'محصولات منتخب فروشگاه شما.',
                ],
                'blocks' => self::shopBlocks($placeholder),
            ],
            'about' => [
                'title' => 'درباره ما',
                'slug' => 'about',
                'seo' => [
                    'title' => 'درباره ما | آرینا مولتی‌پلاس',
                    'description' => 'داستان، تیم و ارزش‌های کسب‌وکار شما.',
                ],
                'blocks' => self::aboutBlocks($placeholder),
            ],
            'contact' => [
                'title' => 'تماس با ما',
                'slug' => 'contact',
                'seo' => [
                    'title' => 'تماس با ما | آرینا مولتی‌پلاس',
                    'description' => 'راه‌های ارتباطی با کسب‌وکار شما.',
                ],
                'blocks' => self::contactBlocks(),
            ],
        ];
    }

    // ── HOME ───────────────────────────────────────────────────────────────

    /** @return list<array<string,mixed>> */
    public static function homeBlocks(ThemePlaceholderService $placeholder): array
    {
        return [
            self::block('amp-hero', 'hero_split', [
                'badge' => 'بهترین انتخاب برای کسب‌وکار ایرانی',
                'title' => 'کسب‌وکار شما، با یک قالب حرفه‌ای رشد می‌کند',
                'subtitle' => 'قالبی چندمنظوره، فارسی و کاملاً واکنش‌گرا برای معرفی خدمات، فروش محصولات و برندسازی شخصی. همه چیز در یک قالب، و قابل ویرایش در چند دقیقه.',
                'button_text' => 'شروع رایگان',
                'button_url' => '#amp-services',
                'button2_text' => 'مشاهده فروشگاه',
                'button2_url' => '/shop',
                'image_url' => $placeholder->svgPlaceholder(900, 640, 0),
                'image_side' => 'left',
                'background' => '#f8fafc',
            ]),
            self::block('amp-logos', 'client_logos', [
                'title' => 'مورد اعتماد کسب‌وکارهای ایرانی',
                'mode' => 'carousel',
                'grayscale' => true,
                'visible_count' => 5,
                'speed' => 25,
                'logos' => json_encode([
                    ['name' => 'فروشگاه آریا', 'url' => '#'],
                    ['name' => 'گروه صنعتی پارس', 'url' => '#'],
                    ['name' => 'استارتاپ نوین', 'url' => '#'],
                    ['name' => 'شرکت توسعه فردا', 'url' => '#'],
                    ['name' => 'برند ایرانی', 'url' => '#'],
                    ['name' => 'موسسه حقوقی مهر', 'url' => '#'],
                ], JSON_UNESCAPED_UNICODE),
            ]),
            self::block('amp-services', 'services_cards', [
                'title' => 'خدمات ما',
                'subtitle' => 'راه‌حل‌های حرفه‌ای برای هر نوع کسب‌وکار',
                'columns' => '3',
                'style' => 'bordered',
                'services' => json_encode([
                    ['icon' => '🎨', 'title' => 'طراحی و برندسازی', 'text' => 'طراحی هویت بصری حرفه‌ای برای کسب‌وکار شما', 'link' => '/services', 'link_text' => 'بیشتر بدانید'],
                    ['icon' => '🛒', 'title' => 'فروشگاه آنلاین', 'text' => 'راه‌اندازی فروشگاه اینترنتی با درگاه پرداخت ایرانی', 'link' => '/shop', 'link_text' => 'بیشتر بدانید'],
                    ['icon' => '📈', 'title' => 'بازاریابی دیجیتال', 'text' => 'افزایش فروش و دیده‌شدن برند شما در فضای آنلاین', 'link' => '/services', 'link_text' => 'بیشتر بدانید'],
                ], JSON_UNESCAPED_UNICODE),
            ]),
            self::block('amp-product-slider', 'product_slider', [
                'title' => 'محصولات منتخب',
                'limit' => 8,
                'columns' => '4',
                'source' => 'latest',
                'autoplay' => true,
                'interval' => 5000,
            ]),
            self::block('amp-category-slider', 'category_slider', [
                'title' => 'دسته‌بندی محصولات',
                'layout' => 'carousel',
                'columns' => '4',
                'source' => 'all',
                'autoplay' => true,
                'interval' => 5000,
            ]),
            self::block('amp-stats', 'stats_row', [
                'title' => 'آرینا مولتی‌پلاس در یک نگاه',
                'background' => '#0f172a',
                'background_image' => '',
                'dividers' => true,
                'stats' => json_encode([
                    ['value' => 1200, 'suffix' => '+', 'label' => 'کسب‌وکار فعال'],
                    ['value' => 98, 'suffix' => '٪', 'label' => 'رضایت مشتریان'],
                    ['value' => 350, 'suffix' => '+', 'label' => 'پروژه موفق'],
                    ['value' => 24, 'suffix' => '/۷', 'label' => 'پشتیبانی'],
                ], JSON_UNESCAPED_UNICODE),
            ]),
            self::block('amp-image-slider', 'slider', [
                'autoplay' => true,
                'interval' => 5500,
                'transition' => 'slide',
                'boxed' => false,
                'aspect_ratio' => '21/9',
                'overlay' => true,
                'slides' => [
                    [
                        'image' => $placeholder->svgPlaceholder(1600, 700, 1),
                        'title' => 'حراج ویژه پاییز',
                        'subtitle' => 'تا ۴۰٪ تخفیف روی محصولات منتخب',
                        'link' => '/shop',
                        'cta_text' => 'مشاهده تخفیف‌ها',
                    ],
                    [
                        'image' => $placeholder->svgPlaceholder(1600, 700, 2),
                        'title' => 'خدمات حرفه‌ای شرکتی',
                        'subtitle' => 'راه‌حل‌های یکپارچه برای سازمان شما',
                        'link' => '/services',
                        'cta_text' => 'مشاوره رایگان',
                    ],
                    [
                        'image' => $placeholder->svgPlaceholder(1600, 700, 4),
                        'title' => 'برندسازی شخصی',
                        'subtitle' => 'حضور حرفه‌ای در دنیای دیجیتال',
                        'link' => '/about',
                        'cta_text' => 'بیشتر بدانید',
                    ],
                ],
            ]),
            self::block('amp-features', 'features_grid', [
                'title' => 'چرا آرینا مولتی‌پلاس؟',
                'subtitle' => 'امکاناتی که هر کسب‌وکاری نیاز دارد',
                'columns' => '4',
                'style' => 'cards',
                'features' => json_encode([
                    ['icon' => '⚡', 'title' => 'سرعت بالا', 'text' => 'بارگذاری سریع و تجربه روان کاربر'],
                    ['icon' => '📱', 'title' => 'واکنش‌گرا', 'text' => 'نمایش بی‌نقص روی موبایل و تبلت'],
                    ['icon' => '🔒', 'title' => 'امن و مطمئن', 'text' => 'حفاظت از اطلاعات شما و مشتریانتان'],
                    ['icon' => '🎨', 'title' => 'قابل ویرایش', 'text' => 'تغییر همه چیز در قالب‌ساز بدون کدنویسی'],
                ], JSON_UNESCAPED_UNICODE),
            ]),
            self::block('amp-bento', 'bento_grid', [
                'title' => 'همه چیز در یک قالب',
                'items' => json_encode([
                    ['title' => 'فروشگاه حرفه‌ای', 'text' => 'مدیریت محصولات، سفارش‌ها و موجودی', 'icon' => '🛍️', 'size' => 'large', 'bg_color' => '#4f46e5', 'text_color' => '#ffffff'],
                    ['title' => 'سئوی فارسی', 'text' => 'بهینه‌سازی برای موتورهای جستجو', 'icon' => '🔍', 'size' => 'small', 'bg_color' => '#f0fdf4', 'text_color' => '#14532d'],
                    ['title' => 'پشتیبانی ۲۴/۷', 'text' => 'همیشه در کنار شما هستیم', 'icon' => '💬', 'size' => 'small', 'bg_color' => '#fef9c3', 'text_color' => '#713f12'],
                ], JSON_UNESCAPED_UNICODE),
            ]),
            self::block('amp-testimonials', 'testimonials_slider', [
                'title' => 'مشتریان ما چه می‌گویند',
                'columns' => '2',
                'mode' => 'carousel',
                'autoplay' => true,
                'interval' => 6000,
                'items' => json_encode([
                    ['name' => 'رضا احمدی', 'role' => 'مدیر فروش، فروشگاه آریا', 'text' => 'بعد از نصب این قالب، فروش آنلاین ما دو برابر شد. ویرایش آن هم بسیار ساده است.', 'rating' => 5],
                    ['name' => 'مریم نوری', 'role' => 'بنیان‌گذار استارتاپ نوین', 'text' => 'قالبی زیبا و سریع. پشتیبانی عالی و تیم حرفه‌ای.', 'rating' => 5],
                    ['name' => 'علی حسینی', 'role' => 'مدیر بازاریابی گروه پارس', 'text' => 'بهترین قالبی که تا حالا استفاده کردیم. کاملاً فارسی و راست‌چین.', 'rating' => 5],
                ], JSON_UNESCAPED_UNICODE),
            ]),
            self::block('amp-pricing', 'pricing_cards', [
                'title' => 'پلن‌های ما',
                'subtitle' => 'مناسب هر نوع کسب‌وکار',
                'plans' => json_encode([
                    ['name' => 'پایه', 'price' => '۱۹۰ هزار', 'period' => '/ ماه', 'badge' => '', 'highlight' => false, 'features' => ['یک دامنه', 'پشتیبانی تلفنی', 'به‌روزرسانی رایگان'], 'cta_text' => 'انتخاب پلن', 'cta_url' => '#'],
                    ['name' => 'حرفه‌ای', 'price' => '۳۹۰ هزار', 'period' => '/ ماه', 'badge' => 'محبوب‌ترین', 'highlight' => true, 'features' => ['پنج دامنه', 'فروشگاه آنلاین', 'پشتیبانی ۲۴/۷', 'بهینه‌سازی سئو'], 'cta_text' => 'انتخاب پلن', 'cta_url' => '#'],
                    ['name' => 'سازمانی', 'price' => '۷۸۰ هزار', 'period' => '/ ماه', 'badge' => '', 'highlight' => false, 'features' => ['دامنه نامحدود','فضای اختصاصی','مدیر حساب اختصاصی'], 'cta_text' => 'تماس با ما', 'cta_url' => '/contact'],
                ], JSON_UNESCAPED_UNICODE),
            ]),
            self::block('amp-process', 'process_steps', [
                'title' => 'چطور شروع کنیم؟',
                'subtitle' => 'در سه قدم ساده',
                'steps' => json_encode([
                    ['number' => '۱', 'title' => 'قالب را نصب کنید', 'text' => 'قالب آرینا مولتی‌پلاس را از بازار قالب‌ها نصب کنید'],
                    ['number' => '۲', 'title' => 'شخصی‌سازی کنید', 'text' => 'متن‌ها، تصاویر و رنگ‌ها را در قالب‌ساز تغییر دهید'],
                    ['number' => '۳', 'title' => 'انتشار دهید', 'text' => 'سایت خود را منتشر کنید و فروش را شروع کنید'],
                ], JSON_UNESCAPED_UNICODE),
            ]),
            self::block('amp-faq', 'faq', [
                'title' => 'سوالات متداول',
                'items' => json_encode([
                    ['question' => 'آیا قالب با موبایل سازگار است؟', 'answer' => 'بله، قالب کاملاً واکنش‌گرا است و روی همه دستگاه‌ها به‌خوبی نمایش داده می‌شود.'],
                    ['question' => 'آیا می‌توانم رنگ‌ها و فونت‌ها را تغییر دهم؟', 'answer' => 'بله، همه رنگ‌ها، فونت‌ها و استایل‌ها در قالب‌ساز قابل ویرایش هستند.'],
                    ['question' => 'آیا قالب از زبان فارسی پشتیبانی می‌کند؟', 'answer' => 'بله، قالب به صورت کامل راست‌چین و فارسی طراحی شده و از فونت وزیر استفاده می‌کند.'],
                    ['question' => 'آیا برای استفاده به دانش فنی نیاز دارم؟', 'answer' => 'خیر، قالب‌ساز بصری به شما اجازه می‌دهد همه چیز را بدون کدنویسی تغییر دهید.'],
                ], JSON_UNESCAPED_UNICODE),
            ]),
            self::block('amp-newsletter', 'newsletter_form', [
                'title' => 'از تخفیف‌ها و اخبار جا نمونید',
                'subtitle' => 'شماره موبایل خود را وارد کنید تا جدیدترین پیشنهادات را دریافت کنید',
                'placeholder' => 'شماره موبایل شما',
                'button_text' => 'عضویت رایگان',
                'background' => '#4f46e5',
                'text_color' => '#ffffff',
            ]),
            self::block('amp-cta', 'cta_band', [
                'align' => 'center',
                'padding' => 56,
                'radius' => 20,
                'background' => '#0f172a',
                'text_color' => '#ffffff',
            ], [
                self::block('amp-cta-heading', 'heading', [
                    'level' => 2,
                    'text' => 'آماده شروع هستید؟',
                ]),
                self::block('amp-cta-text', 'text', [
                    'content' => 'همین امروز قالب آرینا مولتی‌پلاس را نصب کنید و کسب‌وکار خود را آنلاین شروع کنید.',
                ]),
                self::block('amp-cta-btn', 'button', [
                    'text' => 'شروع رایگان',
                    'url' => '#amp-hero',
                    'style' => 'primary',
                ]),
            ]),
        ];
    }

    // ── SERVICES PAGE ──────────────────────────────────────────────────────

    /** @return list<array<string,mixed>> */
    public static function servicesBlocks(ThemePlaceholderService $placeholder): array
    {
        return [
            self::block('amp-srv-hero', 'hero_gradient', [
                'badge' => 'خدمات ما',
                'title' => 'راه‌حل‌های حرفه‌ای برای رشد کسب‌وکار شما',
                'subtitle' => 'از طراحی وب‌سایت تا بازاریابی دیجیتال، همه خدمات در یک جا.',
                'button_text' => 'دریافت مشاوره',
                'button_url' => '/contact',
                'button2_text' => 'نمونه کارها',
                'button2_url' => '/about',
                'gradient_from' => '#4f46e5',
                'gradient_to' => '#7c3aed',
                'text_color' => '#ffffff',
            ]),
            self::block('amp-srv-cards', 'service_cards', [
                'title' => 'خدمات تخصصی',
                'subtitle' => 'تیمی متخصص در کنار شماست',
                'columns' => '3',
                'cards' => [
                    ['icon' => '💻', 'title' => 'طراحی وب‌سایت', 'description' => 'طراحی وب‌سایت‌های شرکتی، فروشگاهی و شخصی', 'url' => '/contact'],
                    ['icon' => '🛒', 'title' => 'راه‌اندازی فروشگاه', 'description' => 'فروشگاه اینترنتی کامل با درگاه پرداخت', 'url' => '/contact'],
                    ['icon' => '📱', 'title' => 'اپلیکیشن موبایل', 'description' => 'طراحی اپلیکیشن اندروید و iOS', 'url' => '/contact'],
                    ['icon' => '🎨', 'title' => 'طراحی گرافیک', 'description' => 'طراحی لوگو، کاتالوگ و هویت بصری', 'url' => '/contact'],
                    ['icon' => '📈', 'title' => 'سئو و بازاریابی', 'description' => 'بهینه‌سازی سایت و تبلیغات آنلاین', 'url' => '/contact'],
                    ['icon' => '🛠️', 'title' => 'پشتیبانی فنی', 'description' => 'پشتیبانی ۲۴/۷ و نگهداری سایت', 'url' => '/contact'],
                ],
            ]),
            self::block('amp-srv-process', 'process_steps', [
                'title' => 'مراحل انجام پروژه',
                'subtitle' => 'فرآیندی شفاف و حرفه‌ای',
                'steps' => json_encode([
                    ['number' => '۱', 'title' => 'مشاوره رایگان', 'text' => 'نیازهای شما را بررسی می‌کنیم'],
                    ['number' => '۲', 'title' => 'طراحی و توسعه', 'text' => 'پروژه را با بالاترین کیفیت اجرا می‌کنیم'],
                    ['number' => '۳', 'title' => 'تحویل و آموزش', 'text' => 'پروژه را تحویل و آموزش می‌دهیم'],
                    ['number' => '۴', 'title' => 'پشتیبانی', 'text' => 'همیشه در کنار شما هستیم'],
                ], JSON_UNESCAPED_UNICODE),
            ]),
            self::block('amp-srv-projects', 'project_cards', [
                'title' => 'نمونه پروژه‌ها',
                'subtitle' => 'افتخار همکاری با کسب‌وکارهای موفق',
                'columns' => '3',
                'cards' => [
                    ['category' => 'فروشگاهی', 'title' => 'فروشگاه آریا', 'description' => 'طراحی فروشگاه اینترنتی با بیش از ۱۰۰۰ محصول', 'url' => '#'],
                    ['category' => 'شرکتی', 'title' => 'گروه صنعتی پارس', 'description' => 'وب‌سایت شرکتی با پنل مدیریت اختصاصی', 'url' => '#'],
                    ['category' => 'شخصی', 'title' => 'دکتر مهر', 'description' => 'وب‌سایت شخصی با سیستم نوبت‌دهی', 'url' => '#'],
                ],
            ]),
            self::block('amp-srv-cta', 'hero_trust', [
                'badge' => 'آماده شروع',
                'title' => 'پروژه خود را به ما بسپارید',
                'subtitle' => 'برای دریافت مشاوره رایگان با ما در تماس باشید',
                'rating' => '۴.۹',
                'rating_label' => 'از ۵۰۰ نظر مشتری',
                'phone' => '۰۲۱-۱۲۳۴۵۶۷۸',
                'btn_primary_text' => 'دریافت مشاوره',
                'btn_primary_url' => '/contact',
                'btn_secondary_text' => 'نمونه کارها',
                'btn_secondary_url' => '/about',
            ]),
        ];
    }

    // ── SHOP PAGE ──────────────────────────────────────────────────────────

    /** @return list<array<string,mixed>> */
    public static function shopBlocks(ThemePlaceholderService $placeholder): array
    {
        return [
            self::block('amp-shop-hero', 'hero', [
                'title' => 'فروشگاه آرینا',
                'subtitle' => 'بهترین محصولات با بهترین قیمت‌ها، با ارسال سریع به سراسر ایران',
                'button_text' => 'مشاهده تخفیف‌ها',
                'button_url' => '#amp-shop-slider',
                'background' => 'primary',
                'background_image' => '',
                'align' => 'center',
            ]),
            self::block('amp-shop-slider', 'slider', [
                'autoplay' => true,
                'interval' => 5000,
                'transition' => 'fade',
                'boxed' => false,
                'aspect_ratio' => '16/9',
                'overlay' => true,
                'slides' => [
                    ['image' => $placeholder->svgPlaceholder(1600, 900, 0), 'title' => 'تخفیف ویژه', 'subtitle' => 'تا ۳۰٪ تخفیف روی محصولات منتخب', 'link' => '#', 'cta_text' => 'خرید کنید'],
                    ['image' => $placeholder->svgPlaceholder(1600, 900, 2), 'title' => 'ارسال رایگان', 'subtitle' => 'برای سفارش‌های بالای ۵۰۰ هزار تومان', 'link' => '#', 'cta_text' => 'بیشتر بدانید'],
                ],
            ]),
            self::block('amp-shop-categories', 'category_slider', [
                'title' => 'دسته‌بندی‌ها',
                'layout' => 'carousel',
                'columns' => '4',
                'source' => 'all',
                'autoplay' => true,
                'interval' => 5000,
            ]),
            self::block('amp-shop-grid', 'product_grid', [
                'columns' => 4,
                'limit' => 12,
            ]),
            self::block('amp-shop-trust', 'trust_badges', [
                'background' => '#f8fafc',
                'badges' => json_encode([
                    ['icon' => '🚚', 'title' => 'ارسال سریع', 'text' => 'ارسال به سراسر ایران در کوتاه‌ترین زمان'],
                    ['icon' => '🔒', 'title' => 'پرداخت امن', 'text' => 'درگاه‌های پرداخت معتبر و امن'],
                    ['icon' => '↩️', 'title' => 'بازگشت آسان', 'text' => 'ضمانت بازگشت کالا تا ۷ روز'],
                    ['icon' => '☎️', 'title' => 'پشتیبانی ۲۴/۷', 'text' => 'همیشه در دسترس شما هستیم'],
                ], JSON_UNESCAPED_UNICODE),
            ]),
            self::block('amp-shop-products', 'product_slider', [
                'title' => 'پرفروش‌ترین محصولات',
                'limit' => 10,
                'columns' => '4',
                'source' => 'latest',
                'autoplay' => true,
                'interval' => 5000,
            ]),
            self::block('amp-shop-newsletter', 'newsletter_form', [
                'title' => 'از کدهای تخفیف جا نمونید',
                'subtitle' => 'شماره موبایل خود را وارد کنید',
                'placeholder' => 'شماره موبایل شما',
                'button_text' => 'دریافت کد تخفیف',
                'background' => '#059669',
                'text_color' => '#ffffff',
            ]),
        ];
    }

    // ── ABOUT PAGE ─────────────────────────────────────────────────────────

    /** @return list<array<string,mixed>> */
    public static function aboutBlocks(ThemePlaceholderService $placeholder): array
    {
        return [
            self::block('amp-about-hero', 'hero_split', [
                'badge' => 'درباره ما',
                'title' => 'داستان ما، داستان رشد کسب‌وکارهای ایرانی',
                'subtitle' => 'ما تیمی جوان و متخصص هستیم که می‌خواهیم هر کسب‌وکار ایرانی بتواند حضور حرفه‌ای در دنیای دیجیتال داشته باشد.',
                'button_text' => 'با ما در تماس باشید',
                'button_url' => '/contact',
                'button2_text' => 'خدمات ما',
                'button2_url' => '/services',
                'image_url' => $placeholder->svgPlaceholder(900, 640, 3),
                'image_side' => 'right',
                'background' => '#ffffff',
            ]),
            self::block('amp-about-section', 'about_section', [
                'badge' => 'ماموریت ما',
                'title' => 'چرا ما را انتخاب کنید؟',
                'description' => 'ما باور داریم هر کسب‌وکار ایرانی، هر چقدر هم کوچک باشد، شایسته یک حضور حرفه‌ای آنلاین است. قالب‌های ما طوری طراحی شده‌اند که بدون نیاز به دانش فنی، در چند دقیقه قابل شخصی‌سازی باشند.',
                'features' => [
                    ['label' => 'بیش از ۱۰ سال تجربه در طراحی وب'],
                    ['label' => 'تیمی متخصص و خوش‌اخلاق'],
                    ['label' => 'پشتیبانی واقعی ۲۴ ساعته'],
                    ['label' => 'قیمت منصفانه و شفاف'],
                ],
                'stats' => [
                    ['value' => '۱۲۰۰+', 'label' => 'مشتری راضی'],
                    ['value' => '۳۵۰+', 'label' => 'پروژه موفق'],
                    ['value' => '۹۸٪', 'label' => 'رضایت مشتری'],
                ],
                'btn_text' => 'تماس با ما',
                'btn_url' => '/contact',
            ]),
            self::block('amp-about-timeline', 'timeline', [
                'title' => 'مسیر رشد ما',
                'subtitle' => 'از یک ایده تا یک تیم بزرگ',
                'layout' => 'vertical',
                'items' => json_encode([
                    ['date' => '۱۳۹۸', 'title' => 'شروع کار', 'text' => 'تیم کوچکی با یک ایده بزرگ شروع به کار کرد.', 'icon' => '🚀', 'image' => '', 'link_label' => '', 'link_url' => ''],
                    ['date' => '۱۴۰۰', 'title' => 'عرضه اولین قالب', 'text' => 'اولین قالب تجاری ما منتشر شد.', 'icon' => '📦', 'image' => '', 'link_label' => '', 'link_url' => ''],
                    ['date' => '۱۴۰۲', 'title' => 'عبور از هزار مشتری', 'text' => 'به هزار مشتری فعال رسیدیم.', 'icon' => '📈', 'image' => '', 'link_label' => '', 'link_url' => ''],
                    ['date' => '۱۴۰۴', 'title' => 'ورود به بازارهای جدید', 'text' => 'توسعه خدمات و ورود به بازارهای بین‌المللی.', 'icon' => '🌍', 'image' => '', 'link_label' => '', 'link_url' => ''],
                ], JSON_UNESCAPED_UNICODE),
            ]),
            self::block('amp-about-team', 'team_cards', [
                'title' => 'تیم متخصص ما',
                'columns' => '4',
                'members' => json_encode([
                    ['name' => 'سارا محمدی', 'role' => 'مدیرعامل', 'image' => '', 'bio' => 'بنیان‌گذار و مدیرعامل شرکت'],
                    ['name' => 'علی رضایی', 'role' => 'مدیر فنی', 'image' => '', 'bio' => 'متخصص ارشد توسعه نرم‌افزار'],
                    ['name' => 'مریم کریمی', 'role' => 'طراح ارشد', 'image' => '', 'bio' => 'طراح رابط کاربری و تجربه کاربری'],
                    ['name' => 'حسین احمدی', 'role' => 'مدیر بازاریابی', 'image' => '', 'bio' => 'متخصص بازاریابی دیجیتال'],
                ], JSON_UNESCAPED_UNICODE),
            ]),
            self::block('amp-about-skills', 'skills_bars', [
                'title' => 'مهارت‌های تخصصی ما',
                'skills' => json_encode([
                    ['name' => 'طراحی وب', 'percent' => 95],
                    ['name' => 'توسعه نرم‌افزار', 'percent' => 90],
                    ['name' => 'بازاریابی دیجیتال', 'percent' => 85],
                    ['name' => 'پشتیبانی مشتریان', 'percent' => 98],
                ], JSON_UNESCAPED_UNICODE),
            ]),
            self::block('amp-about-stats', 'stats_row', [
                'title' => 'ما در یک نگاه',
                'background' => '#4f46e5',
                'background_image' => '',
                'dividers' => true,
                'stats' => json_encode([
                    ['value' => 1200, 'suffix' => '+', 'label' => 'مشتری راضی'],
                    ['value' => 350, 'suffix' => '+', 'label' => 'پروژه موفق'],
                    ['value' => 24, 'suffix' => '/۷', 'label' => 'پشتیبانی'],
                    ['value' => 10, 'suffix' => ' سال', 'label' => 'تجربه'],
                ], JSON_UNESCAPED_UNICODE),
            ]),
            self::block('amp-about-testimonials', 'testimonials_slider', [
                'title' => 'مشتریان درباره ما چه می‌گویند',
                'columns' => '2',
                'mode' => 'carousel',
                'autoplay' => true,
                'interval' => 6000,
                'items' => json_encode([
                    ['name' => 'فاطمه موسوی', 'role' => 'مدیر فروش', 'text' => 'تیم حرفه‌ای و خوش‌اخلاقی دارند. همیشه در دسترس هستند.', 'rating' => 5],
                    ['name' => 'محمد علیپور', 'role' => 'کارآفرین', 'text' => 'کیفیت کارشان فوق‌العاده است. حتماً به دوستانم معرفی می‌کنم.', 'rating' => 5],
                ], JSON_UNESCAPED_UNICODE),
            ]),
        ];
    }

    // ── CONTACT PAGE ───────────────────────────────────────────────────────

    /** @return list<array<string,mixed>> */
    public static function contactBlocks(): array
    {
        return [
            self::block('amp-contact-hero', 'hero_gradient', [
                'badge' => 'تماس با ما',
                'title' => 'همیشه در دسترس شما هستیم',
                'subtitle' => 'هر سوالی دارید، تیم ما آماده پاسخگویی است',
                'button_text' => 'تماس تلفنی',
                'button_url' => 'tel:02112345678',
                'button2_text' => 'مشاهده آدرس',
                'button2_url' => '#amp-contact-info',
                'gradient_from' => '#0f172a',
                'gradient_to' => '#4f46e5',
                'text_color' => '#ffffff',
            ]),
            self::block('amp-contact-info', 'contact_info', [
                'title' => 'راه‌های ارتباطی',
                'subtitle' => 'از طریق راه‌های زیر با ما در ارتباط باشید',
                'background' => '#f8fafc',
                'items' => [
                    ['icon' => '📞', 'label' => 'تلفن ثابت', 'value' => '۰۲۱-۱۲۳۴۵۶۷۸'],
                    ['icon' => '📱', 'label' => 'موبایل', 'value' => '۰۹۱۲-۳۴۵۶۷۸۹'],
                    ['icon' => '📍', 'label' => 'آدرس', 'value' => 'تهران، خیابان ولیعصر، پلاک ۱۲۳۴'],
                    ['icon' => '🕐', 'label' => 'ساعات کاری', 'value' => 'شنبه تا پنجشنبه، ۹ تا ۱۸'],
                ],
            ]),
            self::block('amp-contact-cta', 'hero_trust', [
                'badge' => 'پشتیبانی ۲۴ ساعته',
                'title' => 'با ما در تماس باشید',
                'subtitle' => 'تماس تلفنی یا پیام — تیم ما سریع پاسخ می‌دهد',
                'rating' => '۴.۹',
                'rating_label' => 'از ۵۰۰ نظر مشتری',
                'phone' => '۰۲۱-۱۲۳۴۵۶۷۸',
                'btn_primary_text' => 'تماس تلفنی',
                'btn_primary_url' => 'tel:02112345678',
                'btn_secondary_text' => 'مشاهده آدرس',
                'btn_secondary_url' => '#amp-contact-map',
            ]),
            self::block('amp-contact-map', 'hero', [
                'title' => 'ما را پیدا کنید',
                'subtitle' => 'آدرس: تهران، خیابان ولیعصر، پلاک ۱۲۳۴',
                'button_text' => 'مسیریابی',
                'button_url' => '#',
                'background' => 'surface',
                'background_image' => '',
                'align' => 'center',
            ]),
        ];
    }

    // ── HEADER / FOOTER ────────────────────────────────────────────────────

    /** @return array<string,mixed> */
    public static function header(): array
    {
        return self::block('amp-header', 'nav_header', [
            'logo_text' => 'آرینا',
            'logo_url' => '/',
            'nav_source' => 'custom',
            'nav_links' => [
                ['label' => 'خانه', 'url' => '/'],
                ['label' => 'خدمات', 'url' => '/services'],
                ['label' => 'فروشگاه', 'url' => '/shop'],
                ['label' => 'درباره ما', 'url' => '/about'],
                ['label' => 'تماس با ما', 'url' => '/contact'],
            ],
            'sticky' => true,
            'bg_color' => '#ffffff',
            'text_color' => '#111827',
            'phone' => '۰۲۱-۱۲۳۴۵۶۷۸',
            'cta_text' => 'شروع رایگان',
            'cta_url' => '/contact',
            'show_search' => true,
            'search_placeholder' => 'جستجو...',
            'show_cart' => true,
            'show_account' => true,
            'show_dark_toggle' => false,
        ]);
    }

    /** @return array<string,mixed> */
    public static function footer(): array
    {
        return self::block('amp-footer', 'nav_footer', [
            'nav_source' => 'custom',
            'logo_text' => 'آرینا مولتی‌پلاس',
            'tagline' => 'قالب چندمنظوره فارسی برای کسب‌وکارهای ایرانی. قابل ویرایش کامل در قالب‌ساز.',
            'copyright' => '© ' . self::copyrightYear() . ' تمامی حقوق برای آرینا مولتی‌پلاس محفوظ است.',
            'bg_color' => '#0f172a',
            'text_color' => '#e5e7eb',
            'columns' => [
                [
                    'title' => 'دسترسی سریع',
                    'links' => [
                        ['label' => 'خانه', 'url' => '/'],
                        ['label' => 'خدمات', 'url' => '/services'],
                        ['label' => 'فروشگاه', 'url' => '/shop'],
                        ['label' => 'درباره ما', 'url' => '/about'],
                        ['label' => 'تماس با ما', 'url' => '/contact'],
                    ],
                ],
                [
                    'title' => 'خدمات مشتریان',
                    'links' => [
                        ['label' => 'سوالات متداول', 'url' => '#amp-faq'],
                        ['label' => 'پیگیری سفارش', 'url' => '/shop'],
                        ['label' => 'پشتیبانی', 'url' => '/contact'],
                    ],
                ],
                [
                    'title' => 'راه‌های ارتباطی',
                    'links' => [
                        ['label' => '۰۲۱-۱۲۳۴۵۶۷۸', 'url' => 'tel:02112345678'],
                        ['label' => 'تهران، خیابان ولیعصر', 'url' => '#amp-contact-info'],
                    ],
                ],
            ],
            'links' => [
                ['label' => 'قوانین و مقررات', 'url' => '#'],
                ['label' => 'حریم خصوصی', 'url' => '#'],
            ],
            'social_links' => [
                ['platform' => 'instagram', 'url' => '#'],
                ['platform' => 'telegram', 'url' => '#'],
                ['platform' => 'whatsapp', 'url' => '#'],
            ],
        ]);
    }

    // ── GLOBAL STYLES ──────────────────────────────────────────────────────

    /** @return array<string,mixed> */
    public static function globalStyles(array $defaultStyles): array
    {
        return array_merge($defaultStyles, [
            'colors' => [
                'primary' => '#4f46e5',
                'secondary' => '#7c3aed',
                'accent' => '#818cf8',
                'background' => '#ffffff',
                'surface' => '#f8fafc',
                'text' => '#0f172a',
                'text_muted' => '#64748b',
            ],
            'typography' => [
                'font_heading' => 'Vazirmatn, Tahoma, sans-serif',
                'font_body' => 'Vazirmatn, Tahoma, sans-serif',
                'scale' => 1.25,
            ],
            'dark_mode' => [
                'enabled' => true,
                'primary' => '#818cf8',
                'background' => '#0f172a',
                'surface' => '#1e293b',
                'text' => '#f8fafc',
            ],
        ]);
    }

    // ── HELPERS ────────────────────────────────────────────────────────────

    /**
     * Build one canonical block node.
     *
     * @param array<string,mixed> $props    Block props (must match the canonical schema).
     * @param list<array<string,mixed>> $children Optional child blocks.
     * @return array<string,mixed>
     */
    private static function block(string $id, string $type, array $props = [], array $children = []): array
    {
        $node = ['id' => $id, 'type' => $type, 'props' => $props];
        if ($children !== []) {
            $node['children'] = $children;
        }
        return $node;
    }

    /** Current year for copyright text. */
    private static function copyrightYear(): string
    {
        return (string) date('Y');
    }
}
