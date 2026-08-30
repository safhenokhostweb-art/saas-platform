<?php

namespace App\Console\Commands;

use App\Models\Theme;
use App\Models\ThemeMarketplace;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Seeds the NEXUS PRO template set (Corporate / Personal / Store) into the platform marketplace.
 * Run: php artisan nexus:seed
 * Run with reset: php artisan nexus:seed --fresh
 */
class NexusSeedCommand extends Command
{
    protected $signature = 'nexus:seed {--fresh : Delete existing NEXUS PRO themes first}';
    protected $description = 'Seed NEXUS PRO multi-mode template set into the theme marketplace';

    public function handle(): int
    {
        if ($this->option('fresh')) {
            $deleted = Theme::whereIn('slug', ['nexus-pro-corporate', 'nexus-pro-personal', 'nexus-pro-store'])->delete();
            $this->info("حذف شد: {$deleted} قالب قبلی.");
        }

        $this->seedCorporate();
        $this->seedPersonal();
        $this->seedStore();

        $this->info('✅ NEXUS PRO (Corporate + Personal + Store) با موفقیت در marketplace ثبت شد.');
        $this->info('   مسیر مدیریت: /admin/theme-marketplace');
        return 0;
    }

    // ─── Corporate Mode ─────────────────────────────────────────────────────

    private function seedCorporate(): void
    {
        if (Theme::where('slug', 'nexus-pro-corporate')->exists()) {
            $this->line('⏭  NEXUS PRO Corporate — از قبل وجود دارد، رد شد.');
            return;
        }

        $theme = Theme::create([
            'name'             => 'NEXUS PRO — Corporate',
            'slug'             => 'nexus-pro-corporate',
            'description'      => 'قالب شرکتی پریمیوم با طراحی SaaS-grade. الهام‌گرفته از Linear, Stripe, Vercel. مناسب شرکت‌ها، آژانس‌ها، استارتاپ‌ها.',
            'type'             => Theme::TYPE_FULL,
            'source'           => Theme::SOURCE_SEEDER,
            'site_id'          => null,
            'status'           => Theme::STATUS_PUBLISHED,
            'is_marketplace'   => true,
            'marketplace_price'=> 0,
            'global_styles'    => $this->corporateStyles(),
            'header_blocks'    => $this->sharedHeader(),
            'footer_blocks'    => $this->corporateFooter(),
            'pages'            => $this->corporatePages(),
            'settings'         => [
                'mode'              => 'corporate',
                'version'           => '1.0.0',
                'release_date'      => now()->toDateString(),
                'supported_modules' => ['blog', 'shop', 'courses', 'forms', 'subscription', 'tickets'],
                'changelog'         => ['1.0.0' => ['نسخه اولیه NEXUS PRO Corporate']],
            ],
        ]);

        // Phase 2l: was missing a ThemeMarketplace row entirely (Theme.description
        // reused verbatim — no fabricated marketing copy; sales_count/ratings/
        // featured flags left at their honest zero/false defaults, matching the
        // convention established in MarketplaceThemeSeedCommand).
        ThemeMarketplace::updateOrCreate(['theme_id' => $theme->id], [
            'marketplace_description' => $theme->description,
            'category_tags' => ['شرکتی', 'کسب‌وکار', 'SaaS'],
            'is_featured' => false,
            'is_new_release' => false,
            'is_bestseller' => false,
            'annual_price' => 0,
            'lifetime_price' => 0,
            'screenshots' => [],
            'requirements' => [],
            'sales_count' => 0,
            'has_demo_data' => true,
        ]);

        $this->info('  ✓ NEXUS PRO Corporate — ثبت شد.');
    }

    // ─── Personal Mode ────────────────────────────────────────────────────────

    private function seedPersonal(): void
    {
        if (Theme::where('slug', 'nexus-pro-personal')->exists()) {
            $this->line('⏭  NEXUS PRO Personal — از قبل وجود دارد، رد شد.');
            return;
        }

        $theme = Theme::create([
            'name'             => 'NEXUS PRO — Personal',
            'slug'             => 'nexus-pro-personal',
            'description'      => 'قالب شخصی/پورتفولیو پریمیوم. مناسب فریلنسرها، طراحان، توسعه‌دهندگان و متخصصان.',
            'type'             => Theme::TYPE_FULL,
            'source'           => Theme::SOURCE_SEEDER,
            'site_id'          => null,
            'status'           => Theme::STATUS_PUBLISHED,
            'is_marketplace'   => true,
            'marketplace_price'=> 0,
            'global_styles'    => $this->personalStyles(),
            'header_blocks'    => $this->sharedHeader('#1e1b4b', '#fff'),
            'footer_blocks'    => $this->personalFooter(),
            'pages'            => $this->personalPages(),
            'settings'         => [
                'mode'              => 'personal',
                'version'           => '1.0.0',
                'release_date'      => now()->toDateString(),
                'supported_modules' => ['blog', 'forms', 'tickets'],
                'changelog'         => ['1.0.0' => ['نسخه اولیه NEXUS PRO Personal']],
            ],
        ]);

        ThemeMarketplace::updateOrCreate(['theme_id' => $theme->id], [
            'marketplace_description' => $theme->description,
            'category_tags' => ['شخصی', 'پورتفولیو', 'فریلنسر'],
            'is_featured' => false,
            'is_new_release' => false,
            'is_bestseller' => false,
            'annual_price' => 0,
            'lifetime_price' => 0,
            'screenshots' => [],
            'requirements' => [],
            'sales_count' => 0,
            'has_demo_data' => true,
        ]);

        $this->info('  ✓ NEXUS PRO Personal — ثبت شد.');
    }

    // ─── Store Mode ───────────────────────────────────────────────────────────

    private function seedStore(): void
    {
        if (Theme::where('slug', 'nexus-pro-store')->exists()) {
            $this->line('⏭  NEXUS PRO Store — از قبل وجود دارد، رد شد.');
            return;
        }

        $theme = Theme::create([
            'name'             => 'NEXUS PRO — Store',
            'slug'             => 'nexus-pro-store',
            'description'      => 'قالب فروشگاهی پریمیوم. مناسب فروشگاه آنلاین، بوتیک، فروش محصولات فیزیکی و دیجیتال.',
            'type'             => Theme::TYPE_FULL,
            'source'           => Theme::SOURCE_SEEDER,
            'site_id'          => null,
            'status'           => Theme::STATUS_PUBLISHED,
            'is_marketplace'   => true,
            'marketplace_price'=> 0,
            'global_styles'    => $this->storeStyles(),
            'header_blocks'    => $this->storeHeader(),
            'footer_blocks'    => $this->storeFooter(),
            'pages'            => $this->storePages(),
            'settings'         => [
                'mode'              => 'store',
                'version'           => '1.0.0',
                'release_date'      => now()->toDateString(),
                'supported_modules' => ['shop', 'blog', 'forms', 'subscription', 'tickets'],
                'changelog'         => ['1.0.0' => ['نسخه اولیه NEXUS PRO Store']],
            ],
        ]);

        ThemeMarketplace::updateOrCreate(['theme_id' => $theme->id], [
            'marketplace_description' => $theme->description,
            'category_tags' => ['فروشگاهی', 'ecommerce', 'فروش آنلاین'],
            'is_featured' => false,
            'is_new_release' => false,
            'is_bestseller' => false,
            'annual_price' => 0,
            'lifetime_price' => 0,
            'screenshots' => [],
            'requirements' => [],
            'sales_count' => 0,
            'has_demo_data' => true,
        ]);

        $this->info('  ✓ NEXUS PRO Store — ثبت شد.');
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // CORPORATE
    // ═══════════════════════════════════════════════════════════════════════════

    private function corporateStyles(): array
    {
        return [
            'primary_color'    => '#4f46e5',
            'secondary_color'  => '#0ea5e9',
            'background_color' => '#ffffff',
            'text_color'       => '#0f172a',
            'font_family'      => 'Vazirmatn',
            'border_radius'    => '0.75rem',
            'container_width'  => '80rem',
            'shadow_sm'        => '0 1px 3px rgba(15,23,42,0.1),0 1px 2px rgba(15,23,42,0.06)',
        ];
    }

    private function corporatePages(): array
    {
        return [
            'home' => [
                'title' => 'صفحه اصلی',
                'slug'  => '',
                'blocks' => [
                    // Hero Split — بخش اصلی با تصویر
                    $this->block('hero_split', [
                        'badge'        => '🚀 پلتفرم پریمیوم ایران',
                        'title'        => 'راه‌حل هوشمند برای کسب‌وکار شما',
                        'subtitle'     => 'از ساخت وب‌سایت حرفه‌ای تا مدیریت فروشگاه، دوره‌های آموزشی و پشتیبانی مشتری — همه در یک پلتفرم.',
                        'button_text'  => 'شروع رایگان',
                        'button_url'   => '/register',
                        'button2_text' => 'مشاهده امکانات',
                        'button2_url'  => '#features',
                        'image_url'    => '',
                        'image_side'   => 'left',
                        'background'   => '#fafafa',
                    ]),
                    // Client Logos — لوگو مشتریان
                    $this->block('client_logos', [
                        'title'  => 'مورد اعتماد صدها کسب‌وکار',
                        'logos'  => '[{"name":"تکنولوژی نوین"},{"name":"گروه فناوری"},{"name":"دیجیتال پلاس"},{"name":"استارتاپ هاب"},{"name":"شرکت راهکار"},{"name":"کسب‌وکار آنلاین"},{"name":"پلتفرم ایران"},{"name":"توسعه وب"}]',
                        'scroll' => true,
                    ]),
                    // Features Grid — ویژگی‌ها
                    $this->block('features_grid', [
                        'title'    => 'چرا NEXUS PRO انتخاب شما؟',
                        'subtitle' => 'قدرتمندترین ابزارها در اختیار کسب‌وکار شما',
                        'columns'  => '3',
                        'style'    => 'cards',
                        'features' => json_encode([
                            ['icon' => '🏗️', 'title' => 'سایت‌ساز حرفه‌ای', 'text' => 'قالب‌های آماده با امکان سفارشی‌سازی کامل بدون نیاز به کد'],
                            ['icon' => '🛒', 'title' => 'فروشگاه آنلاین', 'text' => 'مدیریت محصولات، سفارشات و پرداخت‌های آنلاین'],
                            ['icon' => '🎓', 'title' => 'آموزش آنلاین', 'text' => 'ایجاد دوره‌های آموزشی با ابزارهای پیشرفته'],
                            ['icon' => '📊', 'title' => 'داشبورد هوشمند', 'text' => 'تحلیل داده و گزارش‌های جامع برای تصمیم‌گیری بهتر'],
                            ['icon' => '💬', 'title' => 'پشتیبانی مشتری', 'text' => 'سیستم تیکت حرفه‌ای و ارتباط بی‌وقفه با مشتریان'],
                            ['icon' => '🔒', 'title' => 'امنیت پیشرفته', 'text' => 'رمزنگاری داده‌ها و حفاظت کامل از اطلاعات'],
                        ]),
                    ]),
                    // Stats Row — آمار
                    $this->block('stats_row', [
                        'title'      => '',
                        'background' => '#f8fafc',
                        'dividers'   => true,
                        'stats'      => json_encode([
                            ['value' => 1200, 'suffix' => '+', 'label' => 'کسب‌وکار فعال'],
                            ['value' => 98,   'suffix' => '%', 'label' => 'رضایت مشتریان'],
                            ['value' => 45,   'suffix' => '+', 'label' => 'امکانات حرفه‌ای'],
                            ['value' => 24,   'suffix' => '/7', 'label' => 'پشتیبانی'],
                        ]),
                    ]),
                    // Process Steps — مراحل
                    $this->block('process_steps', [
                        'title'    => 'در سه قدم شروع کنید',
                        'subtitle' => 'راه‌اندازی سایت حرفه‌ای در کمتر از ۱۰ دقیقه',
                        'steps'    => json_encode([
                            ['number' => '۱', 'title' => 'ثبت‌نام رایگان', 'text' => 'بدون نیاز به کارت بانکی، همین الان شروع کنید'],
                            ['number' => '۲', 'title' => 'قالب انتخاب کنید', 'text' => 'از میان ده‌ها قالب حرفه‌ای مناسب کسب‌وکارتان را انتخاب کنید'],
                            ['number' => '۳', 'title' => 'سفارشی کنید', 'text' => 'با ویرایشگر بصری بدون کد، سایت را به سلیقه خود درآورید'],
                            ['number' => '۴', 'title' => 'منتشر کنید', 'text' => 'با یک کلیک سایت را برای دنیا منتشر کنید'],
                        ]),
                    ]),
                    // Bento Grid — چرا ما
                    $this->block('bento_grid', [
                        'title' => 'یک پلتفرم، همه چیز',
                        'items' => json_encode([
                            ['title' => 'مدیریت یکپارچه', 'text' => 'همه ابزارهای کسب‌وکار در یک داشبورد', 'icon' => '📊', 'size' => 'large', 'bg_color' => '#4f46e5', 'text_color' => '#ffffff'],
                            ['title' => 'امنیت کامل', 'text' => 'SSL رایگان + رمزنگاری داده', 'icon' => '🔐', 'size' => 'small', 'bg_color' => '#f0fdf4', 'text_color' => '#14532d'],
                            ['title' => 'سرعت فوق‌العاده', 'text' => 'بارگذاری زیر ۱ ثانیه', 'icon' => '⚡', 'size' => 'small', 'bg_color' => '#fefce8', 'text_color' => '#713f12'],
                            ['title' => 'هوش مصنوعی', 'text' => 'محتوای خودکار با AI', 'icon' => '🤖', 'size' => 'small', 'bg_color' => '#fdf4ff', 'text_color' => '#6b21a8'],
                            ['title' => 'پشتیبانی اختصاصی', 'text' => 'تیم متخصص همیشه در کنار شما', 'icon' => '💬', 'size' => 'small', 'bg_color' => '#eff6ff', 'text_color' => '#1d4ed8'],
                        ]),
                    ]),
                    // Testimonials — نظرات مشتریان
                    $this->block('testimonials_slider', [
                        'title'   => 'مشتریان ما چه می‌گویند',
                        'columns' => '3',
                        'items'   => json_encode([
                            ['name' => 'رضا احمدی', 'role' => 'مدیرعامل آژانس دیجیتال', 'text' => 'NEXUS PRO کاملاً کسب‌وکار ما را متحول کرد. از ساخت سایت تا مدیریت مشتریان همه در یک جا. واقعاً حرفه‌ای!', 'rating' => 5],
                            ['name' => 'مریم نوری',  'role' => 'بنیان‌گذار فروشگاه آنلاین',  'text' => 'فروشگاهم را با NEXUS راه‌اندازی کردم. سادگی طراحی و قدرت عملکرد فوق‌العاده‌ست. فروشم ۳ برابر شد!', 'rating' => 5],
                            ['name' => 'علی حسینی',  'role' => 'مدرس آنلاین',              'text' => 'پلتفرم آموزشی‌ام را با NEXUS ساختم. دانشجویانم عاشق تجربه کاربری آن هستند. عالی!', 'rating' => 5],
                        ]),
                    ]),
                    // Pricing Cards — تعرفه
                    $this->block('pricing_cards', [
                        'title'    => 'پلن‌های مناسب هر کسب‌وکار',
                        'subtitle' => 'بدون قرارداد، لغو در هر زمان',
                        'plans'    => json_encode([
                            ['name' => 'استارتر', 'price' => 'رایگان', 'period' => 'برای همیشه', 'badge' => '', 'highlight' => false, 'features' => ['۱ وب‌سایت', 'قالب‌های پایه', 'پشتیبانی ایمیل', 'SSL رایگان', 'حداکثر ۵۰۰ بازدیدکننده/ماه'], 'cta_text' => 'رایگان شروع کنید', 'cta_url' => '/register'],
                            ['name' => 'حرفه‌ای', 'price' => '۱۵۰,۰۰۰', 'period' => 'تومان / ماه', 'badge' => '⭐ محبوب‌ترین', 'highlight' => true, 'features' => ['۳ وب‌سایت', 'همه قالب‌های پریمیوم', 'فروشگاه آنلاین', 'دوره‌های آنلاین', 'پشتیبانی اولویت‌دار', 'بازدیدکننده نامحدود'], 'cta_text' => 'انتخاب این پلن', 'cta_url' => '/register?plan=pro'],
                            ['name' => 'سازمانی', 'price' => '۳۵۰,۰۰۰', 'period' => 'تومان / ماه', 'badge' => '', 'highlight' => false, 'features' => ['وب‌سایت نامحدود', 'API اختصاصی', 'مدیر حساب', 'SLA 99.9%', 'آموزش تیم', 'سفارشی‌سازی کامل'], 'cta_text' => 'تماس بگیرید', 'cta_url' => '/contact'],
                        ]),
                    ]),
                    // CTA
                    $this->block('cta', [
                        'title'       => 'همین الان کسب‌وکار خود را آنلاین کنید',
                        'subtitle'    => 'به هزاران کسب‌وکار موفق بپیوندید — رایگان شروع کنید',
                        'button_text' => 'شروع رایگان — بدون نیاز به کارت بانکی',
                        'button_url'  => '/register',
                        'background'  => '#4f46e5',
                    ]),
                    // FAQ
                    $this->block('module:faq_dynamic', [
                        'title' => 'سوالات متداول',
                        'limit' => 8,
                        'style' => 'accordion',
                    ]),
                    // Support Button
                    $this->block('module:support_button', [
                        'label'    => 'پشتیبانی',
                        'style'    => 'floating',
                        'position' => 'bottom-right',
                        'bg_color' => '#4f46e5',
                    ]),
                ],
            ],
            'about' => [
                'title' => 'درباره ما',
                'slug'  => 'about',
                'blocks' => [
                    $this->block('hero_gradient', [
                        'badge'         => 'داستان ما',
                        'title'         => 'ما اینجا هستیم تا کسب‌وکار شما را متحول کنیم',
                        'subtitle'      => 'تیمی از متخصصان فناوری و کسب‌وکار که به رشد شما اهمیت می‌دهیم',
                        'button_text'   => 'با ما آشنا شوید',
                        'button_url'    => '#team',
                        'button2_text'  => '',
                        'button2_url'   => '#',
                        'gradient_from' => '#1e1b4b',
                        'gradient_to'   => '#312e81',
                        'text_color'    => '#ffffff',
                    ]),
                    $this->block('stats_row', [
                        'background' => '#f8fafc',
                        'dividers'   => true,
                        'stats'      => json_encode([
                            ['value' => 5, 'suffix' => 'سال', 'label' => 'سابقه فعالیت'],
                            ['value' => 1200, 'suffix' => '+', 'label' => 'مشتری راضی'],
                            ['value' => 30, 'suffix' => '+', 'label' => 'عضو تیم'],
                            ['value' => 99, 'suffix' => '%', 'label' => 'آپتایم سرویس'],
                        ]),
                    ]),
                    $this->block('features_grid', [
                        'title'    => 'ارزش‌های ما',
                        'subtitle' => 'اصولی که هر روز به آن‌ها پایبند هستیم',
                        'columns'  => '3',
                        'style'    => 'bordered',
                        'features' => json_encode([
                            ['icon' => '🎯', 'title' => 'مشتری‌محوری', 'text' => 'موفقیت مشتریان ما، موفقیت ماست'],
                            ['icon' => '💡', 'title' => 'نوآوری مداوم', 'text' => 'هر روز بهتر از دیروز'],
                            ['icon' => '🤝', 'title' => 'صداقت و شفافیت', 'text' => 'روابط مبتنی بر اعتماد'],
                            ['icon' => '🌱', 'title' => 'رشد پایدار', 'text' => 'توسعه مسئولانه و پایدار'],
                            ['icon' => '⚡', 'title' => 'چابکی', 'text' => 'واکنش سریع به نیازهای بازار'],
                            ['icon' => '🌐', 'title' => 'تفکر جهانی', 'text' => 'استانداردهای بین‌المللی، عملکرد محلی'],
                        ]),
                    ]),
                    $this->block('team_cards', [
                        'title'   => 'تیم ما',
                        'columns' => '3',
                        'members' => json_encode([
                            ['name' => 'نام مدیرعامل', 'role' => 'مدیرعامل و بنیان‌گذار', 'image' => '', 'bio' => 'بیش از ۱۰ سال تجربه در حوزه فناوری'],
                            ['name' => 'نام مدیر فنی', 'role' => 'مدیر فنی (CTO)', 'image' => '', 'bio' => 'متخصص معماری نرم‌افزار و توسعه مقیاس‌پذیر'],
                            ['name' => 'نام طراح ارشد', 'role' => 'طراح ارشد (CDO)', 'image' => '', 'bio' => 'طراح تجربه کاربری با تخصص در محصولات دیجیتال'],
                        ]),
                    ]),
                ],
            ],
            'services' => [
                'title' => 'خدمات',
                'slug'  => 'services',
                'blocks' => [
                    $this->block('hero_gradient', [
                        'badge'         => 'خدمات ما',
                        'title'         => 'راه‌حل‌های جامع برای کسب‌وکار شما',
                        'subtitle'      => 'از طراحی وب‌سایت تا پشتیبانی فنی — همه چیز زیر یک سقف',
                        'button_text'   => 'مشاوره رایگان',
                        'button_url'    => '/contact',
                        'button2_text'  => '',
                        'button2_url'   => '#',
                        'gradient_from' => '#0ea5e9',
                        'gradient_to'   => '#0284c7',
                        'text_color'    => '#ffffff',
                    ]),
                    $this->block('services_cards', [
                        'title'    => 'چه کمکی می‌توانیم بکنیم؟',
                        'subtitle' => 'خدمات تخصصی برای هر نیاز کسب‌وکار',
                        'columns'  => '3',
                        'style'    => 'bordered',
                        'services' => json_encode([
                            ['icon' => '🏗️', 'title' => 'طراحی و توسعه وب', 'text' => 'ساخت وب‌سایت‌های حرفه‌ای با بهترین استانداردها', 'link' => '/contact', 'link_text' => 'مشاوره بگیرید'],
                            ['icon' => '🛒', 'title' => 'فروشگاه آنلاین', 'text' => 'راه‌اندازی فروشگاه اینترنتی با درگاه‌های پرداخت ایرانی', 'link' => '/contact', 'link_text' => 'مشاوره بگیرید'],
                            ['icon' => '🎓', 'title' => 'پلتفرم آموزشی', 'text' => 'ایجاد آکادمی آنلاین با ابزارهای پیشرفته آموزش', 'link' => '/contact', 'link_text' => 'مشاوره بگیرید'],
                            ['icon' => '📊', 'title' => 'تحلیل و گزارش', 'text' => 'داشبوردهای هوشمند برای تصمیم‌گیری آگاهانه', 'link' => '/contact', 'link_text' => 'مشاوره بگیرید'],
                            ['icon' => '🔧', 'title' => 'پشتیبانی فنی', 'text' => 'نگهداری و به‌روزرسانی مستمر سیستم‌های شما', 'link' => '/contact', 'link_text' => 'مشاوره بگیرید'],
                            ['icon' => '🤖', 'title' => 'راه‌حل‌های هوش مصنوعی', 'text' => 'ادغام AI در فرآیندهای کسب‌وکار', 'link' => '/contact', 'link_text' => 'مشاوره بگیرید'],
                        ]),
                    ]),
                    $this->block('process_steps', [
                        'title'    => 'فرآیند کار ما',
                        'subtitle' => 'از ایده تا اجرا در کنار شما هستیم',
                        'steps'    => json_encode([
                            ['number' => '۱', 'title' => 'مشاوره اولیه', 'text' => 'بررسی نیازها و اهداف کسب‌وکار شما'],
                            ['number' => '۲', 'title' => 'طراحی راه‌حل', 'text' => 'ارائه راه‌حل سفارشی متناسب با بودجه'],
                            ['number' => '۳', 'title' => 'پیاده‌سازی', 'text' => 'اجرای پروژه با تیم متخصص'],
                            ['number' => '۴', 'title' => 'تحویل و پشتیبانی', 'text' => 'تحویل پروژه و پشتیبانی مستمر'],
                        ]),
                    ]),
                    $this->block('cta', [
                        'title'       => 'پروژه‌ای در ذهن دارید؟',
                        'subtitle'    => 'همین الان با ما مشاوره رایگان داشته باشید',
                        'button_text' => 'مشاوره رایگان',
                        'button_url'  => '/contact',
                        'background'  => '#0ea5e9',
                    ]),
                ],
            ],
            'blog' => [
                'title' => 'وبلاگ',
                'slug'  => 'blog',
                'blocks' => [
                    $this->block('module:blog_posts', [
                        'title'        => 'آخرین مطالب',
                        'limit'        => 9,
                        'layout'       => 'grid',
                        'show_excerpt' => true,
                        'show_date'    => true,
                    ]),
                ],
            ],
            'contact' => [
                'title' => 'تماس با ما',
                'slug'  => 'contact',
                'blocks' => [
                    $this->block('contact_split', [
                        'title'     => 'در ارتباط باشید',
                        'subtitle'  => 'تیم ما آماده پاسخگویی به سوالات شماست',
                        'phone'     => '',
                        'email'     => '',
                        'address'   => 'تهران، ایران',
                        'hours'     => 'شنبه تا پنجشنبه، ۹ تا ۱۷',
                        'show_form' => true,
                    ]),
                    $this->block('module:faq_dynamic', [
                        'title' => 'سوالات متداول',
                        'limit' => 6,
                        'style' => 'accordion',
                    ]),
                ],
            ],
            'pricing' => [
                'title' => 'تعرفه',
                'slug'  => 'pricing',
                'blocks' => [
                    $this->block('pricing_cards', [
                        'title'    => 'پلن‌های مناسب هر کسب‌وکار',
                        'subtitle' => 'بدون قرارداد — لغو در هر زمان',
                        'plans'    => json_encode([
                            ['name' => 'استارتر', 'price' => 'رایگان', 'period' => 'برای همیشه', 'badge' => '', 'highlight' => false, 'features' => ['۱ وب‌سایت', 'قالب‌های پایه', 'SSL رایگان', '۵۰۰ بازدید/ماه'], 'cta_text' => 'شروع رایگان', 'cta_url' => '/register'],
                            ['name' => 'حرفه‌ای', 'price' => '۱۵۰,۰۰۰', 'period' => 'تومان / ماه', 'badge' => '⭐ محبوب‌ترین', 'highlight' => true, 'features' => ['۳ وب‌سایت', 'همه قالب‌ها', 'فروشگاه', 'آموزش آنلاین', 'پشتیبانی اولویت‌دار', 'بازدید نامحدود'], 'cta_text' => 'انتخاب پلن', 'cta_url' => '/register?plan=pro'],
                            ['name' => 'سازمانی', 'price' => '۳۵۰,۰۰۰', 'period' => 'تومان / ماه', 'badge' => '', 'highlight' => false, 'features' => ['سایت نامحدود', 'API اختصاصی', 'مدیر حساب', 'SLA 99.9%', 'آموزش تیم'], 'cta_text' => 'تماس بگیرید', 'cta_url' => '/contact'],
                        ]),
                    ]),
                    $this->block('module:faq_dynamic', [
                        'title' => 'سوالات درباره قیمت‌گذاری',
                        'limit' => 6,
                    ]),
                ],
            ],
        ];
    }

    private function sharedHeader(string $bg = '#ffffff', string $textColor = ''): array
    {
        return [
            $this->block('nav_menu', ['location' => 'header']),
        ];
    }

    private function corporateFooter(): array
    {
        return [
            $this->block('newsletter_form', [
                'title'       => 'عضو خبرنامه ما شوید',
                'subtitle'    => 'جدیدترین اخبار و آپدیت‌های پلتفرم را دریافت کنید',
                'placeholder' => 'ایمیل یا شماره موبایل',
                'button_text' => 'عضویت',
                'background'  => '#1e1b4b',
                'text_color'  => '#ffffff',
            ]),
            $this->block('social_icons', ['style' => 'icons']),
        ];
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // PERSONAL
    // ═══════════════════════════════════════════════════════════════════════════

    private function personalStyles(): array
    {
        return [
            'primary_color'    => '#7c3aed',
            'secondary_color'  => '#a855f7',
            'background_color' => '#0f0f1a',
            'text_color'       => '#f1f5f9',
            'font_family'      => 'Vazirmatn',
            'border_radius'    => '1rem',
            'container_width'  => '72rem',
            'shadow_sm'        => '0 1px 3px rgba(0,0,0,0.4)',
            // Phase 2k: this theme is deliberately dark (background_color/
            // text_color above) — the shared --tb-text-muted default
            // (#4b5563, tuned for LIGHT themes) fails WCAG AA against this
            // theme's own near-black background (real browser-measured
            // 3.94:1). A muted-text color must suit ITS OWN theme's
            // brightness direction; slate-400 clears 7.4:1 here.
            'colors' => ['text_muted' => '#94a3b8'],
        ];
    }

    private function personalPages(): array
    {
        return [
            'home' => [
                'title' => 'خانه',
                'slug'  => '',
                'blocks' => [
                    $this->block('hero_gradient', [
                        'badge'         => '👋 سلام، من',
                        'title'         => 'نام شما اینجاست',
                        'subtitle'      => 'طراح UI/UX و توسعه‌دهنده فرانت‌اند با ۵ سال تجربه در ساخت تجربه‌های دیجیتال زیبا',
                        'button_text'   => 'نمونه کارها',
                        'button_url'    => '/page/portfolio',
                        'button2_text'  => 'تماس با من',
                        'button2_url'   => '/page/contact',
                        'gradient_from' => '#4c1d95',
                        'gradient_to'   => '#7c3aed',
                        'text_color'    => '#ffffff',
                    ]),
                    $this->block('stats_row', [
                        'background' => 'transparent',
                        'dividers'   => true,
                        'stats'      => json_encode([
                            ['value' => 5, 'suffix' => 'سال', 'label' => 'تجربه کاری'],
                            ['value' => 80, 'suffix' => '+', 'label' => 'پروژه تحویل شده'],
                            ['value' => 40, 'suffix' => '+', 'label' => 'مشتری راضی'],
                            ['value' => 15, 'suffix' => '+', 'label' => 'جایزه دریافتی'],
                        ]),
                    ]),
                    $this->block('skills_bars', [
                        'title'  => 'مهارت‌های من',
                        'skills' => json_encode([
                            ['name' => 'طراحی UI/UX', 'percent' => 95],
                            ['name' => 'HTML/CSS/JavaScript', 'percent' => 92],
                            ['name' => 'React/Next.js', 'percent' => 85],
                            ['name' => 'طراحی گرافیک', 'percent' => 80],
                            ['name' => 'توسعه موبایل', 'percent' => 70],
                        ]),
                    ]),
                    $this->block('bento_grid', [
                        'title' => 'آنچه من می‌سازم',
                        'items' => json_encode([
                            ['title' => 'طراحی وب‌سایت', 'text' => 'رابط‌های کاربری زیبا و کاربردی', 'icon' => '🎨', 'size' => 'large', 'bg_color' => '#4c1d95', 'text_color' => '#fff'],
                            ['title' => 'اپ موبایل', 'text' => 'Flutter & React Native', 'icon' => '📱', 'size' => 'small', 'bg_color' => '#1e1b4b', 'text_color' => '#c4b5fd'],
                            ['title' => 'برندینگ', 'text' => 'هویت بصری یکپارچه', 'icon' => '✨', 'size' => 'small', 'bg_color' => '#0f172a', 'text_color' => '#e0e7ff'],
                            ['title' => 'مشاوره UX', 'text' => 'بهبود تجربه کاربری', 'icon' => '🔬', 'size' => 'small', 'bg_color' => '#14532d', 'text_color' => '#bbf7d0'],
                            ['title' => 'آموزش آنلاین', 'text' => 'دوره‌های طراحی و کدنویسی', 'icon' => '🎓', 'size' => 'small', 'bg_color' => '#1e3a5f', 'text_color' => '#bae6fd'],
                        ]),
                    ]),
                    $this->block('testimonials_slider', [
                        'title'   => 'مشتریانم می‌گویند',
                        'columns' => '2',
                        'items'   => json_encode([
                            ['name' => 'مشتری اول', 'role' => 'مدیر محصول', 'text' => 'کار با این طراح تجربه فوق‌العاده‌ای بود. کیفیت کار و دقت در جزئیات ستایش‌برانگیز است.', 'rating' => 5],
                            ['name' => 'مشتری دوم', 'role' => 'بنیان‌گذار استارتاپ', 'text' => 'طراحی وب‌سایت ما را با دید خلاقانه‌ای انجام داد که انتظار نداشتیم. نتیجه عالی بود!', 'rating' => 5],
                        ]),
                    ]),
                    $this->block('contact_split', [
                        'title'     => 'بیایید همکاری کنیم',
                        'subtitle'  => 'پروژه جذابی دارید؟ با من در میان بگذارید',
                        'phone'     => '',
                        'email'     => '',
                        'hours'     => 'شنبه تا چهارشنبه، ۱۰ تا ۱۸',
                        'show_form' => true,
                    ]),
                ],
            ],
            'portfolio' => [
                'title' => 'نمونه کارها',
                'slug'  => 'portfolio',
                'blocks' => [
                    $this->block('hero_gradient', [
                        'badge'         => '💼 نمونه کارها',
                        'title'         => 'پروژه‌هایی که به آن‌ها افتخار می‌کنم',
                        'subtitle'      => 'مجموعه‌ای از بهترین پروژه‌هایی که تا به حال انجام داده‌ام',
                        'button_text'   => '',
                        'button_url'    => '#',
                        'button2_text'  => '',
                        'button2_url'   => '#',
                        'gradient_from' => '#4c1d95',
                        'gradient_to'   => '#1e1b4b',
                        'text_color'    => '#ffffff',
                    ]),
                    $this->block('features_grid', [
                        'title'    => 'دسته‌بندی پروژه‌ها',
                        'subtitle' => '',
                        'columns'  => '4',
                        'style'    => 'minimal',
                        'features' => json_encode([
                            ['icon' => '🌐', 'title' => 'وب‌سایت', 'text' => '۳۵+ پروژه'],
                            ['icon' => '📱', 'title' => 'موبایل', 'text' => '۲۰+ اپ'],
                            ['icon' => '🎨', 'title' => 'گرافیک', 'text' => '۵۰+ اثر'],
                            ['icon' => '🎬', 'title' => 'موشن', 'text' => '۱۵+ پروژه'],
                        ]),
                    ]),
                ],
            ],
            'blog' => [
                'title' => 'وبلاگ',
                'slug'  => 'blog',
                'blocks' => [
                    $this->block('module:blog_posts', [
                        'title' => 'مطالب من',
                        'limit' => 9,
                        'layout' => 'grid',
                    ]),
                ],
            ],
            'contact' => [
                'title' => 'تماس',
                'slug'  => 'contact',
                'blocks' => [
                    $this->block('contact_split', [
                        'title'     => 'بیایید همکاری کنیم',
                        'subtitle'  => 'پروژه یا ایده‌ای دارید؟',
                        'show_form' => true,
                    ]),
                ],
            ],
        ];
    }

    private function personalFooter(): array
    {
        return [
            $this->block('social_icons', ['style' => 'icons']),
        ];
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // STORE
    // ═══════════════════════════════════════════════════════════════════════════

    private function storeStyles(): array
    {
        return [
            'primary_color'    => '#ea580c',
            'secondary_color'  => '#f97316',
            'background_color' => '#ffffff',
            'text_color'       => '#0f172a',
            'font_family'      => 'Vazirmatn',
            'border_radius'    => '0.75rem',
            'container_width'  => '80rem',
            'shadow_sm'        => '0 1px 3px rgba(15,23,42,0.08)',
        ];
    }

    private function storeHeader(): array
    {
        return [
            $this->block('nav_menu', ['location' => 'header']),
            $this->block('module:shop_cart_button', ['label' => 'سبد خرید', 'style' => 'button']),
        ];
    }

    private function storePages(): array
    {
        return [
            'home' => [
                'title' => 'صفحه اصلی',
                'slug'  => '',
                'blocks' => [
                    $this->block('hero_split', [
                        'badge'        => '🔥 جدیدترین محصولات',
                        'title'        => 'خرید آسان، تحویل سریع',
                        'subtitle'     => 'بهترین محصولات با مناسب‌ترین قیمت. ارسال رایگان برای سفارش‌های بالای ۲۰۰ هزار تومان.',
                        'button_text'  => 'خرید کنید',
                        'button_url'   => '/shop',
                        'button2_text' => 'پیشنهادهای ویژه',
                        'button2_url'  => '/shop?filter=featured',
                        'image_url'    => '',
                        'image_side'   => 'left',
                        'background'   => '#fff7ed',
                    ]),
                    $this->block('trust_badges', [
                        'badges'     => json_encode([
                            ['icon' => '🚚', 'title' => 'ارسال رایگان', 'text' => 'سفارش‌های بالای ۲۰۰ هزار تومان'],
                            ['icon' => '🔒', 'title' => 'پرداخت امن', 'text' => 'درگاه‌های معتبر ایرانی'],
                            ['icon' => '↩️', 'title' => 'بازگشت تضمینی', 'text' => 'تا ۷ روز بازگشت بدون سوال'],
                            ['icon' => '⭐', 'title' => 'کیفیت ضمانت شده', 'text' => 'همه محصولات اصل و تضمینی'],
                        ]),
                        'background' => '#fff7ed',
                    ]),
                    $this->block('module:shop_categories', [
                        'title' => 'دسته‌بندی‌های محبوب',
                        'style' => 'grid',
                        'limit' => 6,
                    ]),
                    $this->block('module:shop_product_grid', [
                        'title'   => 'جدیدترین محصولات',
                        'limit'   => 8,
                        'columns' => '4',
                        'source'  => 'recent',
                    ]),
                    $this->block('module:shop_countdown', [
                        'promo_text' => '🔥 فروش ویژه — فرصت محدود!',
                        'end_date'   => now()->addDays(3)->format('Y-m-d'),
                        'bg_color'   => '#ea580c',
                    ]),
                    $this->block('module:shop_product_grid', [
                        'title'   => 'پیشنهادهای ویژه',
                        'limit'   => 4,
                        'columns' => '4',
                        'source'  => 'featured',
                    ]),
                    $this->block('testimonials_slider', [
                        'title'   => 'نظر خریداران',
                        'columns' => '3',
                        'items'   => json_encode([
                            ['name' => 'سارا رحیمی', 'role' => 'خریدار', 'text' => 'محصول دقیقاً مطابق توضیحات بود. ارسال سریع و بسته‌بندی عالی. حتماً دوباره خرید می‌کنم!', 'rating' => 5],
                            ['name' => 'محمد کریمی', 'role' => 'مشتری وفادار', 'text' => 'کیفیت محصولات ممتازه. قیمت‌ها هم منصفانه. پشتیبانی هم سریع جواب میدن.', 'rating' => 5],
                            ['name' => 'فاطمه عباسی', 'role' => 'خریدار', 'text' => 'اولین خریدم از این فروشگاه بود. خیلی راضی هستم. قطعاً معرفی می‌کنم!', 'rating' => 5],
                        ]),
                    ]),
                    $this->block('newsletter_form', [
                        'title'       => 'از تخفیف‌ها باخبر شو!',
                        'subtitle'    => 'عضو خبرنامه شو و ۱۰٪ تخفیف برای اولین خریدت بگیر',
                        'placeholder' => 'شماره موبایل یا ایمیل',
                        'button_text' => 'دریافت تخفیف ۱۰٪',
                        // Phase 2g: #ea580c paired with white text measured
                        // 3.56:1 via real browser contrast verification —
                        // below WCAG AA's 4.5:1. #c2410c (same orange family,
                        // one shade darker) measures ~5.18:1 with white.
                        'background'  => '#c2410c',
                        'text_color'  => '#ffffff',
                    ]),
                    $this->block('module:support_button', [
                        'label'    => 'پشتیبانی خرید',
                        'style'    => 'floating',
                        'position' => 'bottom-right',
                        // Phase 2k: the same #ea580c-with-white-text gap
                        // Phase 2g fixed for this theme's newsletter block
                        // (3.56:1, below AA) — a separate block instance
                        // using the identical color, missed at the time.
                        // Same already-validated fix: #c2410c ~5.18:1.
                        'bg_color' => '#c2410c',
                    ]),
                ],
            ],
            'shop' => [
                'title' => 'فروشگاه',
                'slug'  => 'shop',
                'blocks' => [
                    $this->block('module:shop_search_box', [
                        'placeholder' => 'جستجو در محصولات...',
                        'style'       => 'bar',
                    ]),
                    $this->block('module:shop_product_grid', [
                        'title'   => 'همه محصولات',
                        'limit'   => 12,
                        'columns' => '4',
                        'source'  => 'recent',
                    ]),
                ],
            ],
            'blog' => [
                'title' => 'مجله',
                'slug'  => 'blog',
                'blocks' => [
                    $this->block('module:blog_posts', [
                        'title' => 'مجله فروشگاه',
                        'limit' => 9,
                    ]),
                ],
            ],
            'contact' => [
                'title' => 'تماس',
                'slug'  => 'contact',
                'blocks' => [
                    $this->block('contact_split', [
                        'title'     => 'با ما تماس بگیرید',
                        'subtitle'  => 'سوال یا مشکلی دارید؟ تیم پشتیبانی آماده‌ست',
                        'hours'     => 'شنبه تا پنجشنبه، ۹ تا ۲۱',
                        'show_form' => true,
                    ]),
                    $this->block('module:faq_dynamic', [
                        'title' => 'سوالات متداول خرید',
                        'limit' => 8,
                    ]),
                ],
            ],
        ];
    }

    private function storeFooter(): array
    {
        return [
            $this->block('trust_badges', [
                'badges'     => json_encode([
                    ['icon' => '🚚', 'title' => 'ارسال رایگان', 'text' => 'بالای ۲۰۰ هزار'],
                    ['icon' => '🔒', 'title' => 'پرداخت امن', 'text' => 'درگاه معتبر'],
                    ['icon' => '↩️', 'title' => 'بازگشت ۷ روزه', 'text' => 'بدون سوال'],
                    ['icon' => '☎️', 'title' => 'پشتیبانی', 'text' => 'شنبه تا پنجشنبه'],
                ]),
                'background' => '#f8fafc',
            ]),
        ];
    }

    // ─── Helper ─────────────────────────────────────────────────────────────

    private function block(string $type, array $props = []): array
    {
        return [
            'id'    => Str::random(8),
            'type'  => $type,
            'props' => $props,
        ];
    }
}
