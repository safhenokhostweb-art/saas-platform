<?php

namespace SaaSPlatform\ThemeRuntime;

/**
 * Canonical, host-neutral authoring contract for the SleepShiman Home blue
 * commercial theme. Domain access stays in customer-site data sources; these
 * definitions only describe safe manual content and allowlisted bindings.
 */
final class SleepShimanBlockTypes
{
    /** @return array<string,array<string,mixed>> */
    public static function definitions(): array
    {
        $text = fn (string $label, string $default = '') => ['type' => 'string', 'label' => $label, 'default' => $default];
        $link = fn (string $label, string $default = '#') => ['type' => 'link', 'label' => $label, 'default' => $default];
        $image = fn (string $label, string $default = '') => ['type' => 'image', 'label' => $label, 'default' => $default];
        $repeater = fn (string $label, array $fields, array $default = []) => [
            'type' => 'repeater', 'label' => $label, 'storage' => 'array', 'fields' => $fields, 'default' => $default,
        ];

        $productFields = [
            'image' => $image('تصویر محصول', 'images/mattress-room.webp'),
            'image_alt' => $text('متن جایگزین تصویر', 'تشک کالای خواب'),
            'title' => $text('نام محصول', 'تشک دو نفره اسمالینا نخی'),
            'brand' => $text('برند', 'Smalina'),
            'badge' => $text('نشان تخفیف', '٪۳۰ تخفیف'),
            'size' => $text('اندازه', '۱۶۰ × ۲۰۰'),
            'weight' => $text('وزن', '۲ کیلو'),
            'material' => $text('جنس', ''),
            'rating' => $text('امتیاز', '۴.۵'),
            'price_text' => $text('قیمت', '۷۰۰٬۰۰۰ تومان'),
            'old_price_text' => $text('قیمت قبلی', '۷۶۰٬۰۰۰ تومان'),
            'cta_label' => $text('متن دکمه', 'مشاهده'),
            'url' => $link('پیوند محصول', '/shop'),
        ];
        $productDefaults = self::productDefaults();
        $productSchema = [
            'title' => $text('عنوان سکشن'),
            'subtitle' => $text('زیرعنوان سکشن'),
            'view_all_label' => $text('متن مشاهده همه', 'مشاهده همه'),
            'view_all_url' => $link('پیوند مشاهده همه', '/shop'),
            'items' => $repeater('محصولات دستی', $productFields, $productDefaults),
        ];
        $productBinding = ['source' => 'products', 'manual_prop' => 'items', 'default_sort' => 'catalog_order'];
        $featuredFields = array_merge($productFields, [
            'subtitle' => $text('توضیح', 'کیفیت و لطافت برای خوابی آسوده'),
            'sales' => $text('فروش', '۴۲۱'),
            'satisfaction' => $text('رضایت', '۹۵٪'),
        ]);
        $mixedProductSchema = array_merge($productSchema, [
            'feature_image' => $image('تصویر کارت بزرگ', 'images/comforter.webp'),
            'feature_image_alt' => $text('متن جایگزین کارت بزرگ', 'پتوهای خوشخواب'),
            'feature_title' => $text('عنوان کارت بزرگ', 'پتوهای خوشخواب'),
            'feature_subtitle' => $text('زیرعنوان کارت بزرگ', 'مشاهده محصولات'),
            'feature_url' => $link('پیوند کارت بزرگ', '/shop'),
        ]);

        $definitions = [
            'sleepshiman_header' => [
                'schema' => [
                    'logo' => $image('نشان برند', 'images/logo-mark.png'),
                    'logo_alt' => $text('متن جایگزین لوگو', 'SleepShiman'),
                    'brand' => $text('نام برند', 'SleepShiman'),
                    'tagline' => $text('زیرعنوان برند', 'کالای خواب شیمانا'),
                    'account_label' => $text('برچسب حساب', 'حساب کاربری'),
                    'account_url' => $link('پیوند حساب', '/login'),
                    'cart_label' => $text('برچسب سبد', 'سبد خرید'),
                    'cart_url' => $link('پیوند سبد', '/cart'),
                    'search_placeholder' => $text('راهنمای جست‌وجو', 'جست‌وجوی محصول'),
                    'search_url' => $link('مسیر جست‌وجو', '/shop'),
                ],
                'defaultProps' => self::headerDefaults(),
            ],
            'sleepshiman_navigation' => [
                'schema' => [
                    'location_label' => $text('عنوان نشانی', 'انتخاب نشانی'),
                    'location_text' => $text('نشانی', 'اصفهان'),
                    'items' => $repeater('پیوندهای منو', [
                        'label' => $text('عنوان'), 'url' => $link('پیوند'), 'badge' => $text('نشان'),
                    ], self::navigationDefaults()['items']),
                ],
                'defaultProps' => self::navigationDefaults(), 'liveStructureUpdate' => 'document',
            ],
            'sleepshiman_quick_categories' => [
                'schema' => ['items' => $repeater('دسته‌ها', [
                    'title' => $text('عنوان'), 'subtitle' => $text('توضیح'), 'icon' => $text('شناسه آیکن', 'bed'), 'url' => $link('پیوند'),
                ], self::quickCategoryDefaults())],
                'defaultProps' => ['items' => self::quickCategoryDefaults()],
                'dataBinding' => ['source' => 'product_categories', 'manual_prop' => 'items', 'default_sort' => 'catalog_order'],
                'modulePresentation' => ['capability' => 'product_categories.collection', 'label' => 'دسته‌های سریع SleepShiman'],
                'liveStructureUpdate' => 'document',
            ],
            'sleepshiman_featured_product' => [
                'schema' => ['items' => $repeater('محصول ویژهٔ دستی', $featuredFields, [self::featuredDefaults()])],
                'defaultProps' => ['items' => [self::featuredDefaults()]],
                'dataBinding' => ['source' => 'products', 'manual_prop' => 'items', 'default_sort' => 'manual'],
                'modulePresentation' => ['capability' => 'products.collection', 'label' => 'محصول ویژه SleepShiman'],
                'liveStructureUpdate' => 'document',
            ],
            'sleepshiman_services' => [
                'schema' => ['items' => $repeater('خدمت‌ها', [
                    'icon' => $text('شناسه آیکن', 'support'), 'title' => $text('عنوان'), 'subtitle' => $text('توضیح'),
                ], self::serviceDefaults())],
                'defaultProps' => ['items' => self::serviceDefaults()], 'liveStructureUpdate' => 'document',
            ],
            'sleepshiman_amazing_offers' => [
                'schema' => array_merge($productSchema, ['illustration' => $image('تصویر تزئینی', 'images/sleep-illustration.webp')]),
                'defaultProps' => ['title' => 'پیشنهاد شگفت‌انگیز', 'subtitle' => 'انتخابی ویژه برای خواب بهتر', 'illustration' => 'images/sleep-illustration.webp', 'items' => $productDefaults],
                'dataBinding' => ['source' => 'products', 'manual_prop' => 'items', 'default_sort' => 'latest'],
                'modulePresentation' => ['capability' => 'products.collection', 'label' => 'پیشنهاد شگفت‌انگیز SleepShiman'],
                'liveStructureUpdate' => 'document',
            ],
            'sleepshiman_mattresses' => self::productDefinition($productSchema, $productBinding, 'تشک‌های حرفه‌ای', 'خرید تشک', $productDefaults),
            'sleepshiman_promo_banners' => [
                'schema' => ['items' => $repeater('بنرها', [
                    'title' => $text('عنوان'), 'subtitle' => $text('توضیح'), 'image' => $image('تصویر'),
                    'image_alt' => $text('متن جایگزین'), 'url' => $link('پیوند'), 'tone' => $text('رنگ‌بندی', 'green'),
                ], self::promoDefaults())],
                'defaultProps' => ['items' => self::promoDefaults()], 'liveStructureUpdate' => 'document',
            ],
            'sleepshiman_bedding_products' => self::productDefinition($mixedProductSchema, $productBinding, 'محصولات روی تخت', 'کالای خواب', self::beddingDefaults(), self::mixedFeatureDefaults('پتوهای خوشخواب', 'پیشنهاد شیمانا')),
            'sleepshiman_blanket_products' => self::productDefinition($mixedProductSchema, $productBinding, 'محصولات پتو', 'پتو', self::blanketDefaults(), self::mixedFeatureDefaults('پتوهای خوشخواب', 'مشاهده محصولات')),
            'sleepshiman_sleep_banner' => [
                'schema' => ['title' => $text('عنوان'), 'subtitle' => $text('توضیح'), 'image' => $image('تصویر'), 'image_alt' => $text('متن جایگزین'), 'url' => $link('پیوند')],
                'defaultProps' => ['title' => 'یه خواب خوب با کیفیت عالی!', 'subtitle' => 'به‌راحتی می‌توانید درخواست خود را ثبت کنید.', 'image' => 'images/sleep-illustration.webp', 'image_alt' => 'تصویر خواب آرام', 'url' => '/shop'],
            ],
            'sleepshiman_brands' => [
                'schema' => ['items' => $repeater('نشان‌های برند', [
                    'image' => $image('تصویر نشان'), 'image_alt' => $text('متن جایگزین'), 'url' => $link('پیوند'),
                ], self::brandDefaults())],
                'defaultProps' => ['items' => self::brandDefaults()], 'liveStructureUpdate' => 'document',
            ],
            'sleepshiman_published_products' => self::productDefinition($productSchema, $productBinding, 'محصولات منتشرشده', 'محصولات کالای خواب', $productDefaults),
            'sleepshiman_topic_cards' => [
                'schema' => ['title' => $text('عنوان سکشن'), 'subtitle' => $text('زیرعنوان'), 'items' => $repeater('کارت‌ها', [
                    'title' => $text('عنوان'), 'description' => $text('توضیح'), 'image' => $image('تصویر'), 'image_alt' => $text('متن جایگزین'), 'url' => $link('پیوند'),
                ], self::topicDefaults())],
                'defaultProps' => ['title' => 'راهکارهای خواب بهتر', 'subtitle' => 'متناسب با نیاز شما', 'items' => self::topicDefaults()], 'liveStructureUpdate' => 'document',
            ],
            'sleepshiman_articles' => [
                'schema' => ['title' => $text('عنوان سکشن', 'مقالات'), 'subtitle' => $text('زیرعنوان', 'مجلهٔ خواب'), 'view_all_url' => $link('پیوند مشاهده همه', '/blog'),
                    'feature_title' => $text('عنوان مقالهٔ ویژه', 'چند روش برای بهتر و راحت‌تر خوابیدن!'),
                    'feature_kicker' => $text('برچسب مقالهٔ ویژه', 'خواب بهتر'),
                    'feature_image' => $image('تصویر مقالهٔ ویژه', 'images/article-night.webp'),
                    'feature_image_alt' => $text('متن جایگزین مقالهٔ ویژه', 'فردی در خواب آرام'),
                    'feature_url' => $link('پیوند مقالهٔ ویژه', '/blog'),
                    'items' => $repeater('مقاله‌ها', [
                        'title' => $text('عنوان'), 'excerpt' => $text('خلاصه'), 'image' => $image('تصویر'), 'image_alt' => $text('متن جایگزین'), 'url' => $link('پیوند'), 'date_text' => $text('تاریخ'),
                    ], self::articleDefaults())],
                'defaultProps' => ['title' => 'مقالات', 'subtitle' => 'مجلهٔ خواب', 'view_all_url' => '/blog', 'feature_title' => 'چند روش برای بهتر و راحت‌تر خوابیدن!', 'feature_kicker' => 'خواب بهتر', 'feature_image' => 'images/article-night.webp', 'feature_image_alt' => 'فردی در خواب آرام', 'feature_url' => '/blog', 'items' => self::articleDefaults()],
                'dataBinding' => ['source' => 'blog_posts', 'manual_prop' => 'items', 'default_sort' => 'latest'],
                'modulePresentation' => ['capability' => 'blog.posts', 'label' => 'مقالات SleepShiman'],
                'liveStructureUpdate' => 'document',
            ],
            'sleepshiman_editorial' => [
                'schema' => ['body' => $text('متن'), 'cta_label' => $text('متن دکمه'), 'cta_url' => $link('پیوند'), 'image' => $image('تصویر'), 'image_alt' => $text('متن جایگزین')],
                'defaultProps' => ['body' => 'در شیمانا، راهنمای خرید کالای خواب را بر اساس اندازهٔ تخت، نوع خواب و نیاز بدن شما آماده کرده‌ایم.', 'cta_label' => 'مطالعه بیشتر', 'cta_url' => '/blog', 'image' => 'images/article-night.webp', 'image_alt' => 'خواب آرام در اتاقی تاریک'],
            ],
            'sleepshiman_reviews' => [
                'schema' => ['title' => $text('عنوان سکشن', 'تجربه مشتریان'), 'subtitle' => $text('زیرعنوان', 'دیدگاه‌ها'),
                    'items' => $repeater('دیدگاه‌ها', [
                        'author' => $text('نام'), 'role' => $text('نقش'), 'rating' => $text('امتیاز'), 'body' => $text('متن دیدگاه'), 'avatar' => $image('تصویر'), 'avatar_alt' => $text('متن جایگزین'),
                        'helpful_count' => $text('رأی مفید', '۱۱'), 'not_helpful_count' => $text('رأی نامفید', '۰'),
                    ], self::reviewDefaults())],
                'defaultProps' => ['title' => 'کمپین تجربه شما', 'subtitle' => 'دیدگاه مشتریان', 'items' => self::reviewDefaults()],
                'dataBinding' => ['source' => 'reviews', 'manual_prop' => 'items', 'default_sort' => 'latest'],
                'modulePresentation' => ['capability' => 'reviews.collection', 'label' => 'دیدگاه‌های SleepShiman'],
                'liveStructureUpdate' => 'document',
            ],
            'sleepshiman_guide_intro' => [
                'schema' => ['title' => $text('عنوان'), 'subtitle' => $text('توضیح')],
                'defaultProps' => ['title' => 'همیشه یه راه حل بهتری هست!', 'subtitle' => 'با راهنمای خرید، انتخاب کالای خواب مناسب را برای شما ساده‌تر می‌کنیم.'],
            ],
            'sleepshiman_guide_cards' => [
                'schema' => ['items' => $repeater('راهنماها', [
                    'title' => $text('عنوان'), 'image' => $image('تصویر'), 'image_alt' => $text('متن جایگزین'), 'url' => $link('پیوند'),
                ], self::guideDefaults())],
                'defaultProps' => ['items' => self::guideDefaults()], 'liveStructureUpdate' => 'document',
            ],
            'sleepshiman_product_filters' => [
                'schema' => ['title' => $text('عنوان'), 'subtitle' => $text('زیرعنوان'), 'groups' => $repeater('گروه‌های انتخاب', [
                    'title' => $text('عنوان گروه'), 'options' => $repeater('گزینه‌ها', ['label' => $text('عنوان'), 'url' => $link('پیوند')]),
                ], self::filterDefaults())],
                'defaultProps' => ['title' => 'انتخاب تشک مناسب', 'subtitle' => 'بر اساس نیاز شما', 'groups' => self::filterDefaults()], 'liveStructureUpdate' => 'document',
            ],
            'sleepshiman_stats' => [
                'schema' => ['items' => $repeater('آمار', ['value' => $text('مقدار'), 'label' => $text('عنوان')], self::statDefaults())],
                'defaultProps' => ['items' => self::statDefaults()], 'liveStructureUpdate' => 'document',
            ],
            'sleepshiman_footer' => [
                'schema' => [
                    'brand' => $text('نام برند', 'SleepShiman'), 'description' => $text('معرفی کوتاه'),
                    'address' => $text('نشانی'), 'phone' => $text('تلفن'), 'phone_url' => $link('پیوند تلفن', 'tel:03132224510'),
                    'columns' => $repeater('ستون‌های فوتر', [
                        'heading' => $text('عنوان ستون'),
                        'links' => $repeater('پیوندها', ['label' => $text('عنوان'), 'url' => $link('پیوند')]),
                    ], self::footerDefaults()['columns']),
                    'newsletter_heading' => $text('عنوان خبرنامه'), 'newsletter_placeholder' => $text('توضیح خبرنامه'), 'newsletter_button' => $text('متن دکمه'), 'newsletter_url' => $link('پیوند فرم خبرنامه', '/contact'),
                    'trust_marks' => $repeater('نشان‌های اعتماد', [
                        'image' => $image('تصویر نشان'), 'image_alt' => $text('متن جایگزین نشان'), 'label' => $text('عنوان نشان'), 'url' => $link('پیوند نشان'),
                    ], self::footerDefaults()['trust_marks']),
                    'copyright' => $text('کپی‌رایت'),
                ],
                'defaultProps' => self::footerDefaults(), 'liveStructureUpdate' => 'document',
            ],
        ];

        foreach ($definitions as $type => &$definition) {
            $definition = ['name' => $type, 'category' => 'sleepshiman'] + $definition;
        }

        return $definitions;
    }

    public static function register(): void
    {
        foreach (self::definitions() as $type => $definition) {
            BlockRegistry::registerHostType($type, $definition);
        }
    }

    /** @return array<string,mixed> */
    private static function productDefinition(array $schema, array $binding, string $title, string $subtitle, array $items, array $extra = []): array
    {
        return ['schema' => $schema, 'defaultProps' => array_merge(compact('title', 'subtitle', 'items'), $extra), 'dataBinding' => $binding,
            'modulePresentation' => ['capability' => 'products.collection', 'label' => $title], 'liveStructureUpdate' => 'document'];
    }

    public static function headerDefaults(): array { return ['logo' => 'images/logo-mark.png', 'logo_alt' => 'نشان SleepShiman', 'brand' => 'SleepShiman', 'tagline' => 'کالای خواب شیمانا', 'account_label' => 'حساب کاربری', 'account_url' => '/login', 'cart_label' => 'سبد خرید', 'cart_url' => '/cart', 'search_placeholder' => 'جست‌وجوی محصول', 'search_url' => '/shop']; }
    public static function navigationDefaults(): array { return ['location_label' => 'انتخاب نشانی', 'location_text' => 'اصفهان', 'items' => [['label' => 'دسته‌بندی محصولات', 'url' => '/shop', 'badge' => ''], ['label' => 'پرفروش‌ترین‌ها', 'url' => '/shop', 'badge' => '٪۲۵'], ['label' => 'مقالات', 'url' => '/blog', 'badge' => ''], ['label' => 'نحوه ارسال', 'url' => '/delivery', 'badge' => ''], ['label' => 'تماس با ما', 'url' => '/contact', 'badge' => ''], ['label' => 'درباره ما', 'url' => '/about', 'badge' => '']]]; }
    public static function quickCategoryDefaults(): array { return array_map(fn ($row) => ['title' => $row[0], 'subtitle' => $row[1], 'icon' => $row[2], 'url' => '/shop'], [['تشک','تشک‌های طبی و فنری','bed'],['روتختی','روتختی‌های متنوع','layers'],['پتو','پتوهای نرم و گرم','blanket'],['بالشت','بالشت‌های طبی و الیافی','moon'],['تخت','تخت و باکس','bed'],['ملحفه','ملحفه‌های نخی','layers']]); }
    public static function featuredDefaults(): array { return ['image'=>'images/pillow-green.webp','image_alt'=>'بالشت سبز لطیف','title'=>'بالشت دست‌بافت نخ‌دوز','brand'=>'SleepShiman','badge'=>'٪۳۰ OFF','size'=>'','weight'=>'۲ کیلو','material'=>'نخ کامل','rating'=>'۴.۵','price_text'=>'۴٬۷۵۰٬۰۰۰ تومان','old_price_text'=>'۵٬۲۲۰٬۰۰۰ تومان','cta_label'=>'مشاهده محصول','url'=>'/shop','subtitle'=>'کیفیت و لطافت برای خوابی آسوده','sales'=>'۴۲۱','satisfaction'=>'۹۵٪']; }
    public static function serviceDefaults(): array { return array_map(fn ($r) => ['icon'=>$r[0],'title'=>$r[1],'subtitle'=>$r[2]], [['support','پشتیبانی ۱۲ ساعته','حتی جمعه‌ها'],['badge','برترین فروشگاه کالا','انتخاب باکیفیت'],['card','امکان خرید اقساطی','بدون چک و ضامن'],['return','امکان بازگشت وجه','خرید امن و مطمئن']]); }
    public static function productDefaults(): array { return array_map(fn ($i) => ['image'=>'images/mattress-room.webp','image_alt'=>'تشک دو نفره اسمالینا','title'=>'تشک دو نفره اسمالینا نخی','brand'=>'Smalina','badge'=>'٪۳۰ تخفیف','size'=>'۱۶۰ × ۲۰۰','weight'=>'۲ کیلو','material'=>'','rating'=>'۴.۵','price_text'=>'۷۰۰٬۰۰۰ تومان','old_price_text'=>'۷۶۰٬۰۰۰ تومان','cta_label'=>'مشاهده','url'=>'/shop'], range(1,4)); }
    public static function promoDefaults(): array { return [['title'=>'تشک مدل هیبرید','subtitle'=>'انتخابی برای آسایش بیشتر','image'=>'images/mattress-stack.webp','image_alt'=>'تشک چندلایه','url'=>'/shop','tone'=>'green'],['title'=>'پتو راحت مسافرتی','subtitle'=>'لطافت و سبکی در سفر','image'=>'images/blanket-stack.webp','image_alt'=>'پتوهای مسافرتی','url'=>'/shop','tone'=>'blue']]; }
    public static function beddingDefaults(): array { $i=self::productDefaults(); $i[0]['image']=$i[1]['image']='images/bedroom.webp'; return $i; }
    public static function blanketDefaults(): array { $i=self::productDefaults(); foreach($i as &$p){$p['image']='images/blanket-stack.webp';} return $i; }
    public static function mixedFeatureDefaults(string $title, string $subtitle): array { return ['feature_image'=>'images/comforter.webp','feature_image_alt'=>$title,'feature_title'=>$title,'feature_subtitle'=>$subtitle,'feature_url'=>'/shop']; }
    public static function brandDefaults(): array { $out=[]; foreach(array_merge(range(1,4),range(1,4)) as $i){$out[]=['image'=>'images/brand-'.$i.'.webp','image_alt'=>'برند همکار '.$i,'url'=>'#'];} return $out; }
    public static function topicDefaults(): array { return array_map(fn ($r) => ['title'=>$r[0],'description'=>'راهنمای انتخاب بالش و تشک متناسب با بدن و عادت خواب شما.','image'=>$r[1],'image_alt'=>$r[0],'url'=>'/blog'], [['افزایش کیفیت خواب','images/pillow-sleep.webp'],['خواب راحت','images/blue-sleep.webp'],['گردن‌درد','images/man-sleep.webp'],['کمردرد','images/man-review.webp']]); }
    public static function articleDefaults(): array { return [['title'=>'چند روش برای بهتر و راحت‌تر خوابیدن','excerpt'=>'راهنمایی کاربردی برای ساختن عادت‌های بهتر خواب.','image'=>'images/article-night.webp','image_alt'=>'فردی در خواب آرام','url'=>'/blog','date_text'=>'مجله خواب'],['title'=>'انتخاب تشک مناسب','excerpt'=>'نکات مهم پیش از خرید تشک.','image'=>'images/article-sleeper.webp','image_alt'=>'استراحت روی تشک','url'=>'/blog','date_text'=>'راهنمای خرید'],['title'=>'آرامش پیش از خواب','excerpt'=>'چطور محیط خواب آرام‌تری بسازیم.','image'=>'images/article-night.webp','image_alt'=>'اتاق خواب در شب','url'=>'/blog','date_text'=>'خواب بهتر'],['title'=>'مراقبت از کالای خواب','excerpt'=>'راهنمای نگهداری و شست‌وشو.','image'=>'images/article-sleeper.webp','image_alt'=>'کالای خواب','url'=>'/blog','date_text'=>'نگهداری']]; }
    public static function reviewDefaults(): array { return array_map(fn ($i) => ['author'=>'اشکان رضایی مهر','role'=>'مشتری شیمانا','rating'=>'۴.۵','body'=>'از خرید کالای خواب شیمانا راضی بودم. راهنمایی پیش از خرید و کیفیت محصول، انتخاب را برایم ساده کرد.','avatar'=>'images/team-man.webp','avatar_alt'=>'تصویر مشتری','helpful_count'=>'۱۱','not_helpful_count'=>'۰'], range(1,3)); }
    public static function guideDefaults(): array { return array_map(fn ($r) => ['title'=>$r[0],'image'=>$r[1],'image_alt'=>$r[0],'url'=>'/shop'], [['راهنمای خرید تشک','images/mattress-stack.webp'],['راهنمای خرید پتو','images/blanket-stack.webp'],['راهنمای خرید تخت','images/bed-cutout.webp'],['راهنمای خرید روتختی','images/duvet.webp'],['راهنمای خرید بالش','images/pillow-green.webp']]); }
    public static function filterDefaults(): array { $options=array_map(fn ($x)=>['label'=>$x,'url'=>'/shop'],['ساده و اقتصادی','تا ۵ میلیون','تا ۱۵ میلیون','طبی','فنری','دو نفره']); return [['title'=>'قیمت','options'=>$options],['title'=>'ویژگی محصول','options'=>$options]]; }
    public static function statDefaults(): array { return array_map(fn ($r)=>['value'=>$r[0],'label'=>$r[1]],[['+۵۲K','محصول'],['+۳۱۱','مشتری'],['+۷۲','سفارش روزانه'],['+۶۳','برند'],['+۲۳','سال تجربه'],['+۳۲۳','راهنمای خرید']]); }
    public static function footerDefaults(): array { return ['brand'=>'SleepShiman','description'=>'برای انتخابی مطمئن، مشخصات کالا را بررسی کنید و از راهنمایی پشتیبانی شیمانا کمک بگیرید.','address'=>'اصفهان · انتخاب نشانی','phone'=>'۰۳۱ ۳۲۲۲ ۴۵۱۰','phone_url'=>'tel:03132224510','columns'=>[['heading'=>'راهنمای مشتریان','links'=>[['label'=>'درباره ما','url'=>'/about'],['label'=>'تماس با ما','url'=>'/contact'],['label'=>'ارسال و بازگشت','url'=>'/delivery'],['label'=>'پرسش‌های متداول','url'=>'/faq']]],[ 'heading'=>'محصولات ما','links'=>[['label'=>'دسته‌بندی‌ها','url'=>'/shop'],['label'=>'محصولات','url'=>'/shop'],['label'=>'مجله خواب','url'=>'/blog'],['label'=>'تماس با ما','url'=>'/contact']]]],'newsletter_heading'=>'خبرنامه شیمانا','newsletter_placeholder'=>'ایمیل خود را وارد کنید','newsletter_button'=>'عضویت','newsletter_url'=>'/contact','trust_marks'=>[['image'=>'images/logo-enamad.png','image_alt'=>'نشان اعتماد الکترونیکی','label'=>'نماد اعتماد','url'=>'#'],['image'=>'images/brand-4.webp','image_alt'=>'نشان تجارت الکترونیکی','label'=>'نشان تجارت الکترونیکی','url'=>'#'],['image'=>'images/logo-enamad.png','image_alt'=>'نشان اعتماد الکترونیکی','label'=>'نماد اعتماد','url'=>'#'],['image'=>'images/brand-4.webp','image_alt'=>'نشان تجارت الکترونیکی','label'=>'نشان تجارت الکترونیکی','url'=>'#']],'copyright'=>'تمامی حقوق این فروشگاه برای SleepShiman محفوظ است.']; }
}
