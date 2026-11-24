<?php

namespace Database\Seeders;

use App\Models\Theme;
use App\Models\ThemeMarketplace;
use App\Services\ThemeBuilder\ThemeBuilderService;
use App\Services\ThemeCatalog\ThemeRegistry;
use Illuminate\Database\Seeder;
use SaaSPlatform\ThemeRuntime\TemplateLibrary;

/**
 * Canonical energy / electrical-industry theme: «قالب صنعت برق».
 *
 * PLATFORM-THEME-SPEC-v1 (ADR-083) packaged as one assignable Theme, with the
 * ADR-084 header. It defines nothing of its own: every block comes from the
 * theme-runtime template library (resources/library/templates/solar-*.json)
 * and every colour from the `solar-dark` token preset, so the Theme is the
 * same canonical widgets (nav_header, nav_footer, the commercial widgets)
 * configured through props and --tb-* tokens. Editing a blueprint changes
 * this Theme on the next seed; there is no second copy to keep in step.
 *
 * Canonical seeder convention (ThemeCatalogRebuildCommand): published for
 * authoring/workflow reasons only, so it shows in the catalog and can be
 * assigned to a site. Commercial eligibility still needs a ThemeRelease with
 * a passing Commercial Quality Report (`theme-release:backfill`).
 */
final class SolarIndustryThemeSeeder extends Seeder
{
    public const SLUG = 'solar-industry';
    public const NAME = 'قالب صنعت برق';
    public const VERSION = '1.1.0';
    public const TOKEN_PRESET = 'solar-dark';

    /**
     * Theme page key => [library template, title, slug]. The home page is the
     * site root; the others render at /page/{slug} (the about page is the one
     * "@link:about" points at).
     *
     * Every page template of the solar-industry family in the library is a
     * page of this theme (SolarIndustryThemeSeederTest enforces it): 23
     * content pages plus the auth canvas. 1.0.0 shipped only the first five:
     * the other templates were added to the
     * library on 2026-11-14 (a4d8773a) without being added here, so installed
     * sites had 5 pages while the header, footer and pages linked to 13 of
     * the missing ones (/page/projects, /page/consulting, ...). Slugs are the
     * templates' own `route`; the five 1.0.0 entries keep their keys and
     * slugs so existing sites upgrade cleanly.
     */
    public const PAGES = [
        'home' => ['solar-page-home', 'صفحه اصلی', ''],
        'about' => ['solar-page-about', 'درباره ما', 'about'],
        'products' => ['solar-page-products', 'محصولات', 'products'],
        'courses' => ['solar-page-courses', 'آکادمی آموزش', 'courses'],
        'contact' => ['solar-page-contact', 'تماس با ما', 'contact'],
        // 1.1.0
        'services_overview' => ['solar-page-services_overview', 'مرور کلی خدمات فنی و مهندسی', 'services-overview'],
        'residential' => ['solar-page-residential', 'سیستم‌های خورشیدی مسکونی', 'residential'],
        'commercial' => ['solar-page-commercial', 'سیستم‌های خورشیدی تجاری', 'commercial'],
        'industrial' => ['solar-page-industrial', 'سیستم‌های خورشیدی صنعتی', 'industrial'],
        'ongrid' => ['solar-page-ongrid', 'سیستم‌های خورشیدی متصل به شبکه (On-Grid)', 'ongrid'],
        'offgrid' => ['solar-page-offgrid', 'سیستم‌های خورشیدی مستقل از شبکه (Off-Grid)', 'offgrid'],
        'hybrid' => ['solar-page-hybrid', 'سیستم‌های خورشیدی هیبریدی (Hybrid)', 'hybrid'],
        'emergency_power' => ['solar-page-emergency_power', 'سیستم‌های برق اضطراری خورشیدی (Solar UPS)', 'emergency-power'],
        'solar_pumping' => ['solar-page-solar_pumping', 'پمپاژ خورشیدی آب کشاورزی', 'solar-pumping'],
        'consulting' => ['solar-page-consulting', 'مشاوره تخصصی و امکان‌سنجی خورشیدی', 'consulting'],
        'expert_consultation' => ['solar-page-expert_consultation', 'درخواست وقت مشاوره حضوری و تلفنی', 'expert-consultation'],
        'design' => ['solar-page-design', 'طراحی مهندسی سیستم‌های خورشیدی', 'design'],
        'installation' => ['solar-page-installation', 'اجرا و نصب نیروگاه خورشیدی', 'installation'],
        'maintenance' => ['solar-page-maintenance', 'نگهداری و پایش نیروگاه خورشیدی', 'maintenance'],
        'projects' => ['solar-page-projects', 'پروژه‌های اجرایی نیروگاه‌های خورشیدی', 'projects'],
        'best_system' => ['solar-page-best_system', 'راهنمای انتخاب بهترین سیستم خورشیدی', 'best-system'],
        'solar_calculator' => ['solar-page-solar_calculator', 'ماشین‌حساب آنلاین خورشیدی', 'solar-calculator'],
        'download_center' => ['solar-page-download_center', 'مرکز دانلود کاتالوگ و اسناد فنی', 'download-center'],
        // The customer site renders the theme's `login` page at /login
        // (LoginController) instead of its default auth screen; this is the
        // solar sign-in/sign-up canvas (auth_mode dual) made for that slot.
        'login' => ['auth-solar-industrial', 'ورود و عضویت', 'login'],
    ];

    public function run(): void
    {
        $theme = Theme::firstOrNew(['slug' => self::SLUG, 'site_id' => null]);

        if ($theme->exists && $theme->current_release_id !== null) {
            return;
        }

        $theme->fill([
            'name' => self::NAME,
            // themes.description is a 255-character column.
            'description' => 'قالب تیرهٔ صنعتی برای شرکت‌های انرژی خورشیدی و برق: هدر با مگامنوی کارتی، ورود و ثبت‌نام و سبد خرید؛ ۲۴ صفحه شامل خدمات، انواع سیستم‌ها، مشاوره، پروژه‌ها، آکادمی، فروشگاه، تماس و ورود.',
            'type' => Theme::TYPE_FULL,
            'category' => Theme::CATEGORY_INDUSTRIAL,
            'source' => Theme::SOURCE_SEEDER,
            'pages' => self::pages(),
            'header_blocks' => self::library('solar-header', 'solar-header'),
            'footer_blocks' => self::library('solar-footer', 'solar-footer'),
            'global_styles' => self::globalStyles(app(ThemeBuilderService::class)->defaultGlobalStyles()),
            'settings' => array_merge(is_array($theme->settings ?? null) ? $theme->settings : [], [
                'preset' => self::TOKEN_PRESET,
                'spec' => 'PLATFORM-THEME-SPEC-v1',
                'supported_locales' => ['fa-IR'],
                'default_locale' => 'fa-IR',
                'locale_contract' => [
                    'directions' => ['fa-IR' => 'rtl'],
                    'font_families' => ['fa-IR' => 'Vazirmatn'],
                ],
                'meta' => [
                    'id' => self::SLUG,
                    'version' => self::VERSION,
                    'required_platform_version' => '1.0.0',
                    'supported_modules' => ['shop', 'courses', 'forms', 'menu'],
                    'changelog' => [
                        '1.1.0' => [
                            'هجده صفحهٔ دیگر کتابخانهٔ صنعت برق به قالب اضافه شد: خدمات، سیستم‌های مسکونی، تجاری، صنعتی، متصل به شبکه، مستقل، هیبریدی، برق اضطراری، پمپاژ خورشیدی، مشاوره، درخواست مشاوره، طراحی، اجرا و نصب، نگهداری، پروژه‌ها، راهنمای انتخاب سیستم، ماشین‌حساب و مرکز دانلود.',
                            'صفحهٔ ورود و عضویت صنعتی (Auth Canvas) صفحهٔ /login سایت را می‌سازد. جمعاً ۲۴ صفحه.',
                            'پیوندهای هدر، فوتر و صفحه‌ها به این صفحه‌ها (مثل /page/projects و /page/consulting) دیگر به صفحهٔ ناموجود نمی‌روند.',
                        ],
                        '1.0.0' => [
                            'بستهٔ کامل قالب صنعت برق: هدر، فوتر و پنج صفحه از کتابخانهٔ قالب‌ها.',
                            'هدر با مگامنوی کارتی، آیکون‌های قاب‌دار، نشان‌ها، کارت‌های پیشنهادی و دکمه‌های ورود و ثبت‌نام (ADR-084).',
                        ],
                    ],
                ],
            ]),
        ]);

        $theme->status = Theme::STATUS_PUBLISHED;
        // Listing and price belong to the admin («فروشگاه قالب ← Listing»).
        // A new theme starts unlisted (ThemeRegistry owner rule, 2026-11-17).
        // A re-run never touches an existing theme's listing or price: it
        // used to set is_marketplace=true and price 0 every time, which would
        // have re-listed a theme the admin had unlisted, for free.
        if (! $theme->exists) {
            $theme->is_marketplace = ThemeRegistry::LIST_IN_MARKETPLACE_ON_INSTALL;
            $theme->marketplace_price = 0;
        }
        $theme->save();

        // Honest marketplace metadata (NexusSeedCommand convention): no
        // fabricated sales counts, ratings or featured flags. Created once;
        // later edits belong to the admin listing form.
        ThemeMarketplace::firstOrCreate(['theme_id' => $theme->id], [
            'marketplace_description' => $theme->description,
            'category_tags' => ['صنعتی', 'انرژی خورشیدی', 'صنعت برق', 'شرکتی', 'فروشگاهی'],
            'is_featured' => false,
            'is_new_release' => false,
            'is_bestseller' => false,
            'annual_price' => 0,
        ]);
    }

    /** @return array<string, array<string, mixed>> */
    public static function pages(): array
    {
        $pages = [];
        foreach (self::PAGES as $key => [$template, $title, $slug]) {
            $pages[$key] = [
                'title' => $title,
                'slug' => $slug,
                'is_home' => $slug === '',
                'blocks' => self::library($template, 'solar-' . $key),
            ];
        }

        return $pages;
    }

    /**
     * The `solar-dark` preset over the platform defaults. The palette is dark
     * already, so the light/dark toggle palette is switched off.
     *
     * @param array<string, mixed> $defaults
     * @return array<string, mixed>
     */
    public static function globalStyles(array $defaults): array
    {
        $preset = TemplateLibrary::tokenPresets()[self::TOKEN_PRESET]['global_styles']
            ?? throw new \RuntimeException('Token preset ' . self::TOKEN_PRESET . ' is missing from the template library.');

        $styles = array_replace_recursive($defaults, $preset);
        $styles['direction'] = 'rtl';
        $styles['locale'] = 'fa-IR';
        $styles['dark_mode'] = array_merge(is_array($styles['dark_mode'] ?? null) ? $styles['dark_mode'] : [], ['enabled' => false]);

        return $styles;
    }

    /**
     * A library template's resolved blocks, with ids that are stable across
     * seeds and unique inside this Theme (the library's own ids are
     * placeholders that repeat when a page uses a widget twice).
     *
     * @return list<array<string, mixed>>
     */
    private static function library(string $template, string $prefix): array
    {
        $found = TemplateLibrary::find(TemplateLibrary::ID_PREFIX . $template)
            ?? throw new \RuntimeException("Template library blueprint {$template} is missing.");
        $counter = 0;
        $assign = function (array $block) use (&$assign, &$counter, $prefix): array {
            $block['id'] = $prefix . '-' . (++$counter) . '-' . str_replace(['_', ':'], '-', (string) $block['type']);
            $block['children'] = array_map($assign, $block['children'] ?? []);

            return $block;
        };

        return array_map($assign, $found['blocks']);
    }
}
