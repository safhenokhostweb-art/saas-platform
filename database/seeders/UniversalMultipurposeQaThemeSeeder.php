<?php

namespace Database\Seeders;

use App\Models\Theme;
use App\Services\ThemeBuilder\BlockRegistry;
use App\Services\ThemeBuilder\ThemeBuilderService;
use Illuminate\Database\Seeder;

/**
 * A real, reusable master-theme draft for visual and Builder regression QA.
 *
 * It owns only portable Theme structure. Tenant business records (products,
 * posts, forms, courses, etc.) remain the responsibility of the receiving
 * site's ordinary fixture/seeding paths. The block list is intentionally
 * derived from BlockRegistry: adding a normal canonical block makes this
 * fixture visibly incomplete until it is deliberately classified.
 */
class UniversalMultipurposeQaThemeSeeder extends Seeder
{
    public const SLUG = 'universal-multipurpose-qa';

    public function run(): void
    {
        $registry = BlockRegistry::all();
        // customer-site deliberately de-exposes this platform-preview-only
        // entry because it has no tenant content adapter/model. A portable
        // master Theme must not advertise a block a receiving site cannot use.
        unset($registry['module:gallery_widget']);
        $pages = $this->pagesFor($registry);

        Theme::updateOrCreate(
            ['slug' => self::SLUG, 'site_id' => null],
            [
                'name' => 'قالب جامع چندمنظوره',
                'description' => 'قالب مرجع چندمنظوره برای بررسی یکپارچگی Builder و قابلیت‌های قابل‌نمایش سایت.',
                'type' => Theme::TYPE_FULL,
                'category' => Theme::CATEGORY_CORPORATE,
                'source' => Theme::SOURCE_SEEDER,
                // A release is intentionally not fabricated by a seeder;
                // commercial publication remains ThemeReleaseService's gate.
                'status' => Theme::STATUS_DRAFT,
                'header_blocks' => [
                    $this->block('nav_header', $registry),
                    $this->block('nav_menu', $registry),
                ],
                'footer_blocks' => [
                    $this->block('footer_cta', $registry, fallback: 'cta'),
                    $this->block('nav_footer', $registry),
                    $this->block('social_icons', $registry),
                ],
                'pages' => $pages,
                'global_styles' => array_merge(
                    app(ThemeBuilderService::class)->defaultGlobalStyles(),
                    ['primary_color' => '#4f46e5', 'secondary_color' => '#6366f1', 'background_color' => '#f8fafc', 'text_color' => '#111827']
                ),
                'settings' => [
                    'qa_fixture' => true,
                    'meta' => ['supported_modules' => [], 'qa_structure_only' => true],
                ],
                'is_marketplace' => false,
                'license_type' => Theme::LICENSE_FREE,
            ]
        );
    }

    /** @param array<string,array> $registry @return array<string,array> */
    private function pagesFor(array $registry): array
    {
        $definitions = [
            'home' => ['خانه', '', ['hero', 'hero_split', 'features_grid', 'stats_row', 'project_cards', 'product_grid', 'blog_posts', 'testimonials', 'pricing_cards', 'faq', 'gallery', 'newsletter_form']],
            'about' => ['درباره ما', 'about', ['about_section', 'team', 'team_cards', 'skills_bars', 'client_logos', 'trust_badges']],
            'services' => ['خدمات', 'services', ['services_cards', 'service_cards', 'process_steps', 'cta', 'contact_split']],
            'portfolio' => ['نمونه‌کارها', 'portfolio', ['portfolio_grid', 'bento_grid', 'image', 'video', 'modal']],
            'blog' => ['وبلاگ', 'blog', ['module:blog_posts', 'module:blog_featured', 'module:blog_categories', 'search_bar', 'breadcrumb']],
            'shop' => ['فروشگاه', 'shop', ['products', 'product_slider', 'module:shop_product_grid', 'module:shop_product_slider', 'module:shop_categories', 'cart_icon', 'add_to_cart', 'price_box']],
            'learning' => ['آموزش', 'learning', ['module:courses_grid', 'module:course_enroll_cta', 'module:my_learning_link', 'module:edu_article_grid']],
            'contact' => ['تماس با ما', 'contact', ['contact_info', 'module:contact_quick', 'module:form_embed', 'module:support_button', 'module:whatsapp_widget']],
            'account' => ['حساب کاربری', 'account', ['login_register', 'module:account_quick_links', 'module:subscriber_stats', 'module:referral_widget', 'module:invoice_quick_pay']],
            'utilities' => ['امکانات', 'utilities', ['tabs', 'accordion', 'slider', 'countdown', 'promo_banner', 'email_capture', 'language_switcher', 'module:motion']],
        ];

        $used = [];
        $pages = [];
        foreach ($definitions as $key => [$title, $slug, $types]) {
            $blocks = [];
            foreach ($types as $type) {
                if (!isset($registry[$type])) continue;
                $blocks[] = $this->block($type, $registry);
                $used[$type] = true;
            }
            $pages[$key] = ['title' => $title, 'slug' => $slug, 'is_home' => $key === 'home', 'blocks' => $blocks];
        }

        // Keep every remaining canonical block inspectable without a second
        // hardcoded inventory. This page is the explicit regression surface.
        $pages['builder-coverage'] = [
            'title' => 'پوشش عناصر سازنده', 'slug' => 'builder-coverage', 'blocks' => array_map(
                fn (string $type) => $this->block($type, $registry),
                array_keys(array_diff_key($registry, $used))
            ),
        ];

        return $pages;
    }

    /** @param array<string,array> $registry */
    private function block(string $type, array $registry, ?string $fallback = null): array
    {
        $type = isset($registry[$type]) ? $type : $fallback;
        return [
            'id' => 'qa_' . str_replace([':', '-'], '_', $type),
            'type' => $type,
            'props' => $registry[$type]['defaultProps'] ?? [],
            'children' => BlockRegistry::isContainerType($type) ? [] : [],
        ];
    }
}
