<?php

namespace App\Services\ThemeBuilder;

use Illuminate\Support\Str;

/**
 * Generates safe Persian placeholder content for each section type.
 * Safe mode: never copies proprietary assets — all content is auto-generated.
 * Images are inline SVG data URIs (no external dependencies).
 */
class ThemePlaceholderService
{
    // Persian placeholder text banks
    private const HERO_TITLES = [
        'راه‌حل هوشمند برای کسب‌وکار شما',
        'پلتفرم حرفه‌ای مدیریت کسب‌وکار',
        'یک قدم جلوتر از رقبا',
        'رشد کسب‌وکار با هوش مصنوعی',
        'ابزار قدرتمند برای تیم‌های موفق',
    ];

    private const HERO_SUBTITLES = [
        'با پلتفرم ما، مدیریت کسب‌وکارتان را به سطح جدیدی ببرید. امکانات پیشرفته، رابط کاربری ساده.',
        'هزاران تیم موفق به ما اعتماد کرده‌اند. همین امروز شروع کنید و تفاوت را احساس کنید.',
        'از ابزارهای پیشرفته تا پشتیبانی ۲۴ساعته — همه چیز در یک پلتفرم.',
    ];

    private const FEATURE_TITLES = [
        'سرعت بی‌نظیر',
        'امنیت پیشرفته',
        'گزارش‌های هوشمند',
        'همکاری تیمی',
        'پشتیبانی ۲۴/۷',
        'یکپارچگی آسان',
        'رابط کاربری ساده',
        'مقیاس‌پذیری بالا',
    ];

    private const FEATURE_DESCS = [
        'با فناوری‌های روز دنیا، سریع‌ترین تجربه را دارید.',
        'داده‌های شما با بالاترین استانداردهای امنیتی محافظت می‌شود.',
        'تحلیل‌های دقیق و گزارش‌های بلادرنگ برای تصمیم‌گیری بهتر.',
        'ابزارهای قدرتمند برای هماهنگی بهتر تیم شما.',
        'تیم متخصص ما در تمام ساعات شبانه‌روز آماده کمک است.',
        'به راحتی با ابزارهای موجود شما ادغام می‌شود.',
    ];

    private const SERVICE_NAMES = [
        'مشاوره حرفه‌ای',
        'پیاده‌سازی سیستم',
        'آموزش و توانمندسازی',
        'پشتیبانی فنی',
        'توسعه نرم‌افزار',
        'تحلیل داده',
    ];

    private const TESTIMONIAL_NAMES = [
        'علی محمدی',
        'سارا رضایی',
        'محمد کریمی',
        'فاطمه احمدی',
        'رضا موسوی',
    ];

    private const TESTIMONIAL_ROLES = [
        'مدیرعامل، شرکت آینده‌نگر',
        'مدیر فناوری، استارتاپ نوآور',
        'بنیان‌گذار، گروه کارآفرینی',
        'مدیر بازاریابی، برند معروف',
    ];

    private const TESTIMONIAL_TEXTS = [
        'استفاده از این پلتفرم تحولی واقعی در کسب‌وکار ما ایجاد کرد. بهره‌وری تیم ما ۴۰٪ افزایش یافت.',
        'در ابتدا تردید داشتم، اما بعد از یک ماه استفاده، دیگر نمی‌توانم بدون آن کار کنم. فوق‌العاده است!',
        'پشتیبانی عالی و رابط کاربری بسیار ساده. بهترین سرمایه‌گذاری برای تیم ما بود.',
        'دقیقاً همان ابزاری که برای مدیریت پروژه‌هایمان نیاز داشتیم. کاملاً توصیه می‌کنم.',
    ];

    private const FAQ_ITEMS = [
        ['q' => 'چگونه شروع کنم؟', 'a' => 'ثبت‌نام رایگان کنید، حساب خود را فعال کنید و از راهنمای شروع سریع استفاده کنید. در کمتر از ۱۰ دقیقه آماده می‌شوید.'],
        ['q' => 'آیا امکان تست رایگان وجود دارد؟', 'a' => 'بله! ۱۴ روز رایگان با تمام امکانات پریمیوم. بدون نیاز به کارت بانکی.'],
        ['q' => 'آیا داده‌هایم امن است؟', 'a' => 'امنیت داده‌ها اولویت اول ماست. از رمزنگاری AES-256 و پشتیبان‌گیری روزانه استفاده می‌کنیم.'],
        ['q' => 'می‌توانم طرح را ارتقا یا تنزل دهم؟', 'a' => 'بله، در هر زمان می‌توانید طرح خود را تغییر دهید. تغییرات فوری اعمال می‌شود.'],
        ['q' => 'چه نوع پشتیبانی ارائه می‌دهید؟', 'a' => 'پشتیبانی ۲۴/۷ از طریق چت زنده، ایمیل و تلفن. پایگاه دانش جامع نیز در دسترس است.'],
    ];

    private const BLOG_TITLES = [
        'چگونه بهره‌وری تیم خود را ۳ برابر کنید',
        'راهنمای جامع مدیریت پروژه در ۲۰۲۴',
        'هوش مصنوعی چگونه کسب‌وکارها را متحول می‌کند',
        'بهترین روش‌های مدیریت زمان برای کارآفرینان',
        'چرا امنیت دیجیتال برای کسب‌وکار شما حیاتی است',
    ];

    private const PRODUCT_NAMES = [
        'محصول پریمیوم الفا',
        'مجموعه حرفه‌ای بتا',
        'پکیج استارتر',
        'ابزار هوشمند گاما',
        'سرویس سازمانی',
        'راه‌حل اقتصادی',
    ];

    // Gradient palettes for SVG placeholder images
    private const SVG_GRADIENTS = [
        ['#667eea', '#764ba2'],
        ['#f093fb', '#f5576c'],
        ['#4facfe', '#00f2fe'],
        ['#43e97b', '#38f9d7'],
        ['#fa709a', '#fee140'],
        ['#a8edea', '#fed6e3'],
        ['#ffecd2', '#fcb69f'],
        ['#a1c4fd', '#c2e9fb'],
    ];

    // ── Public API ─────────────────────────────────────────────────────────────

    /** Generate hero section placeholder content */
    public function hero(array $context = []): array
    {
        return [
            'title'       => $context['title'] ?? $this->pick(self::HERO_TITLES),
            'subtitle'    => $this->pick(self::HERO_SUBTITLES),
            'button_text' => 'شروع رایگان',
            'button_url'  => '#',
            'image_url'   => $this->svgPlaceholder(800, 500, 0),
            'background'  => 'primary',
        ];
    }

    /** Generate features section placeholder */
    public function features(int $count = 6): array
    {
        $items = [];
        for ($i = 0; $i < $count; $i++) {
            $items[] = [
                'title' => self::FEATURE_TITLES[$i % count(self::FEATURE_TITLES)],
                'text'  => self::FEATURE_DESCS[$i % count(self::FEATURE_DESCS)],
                'icon'  => $this->featureIcon($i),
            ];
        }
        return ['title' => 'ویژگی‌های کلیدی', 'subtitle' => 'همه چیزی که نیاز دارید', 'items' => $items];
    }

    /** Generate services section placeholder */
    public function services(int $count = 4): array
    {
        $items = [];
        for ($i = 0; $i < $count; $i++) {
            $items[] = [
                'title'  => self::SERVICE_NAMES[$i % count(self::SERVICE_NAMES)],
                'text'   => 'ارائه بهترین خدمات با تیم متخصص و تجربه‌دار در این حوزه.',
                'url'    => '#',
                'icon'   => $this->featureIcon($i + 2),
            ];
        }
        return ['title' => 'خدمات ما', 'items' => $items];
    }

    /** Generate pricing section placeholder */
    public function pricing(): array
    {
        return [
            'title'    => 'قیمت‌گذاری شفاف',
            'subtitle' => 'طرح مناسب کسب‌وکار خود را انتخاب کنید',
            'plans'    => [
                [
                    'name'     => 'رایگان',
                    'price'    => '۰',
                    'period'   => 'ماهانه',
                    'features' => ['۵ کاربر', '۱ گیگابایت فضا', 'پشتیبانی ایمیل'],
                    'cta'      => 'شروع رایگان',
                    'featured' => false,
                ],
                [
                    'name'     => 'حرفه‌ای',
                    'price'    => '۱۹۹,۰۰۰',
                    'period'   => 'ماهانه',
                    'features' => ['۲۵ کاربر', '۵۰ گیگابایت فضا', 'پشتیبانی ۲۴/۷', 'API دسترسی', 'گزارش پیشرفته'],
                    'cta'      => 'انتخاب طرح',
                    'featured' => true,
                ],
                [
                    'name'     => 'سازمانی',
                    'price'    => 'تماس بگیرید',
                    'period'   => '',
                    'features' => ['کاربر نامحدود', 'فضای نامحدود', 'پشتیبانی اختصاصی', 'SLA تضمین‌شده'],
                    'cta'      => 'تماس با فروش',
                    'featured' => false,
                ],
            ],
        ];
    }

    /** Generate FAQ placeholder */
    public function faq(int $count = 5): array
    {
        return [
            'title' => 'سوالات متداول',
            'items' => array_slice(self::FAQ_ITEMS, 0, $count),
        ];
    }

    /** Generate testimonials placeholder */
    public function testimonials(int $count = 3): array
    {
        $items = [];
        for ($i = 0; $i < $count; $i++) {
            $items[] = [
                'name'   => self::TESTIMONIAL_NAMES[$i % count(self::TESTIMONIAL_NAMES)],
                'role'   => self::TESTIMONIAL_ROLES[$i % count(self::TESTIMONIAL_ROLES)],
                'text'   => self::TESTIMONIAL_TEXTS[$i % count(self::TESTIMONIAL_TEXTS)],
                'avatar' => $this->svgAvatar($i),
                'rating' => 5,
            ];
        }
        return ['title' => 'آنچه مشتریان می‌گویند', 'items' => $items];
    }

    /** Generate blog placeholder */
    public function blog(int $count = 3): array
    {
        $posts = [];
        for ($i = 0; $i < $count; $i++) {
            $posts[] = [
                'title'   => self::BLOG_TITLES[$i % count(self::BLOG_TITLES)],
                'excerpt' => 'در این مقاله به بررسی روش‌های کاربردی و اثبات‌شده می‌پردازیم که می‌توانند کسب‌وکار شما را متحول کنند.',
                'date'    => now()->subDays($i * 7)->format('Y/m/d'),
                'image'   => $this->svgPlaceholder(400, 250, $i + 3),
                'url'     => '#',
            ];
        }
        return ['title' => 'آخرین مطالب', 'posts' => $posts];
    }

    /** Generate products placeholder */
    public function products(int $count = 4): array
    {
        $products = [];
        $prices   = ['۴۹۹,۰۰۰', '۲۹۹,۰۰۰', '۱۹۹,۰۰۰', '۸۹۹,۰۰۰', '۱,۴۹۹,۰۰۰', '۱۴۹,۰۰۰'];
        for ($i = 0; $i < $count; $i++) {
            $products[] = [
                'name'    => self::PRODUCT_NAMES[$i % count(self::PRODUCT_NAMES)],
                'price'   => $prices[$i % count($prices)],
                'image'   => $this->svgPlaceholder(300, 300, $i + 6),
                'url'     => '#',
                'badge'   => $i === 0 ? 'پرفروش' : null,
            ];
        }
        return ['title' => 'محصولات ویژه', 'products' => $products];
    }

    /** Generate CTA section placeholder */
    public function cta(): array
    {
        return [
            'title'       => 'همین امروز شروع کنید',
            'text'        => 'به هزاران کسب‌وکار موفق بپیوندید. ۱۴ روز رایگان، بدون نیاز به کارت بانکی.',
            'button_text' => 'شروع رایگان',
            'button_url'  => '#',
            'secondary_button_text' => 'مشاهده دمو',
            'secondary_button_url'  => '#',
            'background'  => 'primary',
        ];
    }

    /** Generate header placeholder */
    public function header(array $context = []): array
    {
        return [
            'logo_text'  => $context['site_name'] ?? 'برند شما',
            'logo_url'   => '/',
            'sticky'     => true,
            'background' => '#ffffff',
            'links'      => [
                ['text' => 'خانه',    'url' => '/'],
                ['text' => 'خدمات',   'url' => '#services'],
                ['text' => 'قیمت‌ها', 'url' => '#pricing'],
                ['text' => 'وبلاگ',   'url' => '#blog'],
                ['text' => 'تماس',    'url' => '#contact'],
            ],
            'cta_text' => 'شروع رایگان',
            'cta_url'  => '#',
        ];
    }

    /** Generate footer placeholder */
    public function footer(array $context = []): array
    {
        return [
            'logo_text' => $context['site_name'] ?? 'برند شما',
            'about'     => 'ما به کسب‌وکارها کمک می‌کنیم تا رشد کنند و به اهداف خود برسند.',
            'columns'   => [
                [
                    'title' => 'لینک‌های سریع',
                    'links' => [
                        ['text' => 'درباره ما',   'url' => '#'],
                        ['text' => 'خدمات',       'url' => '#'],
                        ['text' => 'وبلاگ',       'url' => '#'],
                        ['text' => 'تماس با ما',  'url' => '#'],
                    ],
                ],
                [
                    'title' => 'پشتیبانی',
                    'links' => [
                        ['text' => 'مرکز راهنمایی', 'url' => '#'],
                        ['text' => 'سوالات متداول', 'url' => '#'],
                        ['text' => 'تیکت پشتیبانی', 'url' => '#'],
                    ],
                ],
                [
                    'title' => 'تماس با ما',
                    'links' => [
                        ['text' => 'info@example.com', 'url' => 'mailto:info@example.com'],
                        ['text' => '۰۲۱-۱۲۳۴۵۶۷۸',  'url' => 'tel:02112345678'],
                    ],
                ],
            ],
            'copyright' => '© ' . date('Y') . ' همه حقوق محفوظ است.',
            'social'    => [
                ['platform' => 'instagram', 'url' => '#'],
                ['platform' => 'telegram',  'url' => '#'],
                ['platform' => 'linkedin',  'url' => '#'],
            ],
        ];
    }

    /** Get placeholder data for any section key */
    public function forSection(string $sectionKey, array $context = []): array
    {
        return match ($sectionKey) {
            'header'       => $this->header($context),
            'hero'         => $this->hero($context),
            'features'     => $this->features(),
            'services'     => $this->services(),
            'pricing'      => $this->pricing(),
            'faq'          => $this->faq(),
            'testimonials' => $this->testimonials(),
            'blog'         => $this->blog(),
            'products'     => $this->products(),
            'cta'          => $this->cta(),
            'footer'       => $this->footer($context),
            default        => [],
        };
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    private function pick(array $items): string
    {
        return $items[array_rand($items)];
    }

    /** Generate a gradient SVG data URI placeholder image */
    public function svgPlaceholder(int $w, int $h, int $variant = 0): string
    {
        $gradient = self::SVG_GRADIENTS[$variant % count(self::SVG_GRADIENTS)];
        [$c1, $c2] = $gradient;

        $svg = <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="{$w}" height="{$h}" viewBox="0 0 {$w} {$h}">
  <defs>
    <linearGradient id="g" x1="0%" y1="0%" x2="100%" y2="100%">
      <stop offset="0%" style="stop-color:{$c1}"/>
      <stop offset="100%" style="stop-color:{$c2}"/>
    </linearGradient>
  </defs>
  <rect width="{$w}" height="{$h}" fill="url(#g)" rx="8"/>
  <text x="50%" y="50%" dominant-baseline="middle" text-anchor="middle" fill="rgba(255,255,255,0.7)" font-family="Vazirmatn,sans-serif" font-size="18">تصویر نمونه</text>
</svg>
SVG;

        return 'data:image/svg+xml;base64,' . base64_encode($svg);
    }

    /** Generate a circular avatar SVG */
    private function svgAvatar(int $variant): string
    {
        $gradient = self::SVG_GRADIENTS[$variant % count(self::SVG_GRADIENTS)];
        [$c1, $c2] = $gradient;
        $initials = ['ع‌م', 'س‌ر', 'م‌ک', 'ف‌ا', 'ر‌م'];
        $letter   = $initials[$variant % count($initials)];

        $svg = <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="60" height="60" viewBox="0 0 60 60">
  <defs>
    <linearGradient id="ag{$variant}" x1="0%" y1="0%" x2="100%" y2="100%">
      <stop offset="0%" style="stop-color:{$c1}"/>
      <stop offset="100%" style="stop-color:{$c2}"/>
    </linearGradient>
  </defs>
  <circle cx="30" cy="30" r="30" fill="url(#ag{$variant})"/>
  <text x="50%" y="50%" dominant-baseline="middle" text-anchor="middle" fill="white" font-family="Vazirmatn,sans-serif" font-size="16">{$letter}</text>
</svg>
SVG;

        return 'data:image/svg+xml;base64,' . base64_encode($svg);
    }

    private function featureIcon(int $i): string
    {
        $icons = [
            'zap', 'shield', 'bar-chart', 'users', 'headphones',
            'link', 'layout', 'trending-up', 'cpu', 'globe',
        ];
        return $icons[$i % count($icons)];
    }
}
