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
        // customer-site deliberately de-exposes these platform-preview-only
        // entries (app/Services/ThemeCanvas/BlockRegistry.php's own unset()
        // calls) because they have no tenant content adapter/model, or are
        // a duplicate authority for a responsibility a real, production
        // renderer already covers. A portable master Theme must not
        // advertise a block a receiving site cannot use.
        //
        // TASK 3/3 Phase 5 (BLD-15): 'products' was missing from this list
        // — real browser certification through customer-site's ACTUAL
        // renderer (not saas-platform's own preview renderer, which still
        // defines it) caught this exact class of defect PHPUnit-only
        // verification cannot: customer-site's real ElementSchemaRegistry
        // rejects 'products' as unknown, rendering a visible "[invalid]
        // products" marker on the installed builder-coverage page.
        // 'module:portfolio_grid'/'module:services_section' render an
        // honest empty string on customer-site rather than an error (no
        // visible defect today), but are excluded too on the same "must
        // not advertise a block the receiving site cannot use" principle —
        // they add no diagnostic value to the coverage page either way.
        // 'team' is deliberately NOT excluded: customer-site keeps a real,
        // graceful "honest placeholder" fallback for it (used intentionally
        // on the real 'team' page's own content, see teamBlocks() below),
        // the same accepted pattern as module:pricing_table/module:form_embed.
        unset($registry['module:gallery_widget']);
        unset($registry['products']);
        unset($registry['module:portfolio_grid']);
        unset($registry['module:services_section']);

        // Part 4 (7-part local audit task, 2026-09-19): status is
        // deliberately NOT part of the unconditional updateOrCreate payload
        // below. A plain updateOrCreate would reset 'status' to draft on
        // every reseed, even for a theme an admin already published through
        // the real ThemeBuilderService::publish() path — silently
        // un-publishing it. firstOrNew + a create-only status assignment
        // keeps this idempotent (no duplicate row) without corrupting
        // already-published state on re-run.
        $theme = Theme::firstOrNew(['slug' => self::SLUG, 'site_id' => null]);
        $wasNew = !$theme->exists;

        $theme->fill([
            'name' => 'قالب جامع چندمنظوره',
            'description' => 'قالب مرجع چندمنظوره برای بررسی یکپارچگی Builder و قابلیت‌های قابل‌نمایش سایت.',
            'type' => Theme::TYPE_FULL,
            'category' => Theme::CATEGORY_CORPORATE,
            'source' => Theme::SOURCE_SEEDER,
            // Commercial Productization fase-3 (visual identity): the
            // platform's generic indigo default (#4f46e5) is what every
            // Theme starts from — leaving it in place is exactly the
            // "collection of healthy blocks, not a distinct commercial
            // product" complaint this phase must close. A deep teal/amber
            // pairing (with a real, distinct dark palette, not just the
            // platform default's lightened primary) gives this theme its
            // own identity while every semantic/derived token still goes
            // through ThemeTokenNormalizer's real WCAG-AA math.
            'global_styles' => array_merge(
                app(ThemeBuilderService::class)->defaultGlobalStyles(),
                [
                    'primary_color' => '#0f766e',
                    'secondary_color' => '#0e7490',
                    'accent_color' => '#f59e0b',
                    'success_color' => '#16a34a',
                    'warning_color' => '#d97706',
                    'error_color' => '#dc2626',
                    'background_color' => '#fdfdfc',
                    'text_color' => '#111827',
                    'dark_mode' => [
                        'enabled' => true,
                        'primary' => '#2dd4bf',
                        'secondary' => '#22d3ee',
                        'accent' => '#fbbf24',
                        'success' => '#4ade80',
                        'warning' => '#fbbf24',
                        'error' => '#f87171',
                        'background' => '#0f172a',
                        'surface' => '#1e293b',
                        'text' => '#f1f5f9',
                        'text_muted' => '#94a3b8',
                    ],
                ]
            ),
            // TASK 3/3 Phase 6.5 (BLD-20): this theme is no longer a
            // test-only fixture — it is the platform's first real,
            // free, commercially-published Theme product, discoverable
            // through the same is_marketplace + Theme::isCommerciallyEligible()
            // gate every other Marketplace listing goes through
            // (ThemeMarketplaceApiController::index(), ADR-025). Getting
            // it there still requires a real ThemeCommercialQualityGate
            // pass via ThemeBuilderService::publish() — this seeder only
            // makes it eligible to be published, it does not bypass the
            // gate. See ThemeCatalogRebuildCommand / the admin Theme
            // Builder "Publish" action for the real trigger.
            'is_marketplace' => true,
            'license_type' => Theme::LICENSE_FREE,
        ]);

        if ($wasNew) {
            $theme->status = Theme::STATUS_DRAFT;
        }

        $theme->save();

        $this->seedImages($theme);
        $this->seedVideo($theme);

        // TASK 3/3 Phase 6.5 — closes the ThemeCommercialQualityGate's own
        // asset.missing_marketing_image warning (AssetIntegrityCheck): a
        // real, already-seeded theme image, not a fabricated/placeholder
        // asset, used exactly like any real theme's Marketplace thumbnail.
        if ($theme->thumbnail === null || $theme->thumbnail === '') {
            $theme->forceFill(['thumbnail' => $this->img('hero')])->save();
        }

        $headerPresets = $this->headerPresets();
        $footerPresets = $this->footerPresets();
        $pages = $this->pagesFor($registry);

        // THEME-03 (Full Product Commercial Audit, TASK 2A): these 6
        // presets each are a real, structurally distinct Header/Footer
        // (verified by UniversalMultipurposeQaThemeSeederTest's exact
        // type-signature comparison) — exactly the raw material
        // Theme::getHeaderVariants()/getFooterVariants() needs. Previously
        // stored only as inert `settings['header_presets']` metadata with
        // zero consumers anywhere in the app (confirmed by grep — no
        // controller or view ever read it); now populates the real,
        // wired-up `header_variants`/`footer_variants` columns instead, so
        // an installed copy of this Theme ships all 6 as genuinely
        // selectable Page-level variants, not just one baked-in default.
        $headerVariants = array_map(fn (array $p) => [
            'key' => $p['key'], 'label' => $p['name'], 'blocks' => $p['blocks'],
            'is_default' => $p['key'] === $headerPresets[0]['key'],
        ], $headerPresets);
        $footerVariants = array_map(fn (array $p) => [
            'key' => $p['key'], 'label' => $p['name'], 'blocks' => $p['blocks'],
            'is_default' => $p['key'] === $footerPresets[0]['key'],
        ], $footerPresets);

        $theme->fill([
            // Preset #1 ("classic") remains the flat header_blocks/
            // footer_blocks value too — every existing consumer that
            // reads those directly (unaware of variants) keeps working
            // unchanged; header_variants/footer_variants are additive.
            'header_blocks' => $headerPresets[0]['blocks'],
            'footer_blocks' => $footerPresets[0]['blocks'],
            'header_variants' => $headerVariants,
            'footer_variants' => $footerVariants,
            'pages' => $pages,
            'settings' => [
                'qa_fixture' => true,
                'meta' => ['supported_modules' => [], 'qa_structure_only' => false],
            ],
        ])->save();
    }

    // ── Real media ──────────────────────────────────────────────────────────
    // Section H (TASK 3/3, Universal Theme professionalization): every image
    // below is a genuinely rendered GD JPEG — a brand-gradient background
    // plus a deterministic abstract shape pattern derived from the image's
    // own name (so repeated seeder runs always produce byte-identical
    // output, and every image is visually distinct from every other) —
    // never `UploadedFile::fake()->image()`. That helper is a PHPUnit test
    // fixture utility: on this environment's GD driver it produces a flat,
    // near-black single-colour rectangle with no real visual content —
    // structurally valid (real bytes, real dimensions, passes every
    // AssetIntegrityCheck/getimagesizefromstring check) but exactly the
    // "black/empty image block" defect a real customer install exposed.
    // Stored through the same canonical portable master-Theme media path
    // (ThemeMediaController's own disk convention: theme-media/{theme->id}/)
    // — never a customer-site CustomerMedia UUID, which ADR-049 §6/§Y
    // forbids inside master Theme identity.

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
            // TASK 3/3 Phase 3: real portrait assets for team_cards members
            // (previously 'image' => '' on every member — an actual content
            // gap, not a legitimate tenant-data placeholder like product/
            // course/form content is).
            'team_1' => [480, 600],
            'team_2' => [480, 600],
            'team_3' => [480, 600],
            'team_4' => [480, 600],
        ];

        foreach ($specs as $name => [$w, $h]) {
            $file = $this->generateBrandedImage($name, $w, $h);
            $path = $storage->storeUploadedFile($file, $dir, $disk, "{$name}.jpg");
            $this->images[$name] = Storage::disk($disk)->url($path);
        }
    }

    /**
     * Commercial Productization phase — real, local, deterministic demo
     * video (Hero/SaaS-intro `video` block). Unlike the GD-generated
     * images above (regenerated fresh from a PRNG on every run), a real
     * animated video can't be cheaply regenerated per seed run — spawning
     * a headless-browser recording process from a PHP artisan command on
     * every seed would be slow and add a Node/Playwright runtime
     * dependency the deterministic-image approach doesn't need. Instead
     * the pre-rendered bytes are embedded as base64 in tracked PHP source
     * (Database\Seeders\Support\UniversalMultipurposeQaDemoVideo) —
     * deliberately not a loose binary file under the project tree, so
     * `git status`/diffing/deployment never has to reason about an
     * untracked-but-required binary asset. The source is fully
     * reproducible: a small branded/animated HTML scene rendered once via
     * scripts/headless's own Playwright/Chromium tooling (the same engine
     * theme:capture-screenshots uses) — never an external stock-video URL.
     */
    private function seedVideo(Theme $theme): void
    {
        $storage = app(StorageManager::class);
        $disk = app(MediaStorage::class)->diskNameFor('public');
        $dir = "theme-media/{$theme->id}";

        $videoBytes = base64_decode(\Database\Seeders\Support\UniversalMultipurposeQaDemoVideo::videoBase64());
        $videoTmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'utqa_hero_intro_' . getmypid() . '.webm';
        file_put_contents($videoTmp, $videoBytes);
        $videoFile = new UploadedFile($videoTmp, 'hero-intro.webm', 'video/webm', null, true);
        $path = $storage->storeUploadedFile($videoFile, $dir, $disk, 'hero-intro.webm');
        $this->images['hero_video'] = Storage::disk($disk)->url($path);

        $posterBytes = base64_decode(\Database\Seeders\Support\UniversalMultipurposeQaDemoVideo::posterBase64());
        $posterTmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'utqa_hero_intro_poster_' . getmypid() . '.jpg';
        file_put_contents($posterTmp, $posterBytes);
        $posterFile = new UploadedFile($posterTmp, 'hero-intro-poster.jpg', 'image/jpeg', null, true);
        $path = $storage->storeUploadedFile($posterFile, $dir, $disk, 'hero-intro-poster.jpg');
        $this->images['hero_video_poster'] = Storage::disk($disk)->url($path);
    }

    /**
     * A real, deterministic, locally-rendered JPEG — a two-stop brand
     * gradient (the same #4f46e5 → #7c3aed pair this theme's hero/CTA
     * blocks already use) plus a handful of translucent circles whose
     * position/size/opacity are derived from a seeded PRNG keyed on
     * $name, so every image is visually distinct yet byte-identical
     * across repeated seeder runs — never network-dependent, never
     * literally blank. No external URL, no font-file dependency (no text
     * is drawn — alt text is set separately on every block prop that
     * uses these images).
     */
    private function generateBrandedImage(string $name, int $width, int $height): UploadedFile
    {
        $image = imagecreatetruecolor($width, $height);

        $from = [0x4f, 0x46, 0xe5];
        $to   = [0x7c, 0x3a, 0xed];
        for ($y = 0; $y < $height; $y++) {
            $t = $y / max(1, $height - 1);
            $r = (int) round($from[0] + ($to[0] - $from[0]) * $t);
            $g = (int) round($from[1] + ($to[1] - $from[1]) * $t);
            $b = (int) round($from[2] + ($to[2] - $from[2]) * $t);
            $rowColor = imagecolorallocate($image, $r, $g, $b);
            imageline($image, 0, $y, $width, $y, $rowColor);
        }

        // Deterministic PRNG seeded on the image name — same shapes every
        // run, different shapes per image.
        mt_srand(crc32($name));
        imagealphablending($image, true);
        $shapeCount = 5 + (crc32($name) % 4);
        for ($i = 0; $i < $shapeCount; $i++) {
            $cx = mt_rand(0, $width);
            $cy = mt_rand(0, $height);
            $radius = (int) (min($width, $height) * (mt_rand(15, 45) / 100));
            $alpha = mt_rand(90, 115); // 0 (opaque) .. 127 (fully transparent)
            $shade = mt_rand(0, 1) === 1 ? 255 : 0;
            $color = imagecolorallocatealpha($image, $shade, $shade, $shade, $alpha);
            imagefilledellipse($image, $cx, $cy, $radius * 2, $radius * 2, $color);
        }
        mt_srand(); // restore non-deterministic seeding for anything else in this process

        $tmpPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'utqa_' . $name . '_' . getmypid() . '.jpg';
        imagejpeg($image, $tmpPath, 87);
        imagedestroy($image);

        return new UploadedFile($tmpPath, "{$name}.jpg", 'image/jpeg', null, true);
    }

    private function img(string $name): string
    {
        return $this->images[$name] ?? '';
    }

    // ── Header presets (§M) ──────────────────────────────────────────────────

    /** @return array<int, array{key:string,name:string,description:string,blocks:array}> */
    private function headerPresets(): array
    {
        // Commercial Productization phase (Porto-inspired mega menu
        // pattern — every real Porto demo leads with a mega-menu-driven
        // Shop/Services nav item): 'خدمات' and 'فروشگاه' get a real
        // multi-column dropdown + promotional banner. The other 4 links
        // stay plain — proves both shapes coexist correctly in one menu.
        $baseLinks = [
            ['label' => 'خانه', 'url' => '/'],
            ['label' => 'درباره ما', 'url' => '/about'],
            // mega_columns is the real, independent-column visual-editor
            // shape (App\Services\ThemeCanvas\BlockRegistry's
            // nav_links.mega_columns — a genuine nested repeater, columns[]
            // .items[], each column its own array entry, never inferred by
            // matching duplicate title text). Authored here the same way an
            // admin would build it in the Canvas UI. The older flat
            // `mega_items` shape and legacy raw-JSON `mega:{columns:...}`
            // shape are still accepted by the renderer for backward compat
            // with previously authored content, but this seeder — like the
            // editor — only ever emits `mega_columns`.
            [
                'label' => 'خدمات', 'url' => '/services',
                'mega_columns' => [
                    ['title' => 'خدمات کسب‌وکار', 'items' => [
                        ['label' => 'طراحی و توسعه سایت', 'url' => '/services#web', 'icon' => '🎨'],
                        ['label' => 'فروشگاه‌سازی آنلاین', 'url' => '/services#shop', 'icon' => '🛒'],
                        ['label' => 'مشاورهٔ دیجیتال مارکتینگ', 'url' => '/services#marketing', 'icon' => '📈'],
                    ]],
                    ['title' => 'پلتفرم SaaS', 'items' => [
                        ['label' => 'معرفی پلتفرم', 'url' => '/saas'],
                        ['label' => 'تعرفه‌ها', 'url' => '/pricing'],
                        ['label' => 'مستندات و آموزش', 'url' => '/learning'],
                    ]],
                ],
                'mega_banner_image' => $this->img('about'),
                'mega_banner_url' => '/saas',
                'mega_banner_text' => 'پلتفرم جامع کسب‌وکار — رایگان شروع کنید',
                'mega_show_desktop' => true,
                'mega_mobile_behavior' => 'accordion',
            ],
            [
                'label' => 'فروشگاه', 'url' => '/shop',
                'mega_columns' => [
                    ['title' => 'دسته‌بندی‌های محبوب', 'items' => [
                        ['label' => 'پوشاک', 'url' => '/shop?category=clothing'],
                        ['label' => 'دیجیتال و لوازم الکترونیکی', 'url' => '/shop?category=digital'],
                        ['label' => 'خانه و آشپزخانه', 'url' => '/shop?category=home'],
                    ]],
                    ['title' => 'میان‌بر', 'items' => [
                        ['label' => 'محصولات جدید', 'url' => '/shop?source=new', 'icon' => '✨'],
                        ['label' => 'تخفیف‌های ویژه', 'url' => '/shop?source=sale', 'icon' => '🔥'],
                        ['label' => 'همهٔ دسته‌بندی‌ها', 'url' => '/product-categories'],
                    ]],
                ],
                'mega_banner_image' => $this->img('portfolio_1'),
                'mega_banner_url' => '/shop?source=sale',
                'mega_banner_text' => 'تا ۳۰٪ تخفیف پاییزه',
                'mega_show_desktop' => true,
                'mega_mobile_behavior' => 'flat',
            ],
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
                    // Commercial Productization fase-4: real, usable dark
                    // mode — this is the active/default header preset, so
                    // the toggle is genuinely reachable on every page.
                    'show_dark_toggle' => true,
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
            // §D-2 (TASK 3/3, Phase 2): alternate homepage compositions.
            // is_home stays false on all five — a receiving site keeps one
            // active homepage (the corporate 'home' above); these prove the
            // SAME block system produces structurally and editorially
            // different homepages per business category (hero framing,
            // section order, CTA strategy, content density), not six
            // reskins of one layout. Not linked from any nav_menu by
            // default; reachable at their own slug for review/assignment.
            'home_service' => ['title' => 'خانه — کسب‌وکار خدماتی', 'slug' => 'home-service', 'is_home' => false, 'blocks' => $this->homeServiceBlocks()],
            'home_ecommerce' => ['title' => 'خانه — فروشگاه اینترنتی', 'slug' => 'home-ecommerce', 'is_home' => false, 'blocks' => $this->homeEcommerceBlocks()],
            'home_personal' => ['title' => 'خانه — برند شخصی', 'slug' => 'home-personal', 'is_home' => false, 'blocks' => $this->homePersonalBlocks()],
            'home_magazine' => ['title' => 'خانه — مجله و رسانه', 'slug' => 'home-magazine', 'is_home' => false, 'blocks' => $this->homeMagazineBlocks()],
            'home_education' => ['title' => 'خانه — آموزش آنلاین', 'slug' => 'home-education', 'is_home' => false, 'blocks' => $this->homeEducationBlocks()],
            'about' => ['title' => 'درباره ما', 'slug' => 'about', 'blocks' => $this->aboutBlocks()],
            'services' => ['title' => 'خدمات', 'slug' => 'services', 'blocks' => $this->servicesBlocks()],
            // Commercial Productization fase-4: "Service Detail" — audited
            // first (no real Service model exists anywhere in customer-
            // site, confirmed via a direct search of app/Models). Per the
            // architecture decision this requires, since there is no
            // official real data source to bind to, this is built as a
            // real, standard, extensible Theme-Canvas PAGE (the same
            // pattern already established for 'saas'/'product_categories'
            // this session) using only real canonical block types with
            // realistic Persian content — never a fake dynamic route
            // pretending to bind to a database record that does not
            // exist, and no change to any core model. A site owner who
            // later adds a real Service model can wire real per-record
            // routing independently without this page needing to change.
            'service_detail' => ['title' => 'جزئیات خدمت — طراحی سایت اختصاصی', 'slug' => 'service-website-design', 'blocks' => $this->serviceDetailBlocks()],
            'portfolio' => ['title' => 'نمونه‌کارها', 'slug' => 'portfolio', 'blocks' => $this->portfolioBlocks()],
            'pricing' => ['title' => 'تعرفه‌ها', 'slug' => 'pricing', 'blocks' => $this->pricingBlocks()],
            'team' => ['title' => 'تیم ما', 'slug' => 'team', 'blocks' => $this->teamBlocks()],
            'testimonials' => ['title' => 'نظرات مشتریان', 'slug' => 'testimonials', 'blocks' => $this->testimonialsBlocks()],
            'faq' => ['title' => 'سوالات متداول', 'slug' => 'faq', 'blocks' => $this->faqBlocks()],
            'contact' => ['title' => 'تماس با ما', 'slug' => 'contact', 'blocks' => $this->contactBlocks()],
            // Commercial Productization phase — Porto's own "Digital
            // Agency"/SaaS-adjacent demos lead with an icon-tabbed
            // services intro, a process-steps section, and a stats/
            // integrations strip; adapted here as a dedicated platform-
            // intro page (previously only implied by home_service/
            // home_education, never a real standalone page).
            'saas' => ['title' => 'معرفی پلتفرم SaaS', 'slug' => 'saas', 'blocks' => $this->saasBlocks()],
            // Real Category Slider/Grid showcase as a standalone page —
            // the "دسته‌بندی محصولات" page requested in the productization
            // brief. On customer-site this renders real ProductCategory
            // data (see ThemeCanvasRenderer::renderCategorySlider());
            // here it shows the canonical demo fixture.
            'product_categories' => ['title' => 'دسته‌بندی محصولات', 'slug' => 'product-categories', 'blocks' => $this->productCategoriesBlocks()],
            // Dynamic-content pages (blog/shop/learning): real module blocks
            // — genuine "will show once available" placeholder here (no
            // Post/Product/Course model exists for a portable master Theme),
            // real data automatically once installed to a site that has it.
            'blog' => ['title' => 'وبلاگ', 'slug' => 'blog', 'blocks' => [
                $this->block('module:blog_featured', ['title' => 'مطلب ویژه']),
                $this->block('module:blog_posts', ['title' => 'آخرین مطالب', 'limit' => 9, 'layout' => 'grid', 'show_excerpt' => true, 'show_date' => true]),
                $this->block('module:blog_categories', ['title' => 'دسته‌بندی‌ها']),
            ]],
            // MOD-01/MOD-02 (Full Product Commercial Audit, TASK 2A/2A-
            // CONTINUATION): module:shop_wishlist_icon (wishlist + compare
            // modes share one block type) and module:installment_badge are
            // real, working backends (ShopWishlistService/session-compare,
            // InstallmentService) — TASK 3/3 Phase 3 wires them into the
            // real shop page instead of leaving them only reachable via the
            // generic builder-coverage regression page. `product_id` stays
            // empty deliberately: a portable master Theme has no real
            // tenant Product to reference (same "will show once available"
            // honest-placeholder rule this file already applies to
            // module:pricing_table above).
            // Commercial Productization phase: real promotional hero
            // (Porto shop1-inspired: bold discount headline + category
            // shortcut CTAs) and a Product Slider ("تازه‌ها") added ahead
            // of the pre-existing, already-functioning module blocks —
            // those are untouched, this only adds to the top of the page.
            'shop' => ['title' => 'فروشگاه', 'slug' => 'shop', 'blocks' => [
                $this->block('hero_gradient', [
                    'badge' => 'حراج پاییزه', 'title' => 'تا ۳۰٪ تخفیف روی محصولات منتخب',
                    'subtitle' => 'ارسال رایگان برای خریدهای بالای ۵۰۰ هزار تومان',
                    'button_text' => 'مشاهدهٔ محصولات', 'button_url' => '#products',
                    'button2_text' => 'دسته‌بندی‌ها', 'button2_url' => '/product-categories',
                    'gradient_from' => '#ea580c', 'gradient_to' => '#db2777', 'text_color' => '#ffffff',
                ]),
                $this->block('module:shop_search_box', ['placeholder' => 'جستجوی محصول...']),
                $this->block('module:shop_categories', ['title' => 'دسته‌بندی محصولات']),
                $this->block('heading', ['text' => 'تازه‌ترین محصولات', 'level' => 2]),
                $this->block('product_slider', ['title' => '', 'columns' => 4, 'source' => 'new', 'autoplay' => true]),
                $this->block('module:shop_wishlist_icon', ['mode' => 'wishlist', 'style' => 'full', 'label' => 'علاقه‌مندی‌های من']),
                $this->block('module:shop_wishlist_icon', ['mode' => 'compare', 'style' => 'full', 'label' => 'مقایسهٔ محصولات']),
                $this->block('module:shop_product_grid', ['title' => 'همه محصولات', 'limit' => 12, 'columns' => '4']),
                $this->block('module:installment_badge', ['style' => 'full']),
                $this->block('trust_badges', $registry['trust_badges']['defaultProps'] ?? []),
            ]],
            'learning' => ['title' => 'آموزش', 'slug' => 'learning', 'blocks' => [
                $this->block('module:courses_grid', ['title' => 'دوره‌های آموزشی', 'limit' => 9, 'columns' => '3', 'layout' => 'grid', 'show_price' => true]),
                $this->block('module:course_enroll_cta', []),
            ]],
            // MOD-03: module:loyalty_points_badge (real MembershipPointsService
            // integration) belongs on the logged-in customer's own account
            // surface, not the anonymous shop page.
            'account' => ['title' => 'حساب کاربری', 'slug' => 'account', 'blocks' => [
                $this->block('login_register', []),
                $this->block('module:account_quick_links', ['style' => 'buttons', 'show_notifications' => true]),
                $this->block('module:loyalty_points_badge', ['style' => 'full', 'label' => 'امتیاز باشگاه مشتریان من']),
            ]],
            // TASK 3/3 Phase 3: tabs/accordion/slider replaced with real
            // authored content — each was previously an empty container
            // (or, for accordion, one item literally titled "عنوان" with no
            // body), which fails the "no placeholder-only certification"
            // rule. tab_panel children are real nested blocks (the same
            // generic container mechanism every other container type
            // here uses — see BlockRenderer::isStructuralContainer()); the
            // slider carries 4 real slides with real generated images and
            // deliberately varying text length (one has no subtitle, one
            // has a long headline) to exercise the renderer under both ends
            // of realistic content.
            'utilities' => ['title' => 'امکانات', 'slug' => 'utilities', 'blocks' => [
                $this->block('module:site_search', ['placeholder' => 'جستجو در کل سایت...', 'style' => 'hero']),
                $this->block('tabs', ['blocks' => [
                    $this->block('tab_panel', ['title' => 'فروشگاه اینترنتی', 'blocks' => [
                        $this->block('heading', ['text' => 'همه‌چیز برای فروش آنلاین', 'level' => '3']),
                        $this->block('text', ['content' => 'مدیریت محصول، دسته‌بندی، سبد خرید و درگاه پرداخت — بدون نیاز به افزونهٔ جداگانه.']),
                    ]]),
                    $this->block('tab_panel', ['title' => 'آموزش آنلاین', 'blocks' => [
                        $this->block('heading', ['text' => 'فروش دوره و مدیریت دانشجو', 'level' => '3']),
                        $this->block('text', ['content' => 'تعریف دوره، فروش اقساطی یا یک‌جا، و پیگیری پیشرفت دانشجویان در یک داشبورد.']),
                    ]]),
                    $this->block('tab_panel', ['title' => 'وبلاگ و محتوا', 'blocks' => [
                        $this->block('heading', ['text' => 'انتشار محتوای حرفه‌ای', 'level' => '3']),
                        $this->block('text', ['content' => 'دسته‌بندی، برچسب و سئوی داخلی برای هر مطلب، بدون نیاز به ابزار جانبی.']),
                    ]]),
                ]]),
                $this->block('accordion', ['items' => [
                    ['title' => 'آیا می‌توانم استایل هر بخش را جداگانه تغییر دهم؟', 'blocks' => [
                        $this->block('text', ['content' => 'بله، هر بخش رنگ، فاصله‌گذاری، اندازهٔ متن و حاشیهٔ مخصوص به خودش را دارد و مستقل از بقیه قابل تنظیم است.']),
                    ]],
                    ['title' => 'خروجی موبایل جداگانه طراحی می‌شود؟', 'blocks' => [
                        $this->block('text', ['content' => 'خیر، طراحی واکنش‌گراست؛ همان صفحه به‌صورت خودکار برای موبایل و تبلت هم بهینه نمایش داده می‌شود.']),
                    ]],
                    ['title' => 'چطور به نسخهٔ قبلی صفحه برمی‌گردم؟', 'blocks' => [
                        $this->block('text', ['content' => 'از تاریخچهٔ نسخه‌ها در سازنده می‌توانید هر نسخهٔ قبلی صفحه را انتخاب و بازیابی کنید.']),
                    ]],
                ]]),
                $this->block('slider', [
                    'autoplay' => true, 'interval' => 5000,
                    'aspect_ratio' => '21/9', 'overlay' => true,
                    'slides' => [
                        ['image' => $this->img('hero'), 'title' => 'فروش ویژهٔ پاییزه', 'subtitle' => 'تا ۳۰٪ تخفیف روی محصولات منتخب', 'link' => '/shop', 'cta_text' => 'مشاهدهٔ فروشگاه'],
                        ['image' => $this->img('about'), 'title' => 'دوره‌های جدید آموزشی', 'subtitle' => 'ثبت‌نام زودهنگام با تخفیف ویژه', 'link' => '/learning'],
                        ['image' => $this->img('portfolio_1'), 'title' => 'نمونه‌کارهای تازه', 'subtitle' => '', 'link' => '/portfolio'],
                        ['image' => $this->img('gallery_1'), 'title' => 'راهنمای کامل شروع کسب‌وکار آنلاین برای مبتدیان در سال جدید', 'subtitle' => 'یک راهنمای گام‌به‌گام برای راه‌اندازی اولین فروشگاه یا سایت شما', 'link' => '/blog'],
                    ],
                ]),
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
        //
        // TASK 3/3 Phase 6.5: a handful of registry defaultProps are
        // contractually empty by design (e.g. "icon" defaults `name` to ''
        // — there is no generic icon name that makes sense platform-wide),
        // which is correct for the Builder's own "insert with nothing
        // authored yet" UX but means this coverage page rendered them to
        // real empty output — a genuine ThemeCommercialQualityGate
        // render.empty_output failure (EMPTY_POLICY_FLAGGED), not a false
        // positive. A minimal, real, non-marketing override per type is
        // enough to make the coverage instance itself valid content.
        $coverageOverrides = [
            'icon' => ['name' => 'star'],
        ];
        $pages['builder-coverage'] = [
            'title' => 'پوشش عناصر سازنده', 'slug' => 'builder-coverage', 'blocks' => array_map(
                fn (string $type) => $this->block($type, array_merge($registry[$type]['defaultProps'] ?? [], $coverageOverrides[$type] ?? [])),
                array_keys(array_diff_key($registry, $used))
            ),
        ];

        return $pages;
    }

    private function homeBlocks(): array
    {
        return [
            // Hero — TASK 3/3 Phase 6: upgraded from the plain solid-color
            // 'hero' to 'hero_gradient' (real badge, two-button CTA, brand
            // gradient — already-proven CSS this theme previously never
            // used anywhere) for a genuinely premium corporate first
            // impression, distinct from the other 5 homepages' heroes.
            $this->block('hero_gradient', [
                'badge' => 'پلتفرم جامع کسب‌وکار',
                'title' => 'همه‌چیز برای رشد کسب‌وکار شما، در یک پلتفرم',
                'subtitle' => 'ساخت سایت، فروشگاه، وبلاگ و دوره‌های آموزشی — بدون نیاز به دانش فنی، با پشتیبانی واقعی فارسی.',
                'button_text' => 'شروع رایگان', 'button_url' => '/pricing',
                'button2_text' => 'مشاهدهٔ نمونه‌کارها', 'button2_url' => '/portfolio',
                // Commercial Productization fase-3 (visual identity audit):
                // was hardcoded to the platform's generic indigo/violet
                // (#4f46e5/#7c3aed) — a real bug that made the flagship
                // homepage hero ignore this theme's own configured brand
                // colors entirely. Omitting these now inherits
                // renderHeroGradient()'s real --tb-primary/--tb-secondary
                // default (see BlockRenderer.php), so the hero always
                // reflects this theme's actual palette (teal/cyan), not a
                // second, independently-drifting hardcoded copy of it.
                'text_color' => '#ffffff',
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
                    ['name' => 'سارا احمدی', 'role' => 'مدیرعامل و بنیان‌گذار', 'image' => $this->img('team_1'), 'bio' => 'بیش از ۱۰ سال تجربه در توسعهٔ کسب‌وکارهای دیجیتال'],
                    ['name' => 'رضا کریمی', 'role' => 'مدیر فنی', 'image' => $this->img('team_2'), 'bio' => 'معمار زیرساخت پلتفرم و تیم مهندسی'],
                    ['name' => 'نگار حسینی', 'role' => 'مدیر محصول', 'image' => $this->img('team_3'), 'bio' => 'مسئول تجربهٔ کاربری و طراحی محصول'],
                ],
            ]),
            // Testimonials (real carousel with real columns support — see
            // BlockRenderer::renderTestimonialsSlider(); avatar/company are
            // new, additive fields).
            $this->block('testimonials_slider', [
                'title' => 'مشتریان ما چه می‌گویند', 'columns' => '3', 'mode' => 'carousel', 'autoplay' => true, 'interval' => 6000,
                'items' => [
                    ['name' => 'امیر رستمی', 'role' => 'صاحب فروشگاه', 'company' => 'دیجی‌مد', 'avatar' => $this->img('team_1'), 'text' => 'در کمتر از یک هفته فروشگاهم آماده بود. پشتیبانی فارسی واقعاً فرق می‌کند.', 'rating' => 5],
                    ['name' => 'مریم صادقی', 'role' => 'موسس', 'company' => 'آموزشگاه رشد', 'avatar' => $this->img('team_2'), 'text' => 'بخش دوره‌های آموزشی دقیقاً همان چیزی بود که نیاز داشتم.', 'rating' => 5],
                    ['name' => 'حسین یزدانی', 'role' => 'مدیر', 'company' => 'کلینیک سلامت', 'avatar' => $this->img('team_3'), 'text' => 'سازندهٔ بصری فوق‌العاده ساده است، حتی برای من که فنی نیستم.', 'rating' => 4],
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

    // §D-2: alternate homepage compositions (see pagesFor()). Each reuses
    // only existing canonical block types, with real category-specific
    // Persian copy — no lorem ipsum, no shared hero/CTA copy with 'home'.

    /** SERVICE BUSINESS — health clinic. Booking-first CTA, trust via stats + process. */
    private function homeServiceBlocks(): array
    {
        return [
            // TASK 3/3 Phase 6: 'hero_split' (image + text side-by-side,
            // two-button CTA) — structurally distinct from the corporate
            // homepage's gradient hero and the ecommerce/personal homepages'
            // own hero_split instances (different image_side/background).
            $this->block('hero_split', [
                'badge' => 'کلینیک سلامت',
                'title' => 'سلامت شما، اولویت اول ماست',
                'subtitle' => 'نوبت‌دهی آنلاین، مشاورهٔ تخصصی و پیگیری درمان در یک کلینیک مدرن با ۱۸ سال سابقه.',
                'button_text' => 'رزرو نوبت آنلاین', 'button_url' => '/contact',
                'button2_text' => 'مشاهدهٔ خدمات', 'button2_url' => '/services',
                'image_url' => $this->img('about'), 'image_side' => 'left', 'background' => '#f0fdfa',
            ]),
            $this->block('service_cards', [
                'title' => 'خدمات درمانی و تخصصی کلینیک سلامت',
                'services' => [
                    ['icon' => '🩺', 'title' => 'مشاورهٔ عمومی', 'text' => 'ویزیت حضوری و آنلاین با پزشکان عمومی مجرب', 'url' => '/contact'],
                    ['icon' => '🏃', 'title' => 'فیزیوتراپی', 'text' => 'درمان دردهای اسکلتی-عضلانی با دستگاه‌های روز', 'url' => '/services'],
                    ['icon' => '🥗', 'title' => 'تغذیه و رژیم درمانی', 'text' => 'برنامهٔ غذایی اختصاصی با پیگیری هفتگی', 'url' => '/services'],
                    ['icon' => '✨', 'title' => 'مراقبت پوست و زیبایی', 'text' => 'خدمات پوست با متخصصان دارای مجوز', 'url' => '/services'],
                ],
            ]),
            $this->block('stats_row', [
                'title' => '', 'background' => '#f8fafc', 'dividers' => true,
                'stats' => [
                    ['value' => 18, 'suffix' => ' سال', 'label' => 'سابقهٔ درمانی'],
                    ['value' => 40, 'suffix' => '+', 'label' => 'پزشک متخصص'],
                    ['value' => 25000, 'suffix' => '+', 'label' => 'بیمار درمان‌شده'],
                    ['value' => 4.9, 'suffix' => '', 'label' => 'امتیاز رضایت بیماران'],
                ],
            ]),
            $this->block('process_steps', [
                'title' => 'روند دریافت خدمت', 'subtitle' => 'از نوبت‌دهی تا پیگیری درمان، در سه گام روشن',
                'steps' => [
                    ['number' => '۱', 'title' => 'نوبت‌دهی آنلاین', 'text' => 'زمان ویزیت را از سایت یا تلفن انتخاب کنید'],
                    ['number' => '۲', 'title' => 'ویزیت و مشاورهٔ تخصصی', 'text' => 'معاینه و تشخیص توسط پزشک متخصص'],
                    ['number' => '۳', 'title' => 'پیگیری و برنامهٔ درمانی', 'text' => 'دریافت برنامهٔ درمان و پیگیری منظم پرونده'],
                ],
            ]),
            $this->block('testimonials_slider', [
                'title' => 'تجربهٔ بیماران ما', 'columns' => '3', 'autoplay' => true, 'interval' => 6000,
                'items' => [
                    ['name' => 'مینا کاظمی', 'role' => 'بیمار فیزیوتراپی', 'text' => 'بعد از سه ماه فیزیوتراپی منظم، کمردردم کاملاً برطرف شد. برخورد پرسنل هم عالی بود.', 'rating' => 5],
                    ['name' => 'مریم توکلی', 'role' => 'بیمار بخش تغذیه', 'text' => 'برنامهٔ غذایی اختصاصی و پیگیری هفتگی، نتیجه‌ای واقعی و پایدار داد.', 'rating' => 5],
                    ['name' => 'بهزاد رستگار', 'role' => 'بیمار مشاورهٔ عمومی', 'text' => 'نوبت‌دهی آنلاین وقتم را خیلی نجات داد، دیگر لازم نیست پشت تلفن منتظر بمانم.', 'rating' => 4],
                ],
            ]),
            $this->block('faq', [
                'title' => 'سوالات متداول',
                'items' => [
                    ['question' => 'آیا نوبت‌دهی آنلاین رایگان است؟', 'answer' => 'بله، رزرو نوبت از سایت بدون هیچ هزینه‌ای انجام می‌شود.'],
                    ['question' => 'آیا بیمه تکمیلی پذیرفته می‌شود؟', 'answer' => 'بله، با اکثر بیمه‌های تکمیلی طرف قرارداد هستیم.'],
                    ['question' => 'برای اولین ویزیت چه مدارکی لازم است؟', 'answer' => 'کارت ملی و در صورت وجود، سوابق پزشکی قبلی کافی است.'],
                ],
            ]),
            $this->block('cta', [
                'title' => 'همین حالا نوبت خود را رزرو کنید', 'subtitle' => 'ظرفیت نوبت‌های این هفته محدود است',
                'button_text' => 'رزرو نوبت', 'button_url' => '/contact', 'background' => 'primary',
            ]),
        ];
    }

    /** ECOMMERCE — apparel store. Shop-now CTA, product-grid heavy, urgency + trust. */
    private function homeEcommerceBlocks(): array
    {
        return [
            // TASK 3/3 Phase 6: a real sale announcement strip at the very
            // top — the "promotional hero/banner... sale/promotion surface"
            // an ecommerce homepage needs, using promo_banner (previously
            // registered but rendered with zero CSS anywhere — see the
            // accompanying customer-site commit) — plus 'hero_split' (real
            // product imagery beside the pitch) instead of the plain
            // solid-color hero every other page variant already used.
            $this->block('promo_banner', [
                'text' => '🔥 حراج پاییزه — تا ۳۰٪ تخفیف روی محصولات منتخب، فقط تا پایان هفته',
                'link' => '/shop', 'style' => 'sale',
            ]),
            $this->block('hero_split', [
                'badge' => 'کلکسیون پاییز ۱۴۰۴',
                'title' => 'کلکسیون جدید دیجی‌مد رسید',
                'subtitle' => 'ارسال رایگان برای خریدهای بالای ۵۰۰ هزار تومان — ۲۰٪ تخفیف برای اولین خرید شما.',
                'button_text' => 'مشاهده و خرید', 'button_url' => '/shop',
                'button2_text' => 'مشاهدهٔ نمونه‌کارها', 'button2_url' => '/portfolio',
                'image_url' => $this->img('hero'), 'image_side' => 'right', 'background' => '#fff7ed',
            ]),
            $this->block('countdown', [
                'end_date' => date('Y-m-d', strtotime('+5 days')), 'label' => 'پایان حراج پاییزه',
            ]),
            $this->block('features_grid', [
                'title' => 'چرا از دیجی‌مد خرید کنید؟', 'subtitle' => '',
                'columns' => '4', 'style' => 'cards',
                'features' => [
                    ['icon' => '🚚', 'title' => 'ارسال سریع', 'text' => 'تحویل به سراسر کشور طی ۲۴ تا ۷۲ ساعت'],
                    ['icon' => '🔒', 'title' => 'پرداخت امن', 'text' => 'درگاه بانکی معتبر و رمزنگاری کامل'],
                    ['icon' => '✅', 'title' => 'ضمانت اصالت کالا', 'text' => 'تمام محصولات دارای گارانتی اصالت'],
                    ['icon' => '↩️', 'title' => '۷ روز مهلت بازگشت', 'text' => 'بازگشت و تعویض آسان و بدون دردسر'],
                ],
            ]),
            $this->block('category_slider', ['title' => 'دسته‌بندی‌های فروشگاه', 'layout' => 'carousel', 'columns' => '4']),
            $this->block('heading', ['text' => 'پرفروش‌ترین محصولات', 'level' => '2']),
            $this->block('product_grid', ['columns' => 4, 'limit' => 8]),
            $this->block('heading', ['text' => 'تازه‌ترین محصولات', 'level' => '2']),
            $this->block('product_slider', ['title' => '', 'columns' => 3, 'source' => 'latest', 'autoplay' => true]),
            $this->block('gallery', [
                'title' => 'لوک‌بوک فصل جدید', 'columns' => '3',
                'items' => [
                    ['url' => $this->img('portfolio_1'), 'alt' => 'ست پاییزهٔ دیجی‌مد'],
                    ['url' => $this->img('portfolio_2'), 'alt' => 'کلکسیون رسمی دیجی‌مد'],
                    ['url' => $this->img('gallery_1'), 'alt' => 'استایل روزمرهٔ دیجی‌مد'],
                ],
            ]),
            $this->block('testimonials_slider', [
                'title' => 'نظرات خریداران', 'columns' => '3', 'autoplay' => true, 'interval' => 5000,
                'items' => [
                    ['name' => 'سحر ابراهیمی', 'role' => 'خریدار', 'text' => 'کیفیت پارچه فوق‌العاده بود و بسته‌بندی خیلی شیک. حتماً باز خرید می‌کنم.', 'rating' => 5],
                    ['name' => 'کیانا مرادی', 'role' => 'خریدار', 'text' => 'ارسال دقیقاً همون روزی بود که گفته بودن. سایزبندی هم دقیق بود.', 'rating' => 5],
                    ['name' => 'پویا صادقی', 'role' => 'خریدار', 'text' => 'پشتیبانی سریع جواب داد و تعویض سایز بدون هیچ هزینه‌ای انجام شد.', 'rating' => 4],
                ],
            ]),
            $this->block('trust_badges', [
                'badges' => [
                    ['icon' => '↩️', 'title' => 'بازگشت وجه', 'text' => 'تا ۷ روز بدون قید و شرط'],
                    ['icon' => '🔒', 'title' => 'پرداخت امن', 'text' => 'درگاه‌های معتبر بانکی'],
                    ['icon' => '☎️', 'title' => 'پشتیبانی ۲۴/۷', 'text' => 'همیشه در دسترس شما'],
                ],
                'background' => '#f8fafc',
            ]),
            $this->block('newsletter_form', [
                'title' => 'از تخفیف‌های ویژه باخبر شوید', 'subtitle' => 'کدهای تخفیف اختصاصی را زودتر از همه دریافت کنید',
                'placeholder' => 'ایمیل شما', 'button_text' => 'عضویت',
                'background' => '#111827', 'text_color' => '#ffffff',
            ]),
            $this->block('cta', [
                'title' => 'فرصت محدود! تخفیف ویژه تا پایان هفته', 'subtitle' => 'همین حالا خرید کنید تا از تخفیف جا نمانید',
                'button_text' => 'ورود به فروشگاه', 'button_url' => '/shop', 'background' => 'primary',
            ]),
        ];
    }

    /** PERSONAL / PROFESSIONAL BRAND — freelance designer. Minimal, low density, single CTA. */
    private function homePersonalBlocks(): array
    {
        return [
            // TASK 3/3 Phase 6: 'hero_split' with a portrait-side visual —
            // the identity-driven, profile-forward hero a personal-brand
            // homepage needs, distinct from every other homepage's hero
            // (own image_side/background/palette).
            $this->block('hero_split', [
                'badge' => 'طراح گرافیک و مشاور برند',
                'title' => 'نگین رضایی هستم، طراح گرافیک و مشاور برند',
                'subtitle' => 'به کسب‌وکار شما هویت بصری‌ای می‌بخشم که در ذهن مشتری می‌ماند.',
                'button_text' => 'مشاهدهٔ نمونه‌کارها', 'button_url' => '/portfolio',
                'button2_text' => 'درخواست همکاری', 'button2_url' => '/contact',
                'image_url' => $this->img('about'), 'image_side' => 'right', 'background' => '#f8fafc',
            ]),
            $this->block('about_section', [
                'badge' => 'دربارهٔ من', 'title' => 'بیش از ۸ سال تجربه در طراحی برند',
                'description' => 'از سال ۱۳۹۵ با کسب‌وکارهای کوچک و برندهای نوپا کار می‌کنم تا هویت بصری‌ای بسازم که واقعاً نمایندهٔ آن‌هاست، نه یک قالب تکراری.',
                'features' => ['طراحی لوگو و هویت بصری', 'مشاورهٔ برندسازی', 'طراحی رابط کاربری (UI)'],
                'btn_text' => 'بیشتر بخوانید', 'btn_url' => '/about',
            ]),
            $this->block('skills_bars', [
                'title' => 'مهارت‌ها',
                'skills' => [
                    ['name' => 'طراحی گرافیک', 'percent' => 96],
                    ['name' => 'برندسازی', 'percent' => 90],
                    ['name' => 'طراحی رابط کاربری (UI)', 'percent' => 85],
                ],
            ]),
            $this->block('gallery', [
                'title' => 'منتخب پروژه‌ها', 'columns' => '3',
                'items' => [
                    ['url' => $this->img('portfolio_1'), 'alt' => 'هویت بصری برند دیجی‌مد'],
                    ['url' => $this->img('portfolio_2'), 'alt' => 'ریبرندینگ استودیو نقش'],
                    ['url' => $this->img('gallery_1'), 'alt' => 'طراحی رابط کاربری اپلیکیشن رشد'],
                ],
            ]),
            $this->block('testimonials_slider', [
                'title' => 'کارفرمایان چه می‌گویند', 'columns' => '1', 'autoplay' => true, 'interval' => 5500,
                'items' => [
                    ['name' => 'آرش تهرانی', 'role' => 'مدیر استودیو طراحی نقش', 'text' => 'نگین دقیقاً همان چیزی که در ذهن داشتیم را روی کاغذ آورد، حتی بهتر.', 'rating' => 5],
                    ['name' => 'سمیرا قاسمی', 'role' => 'بنیان‌گذار آژانس سفر پرواز', 'text' => 'ریبرندینگ ما نتیجهٔ مستقیمش را در افزایش اعتماد مشتری نشان داد.', 'rating' => 5],
                ],
            ]),
            $this->block('cta', [
                'title' => 'پروژهٔ بعدی خود را با من شروع کنید', 'subtitle' => 'برای مشاورهٔ رایگان تماس بگیرید',
                'button_text' => 'درخواست همکاری', 'button_url' => '/contact', 'background' => 'secondary',
            ]),
        ];
    }

    /** CONTENT / MAGAZINE — business/tech publication. Content-first, highest density of articles. */
    private function homeMagazineBlocks(): array
    {
        return [
            // TASK 3/3 Phase 6: 'hero_gradient' in a dark editorial slate
            // tone (not the corporate homepage's brand indigo, not the
            // education homepage's purple) — a masthead-style lead-in
            // distinct from every other homepage's hero.
            $this->block('hero_gradient', [
                'badge' => 'مجلهٔ کسب‌وکار امروز',
                'title' => 'کارآفرینی، تکنولوژی و کسب‌وکار — هر روز یک ایدهٔ تازه',
                'subtitle' => 'جدیدترین تحلیل‌ها، گزارش‌های بازار و مصاحبه‌های اختصاصی مجلهٔ کسب‌وکار امروز را دنبال کنید.',
                'button_text' => 'مطالعهٔ مطالب', 'button_url' => '/blog',
                'gradient_from' => '#1e293b', 'gradient_to' => '#334155', 'text_color' => '#f1f5f9',
            ]),
            $this->block('module:blog_featured', ['title' => 'مطلب ویژهٔ هفته']),
            $this->block('blog_posts', ['title' => 'جدیدترین مطالب', 'limit' => 9]),
            $this->block('module:blog_categories', ['title' => 'مرور بر اساس موضوع']),
            $this->block('bento_grid', [
                'title' => 'موضوعات پرطرفدار',
                'items' => [
                    ['title' => 'کارآفرینی', 'text' => 'استارتاپ و رشد کسب‌وکار', 'icon' => '🚀', 'size' => 'large', 'bg_color' => '#eef2ff', 'text_color' => '#3730a3'],
                    ['title' => 'فناوری', 'text' => 'اخبار و تحلیل تکنولوژی', 'icon' => '💡', 'size' => 'small', 'bg_color' => '#f0fdf4', 'text_color' => '#14532d'],
                    ['title' => 'بازاریابی', 'text' => 'استراتژی و برندسازی', 'icon' => '📣', 'size' => 'small', 'bg_color' => '#fef9c3', 'text_color' => '#713f12'],
                ],
            ]),
            $this->block('newsletter_form', [
                'title' => 'خلاصهٔ هفتگی را در ایمیل خود دریافت کنید', 'subtitle' => 'بدون تبلیغات، فقط محتوای ارزشمند',
                'placeholder' => 'ایمیل شما', 'button_text' => 'عضویت در خبرنامه',
                'background' => '#111827', 'text_color' => '#ffffff',
            ]),
        ];
    }

    /** EDUCATION / COURSE — online learning platform. Enrollment-first CTA, course-grid heavy. */
    private function homeEducationBlocks(): array
    {
        return [
            // TASK 3/3 Phase 6: 'hero_gradient' in an energetic purple tone
            // — distinct from the corporate homepage's indigo and the
            // magazine homepage's dark slate — with a real two-button CTA
            // (browse courses / enroll free).
            $this->block('hero_gradient', [
                'badge' => 'بیش از ۲۰۰ دورهٔ تخصصی',
                'title' => 'یادگیری آنلاین، مسیر رشد شما',
                'subtitle' => 'بیش از ۲۰۰ دورهٔ تخصصی با مدرک معتبر و پشتیبانی مستقیم مدرس در آموزشگاه رشد.',
                'button_text' => 'مشاهدهٔ دوره‌ها', 'button_url' => '/learning',
                'button2_text' => 'ثبت‌نام رایگان', 'button2_url' => '/account',
                'gradient_from' => '#7c3aed', 'gradient_to' => '#a855f7', 'text_color' => '#ffffff',
            ]),
            $this->block('stats_row', [
                'title' => '', 'background' => '#f8fafc', 'dividers' => true,
                'stats' => [
                    ['value' => 200, 'suffix' => '+', 'label' => 'دورهٔ آموزشی'],
                    ['value' => 15000, 'suffix' => '+', 'label' => 'دانشجو'],
                    ['value' => 60, 'suffix' => '+', 'label' => 'مدرس متخصص'],
                    ['value' => 94, 'suffix' => '%', 'label' => 'رضایت دانشجویان'],
                ],
            ]),
            $this->block('module:courses_grid', ['title' => 'دوره‌های محبوب', 'limit' => 6, 'columns' => '3', 'layout' => 'grid', 'show_price' => true]),
            $this->block('process_steps', [
                'title' => 'چطور یاد می‌گیرید؟', 'subtitle' => 'مسیری ساده از ثبت‌نام تا دریافت مدرک',
                'steps' => [
                    ['number' => '۱', 'title' => 'انتخاب دوره', 'text' => 'از میان ۲۰۰+ دوره، مسیر یادگیری خود را انتخاب کنید'],
                    ['number' => '۲', 'title' => 'یادگیری گام‌به‌گام', 'text' => 'ویدیوهای آموزشی و تمرین‌های عملی هر هفته'],
                    ['number' => '۳', 'title' => 'دریافت مدرک', 'text' => 'مدرک معتبر و پشتیبانی مدرس پس از پایان دوره'],
                ],
            ]),
            $this->block('testimonials_slider', [
                'title' => 'دانشجویان ما می‌گویند', 'columns' => '3', 'autoplay' => true, 'interval' => 6000,
                'items' => [
                    ['name' => 'پویان کریمی', 'role' => 'دانشجوی دورهٔ برنامه‌نویسی', 'text' => 'با اینکه هیچ پیش‌زمینه‌ای نداشتم، الان دارم به‌صورت فریلنس کار می‌گیرم.', 'rating' => 5],
                    ['name' => 'الناز فرهادی', 'role' => 'دانشجوی دورهٔ طراحی', 'text' => 'پشتیبانی مدرس در پروژه‌های عملی واقعاً فرق‌ساز بود.', 'rating' => 5],
                    ['name' => 'امیرحسین نادری', 'role' => 'دانشجوی دورهٔ بازاریابی', 'text' => 'محتوای دوره کاملاً به‌روز و کاربردی بود، نه صرفاً تئوری.', 'rating' => 4],
                ],
            ]),
            $this->block('module:course_enroll_cta', []),
            $this->block('faq', [
                'title' => 'سوالات متداول دوره‌ها',
                'items' => [
                    ['question' => 'مدرک پایان دوره معتبر است؟', 'answer' => 'بله، مدرک آموزشگاه رشد پس از پایان موفق دوره و آزمون نهایی صادر می‌شود.'],
                    ['question' => 'دسترسی به دوره چقدر است؟', 'answer' => 'دسترسی به محتوای دوره پس از ثبت‌نام نامحدود و مادام‌العمر است.'],
                    ['question' => 'امکان بازپرداخت وجود دارد؟', 'answer' => 'بله، تا ۷ روز پس از خرید امکان بازپرداخت کامل وجود دارد.'],
                ],
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
            $this->block('timeline', [
                'title' => 'مسیر رشد ما', 'subtitle' => 'نگاهی به مهم‌ترین نقاط عطف پلتفرم',
                'layout' => 'vertical',
                'items' => [
                    ['date' => '۱۳۹۸', 'title' => 'تأسیس شرکت', 'text' => 'فعالیت را با یک تیم سه‌نفرهٔ مهندسی و یک هدف ساده آغاز کردیم: ساخت سایت برای کسب‌وکارهای ایرانی را آسان کنیم.', 'icon' => '🚀', 'image' => '', 'link_label' => '', 'link_url' => ''],
                    ['date' => '۱۳۹۹', 'title' => 'عرضهٔ نسخهٔ اول پلتفرم', 'text' => 'اولین نسخهٔ Builder بصری منتشر شد و نخستین مشتریان واقعی سایت خود را با آن راه‌اندازی کردند.', 'icon' => '📦', 'image' => $this->img('about'), 'link_label' => '', 'link_url' => ''],
                    ['date' => '۱۴۰۱', 'title' => 'افزودن فروشگاه‌ساز آنلاین', 'text' => 'ماژول فروشگاه، سبد خرید و درگاه پرداخت به پلتفرم اضافه شد و مسیر فروش آنلاین برای مشتریان باز شد.', 'icon' => '🛒', 'image' => '', 'link_label' => '', 'link_url' => '/shop'],
                    ['date' => '۱۴۰۲', 'title' => 'عبور از هزار کسب‌وکار فعال', 'text' => 'با رشد پایدار، بیش از هزار کسب‌وکار ایرانی برای معرفی و فروش خدمات خود از پلتفرم استفاده کردند.', 'icon' => '📈', 'image' => '', 'link_label' => '', 'link_url' => ''],
                    ['date' => '۱۴۰۴', 'title' => 'توسعهٔ خدمات سازمانی', 'text' => 'با افزودن امکانات چندفروشگاهی و پشتیبانی اختصاصی، وارد بازار کسب‌وکارهای بزرگ‌تر شدیم.', 'icon' => '🏢', 'image' => '', 'link_label' => 'مشاهدهٔ تعرفه‌ها', 'link_url' => '/pricing'],
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
            // Real-image logo carousel — new mode/grayscale/image fields;
            // demonstrates the same block type's other homepage usages
            // (text-only marquee) still render unchanged.
            $this->block('client_logos', [
                'title' => 'برندهایی که به ما اعتماد کرده‌اند',
                'mode' => 'carousel', 'grayscale' => true, 'visible_count' => 4,
                'logos' => [
                    ['name' => 'دیجی‌مد', 'image' => $this->img('portfolio_1'), 'url' => '#'],
                    ['name' => 'آموزشگاه رشد', 'image' => $this->img('portfolio_2'), 'url' => '#'],
                    ['name' => 'کلینیک سلامت'],
                    ['name' => 'استودیو نقش'],
                ],
            ]),
        ];
    }

    private function servicesBlocks(): array
    {
        return [
            $this->block('service_cards', [
                'title' => 'خدماتی که ارائه می‌دهیم',
                'services' => [
                    ['icon' => '🎨', 'title' => 'طراحی سایت اختصاصی', 'text' => 'طراحی سفارشی مطابق هویت برند شما', 'url' => '/service-website-design'],
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

    private function serviceDetailBlocks(): array
    {
        return [
            $this->block('hero_split', [
                'badge' => 'خدمات طراحی وب',
                'title' => 'طراحی و توسعهٔ سایت اختصاصی',
                'subtitle' => 'سایتی متناسب با هویت برند شما، سریع، امن و آمادهٔ رشد — از طراحی رابط کاربری تا توسعهٔ فنی و تحویل نهایی.',
                'button_text' => 'دریافت مشاورهٔ رایگان', 'button_url' => '/contact',
                'button2_text' => 'مشاهدهٔ نمونه‌کارها', 'button2_url' => '/portfolio',
                'image_url' => $this->img('about'), 'image_side' => 'left',
            ]),
            $this->block('features_grid', [
                'title' => 'این خدمت شامل چه مواردی است؟',
                'subtitle' => 'یک بستهٔ کامل، بدون نیاز به تأمین‌کنندهٔ جداگانه',
                'columns' => '3', 'style' => 'cards',
                'features' => [
                    ['icon' => '🎨', 'title' => 'طراحی رابط کاربری اختصاصی', 'text' => 'طراحی از صفر، مطابق هویت بصری برند شما — نه یک قالب آماده.'],
                    ['icon' => '📱', 'title' => 'ریسپانسیو کامل', 'text' => 'نمایش صحیح در موبایل، تبلت و دسکتاپ.'],
                    ['icon' => '⚡', 'title' => 'سرعت بارگذاری بالا', 'text' => 'بهینه‌سازی تصاویر و کد برای تجربهٔ کاربری سریع.'],
                    ['icon' => '🔍', 'title' => 'سئوی فنی پایه', 'text' => 'ساختار HTML معنایی و متادیتای صحیح از روز اول.'],
                    ['icon' => '🔒', 'title' => 'گواهی امنیتی SSL', 'text' => 'اتصال امن HTTPS برای همهٔ صفحات.'],
                    ['icon' => '🛠️', 'title' => 'پشتیبانی پس از تحویل', 'text' => 'یک ماه پشتیبانی رایگان پس از راه‌اندازی.'],
                ],
            ]),
            $this->block('process_steps', [
                'title' => 'روند اجرای این خدمت', 'subtitle' => 'از جلسهٔ اول تا تحویل نهایی',
                'steps' => [
                    ['number' => '۱', 'title' => 'مشاوره و بررسی نیاز', 'text' => 'جلسهٔ اولیه برای شناخت کسب‌وکار و هدف سایت'],
                    ['number' => '۲', 'title' => 'طراحی رابط کاربری', 'text' => 'ارائهٔ نمونهٔ طراحی و اعمال بازخورد شما'],
                    ['number' => '۳', 'title' => 'توسعهٔ فنی', 'text' => 'پیاده‌سازی طراحی به‌صورت واقعی و تست‌شده'],
                    ['number' => '۴', 'title' => 'تحویل و راه‌اندازی', 'text' => 'انتشار نهایی سایت و آموزش استفاده'],
                ],
            ]),
            $this->block('stats_row', [
                'stats' => [
                    ['value' => 150, 'suffix' => '+', 'label' => 'پروژهٔ موفق'],
                    ['value' => 7, 'suffix' => ' روز', 'label' => 'میانگین زمان تحویل نسخهٔ اول'],
                    ['value' => 98, 'suffix' => '%', 'label' => 'رضایت مشتریان'],
                ],
            ]),
            $this->block('faq', [
                'title' => 'سوالات متداول دربارهٔ این خدمت',
                'items' => [
                    ['question' => 'هزینهٔ این خدمت چقدر است؟', 'answer' => 'هزینه بسته به پیچیدگی پروژه متفاوت است؛ برای برآورد دقیق در بخش <a href="/pricing">تعرفه‌ها</a> را ببینید یا با ما تماس بگیرید.'],
                    ['question' => 'چقدر طول می‌کشد؟', 'answer' => 'نسخهٔ اول معمولاً بین ۷ تا ۲۱ روز کاری، بسته به دامنهٔ پروژه.'],
                    ['question' => 'آیا محتوای سایت را هم آماده می‌کنید؟', 'answer' => 'تولید محتوا خدمتی مجزاست؛ می‌توانید آن را در مشاورهٔ اولیه اضافه کنید.'],
                ],
            ]),
            $this->block('service_cards', [
                'title' => 'خدمات مرتبط دیگر',
                'services' => [
                    ['icon' => '🛒', 'title' => 'راه‌اندازی فروشگاه آنلاین', 'text' => 'از کاتالوگ محصول تا درگاه پرداخت', 'url' => '/shop'],
                    ['icon' => '📈', 'title' => 'بهینه‌سازی و سئو', 'text' => 'افزایش دیده‌شدن سایت شما در گوگل', 'url' => '/contact'],
                ],
            ]),
            $this->block('cta', [
                'title' => 'آمادهٔ شروع پروژه هستید؟', 'subtitle' => 'همین امروز با ما تماس بگیرید',
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
                    ['name' => 'سارا احمدی', 'role' => 'مدیرعامل و بنیان‌گذار', 'image' => $this->img('team_1'), 'bio' => 'بیش از ۱۰ سال تجربه در توسعهٔ کسب‌وکار دیجیتال'],
                    ['name' => 'رضا کریمی', 'role' => 'مدیر فنی', 'image' => $this->img('team_2'), 'bio' => 'معمار زیرساخت پلتفرم'],
                    ['name' => 'نگار حسینی', 'role' => 'مدیر محصول', 'image' => $this->img('team_3'), 'bio' => 'مسئول تجربهٔ کاربری'],
                    ['name' => 'علی محمدی', 'role' => 'مدیر پشتیبانی', 'image' => $this->img('team_4'), 'bio' => 'سرپرست تیم پشتیبانی مشتریان'],
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

    // TEMPORARY QA fixture — see pagesFor()'s own comment. Remove this
    // method and its pagesFor() entry together once the visual
    // verification pass is complete.
    // Commercial Productization phase — Porto "Digital Agency"-inspired
    // rhythm: hero → capability grid → oversized stats → process steps →
    // integrations strip → testimonial → final CTA, alternating light/
    // dark backgrounds section-to-section rather than a flat repeat.
    private function saasBlocks(): array
    {
        return [
            $this->block('hero_gradient', [
                'badge' => 'پلتفرم SaaS',
                'title' => 'یک پلتفرم، همهٔ ابزارهای رشد کسب‌وکار شما',
                'subtitle' => 'سایت، فروشگاه، وبلاگ و دوره‌های آموزشی را روی یک زیرساخت واحد بسازید و مدیریت کنید — بدون نیاز به چند سرویس جدا و بدون دانش فنی.',
                'button_text' => 'شروع رایگان ۱۴ روزه', 'button_url' => '/pricing',
                'button2_text' => 'مشاهدهٔ امکانات', 'button2_url' => '#capabilities',
                'gradient_from' => '#4f46e5', 'gradient_to' => '#7c3aed', 'text_color' => '#ffffff',
            ]),
            $this->block('heading', ['text' => 'در یک نگاه با پلتفرم آشنا شوید', 'level' => 2]),
            $this->block('video', [
                'url' => $this->img('hero_video'), 'poster' => $this->img('hero_video_poster'),
                'caption' => 'معرفی کوتاه پلتفرم جامع کسب‌وکار در کمتر از یک دقیقه',
                'autoplay' => false, 'loop' => true, 'controls' => true,
            ]),
            $this->block('bento_grid', [
                'title' => 'همه‌چیز که برای رشد لازم دارید',
                'items' => [
                    ['title' => 'سازندهٔ بصری صفحه', 'text' => 'طراحی صفحات با پیش‌نمایش زنده، بدون کدنویسی', 'icon' => '🎨', 'size' => 'large', 'bg_color' => '#eef2ff', 'text_color' => '#3730a3'],
                    ['title' => 'فروشگاه‌ساز کامل', 'text' => 'مدیریت محصول، سبد خرید و پرداخت آنلاین', 'icon' => '🛒', 'size' => 'small', 'bg_color' => '#f0fdf4', 'text_color' => '#14532d'],
                    ['title' => 'وبلاگ و مقالات', 'text' => 'انتشار محتوا با سئوی داخلی', 'icon' => '📝', 'size' => 'small', 'bg_color' => '#fef9c3', 'text_color' => '#713f12'],
                    ['title' => 'دوره‌های آموزشی', 'text' => 'فروش دوره، مدیریت دانشجو و گواهی پایان دوره', 'icon' => '🎓', 'size' => 'small', 'bg_color' => '#fdf4ff', 'text_color' => '#6b21a8'],
                    ['title' => 'گزارش و تحلیل', 'text' => 'داشبورد آمار فروش و بازدید در لحظه', 'icon' => '📊', 'size' => 'small', 'bg_color' => '#f0f9ff', 'text_color' => '#0c4a6e'],
                ],
            ]),
            $this->block('stats_row', [
                'title' => 'اعتماد کسب‌وکارهای ایرانی',
                'stats' => [
                    ['value' => 3200, 'suffix' => '+', 'label' => 'کسب‌وکار فعال'],
                    ['value' => 99, 'suffix' => '%', 'label' => 'آپ‌تایم زیرساخت'],
                    ['value' => 24, 'suffix' => '/۷', 'label' => 'پشتیبانی فارسی'],
                    ['value' => 5, 'suffix' => 'سال', 'label' => 'سابقهٔ فعالیت'],
                ],
                'background' => '#111827', 'dividers' => true,
            ]),
            $this->block('process_steps', [
                'title' => 'در سه مرحله شروع کنید', 'subtitle' => 'راه‌اندازی کامل، معمولاً کمتر از یک روز',
                'steps' => [
                    ['number' => '۱', 'title' => 'ثبت‌نام و انتخاب قالب', 'text' => 'یکی از قالب‌های آماده مثل همین قالب جامع چندمنظوره را انتخاب کنید'],
                    ['number' => '۲', 'title' => 'شخصی‌سازی محتوا', 'text' => 'متن، تصاویر و رنگ‌بندی را با سازندهٔ بصری متناسب برند خود تغییر دهید'],
                    ['number' => '۳', 'title' => 'انتشار و رشد', 'text' => 'دامنهٔ خود را متصل کنید و کسب‌وکارتان را آنلاین کنید'],
                ],
            ]),
            $this->block('client_logos', [
                'title' => 'یکپارچه با ابزارهای مورد اعتماد شما',
                'mode' => 'carousel', 'grayscale' => true, 'visible_count' => 4,
                'logos' => [
                    ['name' => 'درگاه پرداخت زرین‌پال'], ['name' => 'پیامک کاوه‌نگار'],
                    ['name' => 'پست ایران'], ['name' => 'اسنپ‌باکس'],
                ],
            ]),
            $this->block('testimonials_slider', [
                'title' => 'صاحبان کسب‌وکار چه می‌گویند', 'columns' => '3', 'mode' => 'carousel', 'autoplay' => true,
                'items' => [
                    ['name' => 'فرزاد کریمی', 'role' => 'بنیان‌گذار', 'company' => 'فروشگاه دیجی‌مد', 'avatar' => $this->img('team_1'), 'text' => 'در کمتر از یک روز فروشگاهم را راه‌اندازی کردم؛ پشتیبانی فارسی واقعاً فرق می‌کند.', 'rating' => 5],
                    ['name' => 'مریم صادقی', 'role' => 'مدیر آموزشگاه', 'company' => 'آموزشگاه رشد', 'avatar' => $this->img('team_2'), 'text' => 'بخش دوره‌های آموزشی دقیقاً همان چیزی بود که برای فروش آنلاین دوره‌ها نیاز داشتم.', 'rating' => 5],
                    ['name' => 'حسین یزدانی', 'role' => 'مدیرعامل', 'company' => 'کلینیک سلامت', 'avatar' => $this->img('team_3'), 'text' => 'سازندهٔ بصری فوق‌العاده ساده است؛ بدون دانش فنی سایت را خودم به‌روز می‌کنم.', 'rating' => 4],
                ],
            ]),
            $this->block('cta', [
                'title' => 'همین امروز کسب‌وکار خود را آنلاین کنید',
                'subtitle' => 'بدون نیاز به کارت اعتباری — ۱۴ روز رایگان امتحان کنید',
                'button_text' => 'شروع رایگان', 'button_url' => '/pricing', 'background' => 'primary',
            ]),
        ];
    }

    // Commercial Productization phase — dedicated Category Slider/Grid
    // showcase page ("دسته‌بندی محصولات" in the requested 12-page list).
    private function productCategoriesBlocks(): array
    {
        return [
            $this->block('heading', ['text' => 'دسته‌بندی محصولات', 'level' => 1]),
            $this->block('text', ['content' => 'محصولات ما را بر اساس دسته‌بندی مورد نظرتان مرور کنید.']),
            $this->block('category_slider', [
                'title' => 'دسته‌بندی‌های محبوب', 'layout' => 'carousel', 'columns' => '4', 'autoplay' => true,
            ]),
            $this->block('heading', ['text' => 'همهٔ دسته‌بندی‌ها', 'level' => 2]),
            $this->block('category_slider', [
                'title' => '', 'layout' => 'grid', 'columns' => '4',
            ]),
            $this->block('cta', [
                'title' => 'دنبال چیز خاصی می‌گردید؟', 'subtitle' => 'با تیم پشتیبانی ما در تماس باشید',
                'button_text' => 'تماس با ما', 'button_url' => '/contact', 'background' => 'primary',
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

        // TASK 3/3 Phase 5 (BLD-17): this used to always set 'children' =>
        // [] regardless of container type (a dead ternary — both branches
        // were literally the same empty array). Both the shared and
        // customer-site renderers extract children via
        // `$block['children'] ?? $block['blocks'] ?? ($props['blocks'] ?? [])`
        // — since `??` only falls through on a genuinely MISSING/null key,
        // not an empty-but-present array, that always-present empty
        // 'children' key permanently shadowed every container's real
        // content authored via `props.blocks` (tabs/tab_panel — see
        // homeServiceBlocks() et al.'s `$this->block('tabs', ['blocks' =>
        // [...]])` callers). Real browser certification caught this: the
        // public 'utilities' page's tabs rendered a real, correctly
        // labeled nav bar over a permanently EMPTY panel area. Omitting
        // the key entirely (rather than trying to duplicate here which
        // types are containers) lets the existing, correct `??` fallback
        // chain do what it was already designed to do.
        return [
            'id' => 'qa_' . str_replace([':', '-'], '_', $resolvedType) . '_' . substr(md5(json_encode($props) . microtime()), 0, 6),
            'type' => $resolvedType,
            'props' => $props,
        ];
    }
}
