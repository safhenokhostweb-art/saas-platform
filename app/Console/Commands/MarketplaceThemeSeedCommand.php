<?php

namespace App\Console\Commands;

use App\Models\Theme;
use App\Models\ThemeMarketplace;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Seeds 20+ professional themes covering all 15 marketplace categories.
 * Run: php artisan marketplace:seed
 * Run with reset: php artisan marketplace:seed --fresh
 */
class MarketplaceThemeSeedCommand extends Command
{
    protected $signature = 'marketplace:seed {--fresh : Delete and re-seed all marketplace themes}';
    protected $description = 'Seed professional themes for all 15 marketplace categories';

    private array $seeded = [];
    private array $skipped = [];

    public function handle(): int
    {
        $this->info('=== Marketplace Theme Seed ===');

        if ($this->option('fresh')) {
            $deleted = Theme::whereNull('site_id')
                ->where('source', Theme::SOURCE_SEEDER)
                ->whereNotNull('category')
                ->delete();
            $this->info("حذف شد: {$deleted} قالب قبلی.");
        }

        // Backfill existing themes with category
        $this->backfillExistingCategories();

        // Seed missing categories
        $this->seedNews();
        $this->seedEducational();
        $this->seedMedical();
        $this->seedLegal();
        $this->seedRealEstate();
        $this->seedRestaurant();
        $this->seedPortfolio();
        $this->seedAgency();
        $this->seedMarketplace();
        $this->seedSaaS();
        $this->seedLanding();
        $this->seedBlog();

        $this->newLine();
        $this->info('✅ ' . count($this->seeded) . ' قالب ثبت شد. ' . count($this->skipped) . ' قالب از قبل موجود بود.');
        $this->info('   مسیر مدیریت: /admin/theme-marketplace');
        $this->info('   برای امتیازدهی: php artisan marketplace:score');
        return 0;
    }

    // ─── Backfill ──────────────────────────────────────────────────────────────

    private function backfillExistingCategories(): void
    {
        $map = [
            'nexus-pro-corporate'   => Theme::CATEGORY_CORPORATE,
            'nexus-pro-personal'    => Theme::CATEGORY_PERSONAL,
            'nexus-pro-store'       => Theme::CATEGORY_STORE,
            'sales-support-pro'     => Theme::CATEGORY_STORE,
            'professional-corporate'=> Theme::CATEGORY_CORPORATE,
        ];
        $count = 0;
        foreach ($map as $slug => $category) {
            $rows = Theme::where('slug', $slug)->whereNull('category')->get();
            foreach ($rows as $theme) {
                $theme->update(['category' => $category, 'license_type' => Theme::LICENSE_FREE]);
                $count++;
            }
        }
        if ($count) {
            $this->line("  ↺ backfill category روی {$count} قالب موجود.");
        }
    }

    // ─── News ──────────────────────────────────────────────────────────────────

    private function seedNews(): void
    {
        $this->create('خبر آنلاین', 'khabar-online', Theme::CATEGORY_NEWS, 0,
            'قالب خبری مدرن. مناسب پورتال خبری، روزنامه آنلاین، رسانه.',
            ['blog', 'forms'],
            $this->styles('#c0392b', '#2c3e50', '#ecf0f1', '#2c3e50'),
            $this->navHeader('#2c3e50', '#fff'),
            $this->simpleFooter('#2c3e50', '#fff'),
            ['home' => $this->page('home', [
                $this->block('hero', ['title' => 'آخرین اخبار روز', 'subtitle' => 'پوشش جامع اخبار ایران و جهان', 'bg_color' => '#c0392b']),
                $this->block('card_grid', ['title' => 'اخبار برگزیده', 'columns' => 3]),
                $this->block('features_grid', ['title' => 'دسته‌بندی اخبار', 'columns' => 4]),
                $this->block('cta', ['title' => 'عضویت در خبرنامه', 'button_text' => 'عضو شوید']),
                $this->block('card_grid', ['title' => 'ویدئوهای پربازدید', 'columns' => 3]),
            ])]
        );
    }

    // ─── Educational ───────────────────────────────────────────────────────────

    private function seedEducational(): void
    {
        $this->create('اکادمی دانش', 'akademi-danesh', Theme::CATEGORY_EDUCATIONAL, 0,
            'قالب آموزشی مدرن. مناسب آکادمی آنلاین، موسسه آموزشی، دوره‌های مجازی.',
            ['courses', 'blog', 'forms'],
            $this->styles('#2980b9', '#1a252f', '#f8f9fa', '#2c3e50'),
            $this->navHeader('#1a252f', '#fff'),
            $this->simpleFooter('#1a252f', '#fff'),
            ['home' => $this->page('home', [
                $this->block('hero_split', ['title' => 'یادگیری را شروع کنید', 'subtitle' => 'بیش از ۵۰۰ دوره آموزشی آنلاین']),
                $this->block('stats_row', ['stats' => [['label' => 'دانشجو', 'value' => '۱۲,۰۰۰+'], ['label' => 'دوره', 'value' => '۵۰۰+'], ['label' => 'مدرس', 'value' => '۸۰+'], ['label' => 'گواهینامه', 'value' => '۱۵,۰۰۰+']]]),
                $this->block('features_grid', ['title' => 'دسته‌بندی دوره‌ها', 'columns' => 4]),
                $this->block('card_grid', ['title' => 'پرفروش‌ترین دوره‌ها', 'columns' => 3]),
                $this->block('testimonials_slider', ['title' => 'نظر دانشجویان']),
                $this->block('cta', ['title' => 'همین الان شروع کنید', 'button_text' => 'ثبت‌نام رایگان']),
            ])]
        );
    }

    // ─── Medical ───────────────────────────────────────────────────────────────

    private function seedMedical(): void
    {
        $this->create('کلینیک سلامت', 'clinic-salamat', Theme::CATEGORY_MEDICAL, 0,
            'قالب پزشکی حرفه‌ای. مناسب کلینیک، مطب، بیمارستان.',
            ['forms', 'tickets'],
            $this->styles('#16a085', '#1a252f', '#f0fafa', '#2c3e50'),
            $this->navHeader('#1a252f', '#fff'),
            $this->simpleFooter('#1a252f', '#fff'),
            ['home' => $this->page('home', [
                $this->block('hero', ['title' => 'سلامت شما اولویت ماست', 'subtitle' => 'نوبت‌دهی آنلاین ۲۴ ساعته', 'bg_color' => '#16a085']),
                $this->block('features_grid', ['title' => 'خدمات ما', 'columns' => 3]),
                $this->block('team', ['title' => 'پزشکان متخصص']),
                $this->block('stats_row', ['stats' => [['label' => 'بیمار', 'value' => '۵۰,۰۰۰+'], ['label' => 'متخصص', 'value' => '۴۰+'], ['label' => 'سال تجربه', 'value' => '۱۵'], ['label' => 'رضایت', 'value' => '۹۸٪']]]),
                $this->block('testimonials_slider', ['title' => 'نظر بیماران']),
                $this->block('cta', ['title' => 'نوبت بگیرید', 'button_text' => 'رزرو آنلاین']),
            ])]
        );
    }

    // ─── Legal ─────────────────────────────────────────────────────────────────

    private function seedLegal(): void
    {
        $this->create('دفتر حقوقی', 'daftar-hoqooqi', Theme::CATEGORY_LEGAL, 0,
            'قالب حقوقی رسمی. مناسب دفاتر حقوقی، وکلا، مشاوران حقوقی.',
            ['forms', 'tickets', 'blog'],
            $this->styles('#2c3e50', '#1a1a2e', '#f5f6fa', '#2c3e50'),
            $this->navHeader('#1a1a2e', '#fff'),
            $this->simpleFooter('#1a1a2e', '#fff'),
            ['home' => $this->page('home', [
                $this->block('hero', ['title' => 'مشاوره حقوقی تخصصی', 'subtitle' => 'تجربه ۲۰ سال دفاع از حقوق موکلین', 'bg_color' => '#2c3e50']),
                $this->block('features_grid', ['title' => 'حوزه‌های تخصصی', 'columns' => 3]),
                $this->block('stats_row', ['stats' => [['label' => 'پرونده موفق', 'value' => '۲,۵۰۰+'], ['label' => 'موکل', 'value' => '۴,۰۰۰+'], ['label' => 'سال تجربه', 'value' => '۲۰'], ['label' => 'وکیل', 'value' => '۱۵']]]),
                $this->block('team', ['title' => 'تیم وکلا']),
                $this->block('faq', ['title' => 'سوالات متداول']),
                $this->block('cta', ['title' => 'مشاوره رایگان', 'button_text' => 'تماس بگیرید']),
            ])]
        );
    }

    // ─── Real Estate ───────────────────────────────────────────────────────────

    private function seedRealEstate(): void
    {
        $this->create('آژانس مسکن', 'azhans-maskan', Theme::CATEGORY_REAL_ESTATE, 0,
            'قالب املاک مدرن. مناسب آژانس‌های مسکن، سازندگان، مشاوران ملکی.',
            ['blog', 'forms'],
            $this->styles('#e67e22', '#2c3e50', '#fafafa', '#2c3e50'),
            $this->navHeader('#2c3e50', '#fff'),
            $this->simpleFooter('#2c3e50', '#fff'),
            ['home' => $this->page('home', [
                $this->block('hero', ['title' => 'ملک رویایی‌تان را پیدا کنید', 'subtitle' => 'بزرگترین پایگاه داده مسکن ایران', 'bg_color' => '#2c3e50']),
                $this->block('features_grid', ['title' => 'دسته‌بندی', 'columns' => 4]),
                $this->block('card_grid', ['title' => 'ملک‌های ویژه', 'columns' => 3]),
                $this->block('stats_row', ['stats' => [['label' => 'ملک', 'value' => '۸,۰۰۰+'], ['label' => 'معامله', 'value' => '۳,۲۰۰+'], ['label' => 'مشتری', 'value' => '۱۵,۰۰۰+'], ['label' => 'شهر', 'value' => '۳۵']]]),
                $this->block('testimonials_slider', ['title' => 'نظر مشتریان']),
                $this->block('cta', ['title' => 'با ما تماس بگیرید', 'button_text' => 'مشاوره رایگان']),
            ])]
        );
    }

    // ─── Restaurant ────────────────────────────────────────────────────────────

    private function seedRestaurant(): void
    {
        $this->create('رستوران لوکس', 'restaurant-lux', Theme::CATEGORY_RESTAURANT, 0,
            'قالب رستورانی شیک. مناسب رستوران، کافه، فست‌فود، سرویس کیترینگ.',
            ['forms', 'shop'],
            $this->styles('#c0392b', '#1a0a00', '#fff8f0', '#3d2b1f'),
            $this->navHeader('#1a0a00', '#fff'),
            $this->simpleFooter('#1a0a00', '#fff'),
            ['home' => $this->page('home', [
                $this->block('hero', ['title' => 'تجربه‌ای فراموش‌نشدنی', 'subtitle' => 'غذاهای لذیذ ایرانی و بین‌المللی', 'bg_color' => '#1a0a00']),
                $this->block('features_grid', ['title' => 'منوی ما', 'columns' => 4]),
                $this->block('card_grid', ['title' => 'غذاهای پیشنهادی', 'columns' => 3]),
                $this->block('stats_row', ['stats' => [['label' => 'آیتم منو', 'value' => '۱۵۰+'], ['label' => 'سال تجربه', 'value' => '۱۰'], ['label' => 'مشتری راضی', 'value' => '۵۰,۰۰۰+'], ['label' => 'سرآشپز', 'value' => '۸']]]),
                $this->block('cta', ['title' => 'رزرو میز', 'button_text' => 'رزرو آنلاین']),
            ])]
        );
    }

    // ─── Portfolio ─────────────────────────────────────────────────────────────

    private function seedPortfolio(): void
    {
        $this->create('پورتفولیو خلاق', 'portfolio-khalaq', Theme::CATEGORY_PORTFOLIO, 0,
            'قالب پورتفولیو مینیمال. مناسب طراحان، عکاسان، هنرمندان.',
            ['blog', 'forms'],
            $this->styles('#6c5ce7', '#0f0f23', '#f8f9fa', '#2d3436'),
            $this->navHeader('#0f0f23', '#fff'),
            $this->simpleFooter('#0f0f23', '#fff'),
            ['home' => $this->page('home', [
                $this->block('hero_split', ['title' => 'خلق تجربه‌های دیجیتال', 'subtitle' => 'طراح UI/UX و توسعه‌دهنده وب']),
                $this->block('bento_grid', ['title' => 'پروژه‌های اخیر']),
                $this->block('skills_bars', ['title' => 'مهارت‌ها']),
                $this->block('client_logos', ['title' => 'مشتریان']),
                $this->block('testimonials_slider', ['title' => 'نظر کارفرمایان']),
                $this->block('cta', ['title' => 'همکاری کنیم', 'button_text' => 'تماس بگیرید']),
            ])]
        );
    }

    // ─── Agency ────────────────────────────────────────────────────────────────

    private function seedAgency(): void
    {
        $this->create('آژانس دیجیتال', 'azhans-digital', Theme::CATEGORY_AGENCY, 0,
            'قالب آژانس دیجیتال مارکتینگ. مناسب آژانس‌های وب، طراحی، تبلیغات.',
            ['blog', 'forms', 'tickets'],
            $this->styles('#fd79a8', '#2d3436', '#f8f9fa', '#2d3436'),
            $this->navHeader('#2d3436', '#fff'),
            $this->simpleFooter('#2d3436', '#fff'),
            ['home' => $this->page('home', [
                $this->block('hero_gradient', ['title' => 'ما برند شما را می‌سازیم', 'subtitle' => 'طراحی | توسعه | بازاریابی دیجیتال']),
                $this->block('features_grid', ['title' => 'خدمات ما', 'columns' => 3]),
                $this->block('process_steps', ['title' => 'فرآیند کار']),
                $this->block('bento_grid', ['title' => 'نمونه کارها']),
                $this->block('client_logos', ['title' => 'برندهای ما']),
                $this->block('cta', ['title' => 'پروژه خود را شروع کنید', 'button_text' => 'دریافت مشاوره']),
            ])]
        );
    }

    // ─── Marketplace ───────────────────────────────────────────────────────────

    private function seedMarketplace(): void
    {
        $this->create('بازار آنلاین', 'bazar-online', Theme::CATEGORY_MARKETPLACE, 0,
            'قالب مارکت‌پلیس چند فروشنده. مناسب بازارهای آنلاین و پلتفرم‌های خرید و فروش.',
            ['shop', 'blog', 'forms', 'subscription'],
            $this->styles('#00b894', '#2d3436', '#f8f9fa', '#2d3436'),
            $this->navHeader('#2d3436', '#fff'),
            $this->simpleFooter('#2d3436', '#fff'),
            ['home' => $this->page('home', [
                $this->block('hero', ['title' => 'بزرگترین بازار آنلاین', 'subtitle' => 'هزاران فروشنده معتبر در یک پلتفرم', 'bg_color' => '#00b894']),
                $this->block('features_grid', ['title' => 'دسته‌بندی محصولات', 'columns' => 4]),
                $this->block('card_grid', ['title' => 'پرفروش‌ترین‌ها', 'columns' => 4]),
                $this->block('stats_row', ['stats' => [['label' => 'فروشنده', 'value' => '۵,۰۰۰+'], ['label' => 'محصول', 'value' => '۱۰۰,۰۰۰+'], ['label' => 'خریدار', 'value' => '۵۰۰,۰۰۰+'], ['label' => 'تحویل روزانه', 'value' => '۱,۵۰۰+']]]),
                $this->block('trust_badges', ['title' => 'چرا ما؟']),
                $this->block('cta', ['title' => 'فروشنده شوید', 'button_text' => 'ثبت‌نام رایگان']),
            ])]
        );
    }

    // ─── SaaS ──────────────────────────────────────────────────────────────────

    private function seedSaaS(): void
    {
        $this->create('نرم‌افزار ابری', 'narmafzar-abri', Theme::CATEGORY_SAAS, 0,
            'قالب SaaS مدرن. مناسب محصولات نرم‌افزاری، اپلیکیشن‌های ابری، پلتفرم‌های B2B.',
            ['subscription', 'blog', 'forms', 'tickets'],
            $this->styles('#6c5ce7', '#0a0a1a', '#f8f9fa', '#2d3436'),
            $this->navHeader('#0a0a1a', '#fff'),
            $this->simpleFooter('#0a0a1a', '#fff'),
            ['home' => $this->page('home', [
                $this->block('hero_gradient', ['title' => 'کسب‌وکار خود را رشد دهید', 'subtitle' => 'نرم‌افزار مدیریت هوشمند برای تیم‌های موفق']),
                $this->block('features_grid', ['title' => 'ویژگی‌های کلیدی', 'columns' => 3]),
                $this->block('stats_row', ['stats' => [['label' => 'کسب‌وکار', 'value' => '۱۰,۰۰۰+'], ['label' => 'کاربر فعال', 'value' => '۸۰,۰۰۰+'], ['label' => 'آپتایم', 'value' => '۹۹.۹٪'], ['label' => 'پشتیبانی', 'value' => '۲۴/۷']]]),
                $this->block('pricing_cards', ['title' => 'پلان‌ها']),
                $this->block('testimonials_slider', ['title' => 'نظر مشتریان']),
                $this->block('faq', ['title' => 'سوالات متداول']),
                $this->block('cta', ['title' => 'امتحان رایگان ۱۴ روزه', 'button_text' => 'شروع رایگان']),
            ])]
        );
    }

    // ─── Landing Page ──────────────────────────────────────────────────────────

    private function seedLanding(): void
    {
        $this->create('لندینگ پیج حرفه‌ای', 'landing-herfei', Theme::CATEGORY_LANDING, 0,
            'قالب لندینگ پیج با نرخ تبدیل بالا. مناسب تبلیغات، محصولات، خدمات.',
            ['forms', 'subscription'],
            $this->styles('#e17055', '#2d3436', '#f8f9fa', '#2d3436'),
            $this->navHeader('#2d3436', '#fff'),
            $this->simpleFooter('#2d3436', '#fff'),
            ['home' => $this->page('home', [
                $this->block('hero', ['title' => 'راه‌حل ایده‌آل شما', 'subtitle' => 'اثربخش‌ترین صفحه فرود برای کسب‌وکار شما', 'bg_color' => '#e17055']),
                $this->block('features_grid', ['title' => 'مزایا', 'columns' => 3]),
                $this->block('process_steps', ['title' => 'چگونه کار می‌کند؟']),
                $this->block('testimonials_slider', ['title' => 'نظر مشتریان']),
                $this->block('trust_badges', ['title' => 'ضمانت‌ها']),
                $this->block('cta', ['title' => 'همین الان شروع کنید', 'button_text' => 'دریافت رایگان']),
            ])]
        );
    }

    // ─── Blog ──────────────────────────────────────────────────────────────────

    private function seedBlog(): void
    {
        $this->create('مجله آنلاین', 'majale-online', Theme::CATEGORY_BLOG, 0,
            'قالب وبلاگ/مجله آنلاین. مناسب نویسندگان، ناشران دیجیتال، مجلات اینترنتی.',
            ['blog', 'forms', 'subscription'],
            $this->styles('#0984e3', '#2d3436', '#f8f9fa', '#2d3436'),
            $this->navHeader('#2d3436', '#fff'),
            $this->simpleFooter('#2d3436', '#fff'),
            ['home' => $this->page('home', [
                $this->block('hero_split', ['title' => 'داستان‌ها را کشف کنید', 'subtitle' => 'مجله دیجیتال ایده‌ها و راه‌حل‌ها']),
                $this->block('card_grid', ['title' => 'مقالات اخیر', 'columns' => 3]),
                $this->block('features_grid', ['title' => 'موضوعات', 'columns' => 4]),
                $this->block('card_grid', ['title' => 'پرخواننده‌ترین‌ها', 'columns' => 2]),
                $this->block('newsletter_form', ['title' => 'خبرنامه']),
            ])]
        );
    }

    // ─── Helpers ───────────────────────────────────────────────────────────────

    private function create(
        string $name, string $slug, string $category, int $price,
        string $description, array $modules,
        array $globalStyles, array $headerBlocks, array $footerBlocks, array $pages
    ): void {
        if (Theme::where('slug', $slug)->exists()) {
            $this->line("  ↺  {$name} — از قبل موجود، رد شد.");
            $this->skipped[] = $slug;
            return;
        }

        $theme = Theme::create([
            'name'              => $name,
            'slug'              => $slug,
            'description'       => $description,
            'type'              => Theme::TYPE_FULL,
            'source'            => Theme::SOURCE_SEEDER,
            'site_id'           => null,
            'status'            => Theme::STATUS_PUBLISHED,
            'is_marketplace'    => true,
            'marketplace_price' => $price,
            'license_type'      => Theme::LICENSE_FREE,
            'category'          => $category,
            'version'           => 1,
            'global_styles'     => $globalStyles,
            'header_blocks'     => $headerBlocks,
            'footer_blocks'     => $footerBlocks,
            'pages'             => $pages,
            'settings'          => [
                'version'           => '1.0.0',
                'release_date'      => now()->toDateString(),
                'supported_modules' => $modules,
                'changelog'         => ['1.0.0' => ['نسخه اولیه ' . $name]],
            ],
        ]);

        ThemeMarketplace::updateOrCreate(['theme_id' => $theme->id], [
            'marketplace_description' => $description,
            'is_featured'             => false,
            'sales_count'             => 0,
        ]);

        $this->line("  ✔  {$name} ({$category}) — ثبت شد.");
        $this->seeded[] = $slug;
    }

    private function block(string $type, array $props = []): array
    {
        return ['id' => 'blk_' . Str::random(8), 'type' => $type, 'props' => $props];
    }

    private function page(string $slug, array $blocks): array
    {
        return ['slug' => $slug, 'title' => 'صفحه اصلی', 'blocks' => $blocks];
    }

    private function styles(string $primary, string $dark, string $bg, string $text): array
    {
        return [
            'primary_color'    => $primary,
            'secondary_color'  => $dark,
            'accent_color'     => $primary,
            'background_color' => $bg,
            'text_color'       => $text,
            'font_family'      => 'Vazirmatn',
            'border_radius'    => 'md',
            'direction'        => 'rtl',
            'shadow'           => 'soft',
            'dark_mode'        => false,
        ];
    }

    private function navHeader(string $bg, string $textColor): array
    {
        return [$this->block('nav_header', [
            'logo_text'    => 'لوگو',
            'bg_color'     => $bg,
            'text_color'   => $textColor,
            'sticky'       => true,
            'nav_links'    => [
                ['label' => 'خانه', 'url' => '/'],
                ['label' => 'خدمات', 'url' => '/services'],
                ['label' => 'درباره ما', 'url' => '/about'],
                ['label' => 'تماس', 'url' => '/contact'],
            ],
        ])];
    }

    private function simpleFooter(string $bg, string $textColor): array
    {
        return [$this->block('nav_footer', [
            'bg_color'      => $bg,
            'text_color'    => $textColor,
            'copyright'     => 'تمام حقوق محفوظ است.',
            'show_socials'  => true,
        ])];
    }
}
