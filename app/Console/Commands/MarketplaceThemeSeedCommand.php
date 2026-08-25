<?php

namespace App\Console\Commands;

use App\Models\Theme;
use App\Models\ThemeMarketplace;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Seeds 25 production-ready themes across all 24 marketplace categories.
 * Each theme ships with 8 complete pages: home, about, contact, blog,
 * archive, single-post, 404, and search.
 *
 * Usage:
 *   php artisan marketplace:seed          — add new themes (skip existing)
 *   php artisan marketplace:seed --fresh  — delete platform seeds and re-seed
 *   php artisan marketplace:seed --enrich — update pages on existing themes
 */
class MarketplaceThemeSeedCommand extends Command
{
    protected $signature = 'marketplace:seed
        {--fresh  : Delete all platform-seeded marketplace themes and re-seed}
        {--enrich : Update pages on existing themes without deleting them}';

    protected $description = 'Seed 25 professional themes for all marketplace categories (8 pages each)';

    private array $seeded  = [];
    private array $skipped = [];
    private array $enriched = [];

    public function handle(): int
    {
        $this->info('=== Marketplace Theme Seed — Sprint 10.1 ===');

        if ($this->option('fresh')) {
            $deleted = Theme::whereNull('site_id')
                ->where('source', Theme::SOURCE_SEEDER)
                ->where('is_marketplace', true)
                ->delete();
            $this->info("حذف شد: {$deleted} قالب قبلی.");
        }

        $this->backfillExistingCategories();

        if ($this->option('enrich')) {
            $this->enrichExistingPages();
        }

        // ── 15 original categories ─────────────────────────────────────────
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

        // ── 9 new categories (Sprint 10.1) ────────────────────────────────
        $this->seedLawyer();
        $this->seedBeauty();
        $this->seedTravel();
        $this->seedAutomotive();
        $this->seedIndustrial();
        $this->seedConstruction();
        $this->seedFashion();
        $this->seedElectronics();
        $this->seedSingleProduct();
        $this->seedEnterpriseSaaS();

        // ── First commercial production sprint (business/services/medical) ──
        $this->seedBusinessPro();
        $this->seedLocalServicesPro();
        $this->seedMedicalPremium();
        $this->seedRestaurantPremium();
        $this->seedRealEstatePremium();

        $this->newLine();
        $this->info('✅ ' . count($this->seeded)   . ' قالب ثبت شد.');
        $this->info('   ' . count($this->skipped)  . ' قالب از قبل موجود بود.');
        $this->info('   ' . count($this->enriched) . ' قالب بروز شد (enrich).');
        $this->info('   مسیر: /admin/theme-marketplace');
        $this->info('   امتیازدهی: php artisan marketplace:score');
        return 0;
    }

    // ══════════════════════════════════════════════════════════════════════
    //  BACKFILL & ENRICH
    // ══════════════════════════════════════════════════════════════════════

    private function backfillExistingCategories(): void
    {
        $map = [
            'nexus-pro-corporate'    => Theme::CATEGORY_CORPORATE,
            'nexus-pro-personal'     => Theme::CATEGORY_PERSONAL,
            'nexus-pro-store'        => Theme::CATEGORY_STORE,
            'sales-support-pro'      => Theme::CATEGORY_STORE,
            'professional-corporate' => Theme::CATEGORY_CORPORATE,
        ];
        $count = 0;
        foreach ($map as $slug => $category) {
            Theme::where('slug', $slug)->whereNull('category')->each(function ($t) use ($category, &$count) {
                $t->update(['category' => $category, 'license_type' => Theme::LICENSE_FREE]);
                $count++;
            });
        }
        if ($count) {
            $this->line("  ↺ backfill category روی {$count} قالب موجود.");
        }
    }

    private function enrichExistingPages(): void
    {
        $slugMap = [
            'khabar-online'    => ['colors' => ['primary' => '#c0392b', 'bg' => '#ecf0f1'], 'name' => 'خبر آنلاین'],
            'akademi-danesh'   => ['colors' => ['primary' => '#2980b9', 'bg' => '#f8f9fa'], 'name' => 'اکادمی دانش'],
            'clinic-salamat'   => ['colors' => ['primary' => '#16a085', 'bg' => '#f0fafa'], 'name' => 'کلینیک سلامت'],
            'daftar-hoqooqi'   => ['colors' => ['primary' => '#2c3e50', 'bg' => '#f5f6fa'], 'name' => 'دفتر حقوقی'],
            'azhans-maskan'    => ['colors' => ['primary' => '#e67e22', 'bg' => '#fafafa'], 'name' => 'آژانس مسکن'],
            'restaurant-lux'   => ['colors' => ['primary' => '#c0392b', 'bg' => '#fff8f0'], 'name' => 'رستوران لوکس'],
            'portfolio-khalaq' => ['colors' => ['primary' => '#6c5ce7', 'bg' => '#f8f9fa'], 'name' => 'پورتفولیو خلاق'],
            'azhans-digital'   => ['colors' => ['primary' => '#fd79a8', 'bg' => '#f8f9fa'], 'name' => 'آژانس دیجیتال'],
            'bazar-online'     => ['colors' => ['primary' => '#00b894', 'bg' => '#f8f9fa'], 'name' => 'بازار آنلاین'],
            'narmafzar-abri'   => ['colors' => ['primary' => '#6c5ce7', 'bg' => '#f8f9fa'], 'name' => 'نرم‌افزار ابری'],
            'landing-herfei'   => ['colors' => ['primary' => '#e17055', 'bg' => '#f8f9fa'], 'name' => 'لندینگ پیج حرفه‌ای'],
            'majale-online'    => ['colors' => ['primary' => '#0984e3', 'bg' => '#f8f9fa'], 'name' => 'مجله آنلاین'],
        ];

        foreach ($slugMap as $slug => $data) {
            $theme = Theme::where('slug', $slug)->whereNull('site_id')->first();
            if (!$theme) continue;

            $existingPages = $theme->pages ?? [];
            $missingKeys   = array_diff(['about','contact','blog','archive','single','error','search'], array_keys($existingPages));
            if (empty($missingKeys)) continue;

            $std = $this->buildStandardPages($existingPages['home']['blocks'] ?? [], $data['name'], $data['colors']);
            foreach ($missingKeys as $key) {
                $existingPages[$key] = $std[$key];
            }
            $theme->update(['pages' => $existingPages]);
            $this->line("  ↺ enrich pages: {$slug} (+" . count($missingKeys) . " pages)");
            $this->enriched[] = $slug;
        }
    }

    // ══════════════════════════════════════════════════════════════════════
    //  ORIGINAL 12 CATEGORIES
    // ══════════════════════════════════════════════════════════════════════

    private function seedNews(): void
    {
        $colors = ['primary' => '#c0392b', 'bg' => '#ecf0f1'];
        $homeBlocks = [
            $this->block('hero', ['title' => 'آخرین اخبار روز', 'subtitle' => 'پوشش جامع اخبار ایران و جهان', 'background' => 'primary']),
            $this->block('module:blog_posts', ['title' => 'اخبار برگزیده', 'limit' => 6, 'layout' => 'grid']),
            $this->block('features_grid', ['title' => 'دسته‌بندی اخبار', 'columns' => 4]),
            $this->block('email_capture', ['title' => 'عضویت در خبرنامه', 'button_text' => 'عضو شوید']),
            $this->block('module:blog_posts', ['title' => 'ویدئوهای پربازدید', 'limit' => 3, 'layout' => 'grid']),
        ];
        $this->create(
            'خبر آنلاین', 'khabar-online', Theme::CATEGORY_NEWS, 0,
            'قالب خبری مدرن. مناسب پورتال خبری، روزنامه آنلاین، رسانه.',
            ['blog', 'forms'],
            $this->styles($colors['primary'], '#2c3e50', $colors['bg'], '#2c3e50'),
            $this->navHeader('#2c3e50', '#fff'),
            $this->richFooter('#2c3e50', '#fff'),
            $this->buildStandardPages($homeBlocks, 'خبر آنلاین', $colors),
            ['annual_price' => 0, 'lifetime_price' => 0, 'category_tags' => ['خبری', 'رسانه', 'روزنامه'],
             'screenshots' => $this->screenshots('khabar-online', '#c0392b')]
        );
    }

    private function seedEducational(): void
    {
        $colors = ['primary' => '#2980b9', 'bg' => '#f8f9fa'];
        $homeBlocks = [
            $this->block('hero_split', ['title' => 'یادگیری را شروع کنید', 'subtitle' => 'بیش از ۵۰۰ دوره آموزشی آنلاین']),
            $this->block('stats_row', ['stats' => [
                ['label' => 'دانشجو', 'value' => '۱۲,۰۰۰+'],
                ['label' => 'دوره',   'value' => '۵۰۰+'],
                ['label' => 'مدرس',   'value' => '۸۰+'],
                ['label' => 'گواهینامه','value' => '۱۵,۰۰۰+'],
            ]]),
            $this->block('features_grid', ['title' => 'دسته‌بندی دوره‌ها', 'columns' => 4]),
            $this->block('module:blog_posts', ['title' => 'پرفروش‌ترین دوره‌ها', 'limit' => 3, 'layout' => 'grid']),
            $this->block('testimonials_slider', ['title' => 'نظر دانشجویان']),
            $this->block('cta', ['title' => 'همین الان شروع کنید', 'button_text' => 'ثبت‌نام رایگان', 'button_url' => '/register']),
        ];
        $this->create(
            'اکادمی دانش', 'akademi-danesh', Theme::CATEGORY_EDUCATIONAL, 0,
            'قالب آموزشی مدرن. مناسب آکادمی آنلاین، موسسه آموزشی، دوره‌های مجازی.',
            ['courses', 'blog', 'forms'],
            $this->styles($colors['primary'], '#1a252f', $colors['bg'], '#2c3e50'),
            $this->navHeader('#1a252f', '#fff'),
            $this->richFooter('#1a252f', '#fff'),
            $this->buildStandardPages($homeBlocks, 'اکادمی دانش', $colors),
            ['annual_price' => 0, 'lifetime_price' => 0, 'category_tags' => ['آموزشی', 'دوره', 'آکادمی'],
             'screenshots' => $this->screenshots('akademi-danesh', '#2980b9')]
        );
    }

    private function seedMedical(): void
    {
        $colors = ['primary' => '#16a085', 'bg' => '#f0fafa'];
        $homeBlocks = [
            $this->block('hero', ['title' => 'سلامت شما اولویت ماست', 'subtitle' => 'نوبت‌دهی آنلاین ۲۴ ساعته', 'background' => 'primary']),
            $this->block('features_grid', ['title' => 'خدمات ما', 'columns' => 3]),
            $this->block('team', ['title' => 'پزشکان متخصص', 'limit' => 6]),
            $this->block('stats_row', ['stats' => [
                ['label' => 'بیمار',    'value' => '۵۰,۰۰۰+'],
                ['label' => 'متخصص',   'value' => '۴۰+'],
                ['label' => 'سال تجربه','value' => '۱۵'],
                ['label' => 'رضایت',   'value' => '۹۸٪'],
            ]]),
            $this->block('testimonials_slider', ['title' => 'نظر بیماران']),
            $this->block('cta', ['title' => 'نوبت بگیرید', 'button_text' => 'رزرو آنلاین', 'button_url' => '/contact']),
        ];
        $this->create(
            'کلینیک سلامت', 'clinic-salamat', Theme::CATEGORY_MEDICAL, 0,
            'قالب پزشکی حرفه‌ای. مناسب کلینیک، مطب، بیمارستان.',
            ['forms', 'tickets'],
            $this->styles($colors['primary'], '#1a252f', $colors['bg'], '#2c3e50'),
            $this->navHeader('#1a252f', '#fff'),
            $this->richFooter('#1a252f', '#fff'),
            $this->buildStandardPages($homeBlocks, 'کلینیک سلامت', $colors),
            ['annual_price' => 0, 'lifetime_price' => 0, 'category_tags' => ['پزشکی', 'کلینیک', 'سلامت'],
             'screenshots' => $this->screenshots('clinic-salamat', '#16a085')]
        );
    }

    private function seedLegal(): void
    {
        $colors = ['primary' => '#2c3e50', 'bg' => '#f5f6fa'];
        $homeBlocks = [
            $this->block('hero', ['title' => 'مشاوره حقوقی تخصصی', 'subtitle' => 'تجربه ۲۰ سال دفاع از حقوق موکلین', 'background' => 'primary']),
            $this->block('features_grid', ['title' => 'حوزه‌های تخصصی', 'columns' => 3]),
            $this->block('stats_row', ['stats' => [
                ['label' => 'پرونده موفق','value' => '۲,۵۰۰+'],
                ['label' => 'موکل',        'value' => '۴,۰۰۰+'],
                ['label' => 'سال تجربه',  'value' => '۲۰'],
                ['label' => 'وکیل',        'value' => '۱۵'],
            ]]),
            $this->block('team', ['title' => 'تیم وکلا', 'limit' => 4]),
            $this->block('faq', ['title' => 'سوالات متداول']),
            $this->block('cta', ['title' => 'مشاوره رایگان', 'button_text' => 'تماس بگیرید', 'button_url' => '/contact']),
        ];
        $this->create(
            'دفتر حقوقی', 'daftar-hoqooqi', Theme::CATEGORY_LEGAL, 0,
            'قالب حقوقی رسمی. مناسب دفاتر حقوقی، وکلا، مشاوران حقوقی.',
            ['forms', 'tickets', 'blog'],
            $this->styles($colors['primary'], '#1a1a2e', $colors['bg'], '#2c3e50'),
            $this->navHeader('#1a1a2e', '#fff'),
            $this->richFooter('#1a1a2e', '#fff'),
            $this->buildStandardPages($homeBlocks, 'دفتر حقوقی', $colors),
            ['annual_price' => 0, 'lifetime_price' => 0, 'category_tags' => ['حقوقی', 'وکیل', 'مشاوره'],
             'screenshots' => $this->screenshots('daftar-hoqooqi', '#2c3e50')]
        );
    }

    private function seedRealEstate(): void
    {
        $colors = ['primary' => '#e67e22', 'bg' => '#fafafa'];
        $homeBlocks = [
            $this->block('hero', ['title' => 'ملک رویایی‌تان را پیدا کنید', 'subtitle' => 'بزرگترین پایگاه داده مسکن ایران', 'background' => 'primary']),
            $this->block('features_grid', ['title' => 'دسته‌بندی', 'columns' => 4]),
            $this->block('module:shop_product_grid', ['title' => 'ملک‌های ویژه', 'limit' => 6, 'columns' => '3']),
            $this->block('stats_row', ['stats' => [
                ['label' => 'ملک',     'value' => '۸,۰۰۰+'],
                ['label' => 'معامله',  'value' => '۳,۲۰۰+'],
                ['label' => 'مشتری',   'value' => '۱۵,۰۰۰+'],
                ['label' => 'شهر',     'value' => '۳۵'],
            ]]),
            $this->block('testimonials_slider', ['title' => 'نظر مشتریان']),
            $this->block('cta', ['title' => 'با ما تماس بگیرید', 'button_text' => 'مشاوره رایگان', 'button_url' => '/contact']),
        ];
        $this->create(
            'آژانس مسکن', 'azhans-maskan', Theme::CATEGORY_REAL_ESTATE, 0,
            'قالب املاک مدرن. مناسب آژانس‌های مسکن، سازندگان، مشاوران ملکی.',
            ['blog', 'forms'],
            $this->styles($colors['primary'], '#2c3e50', $colors['bg'], '#2c3e50'),
            $this->navHeader('#2c3e50', '#fff'),
            $this->richFooter('#2c3e50', '#fff'),
            $this->buildStandardPages($homeBlocks, 'آژانس مسکن', $colors),
            ['annual_price' => 0, 'lifetime_price' => 0, 'category_tags' => ['مسکن', 'آژانس', 'ملک'],
             'screenshots' => $this->screenshots('azhans-maskan', '#e67e22')]
        );
    }

    private function seedRestaurant(): void
    {
        $colors = ['primary' => '#c0392b', 'bg' => '#fff8f0'];
        $homeBlocks = [
            $this->block('hero', ['title' => 'تجربه‌ای فراموش‌نشدنی', 'subtitle' => 'غذاهای لذیذ ایرانی و بین‌المللی', 'background' => 'primary']),
            $this->block('features_grid', ['title' => 'منوی ما', 'columns' => 4]),
            $this->block('module:shop_product_grid', ['title' => 'غذاهای پیشنهادی', 'limit' => 6, 'columns' => '3']),
            $this->block('stats_row', ['stats' => [
                ['label' => 'آیتم منو',       'value' => '۱۵۰+'],
                ['label' => 'سال تجربه',      'value' => '۱۰'],
                ['label' => 'مشتری راضی',     'value' => '۵۰,۰۰۰+'],
                ['label' => 'سرآشپز',         'value' => '۸'],
            ]]),
            $this->block('cta', ['title' => 'رزرو میز', 'button_text' => 'رزرو آنلاین', 'button_url' => '/contact']),
        ];
        $this->create(
            'رستوران لوکس', 'restaurant-lux', Theme::CATEGORY_RESTAURANT, 0,
            'قالب رستورانی شیک. مناسب رستوران، کافه، فست‌فود، سرویس کیترینگ.',
            ['forms', 'shop'],
            $this->styles($colors['primary'], '#1a0a00', $colors['bg'], '#3d2b1f'),
            $this->navHeader('#1a0a00', '#fff'),
            $this->richFooter('#1a0a00', '#fff'),
            $this->buildStandardPages($homeBlocks, 'رستوران لوکس', $colors),
            ['annual_price' => 0, 'lifetime_price' => 0, 'category_tags' => ['رستوران', 'کافه', 'غذا'],
             'screenshots' => $this->screenshots('restaurant-lux', '#c0392b')]
        );
    }

    private function seedPortfolio(): void
    {
        $colors = ['primary' => '#6c5ce7', 'bg' => '#f8f9fa'];
        $homeBlocks = [
            $this->block('hero_split', ['title' => 'خلق تجربه‌های دیجیتال', 'subtitle' => 'طراح UI/UX و توسعه‌دهنده وب']),
            $this->block('bento_grid', ['title' => 'پروژه‌های اخیر']),
            $this->block('skills_bars', ['title' => 'مهارت‌ها']),
            $this->block('client_logos', ['title' => 'مشتریان']),
            $this->block('testimonials_slider', ['title' => 'نظر کارفرمایان']),
            $this->block('cta', ['title' => 'همکاری کنیم', 'button_text' => 'تماس بگیرید', 'button_url' => '/contact']),
        ];
        $this->create(
            'پورتفولیو خلاق', 'portfolio-khalaq', Theme::CATEGORY_PORTFOLIO, 0,
            'قالب پورتفولیو مینیمال. مناسب طراحان، عکاسان، هنرمندان.',
            ['blog', 'forms'],
            $this->styles($colors['primary'], '#0f0f23', $colors['bg'], '#2d3436'),
            $this->navHeader('#0f0f23', '#fff'),
            $this->richFooter('#0f0f23', '#fff'),
            $this->buildStandardPages($homeBlocks, 'پورتفولیو خلاق', $colors),
            ['annual_price' => 0, 'lifetime_price' => 0, 'category_tags' => ['پورتفولیو', 'طراح', 'هنرمند'],
             'screenshots' => $this->screenshots('portfolio-khalaq', '#6c5ce7')]
        );
    }

    private function seedAgency(): void
    {
        $colors = ['primary' => '#fd79a8', 'bg' => '#f8f9fa'];
        $homeBlocks = [
            $this->block('hero_gradient', ['title' => 'ما برند شما را می‌سازیم', 'subtitle' => 'طراحی | توسعه | بازاریابی دیجیتال']),
            $this->block('features_grid', ['title' => 'خدمات ما', 'columns' => 3]),
            $this->block('process_steps', ['title' => 'فرآیند کار']),
            $this->block('bento_grid', ['title' => 'نمونه کارها']),
            $this->block('client_logos', ['title' => 'برندهای ما']),
            $this->block('cta', ['title' => 'پروژه خود را شروع کنید', 'button_text' => 'دریافت مشاوره', 'button_url' => '/contact']),
        ];
        $this->create(
            'آژانس دیجیتال', 'azhans-digital', Theme::CATEGORY_AGENCY, 0,
            'قالب آژانس دیجیتال مارکتینگ. مناسب آژانس‌های وب، طراحی، تبلیغات.',
            ['blog', 'forms', 'tickets'],
            $this->styles($colors['primary'], '#2d3436', $colors['bg'], '#2d3436'),
            $this->navHeader('#2d3436', '#fff'),
            $this->richFooter('#2d3436', '#fff'),
            $this->buildStandardPages($homeBlocks, 'آژانس دیجیتال', $colors),
            ['annual_price' => 0, 'lifetime_price' => 0, 'category_tags' => ['آژانس', 'دیجیتال', 'مارکتینگ'],
             'screenshots' => $this->screenshots('azhans-digital', '#fd79a8')]
        );
    }

    private function seedMarketplace(): void
    {
        $colors = ['primary' => '#00b894', 'bg' => '#f8f9fa'];
        $homeBlocks = [
            $this->block('hero', ['title' => 'بزرگترین بازار آنلاین', 'subtitle' => 'هزاران فروشنده معتبر در یک پلتفرم', 'background' => 'primary']),
            $this->block('features_grid', ['title' => 'دسته‌بندی محصولات', 'columns' => 4]),
            $this->block('module:shop_product_grid', ['title' => 'پرفروش‌ترین‌ها', 'limit' => 8, 'columns' => '4', 'source' => 'bestselling']),
            $this->block('stats_row', ['stats' => [
                ['label' => 'فروشنده',       'value' => '۵,۰۰۰+'],
                ['label' => 'محصول',         'value' => '۱۰۰,۰۰۰+'],
                ['label' => 'خریدار',        'value' => '۵۰۰,۰۰۰+'],
                ['label' => 'تحویل روزانه',  'value' => '۱,۵۰۰+'],
            ]]),
            $this->block('trust_badges', ['title' => 'چرا ما؟']),
            $this->block('cta', ['title' => 'فروشنده شوید', 'button_text' => 'ثبت‌نام رایگان', 'button_url' => '/register']),
        ];
        $this->create(
            'بازار آنلاین', 'bazar-online', Theme::CATEGORY_MARKETPLACE, 0,
            'قالب مارکت‌پلیس چند فروشنده. مناسب بازارهای آنلاین و پلتفرم‌های خرید و فروش.',
            ['shop', 'blog', 'forms', 'subscription'],
            $this->styles($colors['primary'], '#2d3436', $colors['bg'], '#2d3436'),
            $this->navHeader('#2d3436', '#fff'),
            $this->richFooter('#2d3436', '#fff'),
            $this->buildStandardPages($homeBlocks, 'بازار آنلاین', $colors),
            ['annual_price' => 0, 'lifetime_price' => 0, 'category_tags' => ['مارکت‌پلیس', 'فروشگاه', 'B2C'],
             'screenshots' => $this->screenshots('bazar-online', '#00b894')]
        );
    }

    private function seedSaaS(): void
    {
        $colors = ['primary' => '#6c5ce7', 'bg' => '#f8f9fa'];
        $homeBlocks = [
            $this->block('hero_gradient', ['title' => 'کسب‌وکار خود را رشد دهید', 'subtitle' => 'نرم‌افزار مدیریت هوشمند برای تیم‌های موفق']),
            $this->block('features_grid', ['title' => 'ویژگی‌های کلیدی', 'columns' => 3]),
            $this->block('stats_row', ['stats' => [
                ['label' => 'کسب‌وکار',  'value' => '۱۰,۰۰۰+'],
                ['label' => 'کاربر فعال','value' => '۸۰,۰۰۰+'],
                ['label' => 'آپتایم',    'value' => '۹۹.۹٪'],
                ['label' => 'پشتیبانی', 'value' => '۲۴/۷'],
            ]]),
            $this->block('pricing_cards', ['title' => 'پلان‌ها']),
            $this->block('testimonials_slider', ['title' => 'نظر مشتریان']),
            $this->block('faq', ['title' => 'سوالات متداول']),
            $this->block('cta', ['title' => 'امتحان رایگان ۱۴ روزه', 'button_text' => 'شروع رایگان', 'button_url' => '/register']),
        ];
        $this->create(
            'نرم‌افزار ابری', 'narmafzar-abri', Theme::CATEGORY_SAAS, 0,
            'قالب SaaS مدرن. مناسب محصولات نرم‌افزاری، اپلیکیشن‌های ابری، پلتفرم‌های B2B.',
            ['subscription', 'blog', 'forms', 'tickets'],
            $this->styles($colors['primary'], '#0a0a1a', $colors['bg'], '#2d3436'),
            $this->navHeader('#0a0a1a', '#fff'),
            $this->richFooter('#0a0a1a', '#fff'),
            $this->buildStandardPages($homeBlocks, 'نرم‌افزار ابری', $colors),
            ['annual_price' => 0, 'lifetime_price' => 0, 'category_tags' => ['SaaS', 'نرم‌افزار', 'ابری'],
             'screenshots' => $this->screenshots('narmafzar-abri', '#6c5ce7')]
        );
    }

    private function seedLanding(): void
    {
        $colors = ['primary' => '#e17055', 'bg' => '#f8f9fa'];
        $homeBlocks = [
            $this->block('hero', ['title' => 'راه‌حل ایده‌آل شما', 'subtitle' => 'اثربخش‌ترین صفحه فرود برای کسب‌وکار شما', 'background' => 'primary']),
            $this->block('features_grid', ['title' => 'مزایا', 'columns' => 3]),
            $this->block('process_steps', ['title' => 'چگونه کار می‌کند؟']),
            $this->block('testimonials_slider', ['title' => 'نظر مشتریان']),
            $this->block('trust_badges', ['title' => 'ضمانت‌ها']),
            $this->block('cta', ['title' => 'همین الان شروع کنید', 'button_text' => 'دریافت رایگان', 'button_url' => '/register']),
        ];
        $this->create(
            'لندینگ پیج حرفه‌ای', 'landing-herfei', Theme::CATEGORY_LANDING, 0,
            'قالب لندینگ پیج با نرخ تبدیل بالا. مناسب تبلیغات، محصولات، خدمات.',
            ['forms', 'subscription'],
            $this->styles($colors['primary'], '#2d3436', $colors['bg'], '#2d3436'),
            $this->navHeader('#2d3436', '#fff'),
            $this->richFooter('#2d3436', '#fff'),
            $this->buildStandardPages($homeBlocks, 'لندینگ پیج حرفه‌ای', $colors),
            ['annual_price' => 0, 'lifetime_price' => 0, 'category_tags' => ['لندینگ', 'تبلیغات', 'تبدیل'],
             'screenshots' => $this->screenshots('landing-herfei', '#e17055')]
        );
    }

    private function seedBlog(): void
    {
        $colors = ['primary' => '#0984e3', 'bg' => '#f8f9fa'];
        $homeBlocks = [
            $this->block('hero_split', ['title' => 'داستان‌ها را کشف کنید', 'subtitle' => 'مجله دیجیتال ایده‌ها و راه‌حل‌ها']),
            $this->block('module:blog_posts', ['title' => 'مقالات اخیر', 'limit' => 6, 'layout' => 'grid']),
            $this->block('module:blog_categories', ['title' => 'موضوعات', 'style' => 'cards', 'limit' => 8]),
            $this->block('module:blog_posts', ['title' => 'پرخواننده‌ترین‌ها', 'limit' => 3, 'layout' => 'grid']),
            $this->block('email_capture', ['title' => 'خبرنامه', 'button_text' => 'عضو شوید']),
        ];
        $this->create(
            'مجله آنلاین', 'majale-online', Theme::CATEGORY_BLOG, 0,
            'قالب وبلاگ/مجله آنلاین. مناسب نویسندگان، ناشران دیجیتال، مجلات اینترنتی.',
            ['blog', 'forms', 'subscription'],
            $this->styles($colors['primary'], '#2d3436', $colors['bg'], '#2d3436'),
            $this->navHeader('#2d3436', '#fff'),
            $this->richFooter('#2d3436', '#fff'),
            $this->buildStandardPages($homeBlocks, 'مجله آنلاین', $colors),
            ['annual_price' => 0, 'lifetime_price' => 0, 'category_tags' => ['وبلاگ', 'مجله', 'محتوا'],
             'screenshots' => $this->screenshots('majale-online', '#0984e3')]
        );
    }

    // ══════════════════════════════════════════════════════════════════════
    //  SPRINT 10.1 — 10 NEW CATEGORIES
    // ══════════════════════════════════════════════════════════════════════

    private function seedLawyer(): void
    {
        $colors = ['primary' => '#1a1a2e', 'bg' => '#f4f6f9'];
        $homeBlocks = [
            $this->block('hero', ['title' => 'وکالت تخصصی با تجربه ۲۵ ساله', 'subtitle' => 'حق موکل؛ اولویت ماست', 'background' => 'primary']),
            $this->block('features_grid', ['title' => 'تخصص‌های حقوقی', 'columns' => 3]),
            $this->block('stats_row', ['stats' => [
                ['label' => 'پرونده برنده', 'value' => '۳,۸۰۰+'],
                ['label' => 'موکل',         'value' => '۶,۵۰۰+'],
                ['label' => 'سال تجربه',   'value' => '۲۵'],
                ['label' => 'وکیل پایه یک','value' => '۱۲'],
            ]]),
            $this->block('team', ['title' => 'وکلای ما', 'limit' => 4]),
            $this->block('testimonials_slider', ['title' => 'نظر موکلین']),
            $this->block('faq', ['title' => 'سوالات حقوقی متداول']),
            $this->block('cta', ['title' => 'مشاوره اولیه رایگان', 'button_text' => 'وقت بگیرید', 'button_url' => '/contact']),
        ];
        $this->create(
            'وکیل پرو', 'vakil-pro', Theme::CATEGORY_LAWYER, 290000,
            'قالب وکالت حرفه‌ای. مناسب دفاتر وکالت، وکلای دادگستری، مشاوران حقوقی خصوصی.',
            ['forms', 'tickets', 'blog'],
            $this->styles($colors['primary'], '#0a0a1e', $colors['bg'], '#1a1a2e'),
            $this->navHeader('#0a0a1e', '#fff'),
            $this->richFooter('#0a0a1e', '#fff'),
            $this->buildStandardPages($homeBlocks, 'وکیل پرو', $colors),
            ['annual_price' => 250000, 'lifetime_price' => 490000,
             'category_tags' => ['وکیل', 'حقوقی', 'دادگستری'],
             'is_new_release' => true,
             'screenshots' => $this->screenshots('vakil-pro', '#1a1a2e'),
             'requirements' => ['forms' => 'فرم‌های تماس و نوبت‌گیری', 'tickets' => 'پیگیری پرونده‌ها'],
             'version_notes' => 'نسخه اول — پشتیبانی کامل از RTL، Vazirmatn']
        );
    }

    private function seedBeauty(): void
    {
        $colors = ['primary' => '#e91e8c', 'bg' => '#fff5fb'];
        $homeBlocks = [
            $this->block('hero_gradient', ['title' => 'زیبایی از درون می‌درخشد', 'subtitle' => 'سالن زیبایی و آرایشگاه تخصصی بانوان']),
            $this->block('features_grid', ['title' => 'خدمات ما', 'columns' => 3]),
            $this->block('gallery', ['title' => 'نمونه کارهای ما']),
            $this->block('stats_row', ['stats' => [
                ['label' => 'مشتری راضی',  'value' => '۱۲,۰۰۰+'],
                ['label' => 'متخصص',       'value' => '۱۸'],
                ['label' => 'خدمت',        'value' => '۸۰+'],
                ['label' => 'سال تجربه',   'value' => '۱۲'],
            ]]),
            $this->block('testimonials_slider', ['title' => 'نظر مشتریان']),
            $this->block('email_capture', ['title' => 'رزرو آنلاین', 'button_text' => 'رزرو کنید']),
            $this->block('cta', ['title' => 'نوبت رایگان اول', 'button_text' => 'رزرو آنلاین', 'button_url' => '/contact']),
        ];
        $this->create(
            'سالن زیبا', 'salon-ziba', Theme::CATEGORY_BEAUTY, 190000,
            'قالب سالن زیبایی و آرایشگاه. مناسب سالن‌های بانوان، مراکز مراقبت پوست، اسپا.',
            ['forms', 'shop', 'blog'],
            $this->styles($colors['primary'], '#4a0028', $colors['bg'], '#3d0020'),
            $this->navHeader('#4a0028', '#fff'),
            $this->richFooter('#4a0028', '#fff'),
            $this->buildStandardPages($homeBlocks, 'سالن زیبا', $colors),
            ['annual_price' => 150000, 'lifetime_price' => 290000,
             'category_tags' => ['زیبایی', 'آرایشگاه', 'اسپا'],
             'is_new_release' => true,
             'screenshots' => $this->screenshots('salon-ziba', '#e91e8c'),
             'requirements' => ['forms' => 'رزرو آنلاین نوبت', 'shop' => 'فروش محصولات آرایشی'],
             'version_notes' => 'نسخه اول — پالت صورتی گرم، تصویر محور']
        );
    }

    private function seedTravel(): void
    {
        $colors = ['primary' => '#0096c7', 'bg' => '#f0f8ff'];
        $homeBlocks = [
            $this->block('hero', ['title' => 'دنیا منتظر کشف شدن است', 'subtitle' => 'بهترین تورها و پکیج‌های مسافرتی ایران و جهان', 'background' => 'primary']),
            $this->block('features_grid', ['title' => 'مقاصد محبوب', 'columns' => 4]),
            $this->block('module:shop_product_grid', ['title' => 'تورهای ویژه', 'limit' => 6, 'columns' => '3', 'source' => 'featured']),
            $this->block('stats_row', ['stats' => [
                ['label' => 'مقصد',         'value' => '۱۲۰+'],
                ['label' => 'مسافر',        'value' => '۸۰,۰۰۰+'],
                ['label' => 'سال فعالیت',  'value' => '۱۵'],
                ['label' => 'رضایت',       'value' => '۹۷٪'],
            ]]),
            $this->block('testimonials_slider', ['title' => 'تجربه مسافران']),
            $this->block('trust_badges', ['title' => 'مجوزها و افتخارات']),
            $this->block('cta', ['title' => 'سفر بعدی را با ما برنامه‌ریزی کنید', 'button_text' => 'مشاوره رایگان', 'button_url' => '/contact']),
        ];
        $this->create(
            'سفر لوکس', 'safar-lux', Theme::CATEGORY_TRAVEL, 250000,
            'قالب گردشگری و مسافرتی. مناسب آژانس مسافرتی، تور اپراتور، گردشگری سلامت.',
            ['shop', 'blog', 'forms'],
            $this->styles($colors['primary'], '#023e8a', $colors['bg'], '#03045e'),
            $this->navHeader('#023e8a', '#fff'),
            $this->richFooter('#023e8a', '#fff'),
            $this->buildStandardPages($homeBlocks, 'سفر لوکس', $colors),
            ['annual_price' => 200000, 'lifetime_price' => 390000,
             'category_tags' => ['گردشگری', 'تور', 'مسافرت'],
             'is_new_release' => true,
             'screenshots' => $this->screenshots('safar-lux', '#0096c7'),
             'requirements' => ['shop' => 'فروش پکیج‌های سفر', 'forms' => 'فرم رزرو تور'],
             'version_notes' => 'نسخه اول — تصویر محور، کارت تور حرفه‌ای']
        );
    }

    private function seedAutomotive(): void
    {
        $colors = ['primary' => '#c62828', 'bg' => '#fafafa'];
        $homeBlocks = [
            $this->block('hero', ['title' => 'خودروی ایده‌آل شما اینجاست', 'subtitle' => 'بزرگترین شبکه نمایشگاه خودرو ایران', 'background' => 'primary']),
            $this->block('features_grid', ['title' => 'دسته‌بندی خودروها', 'columns' => 4]),
            $this->block('module:shop_product_grid', ['title' => 'خودروهای ویژه', 'limit' => 6, 'columns' => '3', 'source' => 'featured']),
            $this->block('stats_row', ['stats' => [
                ['label' => 'خودرو',        'value' => '۵,۰۰۰+'],
                ['label' => 'معامله موفق', 'value' => '۱۲,۰۰۰+'],
                ['label' => 'نمایشگاه',    'value' => '۴۵'],
                ['label' => 'شهر',         'value' => '۲۸'],
            ]]),
            $this->block('trust_badges', ['title' => 'خدمات ما']),
            $this->block('testimonials_slider', ['title' => 'نظر خریداران']),
            $this->block('cta', ['title' => 'مشاوره خرید رایگان', 'button_text' => 'همین الان تماس بگیرید', 'button_url' => '/contact']),
        ];
        $this->create(
            'خودرو پرو', 'khodro-pro', Theme::CATEGORY_AUTOMOTIVE, 350000,
            'قالب نمایشگاه خودرو. مناسب نمایشگاه اتومبیل، فروش خودرو، خدمات خودرویی.',
            ['shop', 'blog', 'forms'],
            $this->styles($colors['primary'], '#1a0000', $colors['bg'], '#2c1010'),
            $this->navHeader('#1a0000', '#fff'),
            $this->richFooter('#1a0000', '#fff'),
            $this->buildStandardPages($homeBlocks, 'خودرو پرو', $colors),
            ['annual_price' => 290000, 'lifetime_price' => 590000,
             'category_tags' => ['خودرو', 'نمایشگاه', 'اتومبیل'],
             'is_new_release' => true,
             'screenshots' => $this->screenshots('khodro-pro', '#c62828'),
             'requirements' => ['shop' => 'کاتالوگ خودرو', 'forms' => 'فرم پیش‌خرید'],
             'version_notes' => 'نسخه اول — کارت خودرو با مشخصات فنی']
        );
    }

    private function seedIndustrial(): void
    {
        $colors = ['primary' => '#37474f', 'bg' => '#f5f5f5'];
        $homeBlocks = [
            $this->block('hero', ['title' => 'صنعت با کیفیت ایرانی', 'subtitle' => 'تولیدکننده تجهیزات صنعتی و ماشین‌آلات', 'background' => 'primary']),
            $this->block('features_grid', ['title' => 'محصولات صنعتی', 'columns' => 3]),
            $this->block('module:shop_product_grid', ['title' => 'کاتالوگ محصولات', 'limit' => 6, 'columns' => '3']),
            $this->block('stats_row', ['stats' => [
                ['label' => 'محصول',        'value' => '۲,۰۰۰+'],
                ['label' => 'مشتری صنعتی', 'value' => '۸۰۰+'],
                ['label' => 'سال تولید',   'value' => '۳۰'],
                ['label' => 'کشور صادرات', 'value' => '۱۲'],
            ]]),
            $this->block('client_logos', ['title' => 'مشتریان صنعتی ما']),
            $this->block('process_steps', ['title' => 'فرآیند تولید']),
            $this->block('cta', ['title' => 'درخواست نمونه یا مشاوره', 'button_text' => 'تماس بگیرید', 'button_url' => '/contact']),
        ];
        $this->create(
            'صنعت مولودی', 'sanat-molodi', Theme::CATEGORY_INDUSTRIAL, 390000,
            'قالب صنعتی حرفه‌ای. مناسب کارخانه‌ها، تولیدکنندگان، شرکت‌های B2B صنعتی.',
            ['shop', 'blog', 'forms'],
            $this->styles($colors['primary'], '#263238', $colors['bg'], '#37474f'),
            $this->navHeader('#263238', '#fff'),
            $this->richFooter('#263238', '#fff'),
            $this->buildStandardPages($homeBlocks, 'صنعت مولودی', $colors),
            ['annual_price' => 320000, 'lifetime_price' => 650000,
             'category_tags' => ['صنعتی', 'کارخانه', 'تولید'],
             'is_new_release' => true,
             'screenshots' => $this->screenshots('sanat-molodi', '#37474f'),
             'requirements' => ['shop' => 'کاتالوگ محصولات صنعتی'],
             'version_notes' => 'نسخه اول — طراحی سنگین، مناسب B2B']
        );
    }

    private function seedConstruction(): void
    {
        $colors = ['primary' => '#f57c00', 'bg' => '#fffde7'];
        $homeBlocks = [
            $this->block('hero', ['title' => 'ساختیم تا ماندگار باشد', 'subtitle' => 'شرکت پیمانکاری و ساختمانی — بیش از ۵۰۰ پروژه تکمیل‌شده', 'background' => 'primary']),
            $this->block('features_grid', ['title' => 'خدمات ما', 'columns' => 3]),
            $this->block('bento_grid', ['title' => 'نمونه پروژه‌ها']),
            $this->block('stats_row', ['stats' => [
                ['label' => 'پروژه',        'value' => '۵۰۰+'],
                ['label' => 'سال فعالیت',  'value' => '۲۲'],
                ['label' => 'متر مربع',    'value' => '۲M+'],
                ['label' => 'کارگر ماهر',  'value' => '۳۵۰+'],
            ]]),
            $this->block('team', ['title' => 'تیم مهندسی', 'limit' => 4]),
            $this->block('process_steps', ['title' => 'مراحل اجرای پروژه']),
            $this->block('cta', ['title' => 'پروژه خود را به ما بسپارید', 'button_text' => 'درخواست مشاوره', 'button_url' => '/contact']),
        ];
        $this->create(
            'ساختمان ایران', 'sakhteman-iran', Theme::CATEGORY_CONSTRUCTION, 350000,
            'قالب ساختمانی و پیمانکاری. مناسب شرکت‌های ساختمانی، پیمانکاران، معماران.',
            ['blog', 'forms'],
            $this->styles($colors['primary'], '#bf360c', $colors['bg'], '#4e342e'),
            $this->navHeader('#bf360c', '#fff'),
            $this->richFooter('#bf360c', '#fff'),
            $this->buildStandardPages($homeBlocks, 'ساختمان ایران', $colors),
            ['annual_price' => 290000, 'lifetime_price' => 580000,
             'category_tags' => ['ساختمان', 'پیمانکاری', 'معماری'],
             'is_new_release' => true,
             'screenshots' => $this->screenshots('sakhteman-iran', '#f57c00'),
             'requirements' => ['blog' => 'معرفی پروژه‌ها', 'forms' => 'فرم درخواست پروژه'],
             'version_notes' => 'نسخه اول — گالری پروژه‌محور']
        );
    }

    private function seedFashion(): void
    {
        $colors = ['primary' => '#880e4f', 'bg' => '#fce4ec'];
        $homeBlocks = [
            $this->block('hero_split', ['title' => 'مد ایرانی را تجربه کنید', 'subtitle' => 'جدیدترین کالکشن‌های فصل']),
            $this->block('module:shop_categories', ['title' => 'دسته‌بندی‌ها', 'style' => 'grid', 'limit' => 6]),
            $this->block('module:shop_product_grid', ['title' => 'جدیدترین محصولات', 'limit' => 8, 'columns' => '4', 'source' => 'recent']),
            $this->block('promo_banner', ['text' => '۲۰٪ تخفیف برای اولین خرید', 'style' => 'info']),
            $this->block('module:shop_product_grid', ['title' => 'پرفروش‌ترین‌ها', 'limit' => 4, 'columns' => '4', 'source' => 'bestselling']),
            $this->block('testimonials_slider', ['title' => 'نظر مشتریان']),
            $this->block('email_capture', ['title' => 'از جدیدترین‌ها باخبر شو', 'button_text' => 'عضو خبرنامه']),
        ];
        $this->create(
            'مد ایرانی', 'mode-fa', Theme::CATEGORY_FASHION, 290000,
            'قالب فشن و پوشاک. مناسب فروشگاه‌های مد، برندهای پوشاک، بوتیک‌های آنلاین.',
            ['shop', 'blog', 'subscription'],
            $this->styles($colors['primary'], '#4a0027', $colors['bg'], '#880e4f'),
            $this->navHeader('#4a0027', '#fff'),
            $this->richFooter('#4a0027', '#fff'),
            $this->buildStandardPages($homeBlocks, 'مد ایرانی', $colors),
            ['annual_price' => 240000, 'lifetime_price' => 490000,
             'category_tags' => ['مد', 'فشن', 'پوشاک', 'بوتیک'],
             'is_new_release' => true,
             'screenshots' => $this->screenshots('mode-fa', '#880e4f'),
             'requirements' => ['shop' => 'فروشگاه محصولات مد', 'subscription' => 'عضویت VIP'],
             'version_notes' => 'نسخه اول — تصویر محور، انیمیشن hover نرم']
        );
    }

    private function seedElectronics(): void
    {
        $colors = ['primary' => '#1565c0', 'bg' => '#e3f2fd'];
        $homeBlocks = [
            $this->block('hero', ['title' => 'دیجی شاپ — تکنولوژی روز ایران', 'subtitle' => 'بهترین قیمت، سریع‌ترین ارسال، اصیل‌ترین کالا', 'background' => 'primary']),
            $this->block('module:shop_categories', ['title' => 'دسته‌بندی محصولات', 'style' => 'grid', 'limit' => 8]),
            $this->block('promo_banner', ['text' => 'فروش ویژه هفته — تا ۴۰٪ تخفیف', 'style' => 'info']),
            $this->block('module:shop_product_grid', ['title' => 'پیشنهاد شگفت‌انگیز', 'limit' => 8, 'columns' => '4', 'source' => 'featured']),
            $this->block('stats_row', ['stats' => [
                ['label' => 'محصول',       'value' => '۵۰,۰۰۰+'],
                ['label' => 'برند',        'value' => '۴۰۰+'],
                ['label' => 'مشتری',       'value' => '۲M+'],
                ['label' => 'تحویل سریع', 'value' => '۲۴ ساعته'],
            ]]),
            $this->block('trust_badges', ['title' => 'ضمانت خرید']),
            $this->block('module:shop_product_grid', ['title' => 'پرفروش‌ترین گجت‌ها', 'limit' => 4, 'columns' => '4', 'source' => 'bestselling']),
        ];
        $this->create(
            'دیجی شاپ', 'digi-shop', Theme::CATEGORY_ELECTRONICS, 390000,
            'قالب فروشگاه الکترونیک. مناسب فروشگاه‌های لوازم دیجیتال، موبایل، لپ‌تاپ.',
            ['shop', 'subscription', 'blog'],
            $this->styles($colors['primary'], '#0d47a1', $colors['bg'], '#1565c0'),
            $this->navHeader('#0d47a1', '#fff'),
            $this->richFooter('#0d47a1', '#fff'),
            $this->buildStandardPages($homeBlocks, 'دیجی شاپ', $colors),
            ['annual_price' => 320000, 'lifetime_price' => 650000,
             'category_tags' => ['الکترونیک', 'دیجیتال', 'فروشگاه'],
             'is_new_release' => true,
             'screenshots' => $this->screenshots('digi-shop', '#1565c0'),
             'requirements' => ['shop' => 'کاتالوگ محصولات الکترونیک'],
             'version_notes' => 'نسخه اول — سریع، فیلتر پیشرفته، کارت محصول حرفه‌ای']
        );
    }

    private function seedSingleProduct(): void
    {
        $colors = ['primary' => '#2e7d32', 'bg' => '#f1f8e9'];
        $homeBlocks = [
            $this->block('hero_split', ['title' => 'یک محصول، بهترین انتخاب', 'subtitle' => 'محصول منحصربه‌فردی که زندگی شما را تغییر می‌دهد']),
            $this->block('features_grid', ['title' => 'چرا این محصول؟', 'columns' => 3]),
            $this->block('module:shop_product_grid', ['title' => 'محصول ویژه', 'limit' => 1, 'columns' => '1', 'source' => 'featured']),
            $this->block('process_steps', ['title' => 'چطور کار می‌کند؟']),
            $this->block('stats_row', ['stats' => [
                ['label' => 'مشتری راضی', 'value' => '۲۵,۰۰۰+'],
                ['label' => 'ستاره',      'value' => '۴.۹/۵'],
                ['label' => 'ضمانت',      'value' => '۲ سال'],
                ['label' => 'ارسال',      'value' => 'رایگان'],
            ]]),
            $this->block('testimonials_slider', ['title' => 'نظر خریداران']),
            $this->block('countdown', ['label' => 'پایان پیشنهاد ویژه']),
            $this->block('cta', ['title' => 'همین الان سفارش بدهید', 'button_text' => 'خرید محصول', 'button_url' => '/shop']),
        ];
        $this->create(
            'محصول ویژه', 'mahsool-vije', Theme::CATEGORY_SINGLE_PRODUCT, 190000,
            'قالب محصول واحد با نرخ تبدیل بالا. مناسب لانچ محصول، کمپین فروش، dropshipping.',
            ['shop', 'subscription'],
            $this->styles($colors['primary'], '#1b5e20', $colors['bg'], '#2e7d32'),
            $this->navHeader('#1b5e20', '#fff'),
            $this->richFooter('#1b5e20', '#fff'),
            $this->buildStandardPages($homeBlocks, 'محصول ویژه', $colors),
            ['annual_price' => 150000, 'lifetime_price' => 290000,
             'category_tags' => ['محصول واحد', 'لانچ', 'فروش ویژه'],
             'is_new_release' => true,
             'screenshots' => $this->screenshots('mahsool-vije', '#2e7d32'),
             'requirements' => ['shop' => 'فروشگاه تک محصولی'],
             'version_notes' => 'نسخه اول — focus بر تبدیل، شمارش معکوس، social proof']
        );
    }

    private function seedEnterpriseSaaS(): void
    {
        $colors = ['primary' => '#4527a0', 'bg' => '#ede7f6'];
        $homeBlocks = [
            $this->block('hero_gradient', ['title' => 'راه‌حل Enterprise برای کسب‌وکار بزرگ', 'subtitle' => 'پلتفرم ابری امن، مقیاس‌پذیر، قابل اعتماد']),
            $this->block('client_logos', ['title' => 'سازمان‌هایی که به ما اعتماد کردند']),
            $this->block('features_grid', ['title' => 'قابلیت‌های Enterprise', 'columns' => 3]),
            $this->block('stats_row', ['stats' => [
                ['label' => 'کاربر',     'value' => '۵۰۰K+'],
                ['label' => 'سازمان',   'value' => '۳,۰۰۰+'],
                ['label' => 'آپتایم',   'value' => '۹۹.۹۹٪'],
                ['label' => 'داده',     'value' => '۱۰ PB+'],
            ]]),
            $this->block('pricing_cards', ['title' => 'پلان‌های سازمانی']),
            $this->block('testimonials_slider', ['title' => 'نظر مدیران']),
            $this->block('trust_badges', ['title' => 'امنیت و انطباق']),
            $this->block('faq', ['title' => 'سوالات Enterprise']),
            $this->block('cta', ['title' => 'دمو برای تیم شما', 'button_text' => 'درخواست دمو', 'button_url' => '/contact']),
        ];
        $this->create(
            'Enterprise Cloud', 'enterprise-cloud', Theme::CATEGORY_SAAS, 590000,
            'قالب SaaS سازمانی. مناسب پلتفرم‌های B2B، نرم‌افزارهای ERP، خدمات ابری سازمانی.',
            ['subscription', 'tickets', 'blog', 'forms'],
            $this->styles($colors['primary'], '#1a0050', $colors['bg'], '#311b92'),
            $this->navHeader('#1a0050', '#fff'),
            $this->richFooter('#1a0050', '#fff'),
            $this->buildStandardPages($homeBlocks, 'Enterprise Cloud', $colors),
            ['annual_price' => 490000, 'lifetime_price' => 990000,
             'category_tags' => ['Enterprise', 'SaaS', 'B2B', 'سازمانی'],
             'is_new_release' => true, 'is_featured' => true,
             'screenshots' => $this->screenshots('enterprise-cloud', '#4527a0'),
             'requirements' => ['subscription' => 'مدیریت اشتراک سازمانی', 'tickets' => 'SLA پشتیبانی'],
             'version_notes' => 'نسخه اول — طراحی dark/professional، trust signals قوی']
        );
    }

    // ══════════════════════════════════════════════════════════════════════
    //  FIRST COMMERCIAL PRODUCTION SPRINT — business / services / medical
    // ══════════════════════════════════════════════════════════════════════

    private function seedBusinessPro(): void
    {
        $colors = ['primary' => '#1d4ed8', 'bg' => '#f8fafc'];
        $homeBlocks = [
            $this->block('hero', ['title' => 'کسب‌وکار خود را حرفه‌ای معرفی کنید', 'subtitle' => 'وب‌سایت شرکتی مدرن برای استارتاپ‌ها و کسب‌وکارهای در حال رشد', 'background' => 'primary']),
            $this->block('features_grid', ['title' => 'چرا ما را انتخاب کنید؟', 'columns' => 3]),
            $this->block('stats_row', ['stats' => [
                ['label' => 'مشتری فعال',   'value' => '۲,۰۰۰+'],
                ['label' => 'پروژه موفق',   'value' => '۴۵۰+'],
                ['label' => 'سال تجربه',    'value' => '۸'],
                ['label' => 'رضایت مشتری',  'value' => '۹۶٪'],
            ]]),
            $this->block('process_steps', ['title' => 'چگونه با ما همکاری می‌کنید؟']),
            $this->block('module:blog_posts', ['title' => 'آخرین مقالات', 'limit' => 3, 'layout' => 'grid']),
            $this->block('testimonials_slider', ['title' => 'نظر مشتریان']),
            $this->block('cta', ['title' => 'آماده شروع همکاری هستید؟', 'button_text' => 'درخواست مشاوره رایگان', 'button_url' => '/contact']),
        ];
        $this->create(
            'بیزینس پرو', 'business-pro', Theme::CATEGORY_BUSINESS, 250000,
            'قالب شرکتی حرفه‌ای برای کسب‌وکارهای عمومی. مناسب استارتاپ‌ها، شرکت‌های مشاوره، کسب‌وکارهای رو به رشد بدون صنعت خاص.',
            ['forms', 'blog'],
            $this->styles($colors['primary'], '#0f172a', $colors['bg'], '#1e293b'),
            $this->navHeader('#0f172a', '#fff'),
            $this->richFooter('#0f172a', '#fff'),
            $this->buildStandardPages($homeBlocks, 'بیزینس پرو', $colors),
            ['annual_price' => 210000, 'lifetime_price' => 420000,
             'category_tags' => ['کسب‌وکار', 'شرکتی', 'استارتاپ'],
             'is_new_release' => true,
             'screenshots' => $this->screenshots('business-pro', '#1d4ed8'),
             'requirements' => ['forms' => 'فرم تماس و درخواست مشاوره', 'blog' => 'محتوای تخصصی و اعتمادسازی'],
             'version_notes' => 'نسخه اول — طراحی حرفه‌ای خنثی، مناسب هر صنعتی؛ اولین قالب دسته business']
        );
    }

    private function seedLocalServicesPro(): void
    {
        $colors = ['primary' => '#0d9488', 'bg' => '#f0fdfa'];
        $homeBlocks = [
            $this->block('hero', ['title' => 'خدمات محلی، کیفیت حرفه‌ای', 'subtitle' => 'سرویس‌دهی سریع و مطمئن در محل شما', 'background' => 'primary']),
            $this->block('features_grid', ['title' => 'خدمات ما', 'columns' => 3]),
            $this->block('stats_row', ['stats' => [
                ['label' => 'مشتری راضی',       'value' => '۸,۰۰۰+'],
                ['label' => 'زمان پاسخ‌گویی',   'value' => 'زیر ۳۰ دقیقه'],
                ['label' => 'سال فعالیت',       'value' => '۱۲'],
                ['label' => 'پروژه انجام‌شده',  'value' => '۵,۰۰۰+'],
            ]]),
            $this->block('process_steps', ['title' => 'چطور سفارش بدهم؟']),
            $this->block('testimonials_slider', ['title' => 'نظر مشتریان محلی']),
            $this->block('faq', ['title' => 'سوالات متداول']),
            $this->block('cta', ['title' => 'همین حالا درخواست خدمات بدهید', 'button_text' => 'رزرو آنلاین', 'button_url' => '/contact']),
        ];
        $this->create(
            'خدمات محلی پرو', 'local-services-pro', Theme::CATEGORY_SERVICES, 190000,
            'قالب خدمات محلی. مناسب لوله‌کشان، برق‌کاران، نظافت منزل، تعمیرکاران، پیمانکاران خرد و سایر کسب‌وکارهای خدماتی محلی.',
            ['forms', 'blog'],
            $this->styles($colors['primary'], '#134e4a', $colors['bg'], '#134e4a'),
            $this->navHeader('#134e4a', '#fff'),
            $this->richFooter('#134e4a', '#fff'),
            $this->buildStandardPages($homeBlocks, 'خدمات محلی پرو', $colors),
            ['annual_price' => 150000, 'lifetime_price' => 290000,
             'category_tags' => ['خدمات', 'محلی', 'تعمیرات'],
             'is_new_release' => true,
             'screenshots' => $this->screenshots('local-services-pro', '#0d9488'),
             'requirements' => ['forms' => 'فرم درخواست خدمات و رزرو نوبت', 'blog' => 'راهنمای مشتریان و نکات فنی'],
             'version_notes' => 'نسخه اول — تمرکز بر سرعت تماس و اعتمادسازی محلی؛ اولین قالب دسته services']
        );
    }

    private function seedMedicalPremium(): void
    {
        $colors = ['primary' => '#0e7490', 'bg' => '#ecfeff'];
        $homeBlocks = [
            $this->block('hero', ['title' => 'مراقبت پزشکی درجه یک', 'subtitle' => 'نوبت‌دهی آنلاین، پرونده الکترونیک و پیگیری اختصاصی بیماران', 'background' => 'primary']),
            // Motion Graphics integration proof (Theme Builder <-> Motion
            // Graphics, Step 12): three deliberate, distinct placements —
            // hero, process, cta — matching the block-level contract in
            // BlockRegistry's module:motion entry (customer-site). Each is
            // a stable asset_id reference only; the actual asset must be
            // published + assigned to a site via the Motion admin bridge
            // (admin/motion-graphics/{site}/themes) before it renders —
            // otherwise this block safely renders nothing.
            $this->block('module:motion', ['motion_asset_id' => 'medical-hero-intro', 'placement' => 'hero']),
            $this->block('features_grid', ['title' => 'خدمات تخصصی', 'columns' => 3]),
            $this->block('module:motion', ['motion_asset_id' => 'medical-process-flow', 'placement' => 'section']),
            $this->block('team', ['title' => 'پزشکان و متخصصان ما', 'limit' => 6]),
            $this->block('stats_row', ['stats' => [
                ['label' => 'بیمار تحت درمان', 'value' => '۸۰,۰۰۰+'],
                ['label' => 'متخصص',           'value' => '۶۰+'],
                ['label' => 'سال تجربه',       'value' => '۲۰'],
                ['label' => 'رضایت بیماران',   'value' => '۹۹٪'],
            ]]),
            $this->block('pricing_cards', ['title' => 'پلان‌های عضویت VIP بیماران']),
            $this->block('testimonials_slider', ['title' => 'تجربه بیماران']),
            $this->block('faq', ['title' => 'سوالات پزشکی متداول']),
            $this->block('module:motion', ['motion_asset_id' => 'medical-cta-pulse', 'placement' => 'cta']),
            $this->block('cta', ['title' => 'نوبت بگیرید', 'button_text' => 'رزرو آنلاین نوبت', 'button_url' => '/contact']),
        ];
        $this->create(
            'کلینیک پریمیوم', 'clinic-premium', Theme::CATEGORY_MEDICAL, 390000,
            'قالب پزشکی پریمیوم با پشتیبانی از عضویت VIP بیماران و پیگیری پرونده. مناسب کلینیک‌های تخصصی بزرگ، بیمارستان‌های خصوصی، مجتمع‌های پزشکی.',
            ['forms', 'tickets', 'subscription'],
            $this->styles($colors['primary'], '#083344', $colors['bg'], '#164e63'),
            $this->navHeader('#083344', '#fff'),
            $this->richFooter('#083344', '#fff'),
            $this->buildStandardPages($homeBlocks, 'کلینیک پریمیوم', $colors),
            ['annual_price' => 320000, 'lifetime_price' => 650000,
             'category_tags' => ['پزشکی', 'کلینیک', 'VIP', 'پریمیوم'],
             'is_new_release' => true, 'is_featured' => true,
             'screenshots' => $this->screenshots('clinic-premium', '#0e7490'),
             'requirements' => ['forms' => 'نوبت‌دهی آنلاین', 'tickets' => 'پیگیری پرونده و درخواست‌های بیمار', 'subscription' => 'عضویت VIP و برنامه‌های مراقبتی'],
             'version_notes' => 'نسخه دوم — سه بلوک موشن گرافیک (hero/process/cta) اضافه شد؛ نسخه پریمیوم در کنار قالب رایگان کلینیک سلامت؛ تمرکز بر بیمارستان‌ها و مجتمع‌های پزشکی بزرگ'],
            ['motion' => [
                'package_reference' => 'theme:clinic-premium',
                'mode' => 'bundled',
                'required_assets' => [
                    ['asset_id' => 'medical-hero-intro'],
                    ['asset_id' => 'medical-process-flow'],
                    ['asset_id' => 'medical-cta-pulse'],
                ],
            ]]
        );
    }

    private function seedRestaurantPremium(): void
    {
        $colors = ['primary' => '#92400e', 'bg' => '#fffbeb'];
        $homeBlocks = [
            $this->block('hero', ['title' => 'منویی که همه عاشقش می‌شوند', 'subtitle' => 'تجربه غذایی لوکس با مواد اولیه تازه و سرآشپزهای حرفه‌ای', 'background' => 'primary']),
            // Motion Graphics integration (hero/cta only — deliberate, not
            // decorative): see docs/theme-builder-motion/TEMPLATE_MOTION_CONTRACT.md.
            // Stable asset_id references only; render nothing until an admin
            // assigns the package via admin/motion-graphics/{site}/themes.
            $this->block('module:motion', ['motion_asset_id' => 'restaurant-hero-sizzle', 'placement' => 'hero']),
            $this->block('features_grid', ['title' => 'چرا رستوران ما؟', 'columns' => 3]),
            $this->block('gallery', ['title' => 'فضای رستوران و غذاهای ما']),
            $this->block('module:shop_product_grid', ['title' => 'منوی پیشنهادی', 'limit' => 8, 'columns' => '4', 'source' => 'featured']),
            $this->block('promo_banner', ['text' => 'رزرو گروهی بالای ۶ نفر — ۱۵٪ تخفیف ویژه', 'style' => 'info']),
            $this->block('stats_row', ['stats' => [
                ['label' => 'آیتم منو',   'value' => '۱۸۰+'],
                ['label' => 'سرآشپز',    'value' => '۱۲'],
                ['label' => 'سال تجربه', 'value' => '۱۴'],
                ['label' => 'مهمان راضی','value' => '۶۰,۰۰۰+'],
            ]]),
            $this->block('testimonials_slider', ['title' => 'نظر مهمانان ما']),
            $this->block('contact_split', [
                'title' => 'رزرو میز و آدرس ما', 'subtitle' => 'برای رزرو یا سوال، با ما در ارتباط باشید',
                'address' => 'تهران، خیابان ولیعصر، پلاک ۱۲۰', 'phone' => '۰۲۱-۸۸۷۷۶۶۵۵',
                'hours' => 'همه‌روزه ۱۲ تا ۲۴', 'show_form' => true,
            ]),
            $this->block('module:motion', ['motion_asset_id' => 'restaurant-cta-reserve', 'placement' => 'cta']),
            $this->block('cta', ['title' => 'میز خود را همین حالا رزرو کنید', 'button_text' => 'رزرو آنلاین', 'button_url' => '/contact']),
        ];
        $this->create(
            'رستوران پریمیوم', 'restaurant-premium', Theme::CATEGORY_RESTAURANT, 290000,
            'قالب رستورانی پریمیوم با منوی محصول‌محور، گالری فضا و رزرو آنلاین. مناسب رستوران‌های لوکس، فاین‌داینینگ، کافه‌های بزرگ و سرویس کیترینگ.',
            ['shop', 'forms'],
            $this->styles($colors['primary'], '#451a03', $colors['bg'], '#451a03'),
            $this->navHeader('#451a03', '#fff'),
            $this->richFooter('#451a03', '#fff'),
            $this->buildStandardPages($homeBlocks, 'رستوران پریمیوم', $colors),
            ['annual_price' => 240000, 'lifetime_price' => 490000,
             'category_tags' => ['رستوران', 'کافه', 'فاین‌داینینگ', 'پریمیوم'],
             'is_new_release' => true,
             'screenshots' => $this->screenshots('restaurant-premium', '#92400e'),
             'requirements' => ['shop' => 'نمایش منو به‌صورت محصول‌محور و سفارش آنلاین', 'forms' => 'رزرو میز و درخواست مهمانی خصوصی'],
             'version_notes' => 'نسخه اول — دو بلوک موشن گرافیک (hero/cta)، گالری فضا، منوی محصول‌محور؛ نسخه پریمیوم در کنار رستوران لوکس رایگان'],
            ['motion' => [
                'package_reference' => 'theme:restaurant-premium',
                'mode' => 'bundled',
                'required_assets' => [
                    ['asset_id' => 'restaurant-hero-sizzle'],
                    ['asset_id' => 'restaurant-cta-reserve'],
                ],
            ]]
        );
    }

    private function seedRealEstatePremium(): void
    {
        $colors = ['primary' => '#0c4a6e', 'bg' => '#f0f9ff'];
        $homeBlocks = [
            $this->block('hero', ['title' => 'ملک رویایی خود را کشف کنید', 'subtitle' => 'مشاوره تخصصی خرید، فروش و اجاره املاک لوکس', 'background' => 'primary']),
            $this->block('module:motion', ['motion_asset_id' => 'realestate-hero-tour', 'placement' => 'hero']),
            $this->block('search_bar', ['placeholder' => 'جستجوی ملک بر اساس شهر، متراژ یا محدوده قیمت...']),
            $this->block('features_grid', ['title' => 'چرا آژانس ما؟', 'columns' => 3]),
            $this->block('module:shop_product_grid', ['title' => 'املاک ویژه', 'limit' => 6, 'columns' => '3', 'source' => 'featured']),
            $this->block('stats_row', ['stats' => [
                ['label' => 'ملک فعال',    'value' => '۳,۵۰۰+'],
                ['label' => 'معامله موفق', 'value' => '۲,۱۰۰+'],
                ['label' => 'مشاور',       'value' => '۲۵'],
                ['label' => 'شهر تحت پوشش','value' => '۱۸'],
            ]]),
            $this->block('team_cards', ['title' => 'مشاوران املاک ما', 'columns' => '3', 'members' => json_encode([
                ['name' => 'محمد رستمی', 'role' => 'مشاور ارشد املاک مسکونی', 'image' => '', 'bio' => 'بیش از ۱۲ سال تجربه در معاملات مسکونی منطقه ۱ و ۲'],
                ['name' => 'سارا کریمی', 'role' => 'مشاور املاک تجاری',       'image' => '', 'bio' => 'متخصص خرید و اجاره واحدهای تجاری و اداری'],
                ['name' => 'امیر یزدانی','role' => 'مشاور سرمایه‌گذاری ملکی', 'image' => '', 'bio' => 'تحلیل بازار مسکن و مشاوره سرمایه‌گذاری بلندمدت'],
            ], JSON_UNESCAPED_UNICODE)]),
            $this->block('testimonials_slider', ['title' => 'نظر مشتریان']),
            $this->block('module:motion', ['motion_asset_id' => 'realestate-cta-inquire', 'placement' => 'cta']),
            $this->block('contact_split', [
                'title' => 'درخواست مشاوره ملک', 'subtitle' => 'فرم زیر را پر کنید تا مشاور ما با شما تماس بگیرد',
                'address' => 'تهران، بلوار میرداماد، برج آژانس', 'phone' => '۰۲۱-۲۲۳۳۴۴۵۵',
                'hours' => 'شنبه تا پنجشنبه، ۹ تا ۲۰', 'show_form' => true,
            ]),
            $this->block('cta', ['title' => 'به دنبال ملک ایده‌آل خود هستید؟', 'button_text' => 'درخواست مشاوره رایگان', 'button_url' => '/contact']),
        ];
        $this->create(
            'آژانس مسکن پریمیوم', 'real-estate-premium', Theme::CATEGORY_REAL_ESTATE, 390000,
            'قالب املاک پریمیوم با نمایش ملک محصول‌محور، بخش مشاوران و ساختار آماده جستجو/فیلتر. مناسب آژانس‌های مسکن بزرگ، مشاوران سرمایه‌گذاری ملکی و توسعه‌دهندگان.',
            ['forms', 'blog'],
            $this->styles($colors['primary'], '#082f49', $colors['bg'], '#0c4a6e'),
            $this->navHeader('#082f49', '#fff'),
            $this->richFooter('#082f49', '#fff'),
            $this->buildStandardPages($homeBlocks, 'آژانس مسکن پریمیوم', $colors),
            ['annual_price' => 320000, 'lifetime_price' => 650000,
             'category_tags' => ['املاک', 'مسکن', 'لوکس', 'پریمیوم'],
             'is_new_release' => true, 'is_featured' => true,
             'screenshots' => $this->screenshots('real-estate-premium', '#0c4a6e'),
             'requirements' => ['forms' => 'فرم درخواست مشاوره و بازدید ملک', 'blog' => 'راهنمای خرید و تحلیل بازار مسکن'],
             'version_notes' => 'نسخه اول — دو بلوک موشن گرافیک (hero/cta)، بخش مشاوران با team_cards، ساختار آماده جستجو/فیلتر؛ نسخه پریمیوم در کنار آژانس مسکن رایگان'],
            ['motion' => [
                'package_reference' => 'theme:real-estate-premium',
                'mode' => 'bundled',
                'required_assets' => [
                    ['asset_id' => 'realestate-hero-tour'],
                    ['asset_id' => 'realestate-cta-inquire'],
                ],
            ]]
        );
    }

    // ══════════════════════════════════════════════════════════════════════
    //  STANDARD PAGE BUILDER
    // ══════════════════════════════════════════════════════════════════════

    private function buildStandardPages(array $homeBlocks, string $themeName, array $colors): array
    {
        $primary = $colors['primary'];

        return [
            'home' => [
                'slug' => '', 'title' => 'صفحه اصلی',
                'blocks' => $homeBlocks,
            ],
            'about' => [
                'slug' => 'about', 'title' => 'درباره ما',
                'blocks' => [
                    $this->block('hero', ['title' => 'درباره ' . $themeName, 'subtitle' => 'آشنایی با تیم و ارزش‌های ما', 'background' => 'primary']),
                    $this->block('heading', ['level' => 2, 'text' => 'داستان ما']),
                    $this->block('text', ['content' => 'ما با هدف ارائه بهترین خدمات تاسیس شده‌ایم. تیمی متخصص و پرتجربه در کنار شماست.']),
                    $this->block('stats_row', ['stats' => [
                        ['label' => 'سال تجربه',    'value' => '۱۰+'],
                        ['label' => 'مشتری راضی',   'value' => '۵,۰۰۰+'],
                        ['label' => 'پروژه موفق',   'value' => '۸۰۰+'],
                        ['label' => 'متخصص',        'value' => '۴۵'],
                    ]]),
                    $this->block('team', ['title' => 'تیم ما', 'limit' => 6]),
                    $this->block('testimonials_slider', ['title' => 'نظر مشتریان']),
                    $this->block('cta', ['title' => 'با ما همکاری کنید', 'button_text' => 'تماس بگیرید', 'button_url' => '/contact']),
                ],
            ],
            'contact' => [
                'slug' => 'contact', 'title' => 'تماس با ما',
                'blocks' => [
                    $this->block('hero', ['title' => 'تماس با ما', 'subtitle' => 'آماده پاسخگویی هستیم', 'background' => 'primary']),
                    $this->block('contact_split', [
                        'title'   => 'اطلاعات تماس',
                        'address' => 'تهران، خیابان ولیعصر',
                        'phone'   => '۰۲۱-۱۲۳۴۵۶۷۸',
                        'email'   => 'info@example.com',
                    ]),
                    $this->block('faq', ['title' => 'سوالات متداول', 'items' => [
                        ['question' => 'ساعات کاری چه زمانی است؟', 'answer' => 'شنبه تا چهارشنبه ۸ تا ۱۷.'],
                        ['question' => 'مشاوره رایگان دارید؟', 'answer' => 'بله، اولین جلسه رایگان است.'],
                        ['question' => 'نحوه پیگیری درخواست؟', 'answer' => 'کد پیگیری پس از ثبت ارسال می‌شود.'],
                    ]]),
                    $this->block('cta', ['title' => 'همین الان پیام بفرستید', 'button_text' => 'ارسال پیام', 'button_url' => '#contact-form']),
                ],
            ],
            'blog' => [
                'slug' => 'blog', 'title' => 'وبلاگ',
                'blocks' => [
                    $this->block('hero', ['title' => 'وبلاگ', 'subtitle' => 'آخرین مطالب و اخبار', 'background' => 'primary']),
                    $this->block('module:blog_posts', ['title' => 'تمام مطالب', 'limit' => 9, 'layout' => 'grid']),
                    $this->block('email_capture', ['title' => 'عضویت در خبرنامه', 'button_text' => 'عضو می‌شوم']),
                ],
            ],
            'archive' => [
                'slug' => 'archive', 'title' => 'آرشیو',
                'blocks' => [
                    $this->block('breadcrumb', []),
                    $this->block('heading', ['level' => 1, 'text' => 'آرشیو مطالب']),
                    $this->block('module:blog_posts', ['title' => '', 'limit' => 12, 'layout' => 'grid']),
                    $this->block('module:blog_categories', ['title' => 'دسته‌بندی‌ها', 'style' => 'badges', 'limit' => 12]),
                ],
            ],
            'single' => [
                'slug' => 'single', 'title' => 'مقاله',
                'blocks' => [
                    $this->block('breadcrumb', []),
                    $this->block('heading', ['level' => 1, 'text' => 'عنوان مقاله']),
                    $this->block('text', ['content' => 'محتوای کامل مقاله اینجا نمایش داده می‌شود.']),
                    $this->block('divider', []),
                    $this->block('module:blog_posts', ['title' => 'مطالب مرتبط', 'limit' => 3, 'layout' => 'grid']),
                ],
            ],
            'error' => [
                'slug' => '404', 'title' => 'صفحه یافت نشد',
                'blocks' => [
                    $this->block('hero', [
                        'title'       => '۴۰۴ — صفحه یافت نشد',
                        'subtitle'    => 'این صفحه وجود ندارد یا منتقل شده است.',
                        'button_text' => 'بازگشت به خانه',
                        'button_url'  => '/',
                        'background'  => 'primary',
                    ]),
                    $this->block('module:blog_posts', ['title' => 'مطالب پیشنهادی', 'limit' => 3, 'layout' => 'grid']),
                ],
            ],
            'search' => [
                'slug' => 'search', 'title' => 'جستجو',
                'blocks' => [
                    $this->block('hero', ['title' => 'جستجو', 'subtitle' => 'در تمام محتوا و محصولات جستجو کنید', 'background' => 'primary']),
                    $this->block('search_bar', ['placeholder' => 'جستجو در سایت...']),
                    $this->block('module:blog_posts', ['title' => 'مطالب مرتبط', 'limit' => 6, 'layout' => 'grid']),
                    $this->block('module:shop_product_grid', ['title' => 'محصولات مرتبط', 'limit' => 4, 'columns' => '4']),
                ],
            ],
        ];
    }

    // ══════════════════════════════════════════════════════════════════════
    //  CORE CREATE HELPER
    // ══════════════════════════════════════════════════════════════════════

    private function create(
        string $name, string $slug, string $category, int $price,
        string $description, array $modules,
        array $globalStyles, array $headerBlocks, array $footerBlocks, array $pages,
        array $marketplaceMeta = [],
        array $settingsExtra = []
    ): void {
        if (Theme::where('slug', $slug)->exists()) {
            $this->line("  ↺  {$name} — موجود، رد شد.");
            $this->skipped[] = $slug;
            return;
        }

        $primaryColor = $globalStyles['primary_color'] ?? '#2c3e50';
        $thumbnailHex = ltrim($primaryColor, '#');
        $thumbnail    = "https://placehold.co/400x300/{$thumbnailHex}/ffffff/png?text=" . rawurlencode($name);
        $previewImage = "https://placehold.co/1200x800/{$thumbnailHex}/ffffff/png?text=" . rawurlencode($name . ' Preview');

        $theme = Theme::create([
            'name'              => $name,
            'slug'              => $slug,
            'description'       => $description,
            'thumbnail'         => $thumbnail,
            'preview_image'     => $previewImage,
            'type'              => Theme::TYPE_FULL,
            'source'            => Theme::SOURCE_SEEDER,
            'site_id'           => null,
            'status'            => Theme::STATUS_PUBLISHED,
            'is_marketplace'    => true,
            'marketplace_price' => $price,
            'license_type'      => $price > 0 ? Theme::LICENSE_PREMIUM : Theme::LICENSE_FREE,
            'category'          => $category,
            'version'           => 1,
            'global_styles'     => $globalStyles,
            'header_blocks'     => $headerBlocks,
            'footer_blocks'     => $footerBlocks,
            'pages'             => $pages,
            'settings'          => array_merge([
                'version'           => '1.0.0',
                'release_date'      => now()->toDateString(),
                'supported_modules' => $modules,
                'changelog'         => ['1.0.0' => ['نسخه اولیه ' . $name]],
            ], $settingsExtra),
        ]);

        ThemeMarketplace::updateOrCreate(['theme_id' => $theme->id], [
            'marketplace_description' => $description,
            'is_featured'   => $marketplaceMeta['is_featured']   ?? false,
            'is_new_release'=> $marketplaceMeta['is_new_release'] ?? false,
            'is_bestseller' => $marketplaceMeta['is_bestseller']  ?? false,
            'annual_price'  => $marketplaceMeta['annual_price']   ?? 0,
            'lifetime_price'=> $marketplaceMeta['lifetime_price'] ?? 0,
            'preview_url'   => $marketplaceMeta['preview_url']    ?? '',
            'category_tags' => $marketplaceMeta['category_tags']  ?? [],
            'screenshots'   => $marketplaceMeta['screenshots']    ?? [],
            'requirements'  => $marketplaceMeta['requirements']   ?? [],
            'version_notes' => $marketplaceMeta['version_notes']  ?? '',
            'supported_modules_json' => json_encode($modules),
            'sales_count'   => 0,
            'has_demo_data' => true,
        ]);

        $pageCount = count($pages);
        $this->line("  ✔  {$name} ({$category}) — {$pageCount} صفحه ثبت شد.");
        $this->seeded[] = $slug;
    }

    // ══════════════════════════════════════════════════════════════════════
    //  BLOCK / STYLE HELPERS
    // ══════════════════════════════════════════════════════════════════════

    private function block(string $type, array $props = []): array
    {
        return ['id' => 'blk_' . Str::random(8), 'type' => $type, 'props' => $props];
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
            'logo_text'  => 'لوگو',
            'bg_color'   => $bg,
            'text_color' => $textColor,
            'sticky'     => true,
            'nav_links'  => [
                ['label' => 'خانه',     'url' => '/'],
                ['label' => 'خدمات',    'url' => '/services'],
                ['label' => 'درباره ما','url' => '/about'],
                ['label' => 'وبلاگ',    'url' => '/blog'],
                ['label' => 'تماس',     'url' => '/contact'],
            ],
        ])];
    }

    private function richFooter(string $bg, string $textColor): array
    {
        return [$this->block('nav_footer', [
            'bg_color'      => $bg,
            'text_color'    => $textColor,
            'copyright'     => 'تمام حقوق محفوظ است.',
            'show_socials'  => true,
            'columns'       => [
                ['title' => 'لینک‌های سریع', 'links' => [
                    ['label' => 'خانه',   'url' => '/'],
                    ['label' => 'درباره', 'url' => '/about'],
                    ['label' => 'تماس',  'url' => '/contact'],
                ]],
                ['title' => 'خدمات', 'links' => [
                    ['label' => 'محصولات', 'url' => '/shop'],
                    ['label' => 'وبلاگ',   'url' => '/blog'],
                    ['label' => 'پشتیبانی','url' => '/tickets'],
                ]],
            ],
        ])];
    }

    private function screenshots(string $slug, string $color): array
    {
        $hex = ltrim($color, '#');
        $enc = rawurlencode($slug);
        return [
            "https://placehold.co/1280x800/{$hex}/ffffff/png?text={$enc}-home",
            "https://placehold.co/1280x800/{$hex}/ffffff/png?text={$enc}-about",
            "https://placehold.co/1280x800/{$hex}/ffffff/png?text={$enc}-contact",
        ];
    }
}
