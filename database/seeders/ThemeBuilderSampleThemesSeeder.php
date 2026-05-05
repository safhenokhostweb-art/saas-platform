<?php

namespace Database\Seeders;

use App\Models\Theme;
use Illuminate\Database\Seeder;

/**
 * سه تم نمونهٔ آماده برای تولید:
 * ۱) وب‌سایت شخصی/فریلنسر
 * ۲) وب‌سایت شرکتی
 * ۳) فروشگاه اینترنتی پیشرفته
 */
class ThemeBuilderSampleThemesSeeder extends Seeder
{
    public function run(): void
    {
        $service = app(\App\Services\ThemeBuilder\ThemeBuilderService::class);
        $defaultStyles = $service->defaultGlobalStyles();

        // ─── ۱) تم شخصی / فریلنسر ─────────────────────────────────────────
        $personalDefaults = [
            'name' => 'وب‌سایت شخصی و فریلنسر',
            'description' => 'تم مینیمال و شیک برای برند شخصی، پورتفولیو و تماس. پشتیبانی حالت تاریک.',
            'type' => 'full',
            'site_id' => null,
            'status' => 'published',
            'published_at' => now(),
            'header_blocks' => [
                ['type' => 'section', 'id' => 'h1', 'props' => ['padding' => 'sm', 'background' => 'surface', 'container' => true], 'blocks' => [
                    ['type' => 'nav_menu', 'id' => 'nav1', 'props' => ['location' => 'header']],
                ]],
            ],
            'footer_blocks' => [
                ['type' => 'section', 'id' => 'f1', 'props' => ['padding' => 'lg', 'background' => 'surface', 'container' => true], 'blocks' => [
                    ['type' => 'heading', 'id' => 'fh1', 'props' => ['level' => 3, 'text' => 'تماس با من']],
                    ['type' => 'text', 'id' => 'ft1', 'props' => ['content' => 'برای همکاری و پروژه‌های جدید در ارتباط باشید.']],
                    ['type' => 'social_icons', 'id' => 'fs1', 'props' => ['links' => []]],
                    ['type' => 'divider', 'id' => 'fd1', 'props' => []],
                    ['type' => 'text', 'id' => 'ft2', 'props' => ['content' => '© تمامی حقوق محفوظ است.']],
                ]],
            ],
            'global_styles' => array_merge($defaultStyles, [
                'colors' => [
                    'primary' => '#6366f1',
                    'secondary' => '#818cf8',
                    'accent' => '#a5b4fc',
                    'background' => '#fafafa',
                    'surface' => '#ffffff',
                    'text' => '#1f2937',
                    'text_muted' => '#6b7280',
                ],
                'typography' => [
                    'font_heading' => 'Vazirmatn, Tahoma, sans-serif',
                    'font_body' => 'Vazirmatn, Tahoma, sans-serif',
                    'scale' => 1.25,
                ],
                'dark_mode' => [
                    'enabled' => true,
                    'primary' => '#818cf8',
                    'background' => '#111827',
                    'surface' => '#1f2937',
                    'text' => '#f9fafb',
                ],
            ]),
            'settings' => [
                'template' => 'personal',
                'has_dark_mode' => true,
                'meta' => [
                    'id' => 'personal-website-classic',
                    'version' => '1.0.0',
                    'required_platform_version' => '1.0.0',
                    'supported_modules' => ['blog', 'forms', 'advertisements', 'advanced_search', 'menu', 'social_links'],
                    'changelog' => [
                        '1.0.0' => [
                            'ایجاد تم شخصی مینیمال با هدر، فوتر و تایپوگرافی فارسی.',
                            'بهینه‌سازی برای موبایل و صفحات شخصی (درباره من، نمونه‌کار، تماس).',
                        ],
                    ],
                ],
                'layouts' => [
                    'home' => [
                        // Hero section
                        [
                            'type' => 'hero',
                            'id' => 'phero',
                            'props' => [
                                'title' => 'سلام، من یک فریلنسر حرفه‌ای هستم',
                                'subtitle' => 'طراحی وب، برندینگ و استراتژی محتوا برای کسب‌وکارهای جدی.',
                                'button_text' => 'مشاهده نمونه‌کارها',
                                'button_url' => '#portfolio',
                                'background' => 'primary',
                                'align' => 'center',
                            ],
                        ],
                        // About
                        [
                            'type' => 'section',
                            'id' => 'pabout',
                            'props' => ['padding' => 'lg', 'background' => 'surface', 'container' => true],
                            'blocks' => [
                                ['type' => 'heading', 'id' => 'pab-h', 'props' => ['level' => 2, 'text' => 'درباره من']],
                                ['type' => 'text', 'id' => 'pab-t', 'props' => ['content' => 'چند خط معرفی شخصی، تخصص‌ها و رویکرد کاری شما.']],
                            ],
                        ],
                        // Skills with progress bars
                        [
                            'type' => 'section',
                            'id' => 'pskills',
                            'props' => ['padding' => 'lg', 'background' => 'background', 'container' => true],
                            'blocks' => [
                                ['type' => 'heading', 'id' => 'psk-h', 'props' => ['level' => 2, 'text' => 'مهارت‌ها']],
                                ['type' => 'progress_bar', 'id' => 'psk-1', 'props' => ['label' => 'طراحی UI', 'value' => 90]],
                                ['type' => 'progress_bar', 'id' => 'psk-2', 'props' => ['label' => 'توسعه فرانت‌اند', 'value' => 85]],
                                ['type' => 'progress_bar', 'id' => 'psk-3', 'props' => ['label' => 'برندینگ و استراتژی محتوا', 'value' => 80]],
                            ],
                        ],
                        // Portfolio grid
                        [
                            'type' => 'section',
                            'id' => 'pport',
                            'props' => ['padding' => 'lg', 'background' => 'surface', 'container' => true],
                            'blocks' => [
                                ['type' => 'heading', 'id' => 'pp-h', 'props' => ['level' => 2, 'text' => 'نمونه‌کارها']],
                                ['type' => 'grid', 'id' => 'pp-grid', 'props' => ['columns' => 3, 'gap' => 'md'], 'blocks' => []],
                            ],
                        ],
                        // Testimonials
                        [
                            'type' => 'section',
                            'id' => 'ptest',
                            'props' => ['padding' => 'lg', 'background' => 'background', 'container' => true],
                            'blocks' => [
                                ['type' => 'testimonials', 'id' => 'pt-block', 'props' => ['title' => 'نظرات مشتریان', 'limit' => 3]],
                            ],
                        ],
                        // Blog preview
                        [
                            'type' => 'section',
                            'id' => 'pblog',
                            'props' => ['padding' => 'lg', 'background' => 'surface', 'container' => true],
                            'blocks' => [
                                ['type' => 'blog_posts', 'id' => 'pb-block', 'props' => ['title' => 'آخرین نوشته‌ها', 'limit' => 3]],
                            ],
                        ],
                        // Contact form CTA
                        [
                            'type' => 'section',
                            'id' => 'pcontact',
                            'props' => ['padding' => 'lg', 'background' => 'primary', 'container' => true],
                            'blocks' => [
                                ['type' => 'cta', 'id' => 'pc-cta', 'props' => [
                                    'title' => 'برای شروع همکاری آماده‌اید؟',
                                    'subtitle' => 'فرم تماس سایت شما این بخش را تکمیل می‌کند.',
                                    'button_text' => 'ارتباط با من',
                                    'button_url' => '#contact',
                                    'background' => 'primary',
                                ]],
                            ],
                        ],
                    ],
                ],
            ],
        ];
        $personal = Theme::firstOrCreate(
            ['slug' => 'personal-website'],
            $personalDefaults
        );
        if (!$personal->wasRecentlyCreated) {
            $mergedSettings = array_replace_recursive($personal->settings ?? [], $personalDefaults['settings']);
            $personal->update([
                'header_blocks' => $personalDefaults['header_blocks'],
                'footer_blocks' => $personalDefaults['footer_blocks'],
                'global_styles' => $personalDefaults['global_styles'],
                'settings' => $mergedSettings,
            ]);
        }

        // ─── ۲) تم شرکتی ─────────────────────────────────────────────────
        $corporateDefaults = [
            'name' => 'وب‌سایت شرکتی و سازمانی',
            'description' => 'تم حرفه‌ای برای شرکت‌ها: خدمات، تیم، پروژه‌ها، آمار، FAQ و تماس با نقشه.',
            'type' => 'full',
            'site_id' => null,
            'status' => 'published',
            'published_at' => now(),
            'header_blocks' => [
                ['type' => 'section', 'id' => 'ch1', 'props' => ['padding' => 'md', 'background' => 'primary', 'container' => true], 'blocks' => [
                    ['type' => 'nav_menu', 'id' => 'cn1', 'props' => ['location' => 'header']],
                ]],
            ],
            'footer_blocks' => [
                ['type' => 'section', 'id' => 'cf1', 'props' => ['padding' => 'lg', 'background' => 'text', 'container' => true], 'blocks' => [
                    ['type' => 'columns', 'id' => 'cfc1', 'props' => ['ratios' => '1/3,1/3,1/3', 'gap' => 'lg'], 'blocks' => [
                        ['type' => 'heading', 'id' => 'cfh1', 'props' => ['level' => 4, 'text' => 'درباره شرکت']],
                        ['type' => 'heading', 'id' => 'cfh2', 'props' => ['level' => 4, 'text' => 'لینک‌های سریع']],
                        ['type' => 'heading', 'id' => 'cfh3', 'props' => ['level' => 4, 'text' => 'تماس با ما']],
                    ]],
                    ['type' => 'divider', 'id' => 'cfd1', 'props' => []],
                    ['type' => 'text', 'id' => 'cft1', 'props' => ['content' => '© شرکت. تمامی حقوق محفوظ است.']],
                ]],
            ],
            'global_styles' => array_merge($defaultStyles, [
                'colors' => [
                    'primary' => '#1e40af',
                    'secondary' => '#2563eb',
                    'accent' => '#3b82f6',
                    'background' => '#ffffff',
                    'surface' => '#f8fafc',
                    'text' => '#0f172a',
                    'text_muted' => '#64748b',
                ],
                'typography' => [
                    'font_heading' => 'Vazirmatn, Tahoma, sans-serif',
                    'font_body' => 'Vazirmatn, Tahoma, sans-serif',
                    'scale' => 1.2,
                ],
                'dark_mode' => ['enabled' => false],
            ]),
            'settings' => [
                'template' => 'corporate',
                'trust_focused' => true,
                'meta' => [
                    'id' => 'corporate-business-classic',
                    'version' => '1.0.0',
                    'required_platform_version' => '1.0.0',
                    'supported_modules' => ['blog', 'forms', 'advertisements', 'menu', 'advanced_search'],
                    'changelog' => [
                        '1.0.0' => [
                            'ایجاد تم شرکتی حرفه‌ای با تمرکز بر خدمات، تیم و مطالعات موردی.',
                            'رعایت سلسله‌مراتب تیترها و ساختار سؤالات متداول برای SEO بهتر.',
                        ],
                    ],
                ],
                'layouts' => [
                    'home' => [
                        [
                            'type' => 'hero',
                            'id' => 'chero',
                            'props' => [
                                'title' => 'شرکت شما، شریک رشد کسب‌وکارها',
                                'subtitle' => 'خدمات حرفه‌ای با تمرکز بر اعتماد، شفافیت و نتایج قابل اندازه‌گیری.',
                                'button_text' => 'خدمات ما',
                                'button_url' => '#services',
                                'background' => 'primary',
                                'align' => 'center',
                            ],
                        ],
                    ],
                ],
            ],
        ];
        $corporate = Theme::firstOrCreate(
            ['slug' => 'corporate-business'],
            $corporateDefaults
        );
        if (!$corporate->wasRecentlyCreated) {
            $mergedSettings = array_replace_recursive($corporate->settings ?? [], $corporateDefaults['settings']);
            $corporate->update([
                'header_blocks' => $corporateDefaults['header_blocks'],
                'footer_blocks' => $corporateDefaults['footer_blocks'],
                'global_styles' => $corporateDefaults['global_styles'],
                'settings' => $mergedSettings,
            ]);
        }

        // ─── ۳) تم فروشگاه پیشرفته ───────────────────────────────────────
        $ecommerceDefaults = [
            'name' => 'فروشگاه اینترنتی پیشرفته',
            'description' => 'تم فروشگاهی با اسلایدر تبلیغاتی، گرید محصولات، فیلتر، سبد خرید و چک‌اوت بهینه.',
            'type' => 'full',
            'site_id' => null,
            'status' => 'published',
            'published_at' => now(),
            'header_blocks' => [
                ['type' => 'section', 'id' => 'eh1', 'props' => ['padding' => 'sm', 'background' => 'background', 'container' => true], 'blocks' => [
                    ['type' => 'nav_menu', 'id' => 'en1', 'props' => ['location' => 'header']],
                    ['type' => 'search_bar', 'id' => 'es1', 'props' => ['placeholder' => 'جستجوی محصول...']],
                    ['type' => 'cart_icon', 'id' => 'ec1', 'props' => []],
                ]],
            ],
            'footer_blocks' => [
                ['type' => 'section', 'id' => 'ef1', 'props' => ['padding' => 'lg', 'background' => 'surface', 'container' => true], 'blocks' => [
                    ['type' => 'cta', 'id' => 'efc1', 'props' => [
                        'title' => 'در خبرنامه ما عضو شوید',
                        'subtitle' => 'تخفیف‌ها و اخبار را از دست ندهید.',
                        'button_text' => 'ثبت نام',
                        'button_url' => '#newsletter',
                        'background' => 'primary',
                    ]],
                    ['type' => 'divider', 'id' => 'efd1', 'props' => []],
                    ['type' => 'social_icons', 'id' => 'efs1', 'props' => ['links' => []]],
                    ['type' => 'text', 'id' => 'eft1', 'props' => ['content' => '© فروشگاه. پرداخت امن. ارسال سریع.']],
                ]],
            ],
            'global_styles' => array_merge($defaultStyles, [
                'colors' => [
                    'primary' => '#059669',
                    'secondary' => '#10b981',
                    'accent' => '#34d399',
                    'background' => '#ffffff',
                    'surface' => '#f0fdf4',
                    'text' => '#111827',
                    'text_muted' => '#6b7280',
                ],
                'typography' => [
                    'font_heading' => 'Vazirmatn, Tahoma, sans-serif',
                    'font_body' => 'Vazirmatn, Tahoma, sans-serif',
                    'scale' => 1.25,
                ],
                'dark_mode' => ['enabled' => true, 'primary' => '#10b981', 'background' => '#0f172a', 'surface' => '#1e293b', 'text' => '#f8fafc'],
            ]),
            'settings' => [
                'template' => 'ecommerce',
                'mobile_first' => true,
                'meta' => [
                    'id' => 'ecommerce-store-classic',
                    'version' => '1.0.0',
                    'required_platform_version' => '1.0.0',
                    'supported_modules' => ['shop', 'coupon', 'reviews', 'advertisements', 'forms', 'advanced_search'],
                    'changelog' => [
                        '1.0.0' => [
                            'ایجاد تم فروشگاهی با اسلایدر پروموشن، گرید محصولات و چیدمان صفحه محصول.',
                            'بهینه‌سازی ساختار برای تجربه خرید موبایل و آمادگی برای سئو محصول.',
                        ],
                    ],
                ],
                'layouts' => [
                    'home' => [
                        [
                            'type' => 'hero',
                            'id' => 'eh-hero',
                            'props' => [
                                'title' => 'حراج ویژه آخر هفته',
                                'subtitle' => 'تخفیف‌های محدود برای محبوب‌ترین محصولات.',
                                'button_text' => 'مشاهده پیشنهادها',
                                'button_url' => '#flash-sale',
                                'background' => 'primary',
                                'align' => 'center',
                            ],
                        ],
                        [
                            'type' => 'section',
                            'id' => 'e-products',
                            'props' => ['padding' => 'lg', 'background' => 'surface', 'container' => true],
                            'blocks' => [
                                ['type' => 'heading', 'id' => 'e-products-h', 'props' => ['level' => 2, 'text' => 'محصولات پیشنهادی']],
                                ['type' => 'product_grid', 'id' => 'e-products-grid', 'props' => ['columns' => 4, 'limit' => 8]],
                            ],
                        ],
                        [
                            'type' => 'section',
                            'id' => 'e-news',
                            'props' => ['padding' => 'lg', 'background' => 'background', 'container' => true],
                            'blocks' => [
                                ['type' => 'heading', 'id' => 'e-news-h', 'props' => ['level' => 3, 'text' => 'در خبرنامه ما عضو شوید']],
                                ['type' => 'text', 'id' => 'e-news-t', 'props' => ['content' => 'تخفیف‌ها و اخبار ویژه را از دست ندهید.']],
                            ],
                        ],
                    ],
                ],
            ],
        ];
        $ecommerce = Theme::firstOrCreate(
            ['slug' => 'ecommerce-store'],
            $ecommerceDefaults
        );
        if (!$ecommerce->wasRecentlyCreated) {
            $mergedSettings = array_replace_recursive($ecommerce->settings ?? [], $ecommerceDefaults['settings']);
            $ecommerce->update([
                'header_blocks' => $ecommerceDefaults['header_blocks'],
                'footer_blocks' => $ecommerceDefaults['footer_blocks'],
                'global_styles' => $ecommerceDefaults['global_styles'],
                'settings' => $mergedSettings,
            ]);
        }

        // ─── ۴) تم شخصی: رزومه یک‌صفحه‌ای ────────────────────────────────
        $personalResume = Theme::firstOrCreate(
            ['slug' => 'personal-resume-onepage'],
            [
                'name' => 'رزومه یک‌صفحه‌ای',
                'description' => 'تم یک‌صفحه‌ای برای معرفی رزومه، مهارت‌ها، سوابق و راه‌های ارتباطی.',
                'type' => 'full',
                'site_id' => null,
                'status' => 'published',
                'published_at' => now(),
                'header_blocks' => [
                    ['type' => 'section', 'id' => 'pr-h1', 'props' => ['padding' => 'sm', 'background' => 'surface', 'container' => true], 'blocks' => [
                        ['type' => 'nav_menu', 'id' => 'pr-nav', 'props' => ['location' => 'header']],
                    ]],
                ],
                'footer_blocks' => $personal->footer_blocks,
                'global_styles' => array_merge($defaultStyles, [
                    'colors' => [
                        'primary' => '#7c3aed',
                        'secondary' => '#a855f7',
                        'accent' => '#c4b5fd',
                        'background' => '#f5f3ff',
                        'surface' => '#ffffff',
                        'text' => '#1f2937',
                        'text_muted' => '#6b7280',
                    ],
                ]),
                'settings' => [
                    'template' => 'personal',
                    'has_dark_mode' => false,
                    'meta' => [
                        'id' => 'personal-resume-onepage',
                        'version' => '1.0.0',
                        'required_platform_version' => '1.0.0',
                        'supported_modules' => ['blog', 'forms', 'advanced_search'],
                        'changelog' => [
                            '1.0.0' => [
                                'ایجاد تم رزومه یک‌صفحه‌ای با بخش‌های مهارت، تجربه و تماس.',
                            ],
                        ],
                    ],
                ],
            ]
        );

        // ─── ۵) تم شخصی: پورتفولیو خلاق ─────────────────────────────────
        $personalPortfolio = Theme::firstOrCreate(
            ['slug' => 'personal-creative-portfolio'],
            [
                'name' => 'پورتفولیو خلاق',
                'description' => 'تم خلاقانه برای طراحان و توسعه‌دهندگان با تمرکز بر گالری نمونه‌کار.',
                'type' => 'full',
                'site_id' => null,
                'status' => 'published',
                'published_at' => now(),
                'header_blocks' => [
                    ['type' => 'section', 'id' => 'pc-h1', 'props' => ['padding' => 'sm', 'background' => 'background', 'container' => true], 'blocks' => [
                        ['type' => 'nav_menu', 'id' => 'pc-nav', 'props' => ['location' => 'header']],
                    ]],
                ],
                'footer_blocks' => $personal->footer_blocks,
                'global_styles' => array_merge($defaultStyles, [
                    'colors' => [
                        'primary' => '#0ea5e9',
                        'secondary' => '#22c55e',
                        'accent' => '#eab308',
                        'background' => '#020617',
                        'surface' => '#0f172a',
                        'text' => '#e5e7eb',
                        'text_muted' => '#9ca3af',
                    ],
                    'dark_mode' => ['enabled' => true],
                ]),
                'settings' => [
                    'template' => 'personal',
                    'has_dark_mode' => true,
                    'meta' => [
                        'id' => 'personal-creative-portfolio',
                        'version' => '1.0.0',
                        'required_platform_version' => '1.0.0',
                        'supported_modules' => ['blog', 'gallery', 'forms', 'advanced_search'],
                        'changelog' => [
                            '1.0.0' => [
                                'ایجاد تم پورتفولیو با پس‌زمینه تیره و تمرکز بر گالری.',
                            ],
                        ],
                    ],
                ],
            ]
        );

        // ─── ۶) تم شرکتی: مشاوره ─────────────────────────────────────────
        $corporateConsulting = Theme::firstOrCreate(
            ['slug' => 'corporate-consulting'],
            [
                'name' => 'شرکت مشاوره‌ای',
                'description' => 'تم شرکتی برای مشاوران و آژانس‌ها با تأکید بر خدمات و مطالعات موردی.',
                'type' => 'full',
                'site_id' => null,
                'status' => 'published',
                'published_at' => now(),
                'header_blocks' => $corporate->header_blocks,
                'footer_blocks' => $corporate->footer_blocks,
                'global_styles' => array_merge($defaultStyles, [
                    'colors' => [
                        'primary' => '#0f766e',
                        'secondary' => '#14b8a6',
                        'accent' => '#5eead4',
                        'background' => '#ecfeff',
                        'surface' => '#ffffff',
                        'text' => '#022c22',
                        'text_muted' => '#64748b',
                    ],
                ]),
                'settings' => [
                    'template' => 'corporate',
                    'meta' => [
                        'id' => 'corporate-consulting',
                        'version' => '1.0.0',
                        'required_platform_version' => '1.0.0',
                        'supported_modules' => ['blog', 'forms', 'faq', 'advanced_search'],
                        'changelog' => [
                            '1.0.0' => [
                                'ایجاد تم شرکتی ویژه شرکت‌های مشاوره با تمرکز بر خدمات و سوالات متداول.',
                            ],
                        ],
                    ],
                ],
            ]
        );

        // ─── ۷) تم شرکتی: SaaS B2B ───────────────────────────────────────
        $corporateSaas = Theme::firstOrCreate(
            ['slug' => 'corporate-saas-b2b'],
            [
                'name' => 'شرکت SaaS سازمانی',
                'description' => 'تم مخصوص محصولات SaaS با تأکید بر مزایا، آمار و بخش قیمت‌گذاری.',
                'type' => 'full',
                'site_id' => null,
                'status' => 'published',
                'published_at' => now(),
                'header_blocks' => $corporate->header_blocks,
                'footer_blocks' => $corporate->footer_blocks,
                'global_styles' => array_merge($defaultStyles, [
                    'colors' => [
                        'primary' => '#4f46e5',
                        'secondary' => '#22c55e',
                        'accent' => '#f97316',
                        'background' => '#0b1120',
                        'surface' => '#020617',
                        'text' => '#e5e7eb',
                        'text_muted' => '#9ca3af',
                    ],
                    'dark_mode' => ['enabled' => true],
                ]),
                'settings' => [
                    'template' => 'corporate',
                    'meta' => [
                        'id' => 'corporate-saas-b2b',
                        'version' => '1.0.0',
                        'required_platform_version' => '1.0.0',
                        'supported_modules' => ['blog', 'forms', 'advertisements', 'advanced_search'],
                        'changelog' => [
                            '1.0.0' => [
                                'ایجاد تم شرکتی ویژه محصولات SaaS با تمرکز بر سشن آمار و مزایا.',
                            ],
                        ],
                    ],
                ],
            ]
        );

        // ─── ۸) تم فروشگاهی: مینیمال ─────────────────────────────────────
        $ecommerceMinimal = Theme::firstOrCreate(
            ['slug' => 'ecommerce-minimal'],
            [
                'name' => 'فروشگاه مینیمال',
                'description' => 'تم فروشگاهی روشن و مینیمال برای برندهای مدرن.',
                'type' => 'full',
                'site_id' => null,
                'status' => 'published',
                'published_at' => now(),
                'header_blocks' => $ecommerce->header_blocks,
                'footer_blocks' => $ecommerce->footer_blocks,
                'global_styles' => array_merge($defaultStyles, [
                    'colors' => [
                        'primary' => '#111827',
                        'secondary' => '#6b7280',
                        'accent' => '#f97316',
                        'background' => '#f9fafb',
                        'surface' => '#ffffff',
                        'text' => '#111827',
                        'text_muted' => '#6b7280',
                    ],
                ]),
                'settings' => [
                    'template' => 'ecommerce',
                    'mobile_first' => true,
                    'meta' => [
                        'id' => 'ecommerce-minimal',
                        'version' => '1.0.0',
                        'required_platform_version' => '1.0.0',
                        'supported_modules' => ['shop', 'coupon', 'reviews', 'forms'],
                        'changelog' => [
                            '1.0.0' => [
                                'ایجاد تم فروشگاهی مینیمال با تمرکز بر خوانایی و سادگی مسیر خرید.',
                            ],
                        ],
                    ],
                ],
            ]
        );

        // ─── ۹) تم فروشگاهی: بوتیک ───────────────────────────────────────
        $ecommerceBoutique = Theme::firstOrCreate(
            ['slug' => 'ecommerce-boutique'],
            [
                'name' => 'فروشگاه بوتیک',
                'description' => 'تم مناسب فروشگاه‌های مد و محصولات لوکس.',
                'type' => 'full',
                'site_id' => null,
                'status' => 'published',
                'published_at' => now(),
                'header_blocks' => $ecommerce->header_blocks,
                'footer_blocks' => $ecommerce->footer_blocks,
                'global_styles' => array_merge($defaultStyles, [
                    'colors' => [
                        'primary' => '#be123c',
                        'secondary' => '#db2777',
                        'accent' => '#f59e0b',
                        'background' => '#fff7ed',
                        'surface' => '#ffffff',
                        'text' => '#1f2937',
                        'text_muted' => '#6b7280',
                    ],
                ]),
                'settings' => [
                    'template' => 'ecommerce',
                    'mobile_first' => true,
                    'meta' => [
                        'id' => 'ecommerce-boutique',
                        'version' => '1.0.0',
                        'required_platform_version' => '1.0.0',
                        'supported_modules' => ['shop', 'coupon', 'reviews', 'forms', 'advertisements'],
                        'changelog' => [
                            '1.0.0' => [
                                'ایجاد تم بوتیک مناسب برندهای مد با رنگ‌بندی گرم و اعتمادساز.',
                            ],
                        ],
                    ],
                ],
            ]
        );

        // ─── ۱۰) تم فروشگاهی: دیجیتال ────────────────────────────────────
        $ecommerceDigital = Theme::firstOrCreate(
            ['slug' => 'ecommerce-digital'],
            [
                'name' => 'فروشگاه محصولات دیجیتال',
                'description' => 'تم فروشگاهی برای محصولات دانلودی، لایسنس‌ها و دوره‌ها.',
                'type' => 'full',
                'site_id' => null,
                'status' => 'published',
                'published_at' => now(),
                'header_blocks' => $ecommerce->header_blocks,
                'footer_blocks' => $ecommerce->footer_blocks,
                'global_styles' => array_merge($defaultStyles, [
                    'colors' => [
                        'primary' => '#2563eb',
                        'secondary' => '#0f766e',
                        'accent' => '#f97316',
                        'background' => '#0b1120',
                        'surface' => '#020617',
                        'text' => '#e5e7eb',
                        'text_muted' => '#9ca3af',
                    ],
                    'dark_mode' => ['enabled' => true],
                ]),
                'settings' => [
                    'template' => 'ecommerce',
                    'mobile_first' => true,
                    'meta' => [
                        'id' => 'ecommerce-digital',
                        'version' => '1.0.0',
                        'required_platform_version' => '1.0.0',
                        'supported_modules' => ['shop', 'courses', 'invoice', 'subscription', 'coupon', 'reviews'],
                        'changelog' => [
                            '1.0.0' => [
                                'ایجاد تم فروشگاه محصولات دیجیتال با تمرکز بر دانلود و اشتراک.',
                            ],
                        ],
                    ],
                ],
            ]
        );

        // ─── ۱۱) تم فروشگاهی: حرفه‌ای (سبک دیجی‌کالایی) ──────────────────
        $ecommerceDigistore = Theme::firstOrCreate(
            ['slug' => 'ecommerce-digistore'],
            [
                'name' => 'فروشگاه حرفه‌ای (سبک مارکت‌پلیس)',
                'description' => 'صفحهٔ اصلی شبیه مارکت‌پلیس بزرگ با ردیف‌های متعدد محصولات، بنر تخفیف و بلاگ.',
                'type' => 'full',
                'site_id' => null,
                'status' => 'published',
                'published_at' => now(),
                'header_blocks' => $ecommerce->header_blocks,
                'footer_blocks' => $ecommerce->footer_blocks,
                'global_styles' => array_merge($defaultStyles, [
                    'colors' => [
                        'primary' => '#ef4056',       // قرمز فروشگاهی
                        'secondary' => '#f97316',     // نارنجی برای قیمت و برچسب
                        'accent' => '#22c55e',        // سبز برای وضعیت موجود و تخفیف
                        'background' => '#f3f4f6',
                        'surface' => '#ffffff',
                        'text' => '#111827',
                        'text_muted' => '#6b7280',
                    ],
                    'dark_mode' => [
                        'enabled' => true,
                        'primary' => '#f97373',
                        'background' => '#020617',
                        'surface' => '#020617',
                        'text' => '#e5e7eb',
                    ],
                ]),
                'settings' => [
                    'template' => 'ecommerce',
                    'mobile_first' => true,
                    'meta' => [
                        'id' => 'ecommerce-digistore',
                        'version' => '1.0.0',
                        'required_platform_version' => '1.0.0',
                        'supported_modules' => ['shop', 'coupon', 'reviews', 'advertisements', 'forms', 'advanced_search', 'menu'],
                        'changelog' => [
                            '1.0.0' => [
                                'ایجاد صفحه اصلی چندبخشی برای فروشگاه‌های بزرگ با چند ردیف محصول و بخش مجله.',
                            ],
                        ],
                    ],
                    'layouts' => [
                        'home' => [
                            // Hero اصلی با پیام فروشگاه
                            [
                                'type' => 'hero',
                                'id' => 'dg-hero',
                                'props' => [
                                    'title' => 'همه‌چیز را آنلاین بخرید',
                                    'subtitle' => 'محبوب‌ترین کالاها، تخفیف‌های روزانه، ارسال سریع و پشتیبانی مطمئن.',
                                    'button_text' => 'مشاهده پیشنهادهای امروز',
                                    'button_url' => '#dg-flash-sale',
                                    'background' => 'primary',
                                    'align' => 'center',
                                ],
                            ],
                            // دسته‌بندی‌ها (استاتیک، قابل ویرایش در ادیتور)
                            [
                                'type' => 'section',
                                'id' => 'dg-categories',
                                'props' => ['padding' => 'lg', 'background' => 'surface', 'container' => true],
                                'blocks' => [
                                    ['type' => 'heading', 'id' => 'dg-cat-heading', 'props' => ['level' => 2, 'text' => 'دسته‌بندی‌های محبوب']],
                                    [
                                        'type' => 'grid',
                                        'id' => 'dg-cat-grid',
                                        'props' => ['columns' => 6, 'gap' => 'md'],
                                        'blocks' => [
                                            ['type' => 'text', 'id' => 'dg-cat-1', 'props' => ['content' => 'موبایل و تبلت']],
                                            ['type' => 'text', 'id' => 'dg-cat-2', 'props' => ['content' => 'لپ‌تاپ و کامپیوتر']],
                                            ['type' => 'text', 'id' => 'dg-cat-3', 'props' => ['content' => 'لوازم خانگی برقی']],
                                            ['type' => 'text', 'id' => 'dg-cat-4', 'props' => ['content' => 'مد و پوشاک']],
                                            ['type' => 'text', 'id' => 'dg-cat-5', 'props' => ['content' => 'زیبایی و سلامت']],
                                            ['type' => 'text', 'id' => 'dg-cat-6', 'props' => ['content' => 'کتاب، لوازم تحریر و هنر']],
                                        ],
                                    ],
                                ],
                            ],
                            // ردیف «پیشنهادهای شگفت‌انگیز»
                            [
                                'type' => 'section',
                                'id' => 'dg-flash-sale',
                                'props' => ['padding' => 'lg', 'background' => 'background', 'container' => true],
                                'blocks' => [
                                    ['type' => 'heading', 'id' => 'dg-flash-heading', 'props' => ['level' => 2, 'text' => 'پیشنهادهای شگفت‌انگیز']],
                                    ['type' => 'product_grid', 'id' => 'dg-flash-grid', 'props' => ['columns' => 4, 'limit' => 8]],
                                ],
                            ],
                            // ردیف «جدیدترین کالاها»
                            [
                                'type' => 'section',
                                'id' => 'dg-new-products',
                                'props' => ['padding' => 'lg', 'background' => 'surface', 'container' => true],
                                'blocks' => [
                                    ['type' => 'heading', 'id' => 'dg-new-heading', 'props' => ['level' => 2, 'text' => 'جدیدترین کالاها']],
                                    ['type' => 'product_grid', 'id' => 'dg-new-grid', 'props' => ['columns' => 4, 'limit' => 8]],
                                ],
                            ],
                            // ردیف «محبوب‌ترین در میان خریداران» (دومین گرید محصولات)
                            [
                                'type' => 'section',
                                'id' => 'dg-popular-products',
                                'props' => ['padding' => 'lg', 'background' => 'background', 'container' => true],
                                'blocks' => [
                                    ['type' => 'heading', 'id' => 'dg-popular-heading', 'props' => ['level' => 2, 'text' => 'محبوب‌ترین در میان خریداران']],
                                    ['type' => 'product_grid', 'id' => 'dg-popular-grid', 'props' => ['columns' => 4, 'limit' => 8]],
                                ],
                            ],
                            // بخش مجله / وبلاگ
                            [
                                'type' => 'section',
                                'id' => 'dg-blog-section',
                                'props' => ['padding' => 'lg', 'background' => 'surface', 'container' => true],
                                'blocks' => [
                                    ['type' => 'heading', 'id' => 'dg-blog-heading', 'props' => ['level' => 2, 'text' => 'مجله خواندنی و راهنمای خرید']],
                                    ['type' => 'blog_posts', 'id' => 'dg-blog-posts', 'props' => ['title' => 'آخرین مطالب بلاگ', 'limit' => 3]],
                                ],
                            ],
                            // دعوت به عضویت در خبرنامه
                            [
                                'type' => 'section',
                                'id' => 'dg-newsletter',
                                'props' => ['padding' => 'lg', 'background' => 'primary', 'container' => true],
                                'blocks' => [
                                    ['type' => 'cta', 'id' => 'dg-newsletter-cta', 'props' => [
                                        'title' => 'در خبرنامه ویژهٔ تخفیف‌ها عضو شوید',
                                        'subtitle' => 'کدهای تخفیف دوره‌ای، پیشنهادهای ویژه و اخبار فروشگاه را از دست ندهید.',
                                        'button_text' => 'ثبت ایمیل',
                                        'button_url' => '#newsletter-form',
                                        'background' => 'primary',
                                    ]],
                                ],
                            ],
                        ],
                    ],
                ],
            ]
        );
    }
}
