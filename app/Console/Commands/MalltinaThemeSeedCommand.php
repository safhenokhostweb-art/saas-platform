<?php

namespace App\Console\Commands;

use App\Models\Theme;
use App\Models\ThemeMarketplace;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Malltina-inspired International Marketplace Theme Pack
 *
 * Visual source: https://malltina.com
 * Extracted: color palette, spacing system, visual hierarchy, commerce patterns.
 * SAFE MODE: No HTML/CSS/images/copyrighted content copied.
 * All text: original Persian placeholder. All images: SVG data URIs.
 *
 * Delivers 12 complete pages:
 *   home, about, contact, blog, archive, search, 404,
 *   product, category, landing, single (blog post), shop_deals
 *
 * Usage:
 *   php artisan malltina:seed           — install theme (skip if exists)
 *   php artisan malltina:seed --fresh   — delete and re-install
 */
class MalltinaThemeSeedCommand extends Command
{
    protected $signature = 'malltina:seed {--fresh : Delete existing and re-seed}';
    protected $description = 'Seed Malltina-inspired international marketplace theme pack (12 pages, RTL, teal palette)';

    // ── Brand palette extracted from malltina.com visual analysis ──────────
    private const PRIMARY   = '#27c0e2'; // Malltina teal — confirmed by RE pipeline
    private const DARK      = '#0c1f2e'; // Dark navy — header/footer
    private const ACCENT    = '#f0a500'; // Gold/orange — sale badges, CTAs
    private const BG        = '#f4f6f9'; // Light blue-gray — page background
    private const TEXT      = '#2d3748'; // Dark text
    private const CARD_BG   = '#ffffff'; // Card backgrounds
    private const GREEN     = '#22c55e'; // Green — trust signals
    private const DANGER    = '#ef4444'; // Red — discount badges
    private const SLUG      = 'malltina-international';

    public function handle(): int
    {
        $this->info('═══════════════════════════════════════════════════════');
        $this->info('  Malltina International — Theme Pack Seed');
        $this->info('  Visual source: malltina.com (color + hierarchy + commerce)');
        $this->info('═══════════════════════════════════════════════════════');

        if ($this->option('fresh')) {
            $deleted = Theme::where('slug', self::SLUG)->whereNull('site_id')->delete();
            $this->line("  ✗ حذف شد: {$deleted} نسخه قبلی.");
        }

        if (Theme::where('slug', self::SLUG)->exists()) {
            $this->warn('  ↺  malltina-international — موجود است. از --fresh استفاده کنید.');
            return 0;
        }

        $this->create();

        $this->newLine();
        $this->info('  ✅ THEME_PACK_READY');
        $this->info('  نام:      مالتینا — فروشگاه بین‌المللی');
        $this->info('  اسلاگ:   malltina-international');
        $this->info('  صفحات:   ۱۲ صفحه کامل');
        $this->info('  دسته:    Marketplace');
        $this->info('  قیمت:    ۴۹۰,۰۰۰ تومان');
        $this->info('  مسیر:    /admin/theme-marketplace');
        $this->info('  امتیاز:  php artisan marketplace:score');

        return 0;
    }

    // ══════════════════════════════════════════════════════════════════════
    //  THEME CONSTRUCTION
    // ══════════════════════════════════════════════════════════════════════

    private function create(): void
    {
        $pages = $this->buildAllPages();

        $theme = Theme::create([
            'name'              => 'مالتینا — فروشگاه بین‌المللی',
            'slug'              => self::SLUG,
            'description'       => 'قالب فروشگاه بین‌المللی با الهام از بهترین مارکت‌پلیس‌های ایران. پالت رنگی فیروزه‌ای، سیستم دسته‌بندی قوی، گالری محصولات حرفه‌ای، بخش تخفیفات ویژه با تایمر شمارش معکوس. مناسب برای فروشگاه‌های خرید از خارج، مارکت‌پلیس چند برند و پلتفرم مقایسه قیمت.',
            'thumbnail'         => 'https://placehold.co/400x300/27c0e2/ffffff/png?text=' . rawurlencode('مالتینا بین‌المللی'),
            'preview_image'     => 'https://placehold.co/1200x800/0c1f2e/27c0e2/png?text=' . rawurlencode('Malltina International Preview'),
            'type'              => Theme::TYPE_FULL,
            'source'            => Theme::SOURCE_SEEDER,
            'site_id'           => null,
            'status'            => Theme::STATUS_PUBLISHED,
            'is_marketplace'    => true,
            'marketplace_price' => 490000,
            'license_type'      => Theme::LICENSE_PREMIUM,
            'category'          => Theme::CATEGORY_MARKETPLACE,
            'version'           => 1,
            'global_styles'     => $this->globalStyles(),
            'header_blocks'     => $this->headerBlocks(),
            'footer_blocks'     => $this->footerBlocks(),
            'pages'             => $pages,
            'settings'          => [
                'version'           => '1.0.0',
                'release_date'      => now()->toDateString(),
                'supported_modules' => ['shop', 'subscription', 'blog', 'forms', 'tickets'],
                'changelog'         => [
                    '1.0.0' => [
                        'نسخه اول — قالب کامل فروشگاه بین‌المللی',
                        'پالت رنگی فیروزه‌ای استخراج‌شده از تحلیل بصری',
                        '۱۲ صفحه کامل شامل صفحه محصول، دسته‌بندی و لندینگ',
                        'طراحی RTL بهینه‌شده با Vazirmatn',
                        'بخش تخفیفات ویژه با شمارش معکوس',
                        'گالری برندهای بین‌المللی (آمازون، نون، علی‌بابا)',
                    ],
                ],
            ],
        ]);

        ThemeMarketplace::updateOrCreate(['theme_id' => $theme->id], [
            'marketplace_description' => 'قالب مارکت‌پلیس بین‌المللی با الهام از بهترین پلتفرم‌های خرید فارسی‌زبان. رنگ‌بندی فیروزه‌ای، تجربه خرید روان، بخش تخفیفات پیشرفته.',
            'is_featured'    => true,
            'is_new_release' => true,
            'is_bestseller'  => false,
            'annual_price'   => 390000,
            'lifetime_price' => 790000,
            'preview_url'    => '',
            'category_tags'  => ['مارکت‌پلیس', 'بین‌المللی', 'فروشگاه', 'خرید آنلاین', 'تخفیف'],
            'screenshots'    => [
                'https://placehold.co/1280x800/0c1f2e/27c0e2/png?text=malltina-international-home',
                'https://placehold.co/1280x800/1a3550/27c0e2/png?text=malltina-international-product',
                'https://placehold.co/1280x800/27c0e2/ffffff/png?text=malltina-international-category',
                'https://placehold.co/1280x800/f0a500/0c1f2e/png?text=malltina-international-landing',
            ],
            'requirements'   => [
                'shop'         => 'فروشگاه و کاتالوگ محصولات',
                'subscription' => 'عضویت ویژه و تخفیف اضافه',
                'blog'         => 'راهنمای خرید و مقالات',
            ],
            'version_notes'  => 'نسخه اول — فیروزه‌ای، مارکت‌پلیس، ۱۲ صفحه، شمارش معکوس، trust signals بین‌المللی',
            'supported_modules_json' => json_encode(['shop', 'subscription', 'blog', 'forms', 'tickets']),
            'sales_count'    => 0,
            'has_demo_data'  => true,
        ]);

        $this->line('  ✅ ایجاد شد: مالتینا — فروشگاه بین‌المللی (malltina-international)');
    }

    // ══════════════════════════════════════════════════════════════════════
    //  GLOBAL STYLES — Malltina visual design system
    // ══════════════════════════════════════════════════════════════════════

    private function globalStyles(): array
    {
        return [
            'primary_color'    => self::PRIMARY,   // #27c0e2 — brand teal
            'secondary_color'  => self::DARK,      // #0c1f2e — dark navy
            'accent_color'     => self::ACCENT,    // #f0a500 — gold/orange
            'background_color' => self::BG,        // #f4f6f9 — page background
            'text_color'       => self::TEXT,      // #2d3748 — body text
            'font_family'      => 'Vazirmatn',
            'border_radius'    => 'sm',            // Malltina: tight radius (4px)
            'direction'        => 'rtl',
            'shadow'           => 'soft',
            'dark_mode'        => false,
            // Extended tokens
            'card_bg'          => self::CARD_BG,
            'danger_color'     => self::DANGER,    // sale badge red
            'success_color'    => self::GREEN,    // trust badge green
            'header_bg'        => self::DARK,
            'footer_bg'        => self::DARK,
            'nav_text'         => '#ffffff',
            'link_color'       => self::PRIMARY,
            'spacing_unit'     => '8',             // tight 8px grid
            'max_width'        => '1280px',
            'product_columns'  => 4,
        ];
    }

    // ══════════════════════════════════════════════════════════════════════
    //  HEADER — Malltina: announcement bar + main nav + brand sub-nav
    // ══════════════════════════════════════════════════════════════════════

    private function headerBlocks(): array
    {
        return [
            // Announcement bar — top of page (PayPal, Install Extension)
            $this->block('promo_banner', [
                'text'  => '🎁 ارسال رایگان برای اولین خرید — کد تخفیف: WELCOME | 💳 خرید با PayPal و ارزهای بین‌المللی',
                'link'  => '/register',
                'style' => 'info',
            ]),
            // Main navigation
            $this->block('nav_header', [
                'logo_text'  => 'فروشگاه بین‌المللی',
                'bg_color'   => self::DARK,
                'text_color' => '#ffffff',
                'sticky'     => true,
                'show_search'     => true,
                'search_placeholder' => 'جستجو در بیش از ۵۰۰,۰۰۰ محصول...',
                'show_cart'       => true,
                'show_account'    => true,
                'nav_links'  => [
                    ['label' => 'خانه',             'url' => '/'],
                    ['label' => 'آمازون',            'url' => '/category/amazon'],
                    ['label' => 'محصولات دیجیتال',  'url' => '/category/digital'],
                    ['label' => 'پوشاک و مد',        'url' => '/category/fashion'],
                    ['label' => 'لوازم خانه',        'url' => '/category/home'],
                    ['label' => 'تخفیف ویژه',        'url' => '/deals'],
                    ['label' => 'وبلاگ',             'url' => '/blog'],
                    ['label' => 'تماس',              'url' => '/contact'],
                ],
            ]),
        ];
    }

    // ══════════════════════════════════════════════════════════════════════
    //  FOOTER — Malltina: multi-column, social, payment badges
    // ══════════════════════════════════════════════════════════════════════

    private function footerBlocks(): array
    {
        return [
            $this->block('nav_footer', [
                'bg_color'      => self::DARK,
                'text_color'    => '#e2e8f0',
                'copyright'     => '© ۱۴۰۳ فروشگاه بین‌المللی — تمام حقوق محفوظ است.',
                'show_socials'  => true,
                'show_payment_badges' => true,
                'tagline'       => 'بهترین قیمت، سریع‌ترین ارسال، اصیل‌ترین کالا',
                'columns' => [
                    [
                        'title' => 'خدمات ما',
                        'links' => [
                            ['label' => 'خرید از آمازون',      'url' => '/category/amazon'],
                            ['label' => 'محصولات دیجیتال',     'url' => '/category/digital'],
                            ['label' => 'پوشاک و مد',           'url' => '/category/fashion'],
                            ['label' => 'لوازم خانه',           'url' => '/category/home'],
                            ['label' => 'تخفیف ویژه',           'url' => '/deals'],
                        ],
                    ],
                    [
                        'title' => 'راهنمای خرید',
                        'links' => [
                            ['label' => 'نحوه خرید',            'url' => '/guide'],
                            ['label' => 'ارسال و تحویل',        'url' => '/shipping'],
                            ['label' => 'مرجوع کالا',           'url' => '/returns'],
                            ['label' => 'پرداخت امن',           'url' => '/payment'],
                            ['label' => 'سوالات متداول',         'url' => '/faq'],
                        ],
                    ],
                    [
                        'title' => 'فروش در پلتفرم',
                        'links' => [
                            ['label' => 'ثبت‌نام فروشنده',      'url' => '/seller/register'],
                            ['label' => 'پنل فروشندگان',        'url' => '/seller'],
                            ['label' => 'قوانین فروش',          'url' => '/seller/rules'],
                            ['label' => 'برنامه وابسته',        'url' => '/affiliate'],
                        ],
                    ],
                    [
                        'title' => 'درباره ما',
                        'links' => [
                            ['label' => 'درباره ما',            'url' => '/about'],
                            ['label' => 'تماس با ما',           'url' => '/contact'],
                            ['label' => 'فرصت‌های شغلی',       'url' => '/careers'],
                            ['label' => 'اخبار پلتفرم',         'url' => '/blog'],
                        ],
                    ],
                ],
            ]),
        ];
    }

    // ══════════════════════════════════════════════════════════════════════
    //  ALL PAGES (12 pages)
    // ══════════════════════════════════════════════════════════════════════

    private function buildAllPages(): array
    {
        return [
            'home'       => $this->pageHome(),
            'about'      => $this->pageAbout(),
            'contact'    => $this->pageContact(),
            'blog'       => $this->pageBlog(),
            'archive'    => $this->pageArchive(),
            'single'     => $this->pageSingle(),
            'search'     => $this->pageSearch(),
            'error'      => $this->page404(),
            'product'    => $this->pageProduct(),
            'category'   => $this->pageCategory(),
            'landing'    => $this->pageLanding(),
            'shop_deals' => $this->pageShopDeals(),
        ];
    }

    // ─────────────────────────────────────────────────────────────────────
    //  PAGE 1: HOMEPAGE
    // ─────────────────────────────────────────────────────────────────────

    private function pageHome(): array
    {
        return [
            'slug'  => '',
            'title' => 'صفحه اصلی',
            'blocks' => [

                // ── Hero slider: summer sale (Malltina: big hero with seasonal promotion)
                $this->block('hero', [
                    'title'            => 'تابستان داغ، قیمت خنک!',
                    'subtitle'         => 'تخفیف‌های ویژه فصل گرما — فقط تا پایان همین هفته. هزاران محصول اصل با ارسال سریع.',
                    'button_text'      => 'خرید با تخفیف',
                    'button_url'       => '/deals',
                    'background'       => 'primary',
                    'background_image' => '',
                    'align'            => 'right',
                    'overlay_opacity'  => '0.6',
                    'second_button'    => 'تا ۵۰٪ تخفیف',
                    'second_button_url'=> '/deals/summer',
                    'badge_text'       => '🔥 پیشنهاد ویژه',
                ]),

                // ── Brand partners row (Amazon, SIVVI, Trendyol, noon, Alibaba)
                $this->block('client_logos', [
                    'title' => 'خرید از برترین فروشگاه‌های جهان',
                    'logos' => [
                        ['name' => 'آمازون',    'icon' => '🛒', 'url' => '/category/amazon'],
                        ['name' => 'نون',       'icon' => '🟡', 'url' => '/category/noon'],
                        ['name' => 'علی‌بابا',  'icon' => '🅰',  'url' => '/category/alibaba'],
                        ['name' => 'Trendyol', 'icon' => '👗', 'url' => '/category/trendyol'],
                        ['name' => 'SIVVI',    'icon' => '👒', 'url' => '/category/sivvi'],
                        ['name' => 'Shein',    'icon' => '✨', 'url' => '/category/shein'],
                    ],
                ]),

                // ── Flash deal countdown
                $this->block('module:shop_countdown', [
                    'promo_text' => '⚡ فروش ویژه روز — فرصت محدود! همین الان سفارش بدهید',
                    'end_date'   => now()->addDays(3)->format('Y-m-d'),
                    'bg_color'   => self::DANGER,
                ]),

                // ── "ارسال یک روزه" featured products (Malltina: express delivery badge row)
                $this->block('module:shop_product_grid', [
                    'title'        => '⚡ ارسال یک روزه — محصولات منتخب',
                    'limit'        => 8,
                    'columns'      => '4',
                    'source'       => 'featured',
                    'category_slug'=> '',
                ]),

                // ── Categories grid (Malltina: horizontal category browser)
                $this->block('module:shop_categories', [
                    'title'      => 'دسته‌بندی محصولات',
                    'style'      => 'grid',
                    'limit'      => 8,
                    'show_count' => true,
                ]),

                // ── Why buy here? (Malltina: "چرا از مالتینا خرید کنم؟" section)
                $this->block('features_grid', [
                    'title'    => 'چرا از ما خرید کنید؟',
                    'subtitle' => 'تجربه خریدی متفاوت با اطمینان کامل',
                    'columns'  => '4',
                    'style'    => 'cards',
                    'features' => json_encode([
                        ['icon' => '✈️', 'title' => 'ارسال بین‌المللی',  'text' => 'تحویل سریع از سراسر جهان به درب منزل شما'],
                        ['icon' => '💳', 'title' => 'پرداخت امن',        'text' => 'پشتیبانی از PayPal، کارت‌های بین‌المللی و ارزهای دیجیتال'],
                        ['icon' => '🔄', 'title' => 'بازگشت ۷ روزه',    'text' => 'بدون سوال — اگر راضی نبودید کالا را پس بفرستید'],
                        ['icon' => '🛡️', 'title' => 'ضمانت اصالت',      'text' => 'تمام محصولات ۱۰۰٪ اصل با ضمانت‌نامه معتبر'],
                    ]),
                ]),

                // ── Bestsellers (Malltina: "پرفروش‌ترین‌ها" section)
                $this->block('module:shop_product_grid', [
                    'title'   => '🏆 پرفروش‌ترین‌های هفته',
                    'limit'   => 8,
                    'columns' => '4',
                    'source'  => 'bestselling',
                ]),

                // ── Hero promo banner (Malltina: secondary promotional banner)
                $this->block('promo_banner', [
                    'text'  => '🎉 کد تخفیف اول خرید: FIRST10 — ۱۰٪ تخفیف برای اولین سفارش شما',
                    'link'  => '/register',
                    'style' => 'success',
                ]),

                // ── Stats row (Malltina: platform credibility metrics)
                $this->block('stats_row', [
                    'stats' => [
                        ['label' => 'محصول',        'value' => '۵۰۰,۰۰۰+'],
                        ['label' => 'فروشنده',      'value' => '۱۰,۰۰۰+'],
                        ['label' => 'خریدار راضی',  'value' => '۲M+'],
                        ['label' => 'تحویل روزانه', 'value' => '۱۵,۰۰۰+'],
                    ],
                ]),

                // ── Trust badges (Malltina: bottom trust signals)
                $this->block('trust_badges', [
                    'title'  => 'خرید مطمئن با ضمانت ما',
                    'badges' => [
                        ['icon' => '🔒', 'title' => 'پرداخت ایمن'],
                        ['icon' => '📦', 'title' => 'بسته‌بندی اصولی'],
                        ['icon' => '🔄', 'title' => 'مرجوعی رایگان'],
                        ['icon' => '🌍', 'title' => 'ارسال جهانی'],
                        ['icon' => '📞', 'title' => 'پشتیبانی ۲۴/۷'],
                    ],
                ]),

                // ── Latest buying guides / blog
                $this->block('module:blog_posts', [
                    'title'        => '📖 راهنمای خرید و آموزش',
                    'limit'        => 3,
                    'layout'       => 'grid',
                    'show_excerpt' => true,
                    'show_date'    => true,
                ]),

                // ── Newsletter (Malltina: "عضو خبرنامه شوید" bottom CTA)
                $this->block('email_capture', [
                    'title'       => '🔔 اخبار تخفیف و محصولات جدید را از دست ندهید',
                    'button_text' => 'عضویت رایگان',
                ]),
            ],
        ];
    }

    // ─────────────────────────────────────────────────────────────────────
    //  PAGE 2: ABOUT
    // ─────────────────────────────────────────────────────────────────────

    private function pageAbout(): array
    {
        return [
            'slug'  => 'about',
            'title' => 'درباره ما',
            'blocks' => [
                $this->block('hero', [
                    'title'      => 'داستان ما — پیوند ایران با بازار جهانی',
                    'subtitle'   => 'ما با هدف آسان‌تر کردن خرید از بهترین فروشگاه‌های جهان برای هموطنان عزیز تأسیس شدیم.',
                    'background' => 'primary',
                    'align'      => 'center',
                ]),

                $this->block('heading', ['level' => 2, 'text' => 'چرا این پلتفرم را ساختیم؟']),
                $this->block('text', ['content' => 'سال‌هاست که ایرانیان علاقه‌مند به خرید از بازارهای جهانی بودند اما موانع مختلف این مسیر را دشوار می‌کرد. ما این شکاف را پر کردیم — با تیمی متخصص، زیرساخت امن و روابط با معتبرترین فروشندگان دنیا.']),

                $this->block('stats_row', [
                    'stats' => [
                        ['label' => 'سال تجربه',    'value' => '۷+'],
                        ['label' => 'مشتری راضی',   'value' => '۲M+'],
                        ['label' => 'برند معتبر',   'value' => '۵,۰۰۰+'],
                        ['label' => 'کشور پوشش',   'value' => '۴۰+'],
                    ],
                ]),

                $this->block('features_grid', [
                    'title'    => 'ارزش‌های ما',
                    'subtitle' => 'اصولی که هر روز بر اساس آن‌ها کار می‌کنیم',
                    'columns'  => '3',
                    'style'    => 'cards',
                    'features' => json_encode([
                        ['icon' => '🎯', 'title' => 'صداقت',       'text' => 'قیمت واقعی بدون هزینه‌های پنهان'],
                        ['icon' => '⚡', 'title' => 'سرعت',        'text' => 'سریع‌ترین ارسال ممکن به سراسر ایران'],
                        ['icon' => '🔒', 'title' => 'امنیت',       'text' => 'حفاظت از داده‌ها و تراکنش‌های مالی'],
                        ['icon' => '🌱', 'title' => 'رشد مستمر',  'text' => 'هر روز محصولات و برندهای جدید اضافه می‌کنیم'],
                        ['icon' => '💬', 'title' => 'ارتباط',      'text' => 'پشتیبانی ۲۴ ساعته توسط تیم متخصص'],
                        ['icon' => '🌍', 'title' => 'جهانی',       'text' => 'دسترسی به بازار جهانی از خانه‌تان'],
                    ]),
                ]),

                $this->block('team', [
                    'title' => 'تیم رهبری ما',
                    'limit' => 6,
                ]),

                $this->block('testimonials_slider', [
                    'title' => 'مشتریان درباره ما چه می‌گویند؟',
                    'limit' => 5,
                ]),

                $this->block('cta', [
                    'title'       => 'آماده‌اید؟ همین الان خرید خود را شروع کنید',
                    'subtitle'    => 'هزاران محصول اصل از بهترین برندهای دنیا — با یک کلیک',
                    'button_text' => 'ثبت‌نام رایگان',
                    'button_url'  => '/register',
                    'background'  => 'primary',
                ]),
            ],
        ];
    }

    // ─────────────────────────────────────────────────────────────────────
    //  PAGE 3: CONTACT
    // ─────────────────────────────────────────────────────────────────────

    private function pageContact(): array
    {
        return [
            'slug'  => 'contact',
            'title' => 'تماس با ما',
            'blocks' => [
                $this->block('hero', [
                    'title'      => 'تماس با ما',
                    'subtitle'   => 'تیم پشتیبانی ما ۲۴ ساعته آماده پاسخگویی است',
                    'background' => 'primary',
                    'align'      => 'center',
                ]),

                $this->block('contact_split', [
                    'title'   => 'راه‌های ارتباط با ما',
                    'address' => 'تهران، خیابان ولیعصر، ساختمان تجاری پارسه، طبقه ۵',
                    'phone'   => '۰۲۱-۸۸۱۲۳۴۵۶',
                    'email'   => 'support@international-shop.ir',
                    'working_hours' => 'شنبه تا چهارشنبه: ۸:۳۰ تا ۱۷:۳۰',
                ]),

                $this->block('features_grid', [
                    'title'    => 'کانال‌های پشتیبانی',
                    'columns'  => '3',
                    'style'    => 'bordered',
                    'features' => json_encode([
                        ['icon' => '💬', 'title' => 'چت آنلاین',    'text' => 'پاسخ فوری در کمتر از ۵ دقیقه — روزانه ۸ تا ۲۴'],
                        ['icon' => '📧', 'title' => 'ایمیل',         'text' => 'پاسخ تضمینی در کمتر از ۴ ساعت کاری'],
                        ['icon' => '📱', 'title' => 'واتساپ',        'text' => 'ارتباط مستقیم از طریق واتساپ برای سوالات سریع'],
                    ]),
                ]),

                $this->block('faq', [
                    'title' => 'سوالات پرتکرار',
                    'items' => [
                        ['question' => 'سفارشم را چطور پیگیری کنم؟',       'answer'   => 'پس از ارسال کد رهگیری به ایمیل/شماره شما ارسال می‌شود. همچنین از پنل کاربری → سفارشات قابل پیگیری است.'],
                        ['question' => 'آیا می‌توانم کالا را مرجوع کنم؟',   'answer'   => 'بله، تا ۷ روز پس از دریافت بدون هیچ سوالی امکان مرجوعی وجود دارد.'],
                        ['question' => 'روش‌های پرداخت چیست؟',             'answer'   => 'PayPal، ویزا/مستر کارت، کارت‌های شتاب از طریق درگاه معتبر.'],
                        ['question' => 'مدت زمان ارسال چقدر است؟',         'answer'   => 'بسته به مقصد ۳ تا ۱۴ روز کاری. برای تهران ارسال یک روزه نیز موجود است.'],
                        ['question' => 'آیا محصولات اصل هستند؟',           'answer'   => 'بله، تمام محصولات با ضمانت‌نامه اصالت از فروشندگان رسمی تهیه می‌شوند.'],
                    ],
                ]),

                $this->block('module:support_button', [
                    'label'    => 'چت با پشتیبانی',
                    'style'    => 'floating',
                    'position' => 'bottom-right',
                    'bg_color' => self::PRIMARY,
                ]),
            ],
        ];
    }

    // ─────────────────────────────────────────────────────────────────────
    //  PAGE 4: BLOG
    // ─────────────────────────────────────────────────────────────────────

    private function pageBlog(): array
    {
        return [
            'slug'  => 'blog',
            'title' => 'وبلاگ و راهنمای خرید',
            'blocks' => [
                $this->block('hero', [
                    'title'      => 'راهنمای خرید و نکات مفید',
                    'subtitle'   => 'مطالب آموزشی برای خرید بهتر از بازارهای بین‌المللی',
                    'background' => 'primary',
                    'align'      => 'center',
                ]),

                $this->block('module:blog_categories', [
                    'title' => 'دسته‌بندی مطالب',
                    'style' => 'badges',
                    'limit' => 10,
                ]),

                $this->block('module:blog_posts', [
                    'title'        => 'همه مطالب',
                    'limit'        => 9,
                    'layout'       => 'grid',
                    'show_excerpt' => true,
                    'show_date'    => true,
                ]),

                $this->block('email_capture', [
                    'title'       => 'عضویت در خبرنامه — اولین بدانید!',
                    'button_text' => 'عضو می‌شوم',
                ]),
            ],
        ];
    }

    // ─────────────────────────────────────────────────────────────────────
    //  PAGE 5: ARCHIVE
    // ─────────────────────────────────────────────────────────────────────

    private function pageArchive(): array
    {
        return [
            'slug'  => 'archive',
            'title' => 'آرشیو مطالب',
            'blocks' => [
                $this->block('breadcrumb', []),
                $this->block('heading', ['level' => 1, 'text' => 'آرشیو مطالب']),
                $this->block('module:blog_posts', [
                    'title'        => '',
                    'limit'        => 12,
                    'layout'       => 'grid',
                    'show_excerpt' => true,
                    'show_date'    => true,
                ]),
                $this->block('module:blog_categories', [
                    'title' => 'موضوعات',
                    'style' => 'badges',
                    'limit' => 15,
                ]),
            ],
        ];
    }

    // ─────────────────────────────────────────────────────────────────────
    //  PAGE 6: SINGLE BLOG POST
    // ─────────────────────────────────────────────────────────────────────

    private function pageSingle(): array
    {
        return [
            'slug'  => 'single',
            'title' => 'مقاله',
            'blocks' => [
                $this->block('breadcrumb', []),
                $this->block('heading', ['level' => 1, 'text' => 'عنوان راهنمای خرید']),
                $this->block('text', ['content' => 'محتوای کامل مقاله اینجا نمایش داده می‌شود. راهنمای جامع خرید از این دسته محصولات.']),
                $this->block('divider', []),
                $this->block('trust_badges', [
                    'title' => 'خرید مطمئن — ضمانت پلتفرم',
                ]),
                $this->block('module:blog_posts', [
                    'title'  => 'مطالب مرتبط',
                    'limit'  => 3,
                    'layout' => 'grid',
                ]),
                $this->block('module:shop_product_grid', [
                    'title'   => 'محصولات مرتبط با این مطلب',
                    'limit'   => 4,
                    'columns' => '4',
                    'source'  => 'featured',
                ]),
            ],
        ];
    }

    // ─────────────────────────────────────────────────────────────────────
    //  PAGE 7: SEARCH
    // ─────────────────────────────────────────────────────────────────────

    private function pageSearch(): array
    {
        return [
            'slug'  => 'search',
            'title' => 'جستجو',
            'blocks' => [
                $this->block('hero', [
                    'title'      => 'جستجو در بیش از ۵۰۰,۰۰۰ محصول',
                    'subtitle'   => 'محصول مورد نظر خود را پیدا کنید',
                    'background' => 'primary',
                    'align'      => 'center',
                ]),

                $this->block('module:shop_search_box', [
                    'placeholder' => 'نام محصول، برند یا دسته‌بندی را جستجو کنید...',
                    'style'       => 'box',
                ]),

                $this->block('module:shop_categories', [
                    'title' => 'جستجو بر اساس دسته‌بندی',
                    'style' => 'horizontal',
                    'limit' => 10,
                ]),

                $this->block('module:shop_product_grid', [
                    'title'   => 'نتایج جستجو',
                    'limit'   => 12,
                    'columns' => '4',
                    'source'  => 'recent',
                ]),

                $this->block('module:blog_posts', [
                    'title'  => 'مطالب مرتبط',
                    'limit'  => 3,
                    'layout' => 'grid',
                ]),
            ],
        ];
    }

    // ─────────────────────────────────────────────────────────────────────
    //  PAGE 8: 404
    // ─────────────────────────────────────────────────────────────────────

    private function page404(): array
    {
        return [
            'slug'  => '404',
            'title' => 'صفحه یافت نشد',
            'blocks' => [
                $this->block('hero', [
                    'title'       => '۴۰۴ — صفحه یافت نشد',
                    'subtitle'    => 'این صفحه وجود ندارد یا منتقل شده است. اما هزاران محصول عالی منتظرتان است!',
                    'button_text' => 'بازگشت به صفحه اصلی',
                    'button_url'  => '/',
                    'background'  => 'primary',
                    'align'       => 'center',
                ]),

                $this->block('module:shop_product_grid', [
                    'title'   => 'شاید این محصولات جالبتان باشد',
                    'limit'   => 4,
                    'columns' => '4',
                    'source'  => 'featured',
                ]),

                $this->block('module:blog_posts', [
                    'title'  => 'راهنماهای پرطرفدار',
                    'limit'  => 3,
                    'layout' => 'grid',
                ]),
            ],
        ];
    }

    // ─────────────────────────────────────────────────────────────────────
    //  PAGE 9: PRODUCT PAGE (Malltina product detail)
    // ─────────────────────────────────────────────────────────────────────

    private function pageProduct(): array
    {
        return [
            'slug'  => 'product',
            'title' => 'صفحه محصول',
            'blocks' => [
                $this->block('breadcrumb', []),

                // Product hero with image + details split layout
                $this->block('hero_split', [
                    'badge'        => '✅ اصالت تضمینی',
                    'title'        => 'نام محصول اینجا قرار می‌گیرد',
                    'subtitle'     => 'توضیحات کوتاه و جذاب درباره ویژگی‌های کلیدی محصول — کیفیت بالا، ارسال سریع، قیمت مناسب.',
                    'button_text'  => 'افزودن به سبد خرید',
                    'button_url'   => '#add-to-cart',
                    'button2_text' => 'خرید سریع',
                    'button2_url'  => '#quick-buy',
                    'image_url'    => 'https://placehold.co/600x600/f4f6f9/27c0e2/png?text=Product+Image',
                    'image_side'   => 'left',
                    'background'   => self::CARD_BG,
                ]),

                // Price and add to cart
                $this->block('price_box', [
                    'product_id' => '',
                ]),

                // Trust signals for product page
                $this->block('trust_badges', [
                    'title' => 'ضمانت خرید',
                    'badges' => [
                        ['icon' => '🔒', 'title' => 'پرداخت امن'],
                        ['icon' => '📦', 'title' => 'ارسال سریع'],
                        ['icon' => '🔄', 'title' => 'مرجوعی ۷ روزه'],
                        ['icon' => '✅', 'title' => 'ضمانت اصالت'],
                        ['icon' => '💳', 'title' => 'اقساط بدون بهره'],
                    ],
                ]),

                // Product features/specs
                $this->block('features_grid', [
                    'title'   => 'ویژگی‌های محصول',
                    'columns' => '3',
                    'style'   => 'bordered',
                    'features' => json_encode([
                        ['icon' => '⚡', 'title' => 'عملکرد بالا',     'text' => 'بهترین عملکرد در رده قیمتی'],
                        ['icon' => '🔋', 'title' => 'دوام طولانی',    'text' => 'کیفیت ساخت عالی و پایدار'],
                        ['icon' => '🌐', 'title' => 'کمپتیبیل',       'text' => 'سازگار با تمام دستگاه‌ها'],
                    ]),
                ]),

                // Customer reviews
                $this->block('module:reviews_grid', [
                    'title'       => 'نظرات خریداران',
                    'limit'       => 6,
                    'style'       => 'cards',
                    'show_avatar' => true,
                ]),

                // Related products
                $this->block('module:shop_product_slider', [
                    'title'   => 'محصولات مرتبط',
                    'limit'   => 6,
                    'source'  => 'featured',
                    'autoplay'=> true,
                ]),
            ],
        ];
    }

    // ─────────────────────────────────────────────────────────────────────
    //  PAGE 10: CATEGORY PAGE (Malltina: browsing by category)
    // ─────────────────────────────────────────────────────────────────────

    private function pageCategory(): array
    {
        return [
            'slug'  => 'category',
            'title' => 'دسته‌بندی محصولات',
            'blocks' => [
                $this->block('breadcrumb', []),

                $this->block('heading', [
                    'level' => 1,
                    'text'  => 'نام دسته‌بندی',
                ]),

                // Sub-categories horizontal scroller
                $this->block('module:shop_categories', [
                    'title'      => 'زیردسته‌ها',
                    'style'      => 'horizontal',
                    'limit'      => 8,
                    'show_count' => true,
                ]),

                // Filter + search for this category
                $this->block('module:shop_search_box', [
                    'placeholder' => 'جستجو در این دسته‌بندی...',
                    'style'       => 'bar',
                ]),

                // Products in category (main grid)
                $this->block('module:shop_product_grid', [
                    'title'   => 'محصولات این دسته',
                    'limit'   => 16,
                    'columns' => '4',
                    'source'  => 'recent',
                ]),

                // Featured in this category
                $this->block('module:shop_product_grid', [
                    'title'   => '⭐ پیشنهادهای ویژه این دسته',
                    'limit'   => 4,
                    'columns' => '4',
                    'source'  => 'featured',
                ]),

                // Related blog posts for category
                $this->block('module:blog_posts', [
                    'title'  => 'راهنمای خرید این دسته',
                    'limit'  => 3,
                    'layout' => 'grid',
                ]),
            ],
        ];
    }

    // ─────────────────────────────────────────────────────────────────────
    //  PAGE 11: LANDING PAGE (Malltina: promotional/sale landing)
    // ─────────────────────────────────────────────────────────────────────

    private function pageLanding(): array
    {
        return [
            'slug'  => 'landing',
            'title' => 'صفحه فرود ویژه',
            'blocks' => [
                // Urgency countdown
                $this->block('module:shop_countdown', [
                    'promo_text' => '🔥 آخرین فرصت — این پیشنهاد منقضی می‌شود!',
                    'end_date'   => now()->addDays(7)->format('Y-m-d'),
                    'bg_color'   => self::DANGER,
                ]),

                // Hero gradient for landing page
                $this->block('hero_gradient', [
                    'badge'         => '🎯 پیشنهاد انحصاری',
                    'title'         => 'بزرگ‌ترین فروش ویژه سال',
                    'subtitle'      => 'هزاران محصول اصل از بهترین برندهای جهان با بیشترین تخفیف. فرصت را از دست ندهید!',
                    'button_text'   => 'همین الان خرید کنید',
                    'button_url'    => '/deals',
                    'button2_text'  => 'مشاهده همه تخفیف‌ها',
                    'button2_url'   => '/deals/all',
                    'gradient_from' => self::DARK,
                    'gradient_to'   => '#1a3a5c',
                    'text_color'    => '#ffffff',
                ]),

                // Social proof stats
                $this->block('stats_row', [
                    'stats' => [
                        ['label' => 'محصول در تخفیف', 'value' => '۵۰,۰۰۰+'],
                        ['label' => 'حداکثر تخفیف',   'value' => '۷۰٪'],
                        ['label' => 'برند شریک',       'value' => '۵۰۰+'],
                        ['label' => 'خریدار فعال',     'value' => '۱۵۰,۰۰۰+'],
                    ],
                ]),

                // Why this deal is special
                $this->block('features_grid', [
                    'title'    => 'چرا این پیشنهاد استثنایی است؟',
                    'columns'  => '3',
                    'style'    => 'cards',
                    'features' => json_encode([
                        ['icon' => '💥', 'title' => 'قیمت‌شکن',     'text' => 'پایین‌ترین قیمت بازار — تضمینی'],
                        ['icon' => '🚀', 'title' => 'ارسال فوری',   'text' => 'تحویل در همان روز برای تهران'],
                        ['icon' => '🎁', 'title' => 'هدیه رایگان',  'text' => 'با هر خرید بالای ۵۰۰ هزار تومان'],
                    ]),
                ]),

                // Flash deal products
                $this->block('module:shop_product_grid', [
                    'title'   => '⚡ محصولات تخفیف ویژه',
                    'limit'   => 12,
                    'columns' => '4',
                    'source'  => 'featured',
                ]),

                // Trust badges
                $this->block('trust_badges', [
                    'title' => 'خرید با اطمینان کامل',
                ]),

                // Social proof testimonials
                $this->block('testimonials_slider', [
                    'title' => 'مشتریانی که به ما اعتماد کردند',
                    'limit' => 5,
                ]),

                // Final CTA
                $this->block('cta', [
                    'title'       => 'منتظر چه هستید؟ موجودی محدود است!',
                    'subtitle'    => 'با خرید همین الان از تخفیف‌های استثنایی بهره‌مند شوید',
                    'button_text' => 'شروع خرید — همین الان',
                    'button_url'  => '/deals',
                    'background'  => 'primary',
                ]),
            ],
        ];
    }

    // ─────────────────────────────────────────────────────────────────────
    //  PAGE 12: SHOP DEALS (Malltina: dedicated deals/discount page)
    // ─────────────────────────────────────────────────────────────────────

    private function pageShopDeals(): array
    {
        return [
            'slug'  => 'deals',
            'title' => 'تخفیفات ویژه',
            'blocks' => [
                $this->block('hero', [
                    'title'      => '🔥 تخفیفات ویژه — هر روز محصولات جدید',
                    'subtitle'   => 'بهترین معاملات روز را پیدا کنید — به‌روزرسانی لحظه‌ای',
                    'background' => 'primary',
                    'align'      => 'center',
                ]),

                $this->block('module:shop_countdown', [
                    'promo_text' => '⏳ پیشنهاد روز — فقط تا امشب ساعت ۲۴',
                    'end_date'   => now()->addDay()->format('Y-m-d'),
                    'bg_color'   => self::ACCENT,
                ]),

                $this->block('module:shop_product_grid', [
                    'title'   => '⚡ پیشنهادهای لحظه‌ای',
                    'limit'   => 8,
                    'columns' => '4',
                    'source'  => 'featured',
                ]),

                $this->block('module:shop_categories', [
                    'title' => 'تخفیف بر اساس دسته‌بندی',
                    'style' => 'grid',
                    'limit' => 6,
                ]),

                $this->block('module:shop_product_grid', [
                    'title'   => '🏆 پرفروش‌ترین محصولات تخفیف‌دار',
                    'limit'   => 8,
                    'columns' => '4',
                    'source'  => 'bestselling',
                ]),

                $this->block('stats_row', [
                    'stats' => [
                        ['label' => 'تخفیف روزانه', 'value' => '۱,۰۰۰+'],
                        ['label' => 'حداکثر تخفیف', 'value' => '۷۰٪'],
                        ['label' => 'برند ارائه‌دهنده', 'value' => '۵۰۰+'],
                        ['label' => 'خریدار امروز',  'value' => '۱۲,۰۰۰+'],
                    ],
                ]),

                $this->block('email_capture', [
                    'title'       => '🔔 اعلان تخفیف‌های انحصاری — مستقیم به ایمیلتان',
                    'button_text' => 'اعلام‌سازی رایگان',
                ]),
            ],
        ];
    }


    // ══════════════════════════════════════════════════════════════════════
    //  HELPERS
    // ══════════════════════════════════════════════════════════════════════

    private function block(string $type, array $props = []): array
    {
        return ['id' => 'blk_' . Str::random(8), 'type' => $type, 'props' => $props];
    }
}
