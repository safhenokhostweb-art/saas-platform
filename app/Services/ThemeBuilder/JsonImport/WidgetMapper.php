<?php

namespace App\Services\ThemeBuilder\JsonImport;

use Illuminate\Support\Str;

/**
 * Step 2 of the JSON Import Pipeline.
 *
 * Translates source-builder widget/element types and their settings into
 * ThemeBuilder block format { id, type, props, blocks }.
 *
 * One source node → one ThemeBuilder block (or null to skip invisible nodes).
 */
class WidgetMapper
{
    /**
     * Cross-source widget → our block type map.
     * Keys are lowercase source widget types.
     */
    private const MAP = [
        // ── Elementor ────────────────────────────────────────────────────────
        'elementor' => [
            'heading'                  => 'heading',
            'text-editor'              => 'text',
            'image'                    => 'image',
            'button'                   => 'button',
            'video'                    => 'video',
            'icon'                     => 'icon',
            'icon-box'                 => 'icon',
            'icon-list'                => 'text',
            'counter'                  => 'counter',
            'countdown'                => 'counter',
            'progress'                 => 'progress_bar',
            'gallery'                  => 'gallery',
            'image-gallery'            => 'gallery',
            'image-carousel'           => 'gallery',
            'divider'                  => 'divider',
            'spacer'                   => 'divider',
            'posts'                    => 'blog_posts',
            'portfolio'                => 'blog_posts',
            'testimonial'              => 'testimonials',
            'testimonial-carousel'     => 'testimonials',
            'slides'                   => 'slider',
            'media-carousel'           => 'slider',
            'accordion'                => 'accordion',
            'toggle'                   => 'accordion',
            'tabs'                     => 'tabs',
            'nav-menu'                 => 'heading',
            'call-to-action'           => 'hero',
            'price-table'              => 'pricing_table',
            'price-list'               => 'pricing_table',
            'flip-box'                 => 'hero_split',
            'animated-headline'        => 'heading',
            'woocommerce-products'     => 'product_grid',
            'woocommerce-product-images' => 'gallery',
            'team-member'              => 'team',
            'team-members'             => 'team',
            'social-icons'             => 'icon',
            'map'                      => 'text',
            'html'                     => 'text',
            'shortcode'                => 'text',
            'text-path'                => 'text',
            'rating'                   => 'counter',
            'star-rating'              => 'counter',
            // ── Google Maps ──────────────────────────────────────────────────
            'google_maps'              => 'map',
            // ── ElementsKit Lite / Pro ───────────────────────────────────────
            'elementskit-heading'      => 'heading',
            'elementskit-accordion'    => 'accordion',
            'elementskit-tab'          => 'tabs',
            'elementskit-testimonial'  => 'testimonials',
            'elementskit-blog-posts'   => 'blog_posts',
            'elementskit-post-grid'    => 'blog_posts',
            'elementskit-post-list'    => 'blog_posts',
            'elementskit-slider'       => 'slider',
            'elementskit-image-accordion' => 'gallery',
            'elementskit-icon-box'     => 'icon',
            'elementskit-icon-list'    => 'text',
            'elementskit-team'         => 'team',
            'elementskit-team-carousel'=> 'team',
            'elementskit-price-table'  => 'pricing_table',
            'elementskit-nav-menu'     => 'heading',
            'elementskit-header-info'  => 'text',
            'elementskit-counter'      => 'counter',
            'elementskit-progress-bar' => 'progress_bar',
            'elementskit-caldera-forms'=> 'text',
            'elementskit-social-link'  => 'icon',
            // ── Jeg Kit (Jegtheme) ───────────────────────────────────────────
            'jeg_banner'               => 'hero',
            'jeg_carousel'             => 'slider',
            'jeg_post_block'           => 'blog_posts',
            'jeg_post_carousel'        => 'blog_posts',
            'jeg_countdown'            => 'counter',
            'jeg_news_ticker'          => 'text',
            // ── MetForm ──────────────────────────────────────────────────────
            'mf-form'                  => 'text',
            'metform-form'             => 'text',
            // ── Gum Elementor Addon ──────────────────────────────────────────
            'gum-list'                 => 'text',
            'gum-button'               => 'button',
            'gum-icon-box'             => 'icon',
            // ── Ultimate Addons / Header Footer Elementor ────────────────────
            'ha-logo'                  => 'image',
            'ha-nav-menu'              => 'heading',
        ],

        // ── Bricks ───────────────────────────────────────────────────────────
        'bricks' => [
            'section'       => 'section',
            'container'     => 'container',
            'block'         => 'container',
            'div'           => 'container',
            'heading'       => 'heading',
            'text'          => 'text',
            'text-basic'    => 'text',
            'rich-text'     => 'text',
            'paragraph'     => 'text',
            'image'         => 'image',
            'button'        => 'button',
            'video'         => 'video',
            'icon'          => 'icon',
            'icon-box'      => 'icon',
            'divider'       => 'divider',
            'spacer'        => 'divider',
            'counter'       => 'counter',
            'progress'      => 'progress_bar',
            'gallery'       => 'gallery',
            'posts'         => 'blog_posts',
            'accordion'     => 'accordion',
            'tabs'          => 'tabs',
            'slider'        => 'slider',
            'testimonials'  => 'testimonials',
            'team-members'  => 'team',
            'pricing-table' => 'pricing_table',
            'hero'          => 'hero',
            'form'          => 'text',
            'nav'           => 'heading',
            'logo'          => 'image',
            'social-icons'  => 'icon',
            'breadcrumbs'   => 'text',
        ],

        // ── Shopify ──────────────────────────────────────────────────────────
        'shopify' => [
            'image-banner'            => 'hero',
            'slideshow'               => 'slider',
            'featured-collection'     => 'product_grid',
            'collection-list'         => 'product_grid',
            'featured-product'        => 'product_grid',
            'rich-text'               => 'text',
            'video'                   => 'video',
            'multicolumn'             => 'columns',
            'newsletter'              => 'text',
            'contact-form'            => 'text',
            'testimonials'            => 'testimonials',
            'blog-posts'              => 'blog_posts',
            'image-with-text'         => 'hero_split',
            'collapsible-content'     => 'accordion',
            'predictive-search'       => 'text',
            'product-recommendations' => 'product_grid',
            'apps'                    => 'text',
            'announcement-bar'        => 'text',
            'countdown-timer'         => 'counter',
            'before-after-image'      => 'gallery',
            'popup'                   => 'modal',
            'map'                     => 'text',
        ],

        // ── Webflow ──────────────────────────────────────────────────────────
        'webflow' => [
            'Section'       => 'section',
            'Container'     => 'container',
            'Div Block'     => 'container',
            'Heading'       => 'heading',
            'Paragraph'     => 'text',
            'Text Block'    => 'text',
            'RichText'      => 'text',
            'Image'         => 'image',
            'Video'         => 'video',
            'Link'          => 'button',
            'Link Block'    => 'button',
            'Button'        => 'button',
            'Grid'          => 'grid',
            'Columns'       => 'columns',
            'Column'        => 'container',
            'List'          => 'text',
            'List Item'     => 'text',
            'Form Wrapper'  => 'text',
            'NavbarWrapper' => 'heading',
            'NavbarBrand'   => 'image',
            'NavbarMenu'    => 'heading',
            'Slider'        => 'slider',
            'Slide'         => 'container',
            'Tab Menu'      => 'tabs',
            'Tab Content'   => 'container',
            'Lightbox'      => 'gallery',
            'Symbol'        => 'container',
        ],

        // ── Custom / passthrough ─────────────────────────────────────────────
        'custom' => [],
    ];

    // ── Public API ────────────────────────────────────────────────────────────

    /**
     * Map a single source node to a ThemeBuilder block.
     *
     * @param  string  $source    One of ThemeStructureAnalyzer::SOURCE_*
     * @param  string  $nodeType  Source widget/element type
     * @param  array   $settings  Source widget settings
     * @param  array   $children  Already-mapped child blocks
     * @return array   ThemeBuilder block { id, type, props, blocks }
     */
    public function mapNode(string $source, string $nodeType, array $settings, array $children = []): array
    {
        $blockType = $this->resolveBlockType($source, $nodeType);
        $props     = $this->mapProps($source, $nodeType, $blockType, $settings);

        // TASK 2A (tabs container authoring fix): a `tabs` node's real
        // content is real `tab_panel` CHILD blocks now (canonical container
        // children — see BlockRegistry::isContainerType('tabs')), never
        // `props.tabs[].blocks`. This importer never recursed into a source
        // tabs widget's own per-tab content anyway (each mapped tab always
        // got an empty `blocks: []`, regardless of source), so synthesizing
        // real (still content-empty, title-only) tab_panel children here is
        // behavior-preserving — it only changes which shape carries the
        // titles, not what gets imported.
        if ($blockType === 'tabs' && empty($children)) {
            $children = $this->tabPanelChildren($settings);
        }

        // Inject children into layout blocks' blocks prop
        if (in_array($blockType, ['section', 'container', 'grid', 'columns', 'flex_container'])) {
            $props['blocks'] = $children;
        }

        return [
            'id'    => Str::random(8),
            'type'  => $blockType,
            'props' => $props,
            'blocks'=> $children,
        ];
    }

    /**
     * TASK 2A (tabs container authoring fix): real `tab_panel` children for
     * an imported `tabs` node, one per source tab — title only (this
     * importer has never recursed into a source tabs widget's own per-tab
     * content; each tab's `blocks` was always `[]` under the old
     * `props.tabs[].blocks` shape too). Tries every field name a mapped
     * source has used for a tab's title (`tab_title`/`title`/`label`)
     * rather than keeping the three near-duplicate per-source arms this
     * replaces.
     */
    private function tabPanelChildren(array $settings): array
    {
        $tabs = $settings['tabs'] ?? [['title' => 'تب ۱']];

        return array_map(
            fn ($tab) => [
                'id'     => Str::random(8),
                'type'   => 'tab_panel',
                'props'  => ['title' => $this->text($tab['tab_title'] ?? $tab['title'] ?? $tab['label'] ?? '')],
                'blocks' => [],
            ],
            $tabs,
        );
    }

    /**
     * Resolve block type from source type.
     */
    public function resolveBlockType(string $source, string $nodeType): string
    {
        $map     = self::MAP[$source] ?? [];
        $lcType  = strtolower($nodeType);

        // Exact match first, then lowercase match
        return $map[$nodeType] ?? $map[$lcType] ?? $this->fallbackType($nodeType);
    }

    // ── Props mapping per source ──────────────────────────────────────────────

    public function mapProps(string $source, string $nodeType, string $blockType, array $settings): array
    {
        return match ($source) {
            'elementor' => $this->elementorProps($nodeType, $blockType, $settings),
            'bricks'    => $this->bricksProps($nodeType, $blockType, $settings),
            'shopify'   => $this->shopifyProps($nodeType, $blockType, $settings),
            'webflow'   => $this->webflowProps($nodeType, $blockType, $settings),
            default     => $this->customProps($blockType, $settings),
        };
    }

    // ── Elementor props ───────────────────────────────────────────────────────

    private function elementorProps(string $nodeType, string $blockType, array $s): array
    {
        return match ($blockType) {
            'heading' => [
                // elementskit-heading stores title differently
                'text'  => $this->text(
                    $s['elementskit_section_title'] ?? $s['title'] ?? $s['heading_title'] ?? ''
                ),
                'level' => (int) ltrim($s['header_size'] ?? $s['heading_tag'] ?? 'h2', 'h'),
            ],
            'text' => [
                'content' => $this->text($s['text_editor'] ?? $s['editor'] ?? $s['content'] ?? ''),
            ],
            'image' => [
                'url'   => $s['image']['url'] ?? '',
                'alt'   => $s['image']['alt'] ?? '',
                'align' => 'center',
            ],
            'button' => [
                'text'  => $this->text($s['text'] ?? $s['button_text'] ?? 'دکمه'),
                'url'   => $s['link']['url'] ?? '#',
                'style' => 'primary',
            ],
            'map' => [
                'address' => $this->text($s['address'] ?? ''),
                'zoom'    => (int) ($s['zoom']['size'] ?? 12),
            ],
            'video' => [
                'url'     => $s['link']['url'] ?? $s['youtube_url'] ?? $s['vimeo_url'] ?? '',
                'caption' => $this->text($s['video_title'] ?? ''),
            ],
            'icon' => [
                'name' => $s['icon']['value'] ?? $s['selected_icon']['value'] ?? '',
                'size' => 'md',
            ],
            'counter' => [
                'value'  => (int) ($s['starting_number'] ?? $s['number'] ?? 0),
                'suffix' => $this->text($s['suffix'] ?? ''),
                'label'  => $this->text($s['title'] ?? ''),
            ],
            'progress_bar' => [
                'value' => (int) ($s['percent']['size'] ?? $s['progress_percentage'] ?? 70),
                'label' => $this->text($s['title'] ?? ''),
            ],
            'gallery' => [
                'title' => $this->text($s['gallery_title'] ?? ''),
                'items' => array_map(
                    fn($img) => ['url' => $img['url'] ?? '', 'alt' => $img['alt'] ?? ''],
                    $s['gallery'] ?? []
                ),
            ],
            'hero' => [
                'title'       => $this->text($s['title'] ?? $s['heading'] ?? ''),
                'subtitle'    => $this->text($s['description'] ?? $s['content'] ?? ''),
                'button_text' => $this->text($s['button_text'] ?? 'بیشتر بدانید'),
                'button_url'  => $s['button_link']['url'] ?? '#',
                'background'  => $s['background_color'] ?? 'primary',
            ],
            'hero_split' => [
                'title'      => $this->text($s['title'] ?? ''),
                'subtitle'   => $this->text($s['description'] ?? ''),
                'button_text'=> $this->text($s['button_text'] ?? 'شروع کنید'),
                'button_url' => $s['button_link']['url'] ?? '#',
                'image_url'  => $s['image']['url'] ?? '',
            ],
            'testimonials' => [
                'title' => 'نظرات مشتریان',
                'limit' => 5,
            ],
            'blog_posts' => [
                'title' => $this->text($s['title'] ?? 'آخرین مطالب'),
                'limit' => (int) ($s['posts_per_page'] ?? 6),
            ],
            'product_grid' => [
                'columns' => 4,
                'limit'   => (int) ($s['posts_per_page'] ?? 8),
            ],
            'pricing_table' => [
                'title' => $this->text($s['heading'] ?? 'قیمت‌گذاری'),
            ],
            'accordion' => [
                'items' => array_map(
                    fn($item) => ['title' => $this->text($item['tab_title'] ?? $item['title'] ?? ''), 'blocks' => []],
                    $s['tabs'] ?? $s['accordion_items'] ?? [['title' => 'آیتم']]
                ),
            ],
            // TASK 2A: `tabs` no longer has a props arm here — mapNode()
            // builds its real tab_panel children directly from $settings
            // via tabPanelChildren(), not via mapProps()'s per-blockType
            // switch. Falls through to `default => []` below.
            'slider' => [
                'slides'   => [],
                'autoplay' => true,
                'interval' => 5000,
            ],
            'team' => ['title' => 'تیم ما', 'limit' => 6],
            default => [],
        };
    }

    // ── Bricks props ──────────────────────────────────────────────────────────

    private function bricksProps(string $nodeType, string $blockType, array $s): array
    {
        return match ($blockType) {
            'heading' => [
                'text'  => $this->text($s['text'] ?? $s['heading'] ?? ''),
                'level' => (int) ltrim($s['tag'] ?? 'h2', 'h'),
            ],
            'text' => [
                'content' => $this->text($s['text'] ?? $s['content'] ?? $s['richText'] ?? ''),
            ],
            'image' => [
                'url'   => $s['image']['url'] ?? $s['image']['filename'] ?? '',
                'alt'   => $s['image']['alt'] ?? '',
                'align' => 'center',
            ],
            'button' => [
                'text'  => $this->text($s['text'] ?? $s['label'] ?? 'دکمه'),
                'url'   => $s['link']['url'] ?? '#',
                'style' => 'primary',
            ],
            'video' => [
                'url'     => $s['video_url'] ?? $s['url'] ?? '',
                'caption' => $this->text($s['title'] ?? ''),
            ],
            'icon' => [
                'name' => $s['icon'] ?? '',
                'size' => 'md',
            ],
            'counter' => [
                'value'  => (int) ($s['number'] ?? 0),
                'suffix' => $this->text($s['suffix'] ?? ''),
                'label'  => $this->text($s['title'] ?? ''),
            ],
            'hero' => [
                'title'       => $this->text($s['title'] ?? ''),
                'subtitle'    => $this->text($s['subtitle'] ?? $s['description'] ?? ''),
                'button_text' => $this->text($s['button_text'] ?? ''),
                'background'  => $s['background'] ?? 'primary',
            ],
            'container' => [
                'maxWidth' => '7xl',
                'blocks'   => [],
            ],
            'accordion' => [
                'items' => array_map(
                    fn($item) => ['title' => $this->text($item['title'] ?? ''), 'blocks' => []],
                    $s['items'] ?? [['title' => 'آیتم']]
                ),
            ],
            // TASK 2A: see the sibling mapProps() method's identical note —
            // `tabs` falls through to `default => []` here too.
            'testimonials' => ['title' => 'نظرات مشتریان', 'limit' => 5],
            'pricing_table' => ['title' => 'قیمت‌گذاری'],
            'blog_posts'    => ['title' => 'آخرین مطالب', 'limit' => 6],
            'product_grid'  => ['columns' => 4, 'limit' => 8],
            default         => [],
        };
    }

    // ── Shopify props ─────────────────────────────────────────────────────────

    private function shopifyProps(string $sectionType, string $blockType, array $s): array
    {
        return match ($blockType) {
            'hero' => [
                'title'       => $this->text($s['heading'] ?? $s['title'] ?? ''),
                'subtitle'    => $this->text($s['subheading'] ?? $s['description'] ?? ''),
                'button_text' => $this->text($s['button_label'] ?? $s['button_label_1'] ?? 'خرید کنید'),
                'button_url'  => $s['button_link'] ?? $s['button_link_1'] ?? '#',
                'background'  => $s['color_scheme'] ?? 'primary',
            ],
            'hero_split' => [
                'title'      => $this->text($s['heading'] ?? ''),
                'subtitle'   => $this->text($s['text'] ?? ''),
                'button_text'=> $this->text($s['button_label'] ?? 'بیشتر بدانید'),
                'button_url' => $s['button_link'] ?? '#',
                'image_url'  => $s['image'] ?? '',
            ],
            'text' => [
                'content' => $this->text($s['text'] ?? $s['heading'] ?? $s['title'] ?? ''),
            ],
            'product_grid' => [
                'columns' => (int) ($s['columns_desktop'] ?? 4),
                'limit'   => (int) ($s['products_to_show'] ?? 8),
            ],
            'blog_posts' => [
                'title' => $this->text($s['heading'] ?? 'آخرین مطالب'),
                'limit' => (int) ($s['post_limit'] ?? 6),
            ],
            'slider' => ['slides' => [], 'autoplay' => true, 'interval' => 5000],
            'accordion' => [
                'items' => [],
            ],
            'counter' => [
                'value'  => 0,
                'suffix' => '',
                'label'  => $this->text($s['heading'] ?? ''),
            ],
            'testimonials' => ['title' => 'نظرات مشتریان', 'limit' => 5],
            'gallery'      => ['title' => '', 'items' => []],
            default        => [],
        };
    }

    // ── Webflow props ─────────────────────────────────────────────────────────

    private function webflowProps(string $nodeType, string $blockType, array $s): array
    {
        return match ($blockType) {
            'heading' => [
                'text'  => $this->text($s['text'] ?? $s['text_content'] ?? ''),
                'level' => 2,
            ],
            'text' => [
                'content' => $this->text($s['text'] ?? $s['richText'] ?? $s['text_content'] ?? ''),
            ],
            'image' => [
                'url'   => $s['url'] ?? $s['src'] ?? $s['asset_url'] ?? '',
                'alt'   => $s['alt'] ?? '',
                'align' => 'center',
            ],
            'button' => [
                'text'  => $this->text($s['text'] ?? $s['text_content'] ?? $s['label'] ?? 'دکمه'),
                'url'   => $s['href'] ?? $s['url'] ?? '#',
                'style' => 'primary',
            ],
            'video' => [
                'url'     => $s['url'] ?? '',
                'caption' => $this->text($s['caption'] ?? ''),
            ],
            'container' => ['maxWidth' => '7xl', 'blocks' => []],
            'section'   => ['padding' => 'lg', 'background' => 'transparent', 'container' => true, 'blocks' => []],
            'grid'      => ['columns' => 3, 'gap' => 'md', 'blocks' => []],
            'slider'    => ['slides' => [], 'autoplay' => true, 'interval' => 5000],
            // TASK 2A: `tabs` falls through to `default => []` — see the
            // note in mapProps() above.
            default     => [],
        };
    }

    // ── Custom / ThemePack passthrough props ─────────────────────────────────

    private function customProps(string $blockType, array $s): array
    {
        // Custom format already uses our schema — just return settings as props
        unset($s['blocks'], $s['children'], $s['id'], $s['type']);
        return $s;
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function text(string $raw): string
    {
        return trim(strip_tags($raw));
    }

    private function fallbackType(string $nodeType): string
    {
        $lower = strtolower($nodeType);
        if (str_contains($lower, 'hero'))         return 'hero';
        if (str_contains($lower, 'heading'))      return 'heading';
        if (str_contains($lower, 'testimonial'))  return 'testimonials';
        if (str_contains($lower, 'blog') || str_contains($lower, 'post')) return 'blog_posts';
        if (str_contains($lower, 'team'))         return 'team';
        if (str_contains($lower, 'price'))        return 'pricing_table';
        if (str_contains($lower, 'counter'))      return 'counter';
        if (str_contains($lower, 'accordion'))    return 'accordion';
        if (str_contains($lower, 'tab'))          return 'tabs';
        if (str_contains($lower, 'map'))          return 'map';
        if (str_contains($lower, 'text') || str_contains($lower, 'paragraph')) return 'text';
        if (str_contains($lower, 'image') || str_contains($lower, 'photo'))    return 'image';
        if (str_contains($lower, 'button'))       return 'button';
        if (str_contains($lower, 'video'))        return 'video';
        if (str_contains($lower, 'section'))      return 'section';
        if (str_contains($lower, 'column') || str_contains($lower, 'container')) return 'container';
        if (str_contains($lower, 'gallery') || str_contains($lower, 'slider'))   return 'gallery';
        return 'text';
    }
}
