<?php

namespace Database\Seeders;

use App\Models\Theme;
use App\Services\ThemeBuilder\ThemeBuilderService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

/**
 * Creates exactly three production-ready platform themes via the Theme Generator:
 * 1) Professional Corporate
 * 2) Professional Personal Brand
 * 3) Advanced Professional E-Commerce
 *
 * Versioned (1.0.0), module-aware, RTL-ready, with full header/footer/body layouts.
 */
class ProfessionalThemesSeeder extends Seeder
{
    public function run(): void
    {
        $service = app(ThemeBuilderService::class);
        $defaultStyles = $service->defaultGlobalStyles();

        $this->seedCorporateTheme($service, $defaultStyles);
        $this->seedPersonalBrandTheme($service, $defaultStyles);
        $this->seedEcommerceTheme($service, $defaultStyles);
    }

    protected function seedCorporateTheme(ThemeBuilderService $service, array $defaultStyles): void
    {
        $data = [
            'name' => 'Professional Corporate Theme',
            'slug' => 'professional-corporate',
            'description' => 'تم حرفه‌ای شرکتی: هیرو قوی، گرید خدمات، درباره شرکت، تیم، مطالعات موردی، لوگوی مشتریان، آمار، FAQ، پیش‌نمایش بلاگ، تماس با نقشه و فوتر حرفه‌ای. مناسب کسب‌وکارهای B2B.',
            'type' => Theme::TYPE_FULL,
            'site_id' => null,
            'status' => Theme::STATUS_PUBLISHED,
            'published_at' => now(),
            'header_blocks' => [
                [
                    'type' => 'section',
                    'id' => 'corp-h1',
                    'props' => ['padding' => 'md', 'background' => 'surface', 'container' => true],
                    'blocks' => [
                        ['type' => 'nav_menu', 'id' => 'corp-nav', 'props' => ['location' => 'header']],
                    ],
                ],
            ],
            'footer_blocks' => [
                [
                    'type' => 'section',
                    'id' => 'corp-f1',
                    'props' => ['padding' => 'lg', 'background' => 'text', 'container' => true],
                    'blocks' => [
                        ['type' => 'columns', 'id' => 'corp-fc', 'props' => ['ratios' => '1/3,1/3,1/3', 'gap' => 'lg'], 'blocks' => [
                            ['type' => 'heading', 'id' => 'corp-fh1', 'props' => ['level' => 4, 'text' => 'درباره شرکت']],
                            ['type' => 'heading', 'id' => 'corp-fh2', 'props' => ['level' => 4, 'text' => 'لینک‌های سریع']],
                            ['type' => 'heading', 'id' => 'corp-fh3', 'props' => ['level' => 4, 'text' => 'تماس با ما']],
                        ]],
                        ['type' => 'divider', 'id' => 'corp-fd', 'props' => []],
                        ['type' => 'text', 'id' => 'corp-ft', 'props' => ['content' => '© شرکت. تمامی حقوق محفوظ است.']],
                    ],
                ],
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
                    'id' => 'professional-corporate',
                    'version' => '1.0.0',
                    'release_date' => now()->toDateString(),
                    'required_platform_version' => '1.0.0',
                    'supported_modules' => ['blog', 'forms', 'advertisements', 'menu', 'advanced_search'],
                    'changelog' => [
                        '1.0.0' => [
                            'انتشار اولیه تم حرفه‌ای شرکتی.',
                            'هیرو، گرید خدمات، درباره شرکت، تیم، مطالعات موردی، لوگوی مشتریان، آمار، FAQ، بلاگ و تماس.',
                        ],
                    ],
                ],
                'layouts' => [
                    'home' => [
                        [
                            'type' => 'hero',
                            'id' => 'corp-hero',
                            'props' => [
                                'title' => 'شرکت شما، شریک رشد کسب‌وکارها',
                                'subtitle' => 'خدمات حرفه‌ای با تمرکز بر اعتماد، شفافیت و نتایج قابل اندازه‌گیری.',
                                'button_text' => 'خدمات ما',
                                'button_url' => '#services',
                                'background' => 'primary',
                                'align' => 'center',
                            ],
                        ],
                        [
                            'type' => 'section',
                            'id' => 'corp-services',
                            'props' => ['padding' => 'lg', 'background' => 'surface', 'container' => true],
                            'blocks' => [
                                ['type' => 'heading', 'id' => 'corp-s-h', 'props' => ['level' => 2, 'text' => 'خدمات ما']],
                                ['type' => 'grid', 'id' => 'corp-s-grid', 'props' => ['columns' => 3, 'gap' => 'lg'], 'blocks' => [
                                    ['type' => 'heading', 'id' => 's1', 'props' => ['level' => 3, 'text' => 'مشاوره']],
                                    ['type' => 'heading', 'id' => 's2', 'props' => ['level' => 3, 'text' => 'اجرا']],
                                    ['type' => 'heading', 'id' => 's3', 'props' => ['level' => 3, 'text' => 'پشتیبانی']],
                                ]],
                            ],
                        ],
                        [
                            'type' => 'section',
                            'id' => 'corp-about',
                            'props' => ['padding' => 'lg', 'background' => 'background', 'container' => true],
                            'blocks' => [
                                ['type' => 'heading', 'id' => 'corp-a-h', 'props' => ['level' => 2, 'text' => 'درباره شرکت']],
                                ['type' => 'text', 'id' => 'corp-a-t', 'props' => ['content' => 'معرفی شرکت، ماموریت و چشم‌انداز. این بخش از پنل قابل ویرایش است.']],
                            ],
                        ],
                        [
                            'type' => 'section',
                            'id' => 'corp-team',
                            'props' => ['padding' => 'lg', 'background' => 'surface', 'container' => true],
                            'blocks' => [
                                ['type' => 'team', 'id' => 'corp-team-block', 'props' => ['title' => 'تیم ما', 'limit' => 6]],
                            ],
                        ],
                        [
                            'type' => 'section',
                            'id' => 'corp-cases',
                            'props' => ['padding' => 'lg', 'background' => 'background', 'container' => true],
                            'blocks' => [
                                ['type' => 'heading', 'id' => 'corp-c-h', 'props' => ['level' => 2, 'text' => 'مطالعات موردی']],
                                ['type' => 'grid', 'id' => 'corp-c-grid', 'props' => ['columns' => 2, 'gap' => 'md'], 'blocks' => [
                                    ['type' => 'text', 'id' => 'c1', 'props' => ['content' => 'نمونه پروژه ۱']],
                                    ['type' => 'text', 'id' => 'c2', 'props' => ['content' => 'نمونه پروژه ۲']],
                                ]],
                            ],
                        ],
                        [
                            'type' => 'section',
                            'id' => 'corp-clients',
                            'props' => ['padding' => 'lg', 'background' => 'surface', 'container' => true],
                            'blocks' => [
                                ['type' => 'heading', 'id' => 'corp-cl-h', 'props' => ['level' => 2, 'text' => 'مشتریان ما']],
                                ['type' => 'slider', 'id' => 'corp-cl-slider', 'props' => ['autoplay' => true, 'interval' => 4000, 'slides' => []]],
                            ],
                        ],
                        [
                            'type' => 'section',
                            'id' => 'corp-stats',
                            'props' => ['padding' => 'lg', 'background' => 'primary', 'container' => true],
                            'blocks' => [
                                ['type' => 'grid', 'id' => 'corp-stats-grid', 'props' => ['columns' => 4, 'gap' => 'md'], 'blocks' => [
                                    ['type' => 'counter', 'id' => 'st1', 'props' => ['value' => 150, 'suffix' => '+', 'label' => 'پروژه']],
                                    ['type' => 'counter', 'id' => 'st2', 'props' => ['value' => 50, 'suffix' => '+', 'label' => 'مشتری']],
                                    ['type' => 'counter', 'id' => 'st3', 'props' => ['value' => 12, 'suffix' => '', 'label' => 'سال تجربه']],
                                    ['type' => 'counter', 'id' => 'st4', 'props' => ['value' => 98, 'suffix' => '%', 'label' => 'رضایت']],
                                ]],
                            ],
                        ],
                        [
                            'type' => 'section',
                            'id' => 'corp-faq',
                            'props' => ['padding' => 'lg', 'background' => 'surface', 'container' => true],
                            'blocks' => [
                                ['type' => 'faq', 'id' => 'corp-faq-block', 'props' => ['title' => 'سوالات متداول', 'items' => []]],
                            ],
                        ],
                        [
                            'type' => 'section',
                            'id' => 'corp-blog',
                            'props' => ['padding' => 'lg', 'background' => 'background', 'container' => true],
                            'blocks' => [
                                ['type' => 'blog_posts', 'id' => 'corp-blog-block', 'props' => ['title' => 'آخرین مطالب', 'limit' => 3]],
                            ],
                        ],
                        [
                            'type' => 'section',
                            'id' => 'corp-contact',
                            'props' => ['padding' => 'lg', 'background' => 'surface', 'container' => true],
                            'blocks' => [
                                ['type' => 'heading', 'id' => 'corp-ct-h', 'props' => ['level' => 2, 'text' => 'تماس با ما']],
                                ['type' => 'text', 'id' => 'corp-ct-t', 'props' => ['content' => 'آدرس، نقشه و فرم تماس در این بخش قرار می‌گیرد.']],
                            ],
                        ],
                    ],
                ],
            ],
        ];
        if (Schema::hasColumn('themes', 'source')) {
            $data['source'] = Theme::SOURCE_GENERATOR;
        }

        Theme::updateOrCreate(['slug' => 'professional-corporate'], $data);
    }

    protected function seedPersonalBrandTheme(ThemeBuilderService $service, array $defaultStyles): void
    {
        $data = [
            'name' => 'Professional Personal Brand Theme',
            'slug' => 'professional-personal-brand',
            'description' => 'تم برند شخصی حرفه‌ای: هیرو با تصویر پروفایل، درباره من، مهارت‌ها، پورتفولیو، نظرات، بلاگ، CTA، فرم تماس و لینک‌های اجتماعی. مینیمال و تایپوگرافی‌محور.',
            'type' => Theme::TYPE_FULL,
            'site_id' => null,
            'status' => Theme::STATUS_PUBLISHED,
            'published_at' => now(),
            'header_blocks' => [
                [
                    'type' => 'section',
                    'id' => 'pb-h1',
                    'props' => ['padding' => 'sm', 'background' => 'surface', 'container' => true],
                    'blocks' => [
                        ['type' => 'nav_menu', 'id' => 'pb-nav', 'props' => ['location' => 'header']],
                    ],
                ],
            ],
            'footer_blocks' => [
                [
                    'type' => 'section',
                    'id' => 'pb-f1',
                    'props' => ['padding' => 'lg', 'background' => 'surface', 'container' => true],
                    'blocks' => [
                        ['type' => 'heading', 'id' => 'pb-fh', 'props' => ['level' => 3, 'text' => 'تماس با من']],
                        ['type' => 'text', 'id' => 'pb-ft', 'props' => ['content' => 'برای همکاری و پروژه‌های جدید در ارتباط باشید.']],
                        ['type' => 'social_icons', 'id' => 'pb-fs', 'props' => ['links' => []]],
                        ['type' => 'divider', 'id' => 'pb-fd', 'props' => []],
                        ['type' => 'text', 'id' => 'pb-fc', 'props' => ['content' => '© تمامی حقوق محفوظ است.']],
                    ],
                ],
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
                    'id' => 'professional-personal-brand',
                    'version' => '1.0.0',
                    'release_date' => now()->toDateString(),
                    'required_platform_version' => '1.0.0',
                    'supported_modules' => ['blog', 'forms', 'advertisements', 'advanced_search', 'menu', 'social_links'],
                    'changelog' => [
                        '1.0.0' => [
                            'انتشار اولیه تم برند شخصی حرفه‌ای.',
                            'هیرو با پروفایل، درباره من، مهارت‌ها، پورتفولیو، نظرات و تماس.',
                        ],
                    ],
                ],
                'layouts' => [
                    'home' => [
                        [
                            'type' => 'hero',
                            'id' => 'pb-hero',
                            'props' => [
                                'title' => 'سلام، من یک حرفه‌ای هستم',
                                'subtitle' => 'طراحی وب، برندینگ و استراتژی محتوا برای کسب‌وکارهای جدی.',
                                'button_text' => 'مشاهده نمونه‌کارها',
                                'button_url' => '#portfolio',
                                'background' => 'primary',
                                'align' => 'center',
                            ],
                        ],
                        [
                            'type' => 'section',
                            'id' => 'pb-about',
                            'props' => ['padding' => 'lg', 'background' => 'surface', 'container' => true],
                            'blocks' => [
                                ['type' => 'heading', 'id' => 'pb-a-h', 'props' => ['level' => 2, 'text' => 'درباره من']],
                                ['type' => 'text', 'id' => 'pb-a-t', 'props' => ['content' => 'چند خط معرفی شخصی، تخصص‌ها و رویکرد کاری شما.']],
                            ],
                        ],
                        [
                            'type' => 'section',
                            'id' => 'pb-skills',
                            'props' => ['padding' => 'lg', 'background' => 'background', 'container' => true],
                            'blocks' => [
                                ['type' => 'heading', 'id' => 'pb-sk-h', 'props' => ['level' => 2, 'text' => 'مهارت‌ها و تخصص']],
                                ['type' => 'progress_bar', 'id' => 'pb-sk-1', 'props' => ['label' => 'طراحی UI', 'value' => 90]],
                                ['type' => 'progress_bar', 'id' => 'pb-sk-2', 'props' => ['label' => 'توسعه فرانت', 'value' => 85]],
                                ['type' => 'progress_bar', 'id' => 'pb-sk-3', 'props' => ['label' => 'برندینگ و محتوا', 'value' => 80]],
                            ],
                        ],
                        [
                            'type' => 'section',
                            'id' => 'pb-portfolio',
                            'props' => ['padding' => 'lg', 'background' => 'surface', 'container' => true],
                            'blocks' => [
                                ['type' => 'heading', 'id' => 'pb-p-h', 'props' => ['level' => 2, 'text' => 'نمونه‌کارها']],
                                ['type' => 'grid', 'id' => 'pb-p-grid', 'props' => ['columns' => 3, 'gap' => 'md'], 'blocks' => []],
                            ],
                        ],
                        [
                            'type' => 'section',
                            'id' => 'pb-testimonials',
                            'props' => ['padding' => 'lg', 'background' => 'background', 'container' => true],
                            'blocks' => [
                                ['type' => 'testimonials', 'id' => 'pb-t-block', 'props' => ['title' => 'نظرات مشتریان', 'limit' => 3]],
                            ],
                        ],
                        [
                            'type' => 'section',
                            'id' => 'pb-blog',
                            'props' => ['padding' => 'lg', 'background' => 'surface', 'container' => true],
                            'blocks' => [
                                ['type' => 'blog_posts', 'id' => 'pb-b-block', 'props' => ['title' => 'آخرین نوشته‌ها', 'limit' => 3]],
                            ],
                        ],
                        [
                            'type' => 'section',
                            'id' => 'pb-cta',
                            'props' => ['padding' => 'lg', 'background' => 'primary', 'container' => true],
                            'blocks' => [
                                ['type' => 'cta', 'id' => 'pb-cta-b', 'props' => [
                                    'title' => 'برای شروع همکاری آماده‌اید؟',
                                    'subtitle' => 'فرم تماس سایت شما این بخش را تکمیل می‌کند.',
                                    'button_text' => 'ارتباط با من',
                                    'button_url' => '#contact',
                                    'background' => 'primary',
                                ]],
                            ],
                        ],
                        [
                            'type' => 'section',
                            'id' => 'pb-contact',
                            'props' => ['padding' => 'lg', 'background' => 'surface', 'container' => true],
                            'blocks' => [
                                ['type' => 'heading', 'id' => 'pb-c-h', 'props' => ['level' => 2, 'text' => 'تماس']],
                                ['type' => 'text', 'id' => 'pb-c-t', 'props' => ['content' => 'فرم تماس و لینک‌های اجتماعی.']],
                                ['type' => 'social_icons', 'id' => 'pb-c-s', 'props' => ['links' => []]],
                            ],
                        ],
                    ],
                ],
            ],
        ];
        if (Schema::hasColumn('themes', 'source')) {
            $data['source'] = Theme::SOURCE_GENERATOR;
        }

        Theme::updateOrCreate(['slug' => 'professional-personal-brand'], $data);
    }

    protected function seedEcommerceTheme(ThemeBuilderService $service, array $defaultStyles): void
    {
        $data = [
            'name' => 'Advanced Professional E-Commerce Theme',
            'slug' => 'advanced-professional-ecommerce',
            'description' => 'تم فروشگاهی حرفه‌ای: بنرهای پروموشن، ناوبری دسته‌بندی، محصولات ویژه و گرید، صفحه محصول بهینه، نظرات، اعتماد، سبد و چک‌اوت، خبرنامه و فوتر پیشرفته. مناسب تبدیل و سرعت.',
            'type' => Theme::TYPE_FULL,
            'site_id' => null,
            'status' => Theme::STATUS_PUBLISHED,
            'published_at' => now(),
            'header_blocks' => [
                [
                    'type' => 'section',
                    'id' => 'eco-h1',
                    'props' => ['padding' => 'sm', 'background' => 'background', 'container' => true],
                    'blocks' => [
                        ['type' => 'nav_menu', 'id' => 'eco-nav', 'props' => ['location' => 'header']],
                        ['type' => 'search_bar', 'id' => 'eco-search', 'props' => ['placeholder' => 'جستجوی محصول...']],
                        ['type' => 'cart_icon', 'id' => 'eco-cart', 'props' => []],
                    ],
                ],
            ],
            'footer_blocks' => [
                [
                    'type' => 'section',
                    'id' => 'eco-f1',
                    'props' => ['padding' => 'lg', 'background' => 'surface', 'container' => true],
                    'blocks' => [
                        ['type' => 'cta', 'id' => 'eco-f-cta', 'props' => [
                            'title' => 'در خبرنامه ما عضو شوید',
                            'subtitle' => 'تخفیف‌ها و اخبار را از دست ندهید.',
                            'button_text' => 'ثبت نام',
                            'button_url' => '#newsletter',
                            'background' => 'primary',
                        ]],
                        ['type' => 'divider', 'id' => 'eco-fd', 'props' => []],
                        ['type' => 'columns', 'id' => 'eco-fc', 'props' => ['ratios' => '1/4,1/4,1/4,1/4', 'gap' => 'lg'], 'blocks' => [
                            ['type' => 'heading', 'id' => 'eco-fh1', 'props' => ['level' => 4, 'text' => 'خرید']],
                            ['type' => 'heading', 'id' => 'eco-fh2', 'props' => ['level' => 4, 'text' => 'پشتیبانی']],
                            ['type' => 'heading', 'id' => 'eco-fh3', 'props' => ['level' => 4, 'text' => 'شرکت']],
                            ['type' => 'heading', 'id' => 'eco-fh4', 'props' => ['level' => 4, 'text' => 'تماس']],
                        ]],
                        ['type' => 'divider', 'id' => 'eco-fd2', 'props' => []],
                        ['type' => 'social_icons', 'id' => 'eco-fs', 'props' => ['links' => []]],
                        ['type' => 'text', 'id' => 'eco-ft', 'props' => ['content' => '© فروشگاه. پرداخت امن. ارسال سریع.']],
                    ],
                ],
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
                'dark_mode' => [
                    'enabled' => true,
                    'primary' => '#10b981',
                    'background' => '#0f172a',
                    'surface' => '#1e293b',
                    'text' => '#f8fafc',
                ],
            ]),
            'settings' => [
                'template' => 'ecommerce',
                'mobile_first' => true,
                'meta' => [
                    'id' => 'advanced-professional-ecommerce',
                    'version' => '1.0.0',
                    'release_date' => now()->toDateString(),
                    'required_platform_version' => '1.0.0',
                    'supported_modules' => ['shop', 'coupon', 'reviews', 'advertisements', 'forms', 'advanced_search', 'menu'],
                    'changelog' => [
                        '1.0.0' => [
                            'انتشار اولیه تم فروشگاهی حرفه‌ای.',
                            'بنر پروموشن، دسته‌بندی، گرید محصولات، نظرات، اعتماد، سبد و چک‌اوت، خبرنامه.',
                        ],
                    ],
                ],
                'layouts' => [
                    'home' => [
                        [
                            'type' => 'hero',
                            'id' => 'eco-hero',
                            'props' => [
                                'title' => 'حراج ویژه',
                                'subtitle' => 'تخفیف‌های محدود برای محبوب‌ترین محصولات.',
                                'button_text' => 'مشاهده پیشنهادها',
                                'button_url' => '#featured',
                                'background' => 'primary',
                                'align' => 'center',
                            ],
                        ],
                        [
                            'type' => 'section',
                            'id' => 'eco-promo',
                            'props' => ['padding' => 'md', 'background' => 'accent', 'container' => true],
                            'blocks' => [
                                ['type' => 'promo_banner', 'id' => 'eco-pb', 'props' => ['text' => 'ارسال رایگان برای خریدهای بالای ۵۰۰ هزار تومان', 'link' => '#', 'style' => 'info']],
                            ],
                        ],
                        [
                            'type' => 'section',
                            'id' => 'eco-categories',
                            'props' => ['padding' => 'lg', 'background' => 'surface', 'container' => true],
                            'blocks' => [
                                ['type' => 'heading', 'id' => 'eco-cat-h', 'props' => ['level' => 2, 'text' => 'دسته‌بندی‌ها']],
                                ['type' => 'grid', 'id' => 'eco-cat-grid', 'props' => ['columns' => 4, 'gap' => 'md'], 'blocks' => [
                                    ['type' => 'text', 'id' => 'ec1', 'props' => ['content' => 'دسته ۱']],
                                    ['type' => 'text', 'id' => 'ec2', 'props' => ['content' => 'دسته ۲']],
                                    ['type' => 'text', 'id' => 'ec3', 'props' => ['content' => 'دسته ۳']],
                                    ['type' => 'text', 'id' => 'ec4', 'props' => ['content' => 'دسته ۴']],
                                ]],
                            ],
                        ],
                        [
                            'type' => 'section',
                            'id' => 'eco-featured',
                            'props' => ['padding' => 'lg', 'background' => 'background', 'container' => true],
                            'blocks' => [
                                ['type' => 'heading', 'id' => 'eco-fe-h', 'props' => ['level' => 2, 'text' => 'محصولات پیشنهادی']],
                                ['type' => 'product_grid', 'id' => 'eco-fe-grid', 'props' => ['columns' => 4, 'limit' => 8]],
                            ],
                        ],
                        [
                            'type' => 'section',
                            'id' => 'eco-trust',
                            'props' => ['padding' => 'lg', 'background' => 'surface', 'container' => true],
                            'blocks' => [
                                ['type' => 'grid', 'id' => 'eco-trust-grid', 'props' => ['columns' => 4, 'gap' => 'md'], 'blocks' => [
                                    ['type' => 'text', 'id' => 'et1', 'props' => ['content' => 'پرداخت امن']],
                                    ['type' => 'text', 'id' => 'et2', 'props' => ['content' => 'ارسال سریع']],
                                    ['type' => 'text', 'id' => 'et3', 'props' => ['content' => 'ضمانت بازگشت']],
                                    ['type' => 'text', 'id' => 'et4', 'props' => ['content' => 'پشتیبانی ۲۴/۷']],
                                ]],
                            ],
                        ],
                        [
                            'type' => 'section',
                            'id' => 'eco-reviews',
                            'props' => ['padding' => 'lg', 'background' => 'background', 'container' => true],
                            'blocks' => [
                                ['type' => 'testimonials', 'id' => 'eco-rev-block', 'props' => ['title' => 'نظرات خریداران', 'limit' => 3]],
                            ],
                        ],
                        [
                            'type' => 'section',
                            'id' => 'eco-newsletter',
                            'props' => ['padding' => 'lg', 'background' => 'primary', 'container' => true],
                            'blocks' => [
                                ['type' => 'cta', 'id' => 'eco-nl-cta', 'props' => [
                                    'title' => 'در خبرنامه عضو شوید',
                                    'subtitle' => 'کدهای تخفیف و پیشنهادهای ویژه.',
                                    'button_text' => 'ثبت ایمیل',
                                    'button_url' => '#newsletter-form',
                                    'background' => 'primary',
                                ]],
                            ],
                        ],
                    ],
                ],
            ],
        ];
        if (Schema::hasColumn('themes', 'source')) {
            $data['source'] = Theme::SOURCE_GENERATOR;
        }

        Theme::updateOrCreate(['slug' => 'advanced-professional-ecommerce'], $data);
    }
}
