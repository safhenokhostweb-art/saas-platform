<?php

namespace Database\Seeders;

use App\Models\Theme;
use App\Models\ThemeMarketplace;
use App\Services\ThemeBuilder\ThemeBuilderService;
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
    public const VERSION = '1.0.0';
    public const TOKEN_PRESET = 'solar-dark';

    /**
     * Theme page key => [library template, title, slug]. The home page is the
     * site root; the others render at /page/{slug} (the about page is the one
     * "@link:about" points at).
     */
    public const PAGES = [
        'home' => ['solar-page-home', 'صفحه اصلی', ''],
        'about' => ['solar-page-about', 'درباره ما', 'about'],
        'products' => ['solar-page-products', 'محصولات', 'products'],
        'courses' => ['solar-page-courses', 'آکادمی آموزش', 'courses'],
        'contact' => ['solar-page-contact', 'تماس با ما', 'contact'],
    ];

    public function run(): void
    {
        $theme = Theme::firstOrNew(['slug' => self::SLUG, 'site_id' => null]);

        $theme->fill([
            'name' => self::NAME,
            // themes.description is a 255-character column.
            'description' => 'قالب تیرهٔ صنعتی برای شرکت‌های انرژی خورشیدی و برق: هدر با مگامنوی کارتی، ورود و ثبت‌نام و سبد خرید؛ صفحه‌های اصلی، درباره ما، محصولات، آکادمی و تماس.',
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
                        self::VERSION => [
                            'بستهٔ کامل قالب صنعت برق: هدر، فوتر و پنج صفحه از کتابخانهٔ قالب‌ها.',
                            'هدر با مگامنوی کارتی، آیکون‌های قاب‌دار، نشان‌ها، کارت‌های پیشنهادی و دکمه‌های ورود و ثبت‌نام (ADR-084).',
                        ],
                    ],
                ],
            ]),
        ]);

        $theme->status = Theme::STATUS_PUBLISHED;
        $theme->is_marketplace = true;
        $theme->marketplace_price = 0;
        $theme->save();

        // Honest marketplace row (NexusSeedCommand convention): no fabricated
        // sales counts, ratings or featured flags.
        ThemeMarketplace::updateOrCreate(['theme_id' => $theme->id], [
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
