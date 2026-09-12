<?php

namespace Database\Seeders;

use App\Models\Theme;
use App\Services\Infrastructure\MediaStorage;
use App\Services\Infrastructure\StorageManager;
use App\Services\ThemeBuilder\BlockRegistry;
use App\Services\ThemeBuilder\ThemeBuilderService;
use Illuminate\Database\Seeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * A real, reusable master-theme draft for visual and Builder regression QA,
 * AND a realistic sellable multipurpose Theme structure (Builder Integrity
 * audit, 2026-09-11). It owns only portable Theme structure. Tenant business
 * records (products, posts, forms, courses, etc.) remain the responsibility
 * of the receiving site's ordinary fixture/seeding paths — pages that
 * depend on those (blog/shop/learning) use the real module block types,
 * which render an honest "will show once available" placeholder here and
 * real data once installed to a site with that content.
 *
 * The block list is intentionally derived from BlockRegistry: adding a
 * normal canonical block makes this fixture visibly incomplete until it is
 * deliberately classified. The 'builder-coverage' page remains the
 * exhaustive regression surface (any type not deliberately placed on a
 * real page, rendered with its raw schema defaults); every marketing-facing
 * page below carries real, specific authored content instead.
 */
class UniversalMultipurposeQaThemeSeeder extends Seeder
{
    public const SLUG = 'universal-multipurpose-qa';

    /** @var array<string,string> generated real image URL cache, by logical name */
    private array $images = [];

    public function run(): void
    {
        $registry = BlockRegistry::all();
        // customer-site deliberately de-exposes this platform-preview-only
        // entry because it has no tenant content adapter/model. A portable
        // master Theme must not advertise a block a receiving site cannot use.
        unset($registry['module:gallery_widget']);

        $theme = Theme::updateOrCreate(
            ['slug' => self::SLUG, 'site_id' => null],
            [
                'name' => 'قالب جامع چندمنظوره',
                'description' => 'قالب مرجع چندمنظوره برای بررسی یکپارچگی Builder و قابلیت‌های قابل‌نمایش سایت.',
                'type' => Theme::TYPE_FULL,
                'category' => Theme::CATEGORY_CORPORATE,
                'source' => Theme::SOURCE_SEEDER,
                'status' => Theme::STATUS_DRAFT,
                'global_styles' => array_merge(
                    app(ThemeBuilderService::class)->defaultGlobalStyles(),
                    ['primary_color' => '#4f46e5', 'secondary_color' => '#6366f1', 'background_color' => '#f8fafc', 'text_color' => '#111827']
                ),
                'is_marketplace' => false,
                'license_type' => Theme::LICENSE_FREE,
            ]
        );

        $this->seedImages($theme);

        $headerPresets = $this->headerPresets();
        $footerPresets = $this->footerPresets();
        $pages = $this->pagesFor($registry);

        $theme->fill([
            // Preset #1 ("classic") is the active header/footer — the same
            // real block data every other preset uses, just the default
            // selection. See §M/N: presets are authored Theme-level
            // variants (saas-platform's own header/footer canvas zones,
            // c47c9e9), not a second per-site editing surface.
            'header_blocks' => $headerPresets[0]['blocks'],
            'footer_blocks' => $footerPresets[0]['blocks'],
            'pages' => $pages,
            'settings' => [
                'qa_fixture' => true,
                'meta' => ['supported_modules' => [], 'qa_structure_only' => false],
                // 6 structurally distinct Header/Footer presets (Builder
                // integrity audit, §M/N): each is a real, different block
                // composition and/or functional surface (block count,
                // search/cart/CTA present or absent) — not a color reskin
                // of the same single block. Stored as named alternates on
                // the Theme itself (the existing header/footer-zone
                // mechanism); 'active' points at the index currently
                // applied to header_blocks/footer_blocks above.
                'header_presets' => $headerPresets,
                'footer_presets' => $footerPresets,
                'active_header_preset' => $headerPresets[0]['key'],
                'active_footer_preset' => $footerPresets[0]['key'],
            ],
        ])->save();
    }

    // ── Real media ──────────────────────────────────────────────────────────
    // Section H: real image bytes (GD-generated JPEGs — the same
    // UploadedFile::fake()->image() convention this codebase's own storage
    // tests already treat as "real" fixture imagery), stored through the
    // canonical portable master-Theme media path (ThemeMediaController's own
    // disk convention: theme-media/{theme->id}/) — never a customer-site
    // CustomerMedia UUID, which ADR-049 §6/§Y forbids inside master Theme
    // identity.

    private function seedImages(Theme $theme): void
    {
        $storage = app(StorageManager::class);
        $disk = app(MediaStorage::class)->diskNameFor('public');
        $dir = "theme-media/{$theme->id}";

        $specs = [
            'hero' => [1600, 900],
            'about' => [1200, 900],
            'gallery_1' => [900, 700],
            'gallery_2' => [900, 700],
            'gallery_3' => [900, 700],
            'gallery_4' => [900, 700],
            'portfolio_1' => [1000, 750],
            'portfolio_2' => [1000, 750],
        ];

        foreach ($specs as $name => [$w, $h]) {
            $file = UploadedFile::fake()->image("{$name}.jpg", $w, $h);
            $path = $storage->storeUploadedFile($file, $dir, $disk, "{$name}.jpg");
            $this->images[$name] = Storage::disk($disk)->url($path);
        }
    }

    private function img(string $name): string
    {
        return $this->images[$name] ?? '';
    }

    // ── Header presets (§M) ──────────────────────────────────────────────────

    /** @return array<int, array{key:string,name:string,description:string,blocks:array}> */
    private function headerPresets(): array
    {
        $baseLinks = [
            ['label' => 'خانه', 'url' => '/'],
            ['label' => 'درباره ما', 'url' => '/about'],
            ['label' => 'خدمات', 'url' => '/services'],
            ['label' => 'فروشگاه', 'url' => '/shop'],
            ['label' => 'وبلاگ', 'url' => '/blog'],
            ['label' => 'تماس با ما', 'url' => '/contact'],
        ];

        // Builder integrity final gate (2026-09-12): each preset below has a
        // genuinely distinct BLOCK-TYPE signature (not just different props
        // on the same single nav_header) — verified in
        // UniversalMultipurposeQaThemeSeederTest via an exact ordered
        // type-list comparison, not merely a block-count comparison.
        return [
            [
                'key' => 'classic',
                'name' => 'کلاسیک (لوگو + منو)',
                'description' => 'یک بلوک nav_header — ساده‌ترین ساختار ممکن، پایه برای مقایسه با بقیه.',
                'blocks' => [$this->navHeader([
                    'logo_text' => 'برند شما', 'nav_links' => $baseLinks, 'sticky' => false,
                ])],
            ],
            [
                'key' => 'minimal_nav_only',
                'name' => 'مینیمال (فقط منو، بدون هدر کامل)',
                'description' => 'فقط بلوک nav_menu — بدون لوگو/CTA/جستجوی nav_header — نوع بلوک متفاوت از بقیهٔ پریست‌ها، نه صرفاً پراپ‌های متفاوت.',
                'blocks' => [$this->block('nav_menu', ['location' => 'header'])],
            ],
            [
                'key' => 'split_nav_cart',
                'name' => 'ناوبری + سبد خرید مجزا',
                'description' => 'nav_header به‌همراه بلوک مستقل cart_icon — سبد خرید یک بلوک واقعی و جداگانه است، نه فقط فعال‌سازی show_cart داخل nav_header.',
                'blocks' => [
                    $this->navHeader([
                        'logo_text' => 'برند شما', 'nav_links' => $baseLinks, 'sticky' => true,
                        'cta_text' => 'شروع رایگان', 'cta_url' => '/pricing',
                    ]),
                    $this->block('cart_icon', []),
                ],
            ],
            [
                'key' => 'utility_topbar',
                'name' => 'نوار ابزار + هدر',
                'description' => 'ترکیب دو بلوک واقعی: social_icons به‌عنوان نوار بالای باریک، سپس nav_header.',
                'blocks' => [
                    $this->block('social_icons', ['links' => [
                        ['platform' => 'instagram', 'url' => 'https://instagram.com/yourbrand'],
                        ['platform' => 'telegram', 'url' => 'https://t.me/yourbrand'],
                    ]]),
                    $this->navHeader([
                        'logo_text' => 'برند شما', 'nav_links' => $baseLinks, 'phone' => '۰۲۱-۱۲۳۴۵۶۷۸',
                    ]),
                ],
            ],
            [
                'key' => 'ecommerce_search',
                'name' => 'فروشگاهی (جستجو + هدر + سبد، سه بلوک مستقل)',
                'description' => 'search_bar + nav_header + cart_icon — سه بلوک واقعی و مجزا؛ بیشترین تعداد بلوک از بین پریست‌های هدر.',
                'blocks' => [
                    $this->block('search_bar', ['placeholder' => 'جستجوی محصول...']),
                    $this->navHeader([
                        'logo_text' => 'فروشگاه شما', 'nav_links' => $baseLinks, 'sticky' => true, 'show_account' => true,
                    ]),
                    $this->block('cart_icon', []),
                ],
            ],
            [
                'key' => 'business_cta',
                'name' => 'کسب‌وکاری کامل (تماس + CTA)',
                'description' => 'ترکیب نوار تماس سریع (contact_info) + nav_header کامل با تلفن/CTA/جستجو — بیشترین قابلیت‌های فعال درون یک nav_header.',
                'blocks' => [
                    $this->block('contact_info', [
                        'title' => '', 'background' => '#111827',
                        'items' => [
                            ['icon' => '📞', 'label' => 'تماس', 'value' => '۰۲۱-۱۲۳۴۵۶۷۸'],
                            ['icon' => '✉️', 'label' => 'ایمیل', 'value' => 'info@yourbrand.ir'],
                        ],
                    ]),
                    $this->navHeader([
                        'logo_text' => 'برند شما', 'nav_links' => $baseLinks, 'sticky' => true,
                        'phone' => '۰۲۱-۱۲۳۴۵۶۷۸', 'cta_text' => 'مشاوره رایگان', 'cta_url' => '/contact',
                        'show_search' => true,
                    ]),
                ],
            ],
        ];
    }

    // ── Footer presets (§N) ───────────────────────────────────────────────────

    /** @return array<int, array{key:string,name:string,description:string,blocks:array}> */
    private function footerPresets(): array
    {
        $legal = [
            ['label' => 'حریم خصوصی', 'url' => '/content/privacy'],
            ['label' => 'قوانین و مقررات', 'url' => '/content/terms'],
        ];
        $copyright = '© ' . date('Y') . ' برند شما. تمامی حقوق محفوظ است.';

        // Builder integrity final gate (2026-09-12): each preset below has a
        // genuinely distinct BLOCK-TYPE signature (not just different props
        // on the same single nav_footer) — verified in
        // UniversalMultipurposeQaThemeSeederTest via an exact ordered
        // type-list comparison, not merely a block-count comparison.
        return [
            [
                'key' => 'simple',
                'name' => 'ساده',
                'description' => 'یک بلوک nav_footer با یک ستون کوتاه و کپی‌رایت — ساده‌ترین ساختار ممکن، پایه برای مقایسه با بقیه.',
                'blocks' => [$this->navFooter([
                    'logo_text' => 'برند شما', 'copyright' => $copyright,
                    'columns' => [['title' => 'دسترسی سریع', 'links' => [
                        ['label' => 'درباره ما', 'url' => '/about'], ['label' => 'تماس با ما', 'url' => '/contact'],
                    ]]],
                ])],
            ],
            [
                'key' => 'corporate_multi_column',
                'name' => 'شرکتی چندستونه',
                'description' => 'nav_footer با ۴ ستون واقعی به‌همراه بلوک مستقل language_switcher — تنها پریستی با تعویض زبان واقعی در فوتر.',
                'blocks' => [
                    $this->navFooter([
                        'logo_text' => 'برند شما', 'tagline' => 'راهکار جامع برای کسب‌وکار آنلاین شما',
                        'copyright' => $copyright, 'links' => $legal,
                        'columns' => [
                            ['title' => 'شرکت', 'links' => [['label' => 'درباره ما', 'url' => '/about'], ['label' => 'تیم', 'url' => '/team'], ['label' => 'فرصت‌های شغلی', 'url' => '/careers']]],
                            ['title' => 'محصولات', 'links' => [['label' => 'فروشگاه', 'url' => '/shop'], ['label' => 'دوره‌ها', 'url' => '/learning'], ['label' => 'قیمت‌گذاری', 'url' => '/pricing']]],
                            ['title' => 'منابع', 'links' => [['label' => 'وبلاگ', 'url' => '/blog'], ['label' => 'سوالات متداول', 'url' => '/faq'], ['label' => 'نمونه‌کارها', 'url' => '/portfolio']]],
                            ['title' => 'پشتیبانی', 'links' => [['label' => 'تماس با ما', 'url' => '/contact'], ['label' => 'مرکز راهنما', 'url' => '/content']]],
                        ],
                    ]),
                    $this->block('language_switcher', []),
                ],
            ],
            [
                'key' => 'ecommerce',
                'name' => 'فروشگاهی',
                'description' => 'بلوک مستقل social_icons به‌همراه nav_footer با ستون‌های دسته‌بندی/خدمات مشتری — شبکه‌های اجتماعی یک بلوک واقعی و جداگانه است، نه فقط پراپ داخل nav_footer.',
                'blocks' => [
                    $this->block('social_icons', ['links' => [
                        ['platform' => 'instagram', 'url' => 'https://instagram.com/yourshop'],
                        ['platform' => 'telegram', 'url' => 'https://t.me/yourshop'],
                    ]]),
                    $this->navFooter([
                        'logo_text' => 'فروشگاه شما', 'copyright' => $copyright,
                        'columns' => [
                            ['title' => 'دسته‌بندی‌ها', 'links' => [['label' => 'محصولات جدید', 'url' => '/shop?source=recent'], ['label' => 'پرفروش‌ترین‌ها', 'url' => '/shop']]],
                            ['title' => 'خدمات مشتریان', 'links' => [['label' => 'پیگیری سفارش', 'url' => '/account/orders'], ['label' => 'راهنمای خرید', 'url' => '/content']]],
                        ],
                    ]),
                ],
            ],
            [
                'key' => 'newsletter_focused',
                'name' => 'محور خبرنامه',
                'description' => 'ترکیب دو بلوک واقعی: newsletter_form بالای نوار footer، سپس nav_footer ساده — تنها پریستی با فرم تعاملی واقعی در فوتر.',
                'blocks' => [
                    $this->block('newsletter_form', [
                        'title' => 'از آخرین اخبار باخبر شوید', 'subtitle' => 'هر هفته یک ایمیل، بدون اسپم',
                        'placeholder' => 'ایمیل شما', 'button_text' => 'عضویت',
                        'background' => '#4f46e5', 'text_color' => '#ffffff',
                    ]),
                    $this->navFooter(['logo_text' => 'برند شما', 'copyright' => $copyright]),
                ],
            ],
            [
                'key' => 'contact_heavy',
                'name' => 'محور تماس',
                'description' => 'ترکیب contact_info واقعی (تلفن/ایمیل/آدرس) بالای nav_footer — بیشترین اطلاعات تماس مستقیم از بین پریست‌ها.',
                'blocks' => [
                    $this->block('contact_info', [
                        'title' => 'در تماس باشید', 'background' => '#1f2937',
                        'items' => [
                            ['icon' => '📞', 'label' => 'تلفن', 'value' => '۰۲۱-۱۲۳۴۵۶۷۸'],
                            ['icon' => '✉️', 'label' => 'ایمیل', 'value' => 'info@yourbrand.ir'],
                            ['icon' => '📍', 'label' => 'آدرس', 'value' => 'تهران، خیابان ولیعصر'],
                        ],
                    ]),
                    $this->navFooter(['logo_text' => 'برند شما', 'copyright' => $copyright]),
                ],
            ],
            [
                'key' => 'minimal_legal',
                'name' => 'مینیمال/قانونی',
                'description' => 'فقط بلوک nav_menu — بدون لوگو/ستون/کپی‌رایت nav_footer — نوع بلوک متفاوت از بقیهٔ پریست‌های فوتر، کمترین وزن از بین ۶ پریست.',
                'blocks' => [$this->block('nav_menu', ['location' => 'footer'])],
            ],
        ];
    }

    // ── Pages (§D) ────────────────────────────────────────────────────────────

    /** @param array<string,array> $registry @return array<string,array> */
    private function pagesFor(array $registry): array
    {
        $used = [];
        $pages = [
            'home' => ['title' => 'خانه', 'slug' => '', 'is_home' => true, 'blocks' => $this->homeBlocks()],
            'about' => ['title' => 'درباره ما', 'slug' => 'about', 'blocks' => $this->aboutBlocks()],
            'services' => ['title' => 'خدمات', 'slug' => 'services', 'blocks' => $this->servicesBlocks()],
            'portfolio' => ['title' => 'نمونه‌کارها', 'slug' => 'portfolio', 'blocks' => $this->portfolioBlocks()],
            'pricing' => ['title' => 'تعرفه‌ها', 'slug' => 'pricing', 'blocks' => $this->pricingBlocks()],
            'team' => ['title' => 'تیم ما', 'slug' => 'team', 'blocks' => $this->teamBlocks()],
            'testimonials' => ['title' => 'نظرات مشتریان', 'slug' => 'testimonials', 'blocks' => $this->testimonialsBlocks()],
            'faq' => ['title' => 'سوالات متداول', 'slug' => 'faq', 'blocks' => $this->faqBlocks()],
            'contact' => ['title' => 'تماس با ما', 'slug' => 'contact', 'blocks' => $this->contactBlocks()],
            // Dynamic-content pages (blog/shop/learning): real module blocks
            // — genuine "will show once available" placeholder here (no
            // Post/Product/Course model exists for a portable master Theme),
            // real data automatically once installed to a site that has it.
            'blog' => ['title' => 'وبلاگ', 'slug' => 'blog', 'blocks' => [
                $this->block('module:blog_featured', ['title' => 'مطلب ویژه']),
                $this->block('module:blog_posts', ['title' => 'آخرین مطالب', 'limit' => 9, 'layout' => 'grid', 'show_excerpt' => true, 'show_date' => true]),
                $this->block('module:blog_categories', ['title' => 'دسته‌بندی‌ها']),
            ]],
            'shop' => ['title' => 'فروشگاه', 'slug' => 'shop', 'blocks' => [
                $this->block('module:shop_search_box', ['placeholder' => 'جستجوی محصول...']),
                $this->block('module:shop_categories', ['title' => 'دسته‌بندی محصولات']),
                $this->block('module:shop_product_grid', ['title' => 'همه محصولات', 'limit' => 12, 'columns' => '4']),
                $this->block('trust_badges', $registry['trust_badges']['defaultProps'] ?? []),
            ]],
            'learning' => ['title' => 'آموزش', 'slug' => 'learning', 'blocks' => [
                $this->block('module:courses_grid', ['title' => 'دوره‌های آموزشی', 'limit' => 9, 'columns' => '3', 'layout' => 'grid', 'show_price' => true]),
                $this->block('module:course_enroll_cta', []),
            ]],
            'account' => ['title' => 'حساب کاربری', 'slug' => 'account', 'blocks' => [
                $this->block('login_register', []),
                $this->block('module:account_quick_links', ['style' => 'buttons', 'show_notifications' => true]),
            ]],
            'utilities' => ['title' => 'امکانات', 'slug' => 'utilities', 'blocks' => [
                $this->block('module:site_search', ['placeholder' => 'جستجو در کل سایت...', 'style' => 'hero']),
                $this->block('tabs', $registry['tabs']['defaultProps'] ?? []),
                $this->block('accordion', $registry['accordion']['defaultProps'] ?? []),
                $this->block('countdown', ['end_date' => date('Y-m-d', strtotime('+14 days')), 'label' => 'پایان جشنواره فروش ویژه']),
                $this->block('promo_banner', $registry['promo_banner']['defaultProps'] ?? []),
                $this->block('email_capture', $registry['email_capture']['defaultProps'] ?? []),
                $this->block('language_switcher', []),
                $this->block('module:motion', ['enabled' => true]),
            ]],
            'forms' => ['title' => 'فرم تماس', 'slug' => 'forms', 'blocks' => [
                $this->block('module:form_embed', ['title' => 'فرم درخواست مشاوره', 'show_title' => true, 'style' => 'card']),
            ]],
        ];

        foreach ($pages as $page) {
            foreach ($page['blocks'] as $block) {
                $used[$block['type']] = true;
            }
        }

        // Every remaining canonical block stays inspectable on one explicit
        // regression-coverage page, using the registry's own defaults — no
        // second hardcoded inventory, no fabricated marketing copy for
        // types with no natural business-page home.
        $pages['builder-coverage'] = [
            'title' => 'پوشش عناصر سازنده', 'slug' => 'builder-coverage', 'blocks' => array_map(
                fn (string $type) => $this->block($type, $registry[$type]['defaultProps'] ?? []),
                array_keys(array_diff_key($registry, $used))
            ),
        ];

        return $pages;
    }

    private function homeBlocks(): array
    {
        return [
            // Hero
            $this->block('hero', [
                'title' => 'همه‌چیز برای رشد کسب‌وکار شما، در یک پلتفرم',
                'subtitle' => 'ساخت سایت، فروشگاه، وبلاگ و دوره‌های آموزشی — بدون نیاز به دانش فنی، با پشتیبانی واقعی فارسی.',
                'button_text' => 'شروع رایگان', 'button_url' => '/pricing',
                'background' => 'primary', 'background_image' => $this->img('hero'), 'align' => 'center',
            ]),
            // Trust
            $this->block('client_logos', [
                'title' => 'مورد اعتماد کسب‌وکارهای موفق ایرانی',
                'logos' => [
                    ['name' => 'فروشگاه دیجی‌مد'], ['name' => 'آموزشگاه رشد'], ['name' => 'کلینیک سلامت'],
                    ['name' => 'استودیو طراحی نقش'], ['name' => 'آژانس سفر پرواز'], ['name' => 'رستوران زیتون'],
                ],
                'scroll' => true,
            ]),
            // Services
            $this->block('features_grid', [
                'title' => 'چرا صاحبان کسب‌وکار ما را انتخاب می‌کنند؟',
                'subtitle' => 'ابزارهایی که واقعاً به آن‌ها نیاز دارید، بدون پیچیدگی اضافه',
                'columns' => '3', 'style' => 'cards',
                'features' => [
                    ['icon' => '🎨', 'title' => 'سازندهٔ بصری صفحه', 'text' => 'طراحی صفحات بدون کدنویسی با پیش‌نمایش زنده'],
                    ['icon' => '🛒', 'title' => 'فروشگاه آنلاین کامل', 'text' => 'مدیریت محصول، سبد خرید و پرداخت یکپارچه'],
                    ['icon' => '🎓', 'title' => 'پلتفرم آموزش آنلاین', 'text' => 'فروش دوره و مدیریت دانشجویان'],
                    ['icon' => '📱', 'title' => 'واکنش‌گرا در همهٔ دستگاه‌ها', 'text' => 'تجربهٔ یکسان روی موبایل، تبلت و دسکتاپ'],
                    ['icon' => '🔒', 'title' => 'امنیت و پشتیبان‌گیری خودکار', 'text' => 'اطلاعات شما همیشه محافظت‌شده است'],
                    ['icon' => '💬', 'title' => 'پشتیبانی فارسی واقعی', 'text' => 'تیم پشتیبانی همیشه در دسترس شماست'],
                ],
            ]),
            // Stats
            $this->block('stats_row', [
                'title' => '', 'background' => '#f8fafc', 'dividers' => true,
                'stats' => [
                    ['value' => 3200, 'suffix' => '+', 'label' => 'کسب‌وکار فعال'],
                    ['value' => 98, 'suffix' => '%', 'label' => 'رضایت مشتری'],
                    ['value' => 450, 'suffix' => '+', 'label' => 'قالب و افزونه'],
                    ['value' => 5, 'suffix' => ' سال', 'label' => 'سابقهٔ فعالیت'],
                ],
            ]),
            // About
            $this->block('about_section', [
                'badge' => 'دربارهٔ ما', 'title' => 'ساخته‌شده برای کسب‌وکارهای ایرانی',
                'description' => 'ما از سال ۱۳۹۸ در حال ساخت ابزارهایی هستیم که به کسب‌وکارهای کوچک و متوسط کمک می‌کند بدون نیاز به تیم فنی، حضور آنلاین حرفه‌ای داشته باشند.',
                'features' => ['بدون نیاز به کدنویسی', 'میزبانی و دامنهٔ رایگان', 'پشتیبانی فارسی ۲۴ ساعته'],
                'btn_text' => 'بیشتر بدانید', 'btn_url' => '/about',
            ]),
            // Portfolio (real generated images)
            $this->block('gallery', [
                'title' => 'نمونه‌کارهای ساخته‌شده روی پلتفرم ما',
                'columns' => '3',
                'items' => [
                    ['url' => $this->img('portfolio_1'), 'alt' => 'نمونه‌کار فروشگاه آنلاین'],
                    ['url' => $this->img('portfolio_2'), 'alt' => 'نمونه‌کار سایت شرکتی'],
                    ['url' => $this->img('gallery_1'), 'alt' => 'نمونه‌کار وبلاگ'],
                ],
            ]),
            // Products
            $this->block('product_grid', ['columns' => 4, 'limit' => 8]),
            // Courses
            $this->block('module:courses_grid', ['title' => 'دوره‌های آموزشی محبوب', 'limit' => 3, 'columns' => '3', 'layout' => 'grid', 'show_price' => true]),
            // News/blog
            $this->block('blog_posts', ['title' => 'جدیدترین مطالب وبلاگ', 'limit' => 3]),
            // Team
            $this->block('team_cards', [
                'title' => 'با تیم ما آشنا شوید', 'columns' => '3',
                'members' => [
                    ['name' => 'سارا احمدی', 'role' => 'مدیرعامل و بنیان‌گذار', 'image' => '', 'bio' => 'بیش از ۱۰ سال تجربه در توسعهٔ کسب‌وکارهای دیجیتال'],
                    ['name' => 'رضا کریمی', 'role' => 'مدیر فنی', 'image' => '', 'bio' => 'معمار زیرساخت پلتفرم و تیم مهندسی'],
                    ['name' => 'نگار حسینی', 'role' => 'مدیر محصول', 'image' => '', 'bio' => 'مسئول تجربهٔ کاربری و طراحی محصول'],
                ],
            ]),
            // Testimonials (real carousel — see ThemeCanvasRenderer::renderTestimonialsSlider)
            $this->block('testimonials_slider', [
                'title' => 'مشتریان ما چه می‌گویند', 'columns' => '3', 'autoplay' => true, 'interval' => 6000,
                'items' => [
                    ['name' => 'امیر رستمی', 'role' => 'صاحب فروشگاه دیجی‌مد', 'text' => 'در کمتر از یک هفته فروشگاهم آماده بود. پشتیبانی فارسی واقعاً فرق می‌کند.', 'rating' => 5],
                    ['name' => 'مریم صادقی', 'role' => 'موسس آموزشگاه رشد', 'text' => 'بخش دوره‌های آموزشی دقیقاً همان چیزی بود که نیاز داشتم.', 'rating' => 5],
                    ['name' => 'حسین یزدانی', 'role' => 'مدیر کلینیک سلامت', 'text' => 'سازندهٔ بصری فوق‌العاده ساده است، حتی برای من که فنی نیستم.', 'rating' => 4],
                ],
            ]),
            // Pricing
            $this->block('pricing_cards', [
                'title' => 'پلن مناسب کسب‌وکار خود را انتخاب کنید', 'subtitle' => 'بدون تعهد، هر زمان قابل تغییر',
                'plans' => [
                    ['name' => 'استارتر', 'price' => 'رایگان', 'period' => '', 'badge' => '', 'highlight' => false, 'features' => ['۱ صفحه فرود', '۵۰۰ مگابایت فضا', 'پشتیبانی ایمیلی'], 'cta_text' => 'شروع رایگان', 'cta_url' => '/account'],
                    ['name' => 'حرفه‌ای', 'price' => '۴۹۰', 'period' => 'هزار تومان / ماه', 'badge' => 'محبوب‌ترین', 'highlight' => true, 'features' => ['سایت نامحدود صفحه', 'فروشگاه آنلاین کامل', '۱۰ گیگابایت فضا', 'پشتیبانی اولویت‌دار'], 'cta_text' => 'انتخاب پلن حرفه‌ای', 'cta_url' => '/pricing'],
                    ['name' => 'سازمانی', 'price' => '۱۲۹۰', 'period' => 'هزار تومان / ماه', 'badge' => '', 'highlight' => false, 'features' => ['همهٔ امکانات حرفه‌ای', 'دامنهٔ اختصاصی', 'مدیر حساب اختصاصی', 'SLA پشتیبانی'], 'cta_text' => 'تماس با فروش', 'cta_url' => '/contact'],
                ],
            ]),
            // Gallery
            $this->block('bento_grid', [
                'title' => 'همه‌چیز در یک پلتفرم',
                'items' => [
                    ['title' => 'مدیریت هوشمند', 'text' => 'داشبورد جامع با تحلیل داده', 'icon' => '📊', 'size' => 'large', 'bg_color' => '#4f46e5', 'text_color' => '#ffffff'],
                    ['title' => 'امنیت پیشرفته', 'text' => 'رمزنگاری end-to-end', 'icon' => '🔒', 'size' => 'small', 'bg_color' => '#f0fdf4', 'text_color' => '#14532d'],
                    ['title' => 'سرعت بالا', 'text' => 'CDN داخلی و بین‌المللی', 'icon' => '⚡', 'size' => 'small', 'bg_color' => '#fef9c3', 'text_color' => '#713f12'],
                ],
            ]),
            // FAQ
            $this->block('faq', [
                'title' => 'سوالات متداول',
                'items' => [
                    ['question' => 'آیا نیاز به دانش فنی دارم؟', 'answer' => 'خیر، سازندهٔ بصری برای استفادهٔ بدون کدنویسی طراحی شده است.'],
                    ['question' => 'آیا می‌توانم بعداً پلن را تغییر دهم؟', 'answer' => 'بله، در هر زمان می‌توانید پلن خود را ارتقا یا کاهش دهید.'],
                    ['question' => 'دامنهٔ اختصاصی شامل می‌شود؟', 'answer' => 'در پلن‌های حرفه‌ای و سازمانی، اتصال دامنهٔ اختصاصی رایگان است.'],
                ],
            ]),
            // Form/contact
            $this->block('newsletter_form', [
                'title' => 'در خبرنامهٔ ما عضو شوید', 'subtitle' => 'جدیدترین اخبار و پیشنهادات ویژه را دریافت کنید',
                'placeholder' => 'ایمیل یا شماره موبایل شما', 'button_text' => 'عضویت رایگان',
                'background' => '#4f46e5', 'text_color' => '#ffffff',
            ]),
            // Final CTA
            $this->block('cta', [
                'title' => 'همین امروز کسب‌وکار آنلاین خود را شروع کنید',
                'subtitle' => 'بدون نیاز به کارت اعتباری — در کمتر از ۵ دقیقه',
                'button_text' => 'ساخت سایت رایگان', 'button_url' => '/account', 'background' => 'primary',
            ]),
        ];
    }

    private function aboutBlocks(): array
    {
        return [
            $this->block('hero', [
                'title' => 'داستان ما', 'subtitle' => 'از یک ایدهٔ ساده تا پلتفرمی برای هزاران کسب‌وکار',
                'button_text' => '', 'button_url' => '', 'background' => 'secondary',
                'background_image' => $this->img('about'), 'align' => 'center',
            ]),
            $this->block('about_section', [
                'badge' => 'مسیر ما', 'title' => 'از ۱۳۹۸ تا امروز',
                'description' => 'با یک تیم کوچک شروع کردیم و امروز بیش از ۳۰۰۰ کسب‌وکار از پلتفرم ما برای فروش، آموزش و معرفی خدمات خود استفاده می‌کنند.',
                'features' => ['تیم مهندسی داخلی', 'زیرساخت ایرانی و پایدار', 'بروزرسانی مستمر امکانات'],
                'btn_text' => 'مشاهده خدمات', 'btn_url' => '/services',
            ]),
            $this->block('process_steps', [
                'title' => 'چطور کار می‌کنیم', 'subtitle' => 'از ثبت‌نام تا راه‌اندازی، سه گام ساده',
                'steps' => [
                    ['number' => '۱', 'title' => 'ثبت‌نام رایگان', 'text' => 'در کمتر از یک دقیقه حساب بسازید'],
                    ['number' => '۲', 'title' => 'انتخاب قالب', 'text' => 'از میان قالب‌های آماده یکی را انتخاب کنید'],
                    ['number' => '۳', 'title' => 'شخصی‌سازی و انتشار', 'text' => 'محتوای خود را اضافه کرده و سایت را منتشر کنید'],
                ],
            ]),
            $this->block('skills_bars', [
                'title' => 'توانمندی‌های زیرساخت ما',
                'skills' => [
                    ['name' => 'پایداری سرور', 'percent' => 99],
                    ['name' => 'سرعت بارگذاری', 'percent' => 92],
                    ['name' => 'امنیت داده', 'percent' => 97],
                ],
            ]),
            $this->block('client_logos', [
                'title' => 'برندهایی که به ما اعتماد کرده‌اند',
                'logos' => [['name' => 'دیجی‌مد'], ['name' => 'آموزشگاه رشد'], ['name' => 'کلینیک سلامت'], ['name' => 'استودیو نقش']],
                'scroll' => true,
            ]),
        ];
    }

    private function servicesBlocks(): array
    {
        return [
            $this->block('service_cards', [
                'title' => 'خدماتی که ارائه می‌دهیم',
                'services' => [
                    ['icon' => '🎨', 'title' => 'طراحی سایت اختصاصی', 'text' => 'طراحی سفارشی مطابق هویت برند شما', 'url' => '/contact'],
                    ['icon' => '🛒', 'title' => 'راه‌اندازی فروشگاه آنلاین', 'text' => 'از کاتالوگ محصول تا درگاه پرداخت', 'url' => '/shop'],
                    ['icon' => '🎓', 'title' => 'پلتفرم آموزش آنلاین', 'text' => 'فروش دوره و مدیریت دانشجویان', 'url' => '/learning'],
                    ['icon' => '📈', 'title' => 'بهینه‌سازی و سئو', 'text' => 'افزایش دیده‌شدن سایت شما در گوگل', 'url' => '/contact'],
                ],
            ]),
            $this->block('process_steps', [
                'title' => 'روند همکاری', 'subtitle' => 'شفاف و بدون هزینهٔ پنهان',
                'steps' => [
                    ['number' => '۱', 'title' => 'مشاورهٔ رایگان', 'text' => 'نیاز شما را بررسی می‌کنیم'],
                    ['number' => '۲', 'title' => 'پیشنهاد فنی', 'text' => 'برنامهٔ اجرایی و زمان‌بندی ارائه می‌شود'],
                    ['number' => '۳', 'title' => 'اجرا و تحویل', 'text' => 'سایت شما آماده و تحویل داده می‌شود'],
                ],
            ]),
            $this->block('cta', [
                'title' => 'برای مشاورهٔ رایگان تماس بگیرید', 'subtitle' => 'تیم ما آماده پاسخگویی است',
                'button_text' => 'تماس با ما', 'button_url' => '/contact', 'background' => 'primary',
            ]),
        ];
    }

    private function portfolioBlocks(): array
    {
        return [
            $this->block('heading', ['text' => 'نمونه‌کارهای منتخب', 'level' => '1']),
            $this->block('gallery', [
                'title' => '', 'columns' => '3',
                'items' => [
                    ['url' => $this->img('portfolio_1'), 'alt' => 'پروژهٔ فروشگاه دیجی‌مد'],
                    ['url' => $this->img('portfolio_2'), 'alt' => 'پروژهٔ سایت شرکتی نقش'],
                    ['url' => $this->img('gallery_1'), 'alt' => 'پروژهٔ وبلاگ آموزشی رشد'],
                    ['url' => $this->img('gallery_2'), 'alt' => 'پروژهٔ کلینیک سلامت'],
                    ['url' => $this->img('gallery_3'), 'alt' => 'پروژهٔ آژانس سفر پرواز'],
                    ['url' => $this->img('gallery_4'), 'alt' => 'پروژهٔ رستوران زیتون'],
                ],
            ]),
            $this->block('bento_grid', [
                'title' => 'دسته‌بندی پروژه‌ها',
                'items' => [
                    ['title' => 'فروشگاهی', 'text' => '۱۲ پروژه', 'icon' => '🛒', 'size' => 'small', 'bg_color' => '#eef2ff', 'text_color' => '#3730a3'],
                    ['title' => 'شرکتی', 'text' => '۸ پروژه', 'icon' => '🏢', 'size' => 'small', 'bg_color' => '#f0fdf4', 'text_color' => '#14532d'],
                    ['title' => 'آموزشی', 'text' => '۵ پروژه', 'icon' => '🎓', 'size' => 'small', 'bg_color' => '#fef9c3', 'text_color' => '#713f12'],
                ],
            ]),
        ];
    }

    private function pricingBlocks(): array
    {
        return [
            $this->block('heading', ['text' => 'تعرفه‌ها و پلن‌ها', 'level' => '1']),
            $this->block('pricing_cards', [
                'title' => '', 'subtitle' => 'همهٔ پلن‌ها شامل میزبانی و SSL رایگان است',
                'plans' => [
                    ['name' => 'استارتر', 'price' => 'رایگان', 'period' => '', 'badge' => '', 'highlight' => false, 'features' => ['۱ صفحه فرود', '۵۰۰ مگابایت فضا', 'پشتیبانی ایمیلی'], 'cta_text' => 'شروع رایگان', 'cta_url' => '/account'],
                    ['name' => 'حرفه‌ای', 'price' => '۴۹۰', 'period' => 'هزار تومان / ماه', 'badge' => 'محبوب‌ترین', 'highlight' => true, 'features' => ['سایت نامحدود صفحه', 'فروشگاه آنلاین کامل', '۱۰ گیگابایت فضا', 'پشتیبانی اولویت‌دار'], 'cta_text' => 'انتخاب پلن حرفه‌ای', 'cta_url' => '/account'],
                    ['name' => 'سازمانی', 'price' => '۱۲۹۰', 'period' => 'هزار تومان / ماه', 'badge' => '', 'highlight' => false, 'features' => ['همهٔ امکانات حرفه‌ای', 'دامنهٔ اختصاصی', 'مدیر حساب اختصاصی', 'SLA پشتیبانی'], 'cta_text' => 'تماس با فروش', 'cta_url' => '/contact'],
                ],
            ]),
            $this->block('module:pricing_table', ['title' => 'اشتراک‌های تکمیلی', 'subtitle' => 'برای نیازهای خاص کسب‌وکار خود']),
            $this->block('trust_badges', [
                'badges' => [
                    ['icon' => '↩️', 'title' => 'بازگشت وجه', 'text' => 'تا ۷ روز بدون قید و شرط'],
                    ['icon' => '🔒', 'title' => 'پرداخت امن', 'text' => 'درگاه‌های معتبر بانکی'],
                    ['icon' => '☎️', 'title' => 'پشتیبانی ۲۴/۷', 'text' => 'همیشه در دسترس شما'],
                ],
                'background' => '#f8fafc',
            ]),
        ];
    }

    private function teamBlocks(): array
    {
        return [
            $this->block('heading', ['text' => 'با تیم ما آشنا شوید', 'level' => '1']),
            $this->block('team_cards', [
                'title' => '', 'columns' => '4',
                'members' => [
                    ['name' => 'سارا احمدی', 'role' => 'مدیرعامل و بنیان‌گذار', 'image' => '', 'bio' => 'بیش از ۱۰ سال تجربه در توسعهٔ کسب‌وکار دیجیتال'],
                    ['name' => 'رضا کریمی', 'role' => 'مدیر فنی', 'image' => '', 'bio' => 'معمار زیرساخت پلتفرم'],
                    ['name' => 'نگار حسینی', 'role' => 'مدیر محصول', 'image' => '', 'bio' => 'مسئول تجربهٔ کاربری'],
                    ['name' => 'علی محمدی', 'role' => 'مدیر پشتیبانی', 'image' => '', 'bio' => 'سرپرست تیم پشتیبانی مشتریان'],
                ],
            ]),
            $this->block('team', ['title' => 'اعضای بیشتر تیم (داده پویا)', 'limit' => 6]),
        ];
    }

    private function testimonialsBlocks(): array
    {
        return [
            $this->block('heading', ['text' => 'مشتریان ما چه می‌گویند', 'level' => '1']),
            $this->block('testimonials_slider', [
                'title' => '', 'columns' => '1', 'autoplay' => true, 'interval' => 5000,
                'items' => [
                    ['name' => 'امیر رستمی', 'role' => 'صاحب فروشگاه دیجی‌مد', 'text' => 'در کمتر از یک هفته فروشگاهم آماده بود. پشتیبانی فارسی واقعاً فرق می‌کند.', 'rating' => 5],
                    ['name' => 'مریم صادقی', 'role' => 'موسس آموزشگاه رشد', 'text' => 'بخش دوره‌های آموزشی دقیقاً همان چیزی بود که نیاز داشتم.', 'rating' => 5],
                    ['name' => 'حسین یزدانی', 'role' => 'مدیر کلینیک سلامت', 'text' => 'سازندهٔ بصری فوق‌العاده ساده است، حتی برای من که فنی نیستم.', 'rating' => 4],
                    ['name' => 'لیلا نوری', 'role' => 'مدیر استودیو نقش', 'text' => 'گالری تصاویر و نمونه‌کارها دقیقاً چیزی بود که برای معرفی کارهایم لازم داشتم.', 'rating' => 5],
                ],
            ]),
            $this->block('module:reviews_grid', ['title' => 'نظرات محصولات (داده پویا)', 'limit' => 6, 'style' => 'cards', 'show_avatar' => true]),
        ];
    }

    private function faqBlocks(): array
    {
        return [
            $this->block('heading', ['text' => 'سوالات متداول', 'level' => '1']),
            $this->block('faq', [
                'title' => '',
                'items' => [
                    ['question' => 'آیا نیاز به دانش فنی دارم؟', 'answer' => 'خیر، سازندهٔ بصری برای استفادهٔ بدون کدنویسی طراحی شده و با کشیدن و رهاکردن عناصر کار می‌کنید.'],
                    ['question' => 'آیا می‌توانم بعداً پلن را تغییر دهم؟', 'answer' => 'بله، در هر زمان می‌توانید پلن خود را ارتقا یا کاهش دهید و مابه‌التفاوت به‌صورت خودکار محاسبه می‌شود.'],
                    ['question' => 'دامنهٔ اختصاصی شامل می‌شود؟', 'answer' => 'در پلن‌های حرفه‌ای و سازمانی، اتصال دامنهٔ اختصاصی شما رایگان است.'],
                    ['question' => 'آیا امکان فروش دورهٔ آموزشی وجود دارد؟', 'answer' => 'بله، ماژول آموزش امکان تعریف دوره، فروش و مدیریت دانشجویان را فراهم می‌کند.'],
                    ['question' => 'پشتیبانی چگونه است؟', 'answer' => 'تیم پشتیبانی فارسی ما از طریق تیکت و واتساپ در دسترس شماست.'],
                    ['question' => 'آیا می‌توانم قبل از خرید تست کنم؟', 'answer' => 'بله، پلن استارتر رایگان است و بدون نیاز به کارت اعتباری می‌توانید شروع کنید.'],
                ],
            ]),
            $this->block('module:faq_dynamic', [
                'title' => 'سوالات بیشتر', 'limit' => 10, 'style' => 'list',
                'items' => [
                    ['question' => 'آیا SSL رایگان است؟', 'answer' => 'بله، گواهی SSL برای همهٔ سایت‌ها به‌صورت خودکار و رایگان فعال می‌شود.'],
                    ['question' => 'محدودیت ترافیک چقدر است؟', 'answer' => 'در پلن‌های حرفه‌ای و سازمانی محدودیت ترافیک وجود ندارد.'],
                ],
            ]),
        ];
    }

    private function contactBlocks(): array
    {
        return [
            $this->block('contact_split', [
                'title' => 'با ما در ارتباط باشید', 'subtitle' => 'هر سوالی دارید، تیم ما پاسخگوست',
                'phone' => '۰۲۱-۱۲۳۴۵۶۷۸', 'email' => 'info@yourbrand.ir',
                'address' => 'تهران، خیابان ولیعصر، پلاک ۱۲۳', 'hours' => 'شنبه تا پنجشنبه، ۹ تا ۱۷', 'show_form' => true,
            ]),
            $this->block('module:contact_quick', ['title' => 'تماس سریع', 'show_phone' => true, 'show_address' => true, 'show_map' => true]),
            $this->block('module:support_button', ['label' => 'پشتیبانی آنلاین', 'style' => 'floating', 'position' => 'bottom-right', 'bg_color' => '#4f46e5']),
            $this->block('module:whatsapp_widget', ['label' => 'چت واتساپ', 'position' => 'bottom-left']),
        ];
    }

    // ── Block builders ────────────────────────────────────────────────────────

    private function navHeader(array $props): array
    {
        return $this->block('nav_header', array_merge([
            'logo_url' => '/', 'bg_color' => '#ffffff', 'text_color' => '#111827', 'cta_url' => '#',
        ], $props));
    }

    private function navFooter(array $props): array
    {
        return $this->block('nav_footer', array_merge([
            'bg_color' => '#111827', 'text_color' => '#e5e7eb',
        ], $props));
    }

    private function block(string $type, array $props): array
    {
        static $registry = null;
        $registry ??= BlockRegistry::all();
        $resolvedType = isset($registry[$type]) ? $type : $type;

        return [
            'id' => 'qa_' . str_replace([':', '-'], '_', $resolvedType) . '_' . substr(md5(json_encode($props) . microtime()), 0, 6),
            'type' => $resolvedType,
            'props' => $props,
            'children' => BlockRegistry::isContainerType($resolvedType) ? [] : [],
        ];
    }
}
