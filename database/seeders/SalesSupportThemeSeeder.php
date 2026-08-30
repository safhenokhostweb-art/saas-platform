<?php

namespace Database\Seeders;

use App\Models\Theme;
use App\Models\ThemeMarketplace;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * قالب حرفه‌ای "فروش و پشتیبانی" — canvas-editor compatible.
 * ۷ صفحه: خانه، فروشگاه، آموزشگاه، پشتیبانی، پلن‌ها، درباره ما، وبلاگ.
 * هدر + فوتر کامل. کاملاً قابل ویرایش با drag-and-drop canvas editor.
 */
class SalesSupportThemeSeeder extends Seeder
{
    public function run(): void
    {
        $slug = 'sales-support-pro';

        $globalStyles = [
            'primary_color'    => '#1e40af',
            'secondary_color'  => '#3b82f6',
            'background_color' => '#f8fafc',
            'font_family'      => 'Vazirmatn, Tahoma, sans-serif',
            'colors' => [
                'primary'    => '#1e40af',
                'secondary'  => '#3b82f6',
                'accent'     => '#f59e0b',
                'background' => '#f8fafc',
                'surface'    => '#ffffff',
                'text'       => '#0f172a',
                'text_muted' => '#64748b',
            ],
            'typography' => [
                'font_heading' => 'Vazirmatn, Tahoma, sans-serif',
                'font_body'    => 'Vazirmatn, Tahoma, sans-serif',
                'scale'        => 1.25,
            ],
            'dark_mode' => [
                'enabled'    => false,
                'primary'    => '#3b82f6',
                'background' => '#0f172a',
                'surface'    => '#1e293b',
                'text'       => '#f1f5f9',
            ],
        ];

        $headerBlocks = [
            [
                'type' => 'section',
                'id'   => 'hdr-main',
                'props' => ['padding' => 'sm', 'background' => 'surface', 'container' => true],
                'blocks' => [
                    ['type' => 'nav_menu',         'id' => 'hdr-nav',  'props' => ['location' => 'header']],
                    ['type' => 'module:shop_cart_button', 'id' => 'hdr-cart', 'props' => ['style' => 'icon',   'label' => 'سبد خرید']],
                    ['type' => 'login_register',   'id' => 'hdr-auth', 'props' => []],
                ],
            ],
        ];

        $footerBlocks = [
            [
                'type' => 'section',
                'id'   => 'ftr-top',
                'props' => ['padding' => 'lg', 'background' => 'text', 'container' => true],
                'blocks' => [
                    [
                        'type' => 'columns',
                        'id'   => 'ftr-cols',
                        'props' => ['ratios' => '1/3,1/3,1/3', 'gap' => 'lg'],
                        'blocks' => [
                            ['type' => 'heading', 'id' => 'ftr-brand-h', 'props' => ['level' => 4, 'text' => 'درباره ما']],
                            ['type' => 'heading', 'id' => 'ftr-links-h', 'props' => ['level' => 4, 'text' => 'دسترسی سریع']],
                            ['type' => 'heading', 'id' => 'ftr-contact-h', 'props' => ['level' => 4, 'text' => 'تماس با ما']],
                        ],
                    ],
                    ['type' => 'module:social_links_bar', 'id' => 'ftr-social', 'props' => ['style' => 'horizontal', 'color' => 'white', 'size' => 'md']],
                    ['type' => 'divider', 'id' => 'ftr-div', 'props' => []],
                    ['type' => 'text',    'id' => 'ftr-copy', 'props' => ['content' => '© تمامی حقوق محفوظ است. پرداخت امن با درگاه معتبر.']],
                ],
            ],
        ];

        $pages = [
            // ─── صفحه اصلی ────────────────────────────────────────────────
            'home' => [
                'title' => 'صفحه اصلی',
                'slug'  => '',
                'blocks' => [
                    // اعلان بالا
                    [
                        'type'  => 'module:notifications_bar',
                        'id'    => 'home-notif',
                        'props' => ['style' => 'top-bar', 'show_dismiss' => true],
                    ],
                    // هیرو اصلی
                    [
                        'type'  => 'hero',
                        'id'    => 'home-hero',
                        'props' => [
                            'title'            => 'فروش هوشمند، پشتیبانی بی‌نقص',
                            'subtitle'         => 'محصولات باکیفیت، آموزش تخصصی، پشتیبانی ۲۴ ساعته — همه در یک پلتفرم.',
                            'button_text'      => 'مشاهده محصولات',
                            'button_url'       => '/products',
                            'background'       => 'primary',
                            'background_image' => '',
                            'align'            => 'center',
                        ],
                    ],
                    // آمار اعتمادساز
                    [
                        'type'  => 'section',
                        'id'    => 'home-stats',
                        'props' => ['padding' => 'lg', 'background' => 'surface', 'container' => true],
                        'blocks' => [
                            [
                                'type'  => 'grid',
                                'id'    => 'home-stats-grid',
                                'props' => ['columns' => 4, 'gap' => 'lg'],
                                'blocks' => [
                                    ['type' => 'counter', 'id' => 'stat-1', 'props' => ['value' => 5000,  'suffix' => '+', 'label' => 'مشتری راضی']],
                                    ['type' => 'counter', 'id' => 'stat-2', 'props' => ['value' => 1200,  'suffix' => '+', 'label' => 'محصول متنوع']],
                                    ['type' => 'counter', 'id' => 'stat-3', 'props' => ['value' => 98,    'suffix' => '%', 'label' => 'رضایت مشتریان']],
                                    ['type' => 'counter', 'id' => 'stat-4', 'props' => ['value' => 24,    'suffix' => '/۷', 'label' => 'پشتیبانی آنلاین']],
                                ],
                            ],
                        ],
                    ],
                    // پروموشن شمارش معکوس
                    [
                        'type'  => 'module:shop_countdown',
                        'id'    => 'home-countdown',
                        'props' => [
                            'promo_text' => '🔥 پیشنهاد ویژه هفتگی — فرصت محدود!',
                            'end_date'   => '',
                            'bg_color'   => '#dc2626',
                        ],
                    ],
                    // محصولات پیشنهادی
                    [
                        'type'  => 'section',
                        'id'    => 'home-products',
                        'props' => ['padding' => 'lg', 'background' => 'background', 'container' => true],
                        'blocks' => [
                            ['type' => 'heading', 'id' => 'home-prod-h', 'props' => ['level' => 2, 'text' => 'محصولات پیشنهادی']],
                            [
                                'type'  => 'module:shop_product_grid',
                                'id'    => 'home-prod-grid',
                                'props' => [
                                    'title'         => 'محصولات ویژه',
                                    'limit'         => 8,
                                    'columns'       => '4',
                                    'source'        => 'featured',
                                    'category_slug' => '',
                                ],
                            ],
                            [
                                'type'  => 'button',
                                'id'    => 'home-prod-btn',
                                'props' => ['text' => 'مشاهده همه محصولات', 'url' => '/products', 'style' => 'primary'],
                            ],
                        ],
                    ],
                    // بخش آموزشی
                    [
                        'type'  => 'section',
                        'id'    => 'home-courses',
                        'props' => ['padding' => 'lg', 'background' => 'surface', 'container' => true],
                        'blocks' => [
                            ['type' => 'heading', 'id' => 'home-courses-h', 'props' => ['level' => 2, 'text' => 'دوره‌های آموزشی']],
                            ['type' => 'text',    'id' => 'home-courses-t', 'props' => ['content' => 'با دوره‌های تخصصی ما مهارت‌های خود را ارتقا دهید.']],
                            [
                                'type'  => 'module:courses_grid',
                                'id'    => 'home-courses-grid',
                                'props' => [
                                    'title'           => 'دوره‌های پرطرفدار',
                                    'limit'           => 3,
                                    'columns'         => '3',
                                    'layout'          => 'grid',
                                    'show_price'      => true,
                                    'show_instructor' => true,
                                ],
                            ],
                        ],
                    ],
                    // بخش اشتراک / تعرفه
                    [
                        'type'  => 'section',
                        'id'    => 'home-pricing',
                        'props' => ['padding' => 'lg', 'background' => 'background', 'container' => true],
                        'blocks' => [
                            [
                                'type'  => 'module:pricing_table',
                                'id'    => 'home-pricing-table',
                                'props' => [
                                    'title'               => 'پلن‌های اشتراک',
                                    'subtitle'            => 'بهترین پلن را برای کسب‌وکار خود انتخاب کنید',
                                    'highlight_plan'      => '',
                                    'show_annual_toggle'  => false,
                                ],
                            ],
                        ],
                    ],
                    // نظرات مشتریان
                    [
                        'type'  => 'section',
                        'id'    => 'home-reviews',
                        'props' => ['padding' => 'lg', 'background' => 'surface', 'container' => true],
                        'blocks' => [
                            [
                                'type'  => 'module:reviews_grid',
                                'id'    => 'home-reviews-grid',
                                'props' => [
                                    'title'       => 'نظرات مشتریان ما',
                                    'limit'       => 6,
                                    'style'       => 'cards',
                                    'show_avatar' => true,
                                ],
                            ],
                        ],
                    ],
                    // وبلاگ
                    [
                        'type'  => 'section',
                        'id'    => 'home-blog',
                        'props' => ['padding' => 'lg', 'background' => 'background', 'container' => true],
                        'blocks' => [
                            [
                                'type'  => 'module:blog_posts',
                                'id'    => 'home-blog-posts',
                                'props' => [
                                    'title'        => 'آخرین مطالب',
                                    'limit'        => 3,
                                    'layout'       => 'grid',
                                    'show_excerpt' => true,
                                    'show_date'    => true,
                                    'category_slug'=> '',
                                ],
                            ],
                        ],
                    ],
                    // ویجت معرفی به دوستان
                    [
                        'type'  => 'module:referral_widget',
                        'id'    => 'home-referral',
                        'props' => [
                            'title'     => 'دوستانت را دعوت کن، جایزه بگیر',
                            'subtitle'  => 'با معرفی هر دوست، اعتبار هدیه دریافت کنید',
                            'style'     => 'banner',
                            'show_link' => true,
                        ],
                    ],
                    // CTA پایانی
                    [
                        'type'  => 'cta',
                        'id'    => 'home-cta',
                        'props' => [
                            'title'       => 'آماده شروع هستید؟',
                            'subtitle'    => 'همین الان ثبت‌نام کنید و از پشتیبانی رایگان بهره‌مند شوید.',
                            'button_text' => 'شروع رایگان',
                            'button_url'  => '/register',
                            'background'  => 'primary',
                        ],
                    ],
                    // دکمه پشتیبانی شناور
                    [
                        'type'  => 'module:support_button',
                        'id'    => 'home-support-btn',
                        'props' => [
                            'label'    => 'پشتیبانی آنلاین',
                            'style'    => 'floating',
                            'position' => 'bottom-right',
                            'bg_color' => '#1e40af',
                        ],
                    ],
                ],
            ],

            // ─── فروشگاه ─────────────────────────────────────────────────
            'products' => [
                'title' => 'فروشگاه',
                'slug'  => 'products',
                'blocks' => [
                    [
                        'type'  => 'hero',
                        'id'    => 'shop-hero',
                        'props' => [
                            'title'       => 'فروشگاه آنلاین',
                            'subtitle'    => 'بهترین محصولات با ضمانت اصالت و ارسال سریع',
                            'button_text' => '',
                            'button_url'  => '',
                            'background'  => 'secondary',
                            'align'       => 'center',
                        ],
                    ],
                    [
                        'type'  => 'module:shop_search_box',
                        'id'    => 'shop-search',
                        'props' => ['placeholder' => 'جستجو در محصولات...', 'style' => 'bar'],
                    ],
                    [
                        'type'  => 'section',
                        'id'    => 'shop-categories',
                        'props' => ['padding' => 'lg', 'background' => 'surface', 'container' => true],
                        'blocks' => [
                            ['type' => 'heading', 'id' => 'shop-cat-h', 'props' => ['level' => 2, 'text' => 'دسته‌بندی‌ها']],
                            [
                                'type'  => 'module:shop_categories',
                                'id'    => 'shop-cat-grid',
                                'props' => ['title' => 'دسته‌بندی‌ها', 'style' => 'grid', 'limit' => 8, 'show_count' => true],
                            ],
                        ],
                    ],
                    [
                        'type'  => 'section',
                        'id'    => 'shop-new',
                        'props' => ['padding' => 'lg', 'background' => 'background', 'container' => true],
                        'blocks' => [
                            ['type' => 'heading', 'id' => 'shop-new-h', 'props' => ['level' => 2, 'text' => 'جدیدترین محصولات']],
                            [
                                'type'  => 'module:shop_product_grid',
                                'id'    => 'shop-new-grid',
                                'props' => ['title' => 'جدیدترین', 'limit' => 12, 'columns' => '4', 'source' => 'recent', 'category_slug' => ''],
                            ],
                        ],
                    ],
                    [
                        'type'  => 'section',
                        'id'    => 'shop-bestsellers',
                        'props' => ['padding' => 'lg', 'background' => 'surface', 'container' => true],
                        'blocks' => [
                            ['type' => 'heading', 'id' => 'shop-best-h', 'props' => ['level' => 2, 'text' => 'پرفروش‌ترین محصولات']],
                            [
                                'type'  => 'module:shop_product_grid',
                                'id'    => 'shop-best-grid',
                                'props' => ['title' => 'پرفروش', 'limit' => 8, 'columns' => '4', 'source' => 'bestselling', 'category_slug' => ''],
                            ],
                        ],
                    ],
                    [
                        'type'  => 'module:shop_countdown',
                        'id'    => 'shop-countdown',
                        'props' => ['promo_text' => '⚡ پیشنهاد ویژه — فرصت محدود!', 'end_date' => '', 'bg_color' => '#dc2626'],
                    ],
                    [
                        'type'  => 'module:support_button',
                        'id'    => 'shop-support',
                        'props' => ['label' => 'راهنمای خرید', 'style' => 'floating', 'position' => 'bottom-right', 'bg_color' => '#1e40af'],
                    ],
                ],
            ],

            // ─── آموزشگاه ─────────────────────────────────────────────────
            'learning' => [
                'title' => 'آموزشگاه',
                'slug'  => 'learning',
                'blocks' => [
                    [
                        'type'  => 'hero',
                        'id'    => 'edu-hero',
                        'props' => [
                            'title'       => 'آموزشگاه تخصصی',
                            'subtitle'    => 'دوره‌های جامع برای ارتقای مهارت‌های حرفه‌ای شما',
                            'button_text' => 'مشاهده همه دوره‌ها',
                            'button_url'  => '/learning/courses',
                            'background'  => 'primary',
                            'align'       => 'center',
                        ],
                    ],
                    [
                        'type'  => 'section',
                        'id'    => 'edu-stats',
                        'props' => ['padding' => 'md', 'background' => 'surface', 'container' => true],
                        'blocks' => [
                            [
                                'type'  => 'grid',
                                'id'    => 'edu-stats-grid',
                                'props' => ['columns' => 3, 'gap' => 'lg'],
                                'blocks' => [
                                    ['type' => 'counter', 'id' => 'edu-stat-1', 'props' => ['value' => 50,    'suffix' => '+', 'label' => 'دوره آموزشی']],
                                    ['type' => 'counter', 'id' => 'edu-stat-2', 'props' => ['value' => 3000,  'suffix' => '+', 'label' => 'دانشجو فعال']],
                                    ['type' => 'counter', 'id' => 'edu-stat-3', 'props' => ['value' => 95,    'suffix' => '%', 'label' => 'رضایت دانشجویان']],
                                ],
                            ],
                        ],
                    ],
                    [
                        'type'  => 'section',
                        'id'    => 'edu-courses',
                        'props' => ['padding' => 'lg', 'background' => 'background', 'container' => true],
                        'blocks' => [
                            ['type' => 'heading', 'id' => 'edu-courses-h', 'props' => ['level' => 2, 'text' => 'دوره‌های فعال']],
                            [
                                'type'  => 'module:courses_grid',
                                'id'    => 'edu-courses-grid',
                                'props' => ['title' => 'دوره‌های آموزشی', 'limit' => 9, 'columns' => '3', 'layout' => 'grid', 'show_price' => true, 'show_instructor' => true],
                            ],
                        ],
                    ],
                    [
                        'type'  => 'module:course_enroll_cta',
                        'id'    => 'edu-enroll-cta',
                        'props' => [
                            'title'       => 'همین الان شروع کن!',
                            'subtitle'    => 'به هزاران دانشجو بپیوند و آینده‌ات را بساز',
                            'button_text' => 'ثبت‌نام رایگان',
                            'bg_color'    => '#1e40af',
                        ],
                    ],
                    [
                        'type'  => 'section',
                        'id'    => 'edu-articles',
                        'props' => ['padding' => 'lg', 'background' => 'surface', 'container' => true],
                        'blocks' => [
                            ['type' => 'heading', 'id' => 'edu-articles-h', 'props' => ['level' => 2, 'text' => 'مقالات آموزشی']],
                            [
                                'type'  => 'module:edu_article_grid',
                                'id'    => 'edu-articles-grid',
                                'props' => ['title' => 'مقالات آموزشی', 'limit' => 6, 'layout' => 'grid'],
                            ],
                        ],
                    ],
                    [
                        'type'  => 'module:my_learning_link',
                        'id'    => 'edu-mylearning',
                        'props' => ['label' => 'دوره‌های من', 'style' => 'button'],
                    ],
                ],
            ],

            // ─── پشتیبانی ─────────────────────────────────────────────────
            'support' => [
                'title' => 'پشتیبانی',
                'slug'  => 'support',
                'blocks' => [
                    [
                        'type'  => 'hero',
                        'id'    => 'sup-hero',
                        'props' => [
                            'title'       => 'چطور می‌توانیم کمک کنیم؟',
                            'subtitle'    => 'تیم پشتیبانی ما ۲۴ ساعته آماده پاسخگویی به سوالات شما است',
                            'button_text' => 'ارسال تیکت',
                            'button_url'  => '/tickets/create',
                            'background'  => 'primary',
                            'align'       => 'center',
                        ],
                    ],
                    // کانال‌های پشتیبانی
                    [
                        'type'  => 'section',
                        'id'    => 'sup-channels',
                        'props' => ['padding' => 'lg', 'background' => 'surface', 'container' => true],
                        'blocks' => [
                            ['type' => 'heading', 'id' => 'sup-ch-h', 'props' => ['level' => 2, 'text' => 'کانال‌های ارتباطی']],
                            [
                                'type'  => 'grid',
                                'id'    => 'sup-ch-grid',
                                'props' => ['columns' => 3, 'gap' => 'lg'],
                                'blocks' => [
                                    ['type' => 'heading', 'id' => 'sup-ch-1', 'props' => ['level' => 4, 'text' => '🎫 سیستم تیکت']],
                                    ['type' => 'heading', 'id' => 'sup-ch-2', 'props' => ['level' => 4, 'text' => '📞 تماس تلفنی']],
                                    ['type' => 'heading', 'id' => 'sup-ch-3', 'props' => ['level' => 4, 'text' => '💬 چت آنلاین']],
                                ],
                            ],
                        ],
                    ],
                    // فرم تماس سریع
                    [
                        'type'  => 'section',
                        'id'    => 'sup-contact',
                        'props' => ['padding' => 'lg', 'background' => 'background', 'container' => true],
                        'blocks' => [
                            [
                                'type'  => 'module:contact_quick',
                                'id'    => 'sup-contact-form',
                                'props' => [
                                    'title'        => 'تماس با ما',
                                    'show_phone'   => true,
                                    'show_address' => true,
                                    'show_map'     => false,
                                ],
                            ],
                        ],
                    ],
                    // سوالات متداول
                    [
                        'type'  => 'section',
                        'id'    => 'sup-faq',
                        'props' => ['padding' => 'lg', 'background' => 'surface', 'container' => true],
                        'blocks' => [
                            [
                                'type'  => 'module:faq_dynamic',
                                'id'    => 'sup-faq-block',
                                'props' => ['title' => 'سوالات متداول', 'limit' => 10, 'style' => 'accordion'],
                            ],
                        ],
                    ],
                    [
                        'type'  => 'module:support_button',
                        'id'    => 'sup-float-btn',
                        'props' => ['label' => 'ارسال تیکت', 'style' => 'floating', 'position' => 'bottom-right', 'bg_color' => '#1e40af'],
                    ],
                ],
            ],

            // ─── پلن‌ها و قیمت‌گذاری ────────────────────────────────────
            'plans' => [
                'title' => 'پلن‌ها و قیمت‌گذاری',
                'slug'  => 'plans',
                'blocks' => [
                    [
                        'type'  => 'hero',
                        'id'    => 'plans-hero',
                        'props' => [
                            'title'       => 'پلن مناسب خود را انتخاب کنید',
                            'subtitle'    => 'بدون هزینه پنهان — تمام امکانات در یک اشتراک',
                            'button_text' => '',
                            'button_url'  => '',
                            'background'  => 'primary',
                            'align'       => 'center',
                        ],
                    ],
                    [
                        'type'  => 'section',
                        'id'    => 'plans-table',
                        'props' => ['padding' => 'lg', 'background' => 'background', 'container' => true],
                        'blocks' => [
                            [
                                'type'  => 'module:pricing_table',
                                'id'    => 'plans-pricing',
                                'props' => [
                                    'title'              => 'پلن‌های اشتراک',
                                    'subtitle'           => 'بهترین پلن برای کسب‌وکار شما',
                                    'highlight_plan'     => '',
                                    'show_annual_toggle' => false,
                                ],
                            ],
                        ],
                    ],
                    // آمار مشترکین
                    [
                        'type'  => 'section',
                        'id'    => 'plans-stats',
                        'props' => ['padding' => 'md', 'background' => 'surface', 'container' => true],
                        'blocks' => [
                            [
                                'type'  => 'grid',
                                'id'    => 'plans-stats-grid',
                                'props' => ['columns' => 3, 'gap' => 'lg'],
                                'blocks' => [
                                    ['type' => 'module:subscriber_stats', 'id' => 'plans-sub-stat', 'props' => ['label' => 'مشترک فعال', 'show_count' => true, 'style' => 'badge']],
                                    ['type' => 'counter', 'id' => 'plans-ctr-2', 'props' => ['value' => 99, 'suffix' => '%', 'label' => 'آپتایم سرویس']],
                                    ['type' => 'counter', 'id' => 'plans-ctr-3', 'props' => ['value' => 7,  'suffix' => ' روز', 'label' => 'ضمانت بازگشت وجه']],
                                ],
                            ],
                        ],
                    ],
                    [
                        'type'  => 'module:plan_cta',
                        'id'    => 'plans-cta',
                        'props' => [
                            'title'       => 'همین الان شروع کنید',
                            'subtitle'    => 'اشتراک رایگان، بدون نیاز به کارت بانکی',
                            'button_text' => 'ثبت‌نام رایگان',
                            'bg_color'    => '#1e40af',
                        ],
                    ],
                    [
                        'type'  => 'section',
                        'id'    => 'plans-faq',
                        'props' => ['padding' => 'lg', 'background' => 'background', 'container' => true],
                        'blocks' => [
                            [
                                'type'  => 'module:faq_dynamic',
                                'id'    => 'plans-faq-block',
                                'props' => ['title' => 'سوالات متداول درباره پلن‌ها', 'limit' => 8, 'style' => 'accordion'],
                            ],
                        ],
                    ],
                ],
            ],

            // ─── درباره ما ───────────────────────────────────────────────
            'about' => [
                'title' => 'درباره ما',
                'slug'  => 'about',
                'blocks' => [
                    [
                        'type'  => 'hero',
                        'id'    => 'about-hero',
                        'props' => [
                            'title'       => 'داستان ما',
                            'subtitle'    => 'تعهد به کیفیت، شفافیت و رضایت مشتریان',
                            'button_text' => 'تماس با ما',
                            'button_url'  => '/support',
                            'background'  => 'primary',
                            'align'       => 'center',
                        ],
                    ],
                    [
                        'type'  => 'section',
                        'id'    => 'about-mission',
                        'props' => ['padding' => 'lg', 'background' => 'surface', 'container' => true],
                        'blocks' => [
                            ['type' => 'heading', 'id' => 'about-m-h', 'props' => ['level' => 2, 'text' => 'ماموریت ما']],
                            ['type' => 'text',    'id' => 'about-m-t', 'props' => ['content' => 'ما با هدف ایجاد پلتفرمی یکپارچه برای فروش، آموزش و پشتیبانی فعالیت می‌کنیم. مشتریان ما می‌توانند از بهترین محصولات، آموزش‌های تخصصی و پشتیبانی ۲۴ ساعته بهره‌مند شوند.']],
                        ],
                    ],
                    // آمار شرکت
                    [
                        'type'  => 'section',
                        'id'    => 'about-stats',
                        'props' => ['padding' => 'lg', 'background' => 'background', 'container' => true],
                        'blocks' => [
                            ['type' => 'heading', 'id' => 'about-stats-h', 'props' => ['level' => 2, 'text' => 'ما در اعداد']],
                            [
                                'type'  => 'grid',
                                'id'    => 'about-stats-grid',
                                'props' => ['columns' => 4, 'gap' => 'lg'],
                                'blocks' => [
                                    ['type' => 'counter', 'id' => 'about-stat-1', 'props' => ['value' => 5,    'suffix' => ' سال', 'label' => 'سابقه فعالیت']],
                                    ['type' => 'counter', 'id' => 'about-stat-2', 'props' => ['value' => 5000, 'suffix' => '+',    'label' => 'مشتری راضی']],
                                    ['type' => 'counter', 'id' => 'about-stat-3', 'props' => ['value' => 50,   'suffix' => '+',    'label' => 'متخصص در تیم']],
                                    ['type' => 'counter', 'id' => 'about-stat-4', 'props' => ['value' => 1200, 'suffix' => '+',    'label' => 'محصول متنوع']],
                                ],
                            ],
                        ],
                    ],
                    // مزایا
                    [
                        'type'  => 'section',
                        'id'    => 'about-values',
                        'props' => ['padding' => 'lg', 'background' => 'surface', 'container' => true],
                        'blocks' => [
                            ['type' => 'heading', 'id' => 'about-val-h', 'props' => ['level' => 2, 'text' => 'چرا ما؟']],
                            [
                                'type'  => 'grid',
                                'id'    => 'about-val-grid',
                                'props' => ['columns' => 3, 'gap' => 'lg'],
                                'blocks' => [
                                    ['type' => 'heading', 'id' => 'val-1-h', 'props' => ['level' => 4, 'text' => '✅ محصولات اصل']],
                                    ['type' => 'heading', 'id' => 'val-2-h', 'props' => ['level' => 4, 'text' => '🚀 ارسال سریع']],
                                    ['type' => 'heading', 'id' => 'val-3-h', 'props' => ['level' => 4, 'text' => '🛡️ ضمانت بازگشت وجه']],
                                    ['type' => 'text',    'id' => 'val-1-t', 'props' => ['content' => 'تمام محصولات با ضمانت اصالت عرضه می‌شوند.']],
                                    ['type' => 'text',    'id' => 'val-2-t', 'props' => ['content' => 'ارسال در کمترین زمان ممکن به سراسر کشور.']],
                                    ['type' => 'text',    'id' => 'val-3-t', 'props' => ['content' => '۷ روز ضمانت بازگشت وجه بدون سوال.']],
                                ],
                            ],
                        ],
                    ],
                    // نظرات
                    [
                        'type'  => 'section',
                        'id'    => 'about-reviews',
                        'props' => ['padding' => 'lg', 'background' => 'background', 'container' => true],
                        'blocks' => [
                            [
                                'type'  => 'module:reviews_grid',
                                'id'    => 'about-reviews-grid',
                                'props' => ['title' => 'چه می‌گویند', 'limit' => 3, 'style' => 'cards', 'show_avatar' => true],
                            ],
                        ],
                    ],
                    [
                        'type'  => 'cta',
                        'id'    => 'about-cta',
                        'props' => [
                            'title'       => 'همین امروز با ما همراه شوید',
                            'subtitle'    => 'از مشاوره رایگان تا پشتیبانی دائمی، کنارتان هستیم.',
                            'button_text' => 'شروع کنید',
                            'button_url'  => '/register',
                            'background'  => 'primary',
                        ],
                    ],
                ],
            ],

            // ─── وبلاگ ───────────────────────────────────────────────────
            'blog' => [
                'title' => 'وبلاگ',
                'slug'  => 'blog',
                'blocks' => [
                    [
                        'type'  => 'hero',
                        'id'    => 'blog-hero',
                        'props' => [
                            'title'       => 'وبلاگ و مجله آموزشی',
                            'subtitle'    => 'آخرین مطالب، راهنماها و اخبار حوزه تخصصی ما',
                            'button_text' => '',
                            'button_url'  => '',
                            'background'  => 'secondary',
                            'align'       => 'center',
                        ],
                    ],
                    [
                        'type'  => 'section',
                        'id'    => 'blog-posts',
                        'props' => ['padding' => 'lg', 'background' => 'background', 'container' => true],
                        'blocks' => [
                            [
                                'type'  => 'module:blog_posts',
                                'id'    => 'blog-posts-grid',
                                'props' => [
                                    'title'         => 'آخرین مطالب',
                                    'limit'         => 12,
                                    'layout'        => 'grid',
                                    'show_excerpt'  => true,
                                    'show_date'     => true,
                                    'category_slug' => '',
                                ],
                            ],
                        ],
                    ],
                    [
                        'type'  => 'section',
                        'id'    => 'blog-categories',
                        'props' => ['padding' => 'lg', 'background' => 'surface', 'container' => true],
                        'blocks' => [
                            ['type' => 'heading', 'id' => 'blog-cat-h', 'props' => ['level' => 3, 'text' => 'دسته‌بندی مطالب']],
                            [
                                'type'  => 'module:blog_categories',
                                'id'    => 'blog-cat-list',
                                'props' => ['title' => 'دسته‌بندی‌ها', 'style' => 'badges', 'limit' => 12],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $themeData = [
            'name'             => 'قالب فروش و پشتیبانی حرفه‌ای',
            'description'      => 'قالب جامع برای کسب‌وکارهای فروش و پشتیبانی: فروشگاه، آموزشگاه، سیستم تیکت، پلن اشتراک، وبلاگ و معرفی به دوستان — کاملاً قابل ویرایش با canvas drag-and-drop.',
            'type'             => Theme::TYPE_FULL,
            'source'           => Theme::SOURCE_SEEDER,
            'site_id'          => null,
            'status'           => Theme::STATUS_PUBLISHED,
            'published_at'     => now(),
            'is_marketplace'   => true,
            'marketplace_price'=> 0,
            'header_blocks'    => $headerBlocks,
            'footer_blocks'    => $footerBlocks,
            'pages'            => $pages,
            'global_styles'    => $globalStyles,
            'settings' => [
                'template'    => 'sales-support',
                'mobile_first'=> true,
                // Phase 2l: also declared at the top level — every other
                // canonical theme's settings.supported_modules lives here
                // (see NexusSeedCommand/MarketplaceThemeSeedCommand), and
                // theme-release:backfill-dependencies reads exactly this
                // path. The nested settings.meta.supported_modules copy
                // below is left in place (real, pre-existing data other
                // code may already read from there) rather than removed.
                'supported_modules' => [
                    'shop', 'courses', 'subscription', 'tickets', 'blog',
                    'referral', 'reviews', 'forms', 'social_links', 'gallery',
                ],
                'meta' => [
                    'id'                        => $slug,
                    'version'                   => '1.0.0',
                    'required_platform_version' => '1.0.0',
                    'supported_modules'         => [
                        'shop', 'courses', 'subscription', 'tickets', 'blog',
                        'referral', 'reviews', 'forms', 'social_links', 'gallery',
                    ],
                    'changelog' => [
                        '1.0.0' => [
                            'قالب حرفه‌ای فروش و پشتیبانی با ۷ صفحه کامل.',
                            'پشتیبانی از تمام ماژول‌های فروشگاه، آموزش و تیکت.',
                            'کاملاً سازگار با canvas drag-and-drop editor.',
                        ],
                    ],
                ],
            ],
        ];

        $theme = Theme::updateOrCreate(['slug' => $slug], $themeData);

        // ثبت در marketplace
        ThemeMarketplace::updateOrCreate(
            ['theme_id' => $theme->id],
            [
                'is_featured'            => true,
                'requirements'           => ['shop', 'courses', 'subscription', 'tickets'],
                'screenshots'            => [],
                'sales_count'            => 0,
                'marketplace_description'=> 'قالب جامع فروش و پشتیبانی با ۷ صفحه آماده، کاملاً قابل ویرایش با canvas drag-and-drop.',
                'version_notes'          => 'نسخه ۱.۰.۰ — اولین انتشار',
            ]
        );

        $this->command->info("✅ Sales & Support Pro theme seeded (ID: {$theme->id})");
    }
}
