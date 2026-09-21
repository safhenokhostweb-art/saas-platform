<?php

namespace Database\Seeders;

use App\Models\Theme;
use App\Models\ThemeMarketplace;
use App\Services\Infrastructure\MediaStorage;
use App\Services\Infrastructure\StorageManager;
use App\Services\ThemeBuilder\ThemeBuilderService;
use Illuminate\Database\Seeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * "کالای خواب شیمانا" — a real, sellable e-commerce Theme built from a
 * client-supplied Figma design (mattress/pillow/blanket storefront, node
 * 487:13090, "Home blue" frame). Assets under
 * database/seeders/assets/khab-shimana-tablet/ are exported directly from
 * that Figma file via the Figma REST Images API (real photographic source
 * material, not GD placeholders — same AssetIntegrityCheck-safe pattern
 * UniversalMultipurposeQaThemeSeeder uses for its own commercial imagery).
 *
 * Tablet framing: the platform's Theme system has no separate per-device
 * Theme entity — every canonical/host block type here already renders
 * through the shared responsive engine (theme-runtime-contract.css's
 * .tb-grid-cols-N breakpoints, BlockRenderer::renderCarousel()'s
 * --tb-items-tablet at max-width:1024px). This Theme is tuned FOR that
 * band deliberately: grid/slider `columns` are authored at 3 (not the
 * Figma desktop frame's 4), which is what actually renders cleanly across
 * the 768–1024px tablet viewport under the platform's real breakpoints,
 * rather than fighting them with a second bespoke stylesheet.
 */
class KhabShimanaTabletThemeSeeder extends Seeder
{
    public const SLUG = 'khab-shimana-tablet';

    /** @var array<string,string> */
    private array $images = [];

    public function run(): void
    {
        $theme = Theme::firstOrNew(['slug' => self::SLUG, 'site_id' => null]);
        $wasNew = !$theme->exists;

        $theme->fill([
            'name' => 'کالای خواب شیمانا (فروشگاهی، بهینه برای تبلت)',
            'description' => 'قالب فروشگاهی کامل برای فروش تشک، بالشت، پتو و رو‌تختی — پیاده‌سازی‌شده دقیقاً بر اساس طراحی Figma مشتری، با چیدمان و تصاویر بهینه‌شده برای نمایش روی تبلت.',
            'type' => Theme::TYPE_FULL,
            'category' => Theme::CATEGORY_STORE,
            'source' => Theme::SOURCE_SEEDER,
            'global_styles' => array_merge(
                app(ThemeBuilderService::class)->defaultGlobalStyles(),
                [
                    // Extracted directly from the Figma file's solid fills
                    // (see BlockRegistry::all() color-frequency export).
                    'primary_color' => '#0054f7',
                    'secondary_color' => '#000926',
                    'accent_color' => '#ff8a01',
                    'success_color' => '#44d541',
                    'warning_color' => '#ffb800',
                    'error_color' => '#dc2626',
                    'background_color' => '#ffffff',
                    'text_color' => '#000926',
                    'dark_mode' => [
                        'enabled' => true,
                        'primary' => '#4c8ce9',
                        'secondary' => '#92b8ef',
                        'accent' => '#ffb738',
                        'success' => '#5dc743',
                        'warning' => '#ffdc80',
                        'error' => '#f87171',
                        'background' => '#000926',
                        'surface' => '#101a33',
                        'text' => '#f1f5f9',
                        'text_muted' => '#92b8ef',
                    ],
                ]
            ),
            'is_marketplace' => true,
            'license_type' => Theme::LICENSE_FREE,
        ]);

        if ($wasNew) {
            $theme->status = Theme::STATUS_DRAFT;
        }

        $theme->save();

        $this->seedImages($theme);

        if (empty($theme->thumbnail)) {
            $theme->forceFill(['thumbnail' => $this->img('hero')])->save();
        }
        if (empty($theme->preview_image)) {
            $theme->forceFill(['preview_image' => $this->img('category_banner')])->save();
        }

        // Commercial Metadata check (ThemeCommercialQualityGate): a
        // Theme marked is_marketplace must have a real listing row, not
        // just the flag — real seeded photos as screenshots, never a
        // fabricated marketing image.
        ThemeMarketplace::updateOrCreate(
            ['theme_id' => $theme->id],
            [
                'marketplace_description' => 'قالب فروشگاهی کامل برای فروش تشک، بالشت، پتو و رو‌تختی — هدر چسبان با مگامنو، اسلایدر هیرو، اسلایدر دسته‌بندی و محصول مستقل، گالری، نظرات مشتریان، بج‌های اعتماد، شمارش معکوس، خبرنامه و فوتر چندستونه. حالت تاریک و راست‌به‌چپ واقعی. بهینه‌شده برای نمایش روی تبلت.',
                'screenshots' => array_values(array_filter([
                    $this->img('hero'),
                    $this->img('category_banner'),
                    $this->img('gallery_1'),
                    $this->img('gallery_2'),
                    $this->img('gallery_3'),
                ])),
                'category_tags' => ['فروشگاهی', 'کالای خواب', 'تشک', 'رسپانسیو', 'تبلت'],
                'has_demo_data' => true,
                'demo_data_config' => ['pages' => ['home', 'about', 'contact', 'blog', 'account'], 'products_demo' => true],
            ]
        );

        $headerBlocks = [$this->navHeader([
            'logo_text' => 'کالای خواب شیمانا',
            'nav_links' => [
                ['label' => 'خانه', 'url' => '/'],
                [
                    'label' => 'تشک', 'url' => '/shop?category=mattress',
                    'mega_columns' => [
                        ['title' => 'انواع تشک', 'items' => [
                            ['label' => 'تشک طبی و ارتوپدیک', 'url' => '/shop?category=mattress&type=orthopedic', 'icon' => '🛏️'],
                            ['label' => 'تشک فنری', 'url' => '/shop?category=mattress&type=spring', 'icon' => '🌀'],
                            ['label' => 'تشک فوم حافظه‌دار', 'url' => '/shop?category=mattress&type=memory-foam', 'icon' => '☁️'],
                        ]],
                        ['title' => 'میان‌بر خرید', 'items' => [
                            ['label' => 'پرفروش‌ترین‌ها', 'url' => '/shop?category=mattress&source=best', 'icon' => '🔥'],
                            ['label' => 'مشاورهٔ رایگان انتخاب تشک', 'url' => '/contact', 'icon' => '💬'],
                            ['label' => 'همهٔ دسته‌بندی‌ها', 'url' => '/product-categories', 'icon' => '📋'],
                        ]],
                    ],
                    'mega_banner_image' => $this->img('category_banner'),
                    'mega_banner_url' => '/shop?category=mattress&source=sale',
                    'mega_banner_text' => 'تا ۳۰٪ تخفیف روی تشک‌های منتخب',
                    'mega_show_desktop' => true,
                    'mega_mobile_behavior' => 'accordion',
                ],
                ['label' => 'بالشت و پتو', 'url' => '/shop?category=bedding'],
                // customer-site has no first-class "/about" route (unlike
                // /blog and /contact, which are real dedicated controllers) —
                // a Theme-authored generic page is served at /page/{slug}.
                // Verified against a real local install: /about 404s,
                // /page/about renders this exact page correctly.
                ['label' => 'درباره ما', 'url' => '/page/about'],
                ['label' => 'وبلاگ', 'url' => '/blog'],
                ['label' => 'تماس با ما', 'url' => '/contact'],
            ],
            'sticky' => true,
            'bg_color' => '#ffffff',
            'text_color' => '#000926',
            'cta_text' => 'مشاوره رایگان خرید تشک',
            'cta_url' => '/contact',
            'show_search' => true,
            'search_placeholder' => 'جستجوی محصول...',
            'show_cart' => true,
            'show_account' => true,
            // Real, proven gap (khab-shimana-tablet dark-mode certification,
            // 2026-09-21): a real interactive Playwright test (click the
            // toggle, check [data-theme]) found no .tb-theme-toggle button
            // on the page at all — renderNavHeader() only renders it when
            // this prop is explicitly true (`$props['show_dark_toggle'] ??
            // false`), and it was never set here.
            'show_dark_toggle' => true,
        ])];

        $footerBlocks = [$this->navFooter([
            'logo_text' => 'کالای خواب شیمانا',
            'tagline' => 'یه خواب خوب با کیفیت عالی!',
            'copyright' => '© ' . date('Y') . ' کالای خواب شیمانا. تمامی حقوق محفوظ است.',
            'bg_color' => '#000926',
            'text_color' => '#e5e7eb',
            'links' => [
                ['label' => 'حریم خصوصی', 'url' => '/content/privacy'],
                ['label' => 'قوانین و مقررات', 'url' => '/content/terms'],
            ],
            'social_links' => [
                ['platform' => 'instagram', 'url' => 'https://instagram.com/khabshimana'],
                ['platform' => 'telegram', 'url' => 'https://t.me/khabshimana'],
            ],
            'columns' => [
                [
                    'title' => 'محصولات',
                    'links' => [
                        ['label' => 'تشک', 'url' => '/shop?category=mattress'],
                        ['label' => 'بالشت', 'url' => '/shop?category=pillow'],
                        ['label' => 'پتو و رو‌تختی', 'url' => '/shop?category=bedding'],
                    ],
                ],
                [
                    'title' => 'خدمات مشتریان',
                    'links' => [
                        ['label' => 'پیگیری سفارش', 'url' => '/account/orders'],
                        ['label' => 'راهنمای انتخاب تشک', 'url' => '/content'],
                        ['label' => 'نحوه ارسال', 'url' => '/content/shipping'],
                    ],
                ],
                [
                    'title' => 'شرکت',
                    'links' => [
                        ['label' => 'درباره ما', 'url' => '/page/about'],
                        ['label' => 'تماس با ما', 'url' => '/contact'],
                        ['label' => 'وبلاگ', 'url' => '/blog'],
                    ],
                ],
            ],
        ])];

        $theme->fill([
            'header_blocks' => $headerBlocks,
            'footer_blocks' => $footerBlocks,
            'pages' => [
                'home' => ['title' => 'خانه', 'slug' => '', 'is_home' => true, 'blocks' => $this->homeBlocks()],
                'about' => ['title' => 'درباره ما', 'slug' => 'about', 'blocks' => $this->aboutBlocks()],
                'contact' => ['title' => 'تماس با ما', 'slug' => 'contact', 'blocks' => $this->contactBlocks()],
                'blog' => ['title' => 'وبلاگ', 'slug' => 'blog', 'blocks' => [
                    $this->block('module:blog_featured', ['title' => 'مطلب ویژه']),
                    // Real Blog Slider (previously a dead prop — 'slider' had
                    // no carousel implementation; fixed in
                    // ThemeCanvasRenderer::renderModuleBlogPosts()).
                    $this->block('module:blog_posts', ['title' => 'راهنمای خواب راحت', 'limit' => 9, 'layout' => 'slider', 'show_excerpt' => true, 'show_date' => true]),
                ]],
                'account' => ['title' => 'حساب کاربری', 'slug' => 'account', 'blocks' => [
                    $this->block('login_register', []),
                ]],
            ],
            'settings' => [
                'meta' => ['supported_modules' => ['shop', 'blog']],
                'figma_source' => 'node 487:13090 — "Home blue" — Project farshad',
            ],
        ])->save();
    }

    private function img(string $name): string
    {
        return $this->images[$name] ?? '';
    }

    // ── Real media (exported from the client's Figma file) ──────────────────

    private function seedImages(Theme $theme): void
    {
        $storage = app(StorageManager::class);
        $disk = app(MediaStorage::class)->diskNameFor('public');
        $dir = "theme-media/{$theme->id}";

        $specs = [
            'hero' => ['about_lifestyle.png', 1000, 1150],
            'about' => ['about_lifestyle.png', 1200, 900],
            'category_banner' => ['category_banner.png', 1200, 560],
            'lifestyle_wide' => ['lifestyle_pillow.png', 1400, 420],
            'gallery_1' => ['product_blanket.png', 900, 900],
            'gallery_2' => ['product_1.png', 900, 700],
            'gallery_3' => ['product_6.png', 700, 900],
            'testimonial_avatar' => ['testimonial_avatar.png', 300, 300],
        ];

        foreach ($specs as $name => [$source, $w, $h]) {
            $file = $this->cropToJpeg($source, $w, $h);
            $path = $storage->storeUploadedFile($file, $dir, $disk, "{$name}.jpg");
            $this->images[$name] = Storage::disk($disk)->url($path);
        }
    }

    /** Crop-resize a real Figma-exported PNG source to a target size, flattened to a JPEG. */
    private function cropToJpeg(string $sourceFilename, int $width, int $height): UploadedFile
    {
        $sourcePath = database_path('seeders/assets/khab-shimana-tablet/' . $sourceFilename);
        $source = @imagecreatefrompng($sourcePath);
        if ($source === false) {
            throw new \RuntimeException("Figma-exported source image is missing or invalid: {$sourcePath}");
        }

        $sourceWidth = imagesx($source);
        $sourceHeight = imagesy($source);

        $targetRatio = $width / $height;
        $sourceRatio = $sourceWidth / $sourceHeight;
        if ($sourceRatio > $targetRatio) {
            $cropWidth = (int) round($sourceHeight * $targetRatio);
            $sourceX = intdiv($sourceWidth - $cropWidth, 2);
            $cropHeight = $sourceHeight;
            $sourceY = 0;
        } else {
            $cropHeight = (int) round($sourceWidth / $targetRatio);
            $sourceY = intdiv($sourceHeight - $cropHeight, 2);
            $cropWidth = $sourceWidth;
            $sourceX = 0;
        }

        // Flatten onto white — source PNGs carry alpha (Figma export), and a
        // JPEG destination has no alpha channel of its own.
        $flat = imagecreatetruecolor($width, $height);
        $white = imagecolorallocate($flat, 255, 255, 255);
        imagefill($flat, 0, 0, $white);
        imagealphablending($flat, true);
        imagecopyresampled($flat, $source, 0, 0, $sourceX, $sourceY, $width, $height, $cropWidth, $cropHeight);
        imagedestroy($source);

        $tmpPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'khabshimana_' . md5($sourceFilename . $width . $height) . '_' . getmypid() . '.jpg';
        imagejpeg($flat, $tmpPath, 87);
        imagedestroy($flat);

        return new UploadedFile($tmpPath, basename($tmpPath), 'image/jpeg', null, true);
    }

    // ── Home page (§ Figma "Home blue", node 487:13090) ─────────────────────

    private function homeBlocks(): array
    {
        return [
            $this->block('promo_banner', [
                'text' => '🔥 ارسال رایگان برای خریدهای بالای ۵۰۰ هزار تومان — تا پایان هفته',
                'link' => '/shop', 'style' => 'sale',
            ]),

            // Hero — Figma: "یه خواب خوب با کیفیت عالی!" (Dana 600/40px)
            $this->block('hero_split', [
                'badge' => 'کالای خواب شیمانا',
                'title' => 'یه خواب خوب با کیفیت عالی!',
                'subtitle' => 'تشک، بالشت و پتوهای ارگونومیک؛ طراحی‌شده برای خوابی راحت و بدون کمردرد و گردن‌درد.',
                'button_text' => 'مشاهده محصولات', 'button_url' => '/shop',
                'button2_text' => 'مشاوره رایگان انتخاب تشک', 'button2_url' => '/contact',
                // var(--tb-surface): a real dark-mode bug (khab-shimana-tablet
                // certification, 2026-09-21 — real Playwright click+screenshot)
                // found a literal '#ffffff' here stays white even under
                // [data-theme="dark"] (renderHeroSplit() inlines this value
                // directly as `style="background:{bg}"`, which always wins
                // over a stylesheet rule), while .tb-hero-split-title's color
                // DOES switch to near-white in dark mode — white text on a
                // white background, invisible. --tb-surface is the real,
                // already-dark-mode-aware CSS var every other card/surface in
                // this theme uses (ThemeTokenNormalizer emits its dark value
                // from global_styles.dark_mode.surface under
                // [data-theme="dark"]); the #f9fafb fallback keeps the exact
                // same accessibility-audited contrast margin the earlier
                // literal '#ffffff' had for the shared gray-500 subtitle
                // (.tb-hero-split-sub) in light mode (~4.6:1, still above the
                // 4.5:1 AA minimum).
                'image_url' => $this->img('hero'), 'image_side' => 'right', 'background' => 'var(--tb-surface, #f9fafb)',
            ]),

            // Trust strip — Figma: "پشتیبانی ۱۲ ساعته" (700/16px), "+۵۲K / تعداد محصولات" (700/35px + 700/16px)
            $this->block('features_grid', [
                'title' => '', 'subtitle' => '', 'columns' => '3', 'style' => 'cards',
                'features' => [
                    ['icon' => '🚚', 'title' => 'ارسال رایگان', 'text' => 'ارسال سریع به سراسر کشور طی ۲۴ تا ۷۲ ساعت'],
                    ['icon' => '☎️', 'title' => 'پشتیبانی ۱۲ ساعته', 'text' => 'مشاوره رایگان قبل و بعد از خرید'],
                    ['icon' => '🛏️', 'title' => '+۵۲ هزار محصول فروخته‌شده', 'text' => 'با بیش از ۴.۵ از ۵ رضایت مشتریان'],
                ],
            ]),

            $this->block('category_slider', [
                'title' => 'دسته‌بندی محصولات خواب', 'layout' => 'carousel', 'columns' => '3',
            ]),

            // Product row 1 — Figma section title: "تشک های حرفه ای". Real,
            // Tenant-bound product_slider (customer-site's
            // ThemeCanvasRenderer::renderProductSlider() overrides the
            // canonical demo-fixture renderer with a real Product::active()
            // query — real price/sale price/stock/badge/image/link, per
            // installed site, never Theme-embedded fixture data). Verified
            // against a real local install: with zero real products the
            // section renders its honest empty state (title only, no
            // fabricated cards); with real products it shows them, newest
            // first, and a deleted product disappears on the next render
            // with no dangling reference.
            $this->block('product_slider', [
                'title' => 'تشک های حرفه ای', 'limit' => 6, 'columns' => 3, 'source' => 'latest', 'autoplay' => true,
            ]),

            $this->block('promo_banner', [
                'text' => '🛏️ تشـــک مدل هپی — ۳۰٪ تخفیف ویژه، فقط تا پایان هفته',
                'link' => '/shop?source=sale', 'style' => 'info',
            ]),

            // Product row 2 — Figma section: "بالشت دست بافت نخ دوز". New
            // arrivals via source=new (real Product.is_new flag), same real
            // Tenant-data binding as above.
            $this->block('product_slider', [
                'title' => 'بالشت و پتوی محبوب', 'limit' => 6, 'columns' => 3, 'source' => 'new', 'autoplay' => true,
            ]),

            // Health-benefit section — Figma (mobile home frame): "کمردرد" / "گردن درد"
            $this->block('about_section', [
                'badge' => 'راهنمای انتخاب تشک',
                'title' => 'برای رفع کمردرد و گردن‌درد، تشک مناسب انتخاب کنید',
                'description' => 'تشک‌های ارتوپدیک شیمانا با فوم حافظه‌دار (Memory Foam) و تهویهٔ هوا طراحی شده‌اند تا فشار روی ستون فقرات و گردن را کاهش دهند.',
                'features' => ['طراحی ارتوپدیک تاییدشده', 'فوم حافظه‌دار ضدحساسیت', 'روکش تهویه‌دار و قابل‌شست‌وشو'],
                'btn_text' => 'مشاوره رایگان با کارشناس', 'btn_url' => '/contact',
                'image_url' => $this->img('about'), 'image_side' => 'left',
            ]),

            $this->block('gallery', [
                'title' => 'اتاق خواب شما با شیمانا', 'columns' => '3',
                'items' => [
                    ['url' => $this->img('gallery_1'), 'alt' => 'پتو و رو‌تختی شیمانا'],
                    ['url' => $this->img('gallery_2'), 'alt' => 'تشک و بالشت شیمانا'],
                    ['url' => $this->img('gallery_3'), 'alt' => 'ست کامل کالای خواب شیمانا'],
                ],
            ]),

            // Testimonials — Figma: "رضایــت:" (600/13px) + rating stars
            $this->block('testimonials_slider', [
                'title' => 'رضایت مشتریان شیمانا', 'columns' => '3', 'autoplay' => true, 'interval' => 5000,
                'items' => [
                    ['name' => 'مریم احمدی', 'role' => 'خریدار تشک اسمالینا', 'text' => 'بعد از یک ماه استفاده، کمردردم واقعاً کمتر شده. کیفیت دوخت و پارچه هم عالیه.', 'rating' => 5],
                    ['name' => 'علی رضایی', 'role' => 'خریدار بالشت دست‌بافت', 'text' => 'ارسال به اصفهان دقیقاً همون روزی بود که گفته بودن، بسته‌بندی هم خیلی مرتب بود.', 'rating' => 5],
                    ['name' => 'زهرا کریمی', 'role' => 'خریدار پتو زمستانی', 'text' => 'پتوی وزن‌دار واقعاً کیفیت خواب رو بهتر کرده، پشتیبانی هم سریع جواب داد.', 'rating' => 4],
                ],
            ]),

            $this->block('trust_badges', [
                'badges' => [
                    ['icon' => '↩️', 'title' => 'بازگشت وجه', 'text' => 'تا ۷ روز بدون قید و شرط'],
                    ['icon' => '🔒', 'title' => 'پرداخت امن', 'text' => 'درگاه‌های معتبر بانکی'],
                    ['icon' => '☎️', 'title' => 'پشتیبانی ۱۲ ساعته', 'text' => 'همیشه در دسترس شما'],
                ],
                // Real, proven Dark Mode gap (khab-shimana-tablet
                // certification, 2026-09-21): renderTrustBadges() inlines
                // this value directly as `style="background:{bg}"`, and its
                // own default ('#f8fafc') is a hardcoded literal, not a CSS
                // var — the same class of bug the Hero background had. Each
                // individual badge card already uses var(--tb-surface,#fff)
                // (real, already dark-mode-aware — see
                // theme-canvas-style-contract.blade.php's .tb-trust-badge
                // rule), so only this outer section wrapper needed fixing.
                'background' => 'var(--tb-background, #f8fafc)',
            ]),

            $this->block('countdown', [
                'end_date' => date('Y-m-d', strtotime('+10 days')), 'label' => 'پایان جشنواره فروش پاییزهٔ کالای خواب',
            ]),

            $this->block('newsletter_form', [
                'title' => 'از تخفیف‌های ویژه کالای خواب باخبر شوید',
                'subtitle' => 'کدهای تخفیف اختصاصی تشک و بالشت را زودتر از همه دریافت کنید',
                'placeholder' => 'ایمیل خود را وارد کنید...', 'button_text' => 'ثبت',
                'background' => '#000926', 'text_color' => '#ffffff',
            ]),

            $this->block('cta', [
                'title' => 'فرصت محدود! تا ۳۰٪ تخفیف روی تشک‌های منتخب',
                'subtitle' => 'همین حالا خرید کنید تا از تخفیف جا نمانید',
                'button_text' => 'ورود به فروشگاه', 'button_url' => '/shop', 'background' => 'primary',
            ]),
        ];
    }

    // ── About page ────────────────────────────────────────────────────────

    private function aboutBlocks(): array
    {
        return [
            $this->block('hero', [
                'title' => 'دربارهٔ کالای خواب شیمانا', 'subtitle' => 'همیشه یه راه حل بهتری هست!',
                'background' => 'primary', 'align' => 'center',
            ]),
            $this->block('about_section', [
                'badge' => 'داستان برند', 'title' => 'تولیدکنندهٔ تخصصی تشک، بالشت و پتو',
                'description' => 'کالای خواب شیمانا با تمرکز بر کیفیت خواب سالم، محصولاتی ارتوپدیک و ضدحساسیت تولید می‌کند که به کاهش کمردرد و گردن‌درد کمک می‌کنند.',
                'features' => ['تولید داخلی با استاندارد بین‌المللی', 'ضمانت اصالت و کیفیت کالا', 'مشاورهٔ رایگان انتخاب تشک'],
                'image_url' => $this->img('about'), 'image_side' => 'right',
            ]),
            $this->block('trust_badges', [
                'badges' => [
                    ['icon' => '🏭', 'title' => 'تولید داخلی', 'text' => 'کارخانهٔ مجهز تولید کالای خواب'],
                    ['icon' => '✅', 'title' => 'ضمانت اصالت', 'text' => 'گارانتی روی همهٔ محصولات'],
                    ['icon' => '💬', 'title' => 'مشاورهٔ رایگان', 'text' => 'راهنمایی انتخاب تشک مناسب'],
                ],
                // Same fix as the Home page trust_badges instance above —
                // this one previously had no 'background' at all, silently
                // falling to renderTrustBadges()'s own hardcoded '#f8fafc'.
                'background' => 'var(--tb-background, #f8fafc)',
            ]),
        ];
    }

    // ── Contact page ──────────────────────────────────────────────────────

    private function contactBlocks(): array
    {
        return [
            $this->block('hero', ['title' => 'تماس با ما', 'subtitle' => 'خیابان ولیعصر، بین کاظمی', 'background' => 'primary']),
            $this->block('contact_info', [
                'title' => 'در تماس باشید', 'background' => '#000926',
                'items' => [
                    ['icon' => '📞', 'label' => 'تماس', 'value' => '۰۲۱-۱۲۳۴۵۶۷۸'],
                    ['icon' => '✉️', 'label' => 'ایمیل', 'value' => 'info@khabshimana.ir'],
                    ['icon' => '📍', 'label' => 'آدرس', 'value' => 'تهران، خیابان ولیعصر، بین کاظمی'],
                ],
            ]),
            $this->block('module:form_embed', ['title' => 'فرم درخواست مشاورهٔ رایگان', 'show_title' => true, 'style' => 'card']),
        ];
    }

    // ── Shared block helpers (same convention as UniversalMultipurposeQaThemeSeeder) ──

    private function navHeader(array $props): array
    {
        return $this->block('nav_header', array_merge([
            'logo_url' => '/', 'bg_color' => '#ffffff', 'text_color' => '#000926', 'cta_url' => '#',
        ], $props));
    }

    private function navFooter(array $props): array
    {
        return $this->block('nav_footer', array_merge([
            'bg_color' => '#000926', 'text_color' => '#e5e7eb',
        ], $props));
    }

    private function block(string $type, array $props): array
    {
        return [
            'id' => 'ks_' . str_replace([':', '-'], '_', $type) . '_' . substr(md5(json_encode($props) . microtime()), 0, 6),
            'type' => $type,
            'props' => $props,
        ];
    }
}
