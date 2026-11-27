<?php

namespace SaaSPlatform\ThemeRuntime\Header;

use SaaSPlatform\ThemeRuntime\Support\IconLibrary;

/**
 * Normalizes and resolves all header properties into a unified, skin-neutral data model.
 *
 * Guarantees 100% backward compatibility with legacy camelCase keys and
 * supports the 3-way navigation source (menu module, pages module, custom links).
 */
final class HeaderDataResolver
{
    /**
     * Safely extract a string from a prop value that could be a scalar, an array (e.g. Canvas link or ID object), or null.
     */
    public static function safeString(mixed $val, string $default = ''): string
    {
        if (is_array($val)) {
            $val = $val['url'] ?? $val['href'] ?? $val['id'] ?? $val['label'] ?? $val['name'] ?? $default;
        }
        return is_scalar($val) ? (string) $val : $default;
    }

    /**
     * Resolve and normalize all header props.
     *
     * @param array<string, mixed> $props Raw block props
     * @param array<string, mixed> $context Render context (services, site metadata, auth, etc.)
     * @return array<string, mixed> Unified header data model
     */
    public static function resolve(array $props, array $context = []): array
    {
        // ─── 1. Brand & Logo ──────────────────────────────────────────────────
        $siteName = self::safeString($context['site_name'] ?? 'برند شما', 'برند شما');
        $logoText = trim(self::safeString($props['logo_text'] ?? $props['brand_name'] ?? $props['brandName'] ?? ''));
        if ($logoText === '') {
            $logoText = $siteName;
        }

        $logoUrl = self::safeString($props['logo_url'] ?? $props['home_url'] ?? $props['homeUrl'] ?? '/', '/');
        $desktopLogoImage = self::safeString($props['logo_image'] ?? '');
        // If logo_url was passed as an image (like in header_glass_nimbus), treat it as image if it looks like one
        if ($desktopLogoImage === '' && isset($props['logo_url']) && is_string($props['logo_url']) && (str_contains($props['logo_url'], '/') && !str_starts_with($props['logo_url'], 'http') && !str_starts_with($props['logo_url'], '/'))) {
            $desktopLogoImage = $props['logo_url'];
        }
        if ($desktopLogoImage === '' && !empty($props['logoUrl'])) {
            $desktopLogoImage = self::safeString($props['logoUrl']);
        }

        $mobileLogoImage = self::safeString($props['mobile_logo'] ?? '');
        $logoAlt = self::safeString($props['logo_alt'] ?? $logoText, $logoText);
        $logoHeight = (int) ($props['logo_height'] ?? 40);
        $mobileLogoHeight = (int) ($props['logo_height_mobile'] ?? 32);
        $logoBadgeSymbol = self::safeString($props['logo_badge_symbol'] ?? '◆', '◆');
        $logoIcon = trim(self::safeString($props['logo_icon'] ?? ''));
        $logoTextAccent = trim(self::safeString($props['logo_text_accent'] ?? ''));
        $logoTagline = trim(self::safeString($props['logo_tagline'] ?? ''));
        $logoAnimation = self::safeString($props['logo_animation'] ?? 'none', 'none');
        $logoAnimationDur = (int) ($props['logo_animation_duration'] ?? 5);

        // ─── 2. Top Bar ───────────────────────────────────────────────────────
        $topbarEnable = (bool) ($props['topbar_enable'] ?? false);
        $topbar = [
            'enabled' => $topbarEnable,
            'hide_mobile' => (bool) ($props['topbar_hide_mobile'] ?? false),
            'text' => self::safeString($props['topbar_text'] ?? ''),
            'phone' => self::safeString($props['topbar_phone'] ?? ''),
            'email' => self::safeString($props['topbar_email'] ?? ''),
            'address' => self::safeString($props['topbar_address'] ?? ''),
            'bg' => self::safeString($props['topbar_bg'] ?? ''),
            'color' => self::safeString($props['topbar_color'] ?? ''),
            'social_links_enabled' => (bool) ($props['topbar_social_links'] ?? true),
            'links' => is_array($props['topbar_links'] ?? null) ? $props['topbar_links'] : [],
        ];

        // ─── 3. Navigation & 3-way Menu Source ────────────────────────────────
        $menuSource = self::safeString($props['menu_source'] ?? '');
        if ($menuSource === '') {
            $navSource = self::safeString($props['nav_source'] ?? '');
            $menuSource = ($navSource === 'menu') ? 'menu_module' : 'custom_links';
        }

        $currentUrl = self::safeString($context['current_url'] ?? '');
        $menuItems = self::resolveMenuItems($menuSource, $props, $context, $currentUrl);

        // ─── 4. Actions & Quick Access ────────────────────────────────────────
        $ctaText = self::safeString($props['cta_text'] ?? $props['cta_label'] ?? $props['ctaText'] ?? '');
        $ctaUrl = self::safeString($props['cta_url'] ?? $props['ctaUrl'] ?? '/contact', '/contact');
        $ctaIcon = self::safeString($props['cta_icon'] ?? '');
        $ctaStyle = self::safeString($props['cta_style'] ?? 'primary', 'primary');
        $ctaColorStart = self::safeString($props['cta_color_start'] ?? $props['ctaColor'] ?? '#e20c35', '#e20c35');
        $ctaColorEnd = self::safeString($props['cta_color_end'] ?? '#c4008d', '#c4008d');

        $cta2Text = self::safeString($props['cta2_text'] ?? $props['secondary_label'] ?? $props['cta2Text'] ?? '');
        $cta2Url = self::safeString($props['cta2_url'] ?? $props['secondary_url'] ?? $props['cta2Url'] ?? '/login', '/login');
        $cta2Icon = self::safeString($props['cta2_icon'] ?? '');
        $cta2Style = self::safeString($props['cta2_style'] ?? 'outline', 'outline');

        $phone = self::safeString($props['phone'] ?? $props['topbar_phone'] ?? '');

        $showSearch = (bool) ($props['show_search'] ?? false);
        $searchMode = self::safeString($props['search_mode'] ?? 'icon', 'icon');
        $searchPlaceholder = self::safeString($props['search_placeholder'] ?? 'جستجو...', 'جستجو...');
        $searchScope = self::safeString($props['search_scope'] ?? 'all', 'all');

        $showCart = (bool) ($props['show_cart'] ?? false);
        $showAccount = (bool) ($props['show_account'] ?? false);
        $showLoginBtn = (bool) ($props['show_login_btn'] ?? false);
        $showRegisterBtn = (bool) ($props['show_register_btn'] ?? false);
        $showDarkToggle = (bool) ($props['show_dark_toggle'] ?? false);

        // ─── 5. Sticky, Backdrop & Mobile ─────────────────────────────────────
        $sticky = (bool) ($props['sticky'] ?? true);
        $stickyHideOnScroll = (bool) ($props['sticky_hide_on_scroll'] ?? false);
        $stickyOffset = (int) ($props['sticky_offset'] ?? 0);
        $stickyShrink = (bool) ($props['sticky_shrink'] ?? false);
        $backdropBlur = (int) ($props['backdrop_blur'] ?? 16);

        $burgerBreakpoint = self::safeString($props['burger_breakpoint'] ?? 'mobile', 'mobile');
        $drawerStyle = self::safeString($props['drawer_style'] ?? 'popover', 'popover');
        $drawerSide = self::safeString($props['drawer_side'] ?? 'start', 'start');

        // ─── 6. Colors & Ink ──────────────────────────────────────────────────
        $bgColor = self::safeString($props['bg_color'] ?? $props['bgColor'] ?? '#ffffff', '#ffffff');
        $textColor = self::safeString($props['text_color'] ?? $props['textColor'] ?? '#111827', '#111827');
        $inkColor = self::safeString($props['ink_color'] ?? '#211c48', '#211c48');

        return [
            'brand' => [
                'name' => $logoText,
                'url' => $logoUrl,
                'image' => $desktopLogoImage,
                'mobile_image' => $mobileLogoImage,
                'alt' => $logoAlt,
                'height' => $logoHeight,
                'mobile_height' => $mobileLogoHeight,
                'badge_symbol' => $logoBadgeSymbol,
                'icon' => $logoIcon,
                'accent' => $logoTextAccent,
                'tagline' => $logoTagline,
                'animation' => $logoAnimation,
                'animation_dur' => $logoAnimationDur,
                'icon_size' => (int) ($props['logo_icon_size'] ?? 34),
                'icon_color' => (string) ($props['logo_icon_color'] ?? ''),
                'accent_color' => (string) ($props['logo_accent_color'] ?? ''),
                'tagline_color' => (string) ($props['logo_tagline_color'] ?? ''),
            ],
            'topbar' => $topbar,
            'navigation' => [
                'source' => $menuSource,
                'align' => (string) ($props['menu_align'] ?? 'center'),
                'items' => $menuItems,
                'pointer' => (string) ($props['pointer'] ?? 'pill'),
                'pointer_animation' => (string) ($props['pointer_animation'] ?? 'grow'),
                'item_radius' => (int) ($props['menu_item_radius'] ?? 12),
                'menu_icon_size' => (int) ($props['menu_icon_size'] ?? 18),
                'menu_color' => (string) ($props['menu_color'] ?? ''),
                'menu_color_hover' => (string) ($props['menu_color_hover'] ?? ''),
                'menu_color_active' => (string) ($props['menu_color_active'] ?? ''),
                'menu_bg' => (string) ($props['menu_bg'] ?? ''),
                'menu_bg_hover' => (string) ($props['menu_bg_hover'] ?? ''),
                'menu_bg_active' => (string) ($props['menu_bg_active'] ?? ''),
                'menu_aria_label' => (string) ($props['menu_aria_label'] ?? 'منوی اصلی'),
            ],
            'actions' => [
                'phone' => $phone,
                'cta' => [
                    'text' => $ctaText,
                    'url' => $ctaUrl,
                    'icon' => $ctaIcon,
                    'style' => $ctaStyle,
                    'color_start' => $ctaColorStart,
                    'color_end' => $ctaColorEnd,
                    'glow_enabled' => (bool) ($props['cta_glow_enabled'] ?? false),
                    'glow_color' => (string) ($props['cta_glow_color'] ?? ''),
                    'radius' => (int) ($props['cta_radius'] ?? 12),
                ],
                'cta2' => [
                    'text' => $cta2Text,
                    'url' => $cta2Url,
                    'icon' => $cta2Icon,
                    'style' => $cta2Style,
                ],
                'show_login' => $showLoginBtn,
                'show_register' => $showRegisterBtn,
                'show_search' => $showSearch,
                'search_mode' => $searchMode,
                'search_placeholder' => $searchPlaceholder,
                'search_scope' => $searchScope,
                'show_cart' => $showCart,
                'show_account' => $showAccount,
                'show_dark_toggle' => $showDarkToggle,
                'social_links' => is_array($props['social_links'] ?? null) ? $props['social_links'] : [],
                'social_style' => self::safeString($props['social_style'] ?? 'mono', 'mono'),
                'social_show_mobile' => (bool) ($props['social_show_mobile'] ?? false),
                'icons_animation' => self::safeString($props['header_icons_animation'] ?? 'none', 'none'),
                'action_icon_bg' => self::safeString($props['action_icon_bg'] ?? ''),
                'action_icon_border' => self::safeString($props['action_icon_border'] ?? ''),
                'action_icon_color' => self::safeString($props['action_icon_color'] ?? ''),
                'action_icon_radius' => (int) ($props['action_icon_radius'] ?? 8),
            ],
            'sticky' => [
                'enabled' => $sticky,
                'hide_on_scroll' => $stickyHideOnScroll,
                'devices' => self::safeString($props['sticky_devices'] ?? 'all', 'all'),
                'offset' => $stickyOffset,
                'scroll_threshold' => (int) ($props['scroll_threshold'] ?? 8),
                'shrink' => $stickyShrink,
                'shrink_height' => (int) ($props['sticky_shrink_height'] ?? 56),
                'logo_scale' => (int) ($props['sticky_logo_scale'] ?? 80),
                'scrolled_bg' => self::safeString($props['scrolled_bg_color'] ?? ''),
                'scrolled_text' => self::safeString($props['scrolled_text_color'] ?? ''),
                'scrolled_shadow' => $props['scrolled_shadow'] ?? null,
                'z_index' => (int) ($props['z_index'] ?? 900),
                'transparent_over_hero' => (bool) ($props['transparent_over_hero'] ?? false),
                'transparent_text_color' => self::safeString($props['transparent_text_color'] ?? ''),
            ],
            'mobile' => [
                'breakpoint' => $burgerBreakpoint,
                'drawer_style' => $drawerStyle,
                'drawer_side' => $drawerSide,
                'show_logo' => (bool) ($props['drawer_show_logo'] ?? true),
                'show_search' => (bool) ($props['drawer_show_search'] ?? true),
                'show_ctas' => (bool) ($props['drawer_show_ctas'] ?? true),
                'action_text' => self::safeString($props['drawer_action_text'] ?? ''),
                'action_url' => self::safeString($props['drawer_action_url'] ?? ''),
                'action_icon' => self::safeString($props['drawer_action_icon'] ?? ''),
                'action_style' => self::safeString($props['drawer_action_style'] ?? 'primary', 'primary'),
                'duration' => (float) ($props['drawer_duration'] ?? 0.38),
                'easing' => self::safeString($props['drawer_easing'] ?? 'cubic-bezier(.16,1,.3,1)', 'cubic-bezier(.16,1,.3,1)'),
                'bg' => self::safeString($props['drawer_bg'] ?? ''),
                'text' => self::safeString($props['drawer_text'] ?? ''),
                'divider_color' => self::safeString($props['drawer_divider_color'] ?? ''),
                'width' => self::safeString($props['drawer_width'] ?? ''),
                'overlay_color' => self::safeString($props['drawer_overlay_color'] ?? ''),
                'burger_color' => self::safeString($props['burger_color'] ?? ''),
                'burger_size' => self::safeString($props['burger_size'] ?? ''),
            ],
            'style' => [
                'bg_color' => $bgColor,
                'text_color' => $textColor,
                'ink_color' => $inkColor,
                'min_height' => (int) ($props['min_height'] ?? 64),
                'padding' => $props['padding'] ?? null,
                'items_gap' => $props['items_gap'] ?? '',
                'content_width' => self::safeString($props['content_width'] ?? 'boxed', 'boxed'),
                'content_max_width' => (int) ($props['content_max_width'] ?? 1280),
                'border_bottom_width' => (int) ($props['border_bottom_width'] ?? 0),
                'border_bottom_color' => self::safeString($props['border_bottom_color'] ?? ''),
                'header_shadow' => $props['header_shadow'] ?? null,
                'backdrop_blur' => $backdropBlur,
            ],
            'order' => is_array($props['header_order'] ?? null) ? $props['header_order'] : ['logo', 'menu', 'search', 'social', 'phone', 'cta', 'cart', 'account', 'dark_toggle', 'burger'],
            'asset_version' => self::safeString($props['asset_version'] ?? '1.2.0', '1.2.0'),
            'raw_props' => $props,
        ];
    }

    /**
     * Resolves the navigation menu items from the selected source.
     *
     * @param string $source
     * @param array<string, mixed> $props
     * @param array<string, mixed> $context
     * @return list<array<string, mixed>>
     */
    private static function resolveMenuItems(string $source, array $props, array $context, string $currentUrl = ''): array
    {
        // ── Mode 1: Pre-defined menu in Menu Module ──────────────────────────
        if ($source === 'menu_module') {
            $selectedMenuId = $props['selected_menu_id'] ?? 'primary';

            // Check if pre-resolved in context
            if (isset($context['resolved_menus'][$selectedMenuId]) && is_array($context['resolved_menus'][$selectedMenuId])) {
                return self::normalizeTreeItems($context['resolved_menus'][$selectedMenuId], $currentUrl);
            }

            // Resolve via MenuService if available in host runtime
            if (class_exists(\App\Models\Menu::class) && class_exists(\App\Services\MenuService::class)) {
                try {
                    $menu = is_numeric($selectedMenuId)
                        ? \App\Models\Menu::find((int) $selectedMenuId)
                        : \App\Models\Menu::forPublishedLocation((string) $selectedMenuId);

                    if ($menu) {
                        $tree = app(\App\Services\MenuService::class)->buildMenuTree($menu);
                        return self::normalizeTreeItems($tree, $currentUrl);
                    }
                } catch (\Throwable) {
                    // Fall back to custom links
                }
            }
        }

        // ── Mode 2: Published pages in Pages Module ──────────────────────────
        if ($source === 'pages_module') {
            $selectedPageIds = $props['selected_page_ids'] ?? [];
            if (is_string($selectedPageIds)) {
                $decoded = json_decode($selectedPageIds, true);
                $selectedPageIds = is_array($decoded) ? $decoded : [];
            }

            if (!empty($selectedPageIds)) {
                // If pages passed in context
                if (isset($context['published_pages']) && is_array($context['published_pages'])) {
                    $pagesById = [];
                    foreach ($context['published_pages'] as $p) {
                        if (isset($p['id'])) {
                            $pagesById[$p['id']] = $p;
                        }
                    }
                    $items = [];
                    foreach ($selectedPageIds as $pid) {
                        if (isset($pagesById[$pid])) {
                            $page = $pagesById[$pid];
                            $slug = $page['slug'] ?? '';
                            $url = function_exists('route') ? route('page.show', $slug) : '/' . ltrim($slug, '/');
                            $items[] = [
                                'label' => $page['title'] ?? '',
                                'url' => $url,
                                'icon' => $page['icon'] ?? '',
                                'target' => '_self',
                                'badge' => null,
                                'children' => [],
                                'mega_columns' => [],
                                'is_active' => self::isUrlActive($url, $currentUrl),
                            ];
                        }
                    }
                    if (!empty($items)) {
                        return $items;
                    }
                }

                // Query DB directly if Page model is available
                if (class_exists(\App\Models\Page::class)) {
                    try {
                        $rawIds = (array) $selectedPageIds;
                        $ids = [];
                        $slugs = [];
                        foreach ($rawIds as $v) {
                            if (is_numeric($v)) {
                                $ids[] = (int) $v;
                            } elseif (is_string($v) && trim($v) !== '') {
                                $slugs[] = trim($v);
                            }
                        }
                        $query = \App\Models\Page::query();
                        if (method_exists(\App\Models\Page::class, 'scopePublished')) {
                            $query->published();
                        } else {
                            $query->where('status', 'published');
                        }
                        $query->where(function ($q) use ($ids, $slugs) {
                            if (!empty($ids)) {
                                $q->whereIn('id', $ids);
                            }
                            if (!empty($slugs)) {
                                $q->orWhereIn('slug', $slugs);
                            }
                        });
                        $pages = $query->get();
                        $byKey = [];
                        foreach ($pages as $p) {
                            $byKey[$p->id] = $p;
                            $byKey[$p->slug] = $p;
                        }

                        $items = [];
                        foreach ($rawIds as $v) {
                            if (isset($byKey[$v])) {
                                $page = $byKey[$v];
                                $url = function_exists('route') && \Illuminate\Support\Facades\Route::has('page.show')
                                    ? route('page.show', $page->slug)
                                    : '/' . ltrim($page->slug, '/');
                                $items[] = [
                                    'label' => $page->title,
                                    'url' => $url,
                                    'icon' => '',
                                    'target' => '_self',
                                    'badge' => null,
                                    'children' => [],
                                    'mega_columns' => [],
                                    'is_active' => self::isUrlActive($url, $currentUrl),
                                ];
                            }
                        }
                        if (!empty($items)) {
                            return $items;
                        }
                    } catch (\Throwable) {
                        // Fall back to custom links
                    }
                }
            }
        }

        // ── Mode 3: Custom links (Default & Fallback) ─────────────────────────
        $rawLinks = $props['nav_links']
            ?? $props['menu_items']
            ?? $props['menuItems']
            ?? $props['links']
            ?? [];

        if (is_string($rawLinks)) {
            $decoded = json_decode($rawLinks, true);
            $rawLinks = is_array($decoded) ? $decoded : [];
        }

        if (!is_array($rawLinks) || empty($rawLinks)) {
            $rawLinks = [
                ['label' => 'صفحه اصلی', 'url' => '/', 'icon' => 'home'],
                ['label' => 'محصولات', 'url' => '/shop', 'icon' => 'shopping-bag'],
                ['label' => 'تعرفه‌ها', 'url' => '/pricing', 'icon' => 'tag'],
                ['label' => 'وبلاگ', 'url' => '/blog', 'icon' => 'book-open'],
                ['label' => 'تماس با ما', 'url' => '/contact', 'icon' => 'phone'],
            ];
        }

        $items = [];
        foreach ($rawLinks as $link) {
            if (!is_array($link)) {
                continue;
            }
            $label = trim(self::safeString($link['label'] ?? $link['title'] ?? ''));
            if ($label === '') {
                continue;
            }
            $url = self::safeString($link['url'] ?? '#', '#');
            $icon = trim(self::safeString($link['icon'] ?? ''));
            $target = self::safeString($link['target'] ?? '_self', '_self');

            // Preserve all original link properties (e.g. mega menu structures, promo cards, badges)
            $items[] = array_merge($link, [
                'label' => $label,
                'url' => $url,
                'icon' => $icon,
                'target' => $target,
                'children' => is_array($link['children'] ?? null) ? self::normalizeTreeItems($link['children'], $currentUrl) : ($link['children'] ?? []),
                'is_active' => self::isUrlActive($url, $currentUrl),
            ]);
        }

        return $items;
    }

    /**
     * Normalize nested tree items from MenuService.
     *
     * @param list<array<string, mixed>> $tree
     * @param string $currentUrl
     * @return list<array<string, mixed>>
     */
    private static function normalizeTreeItems(array $tree, string $currentUrl = ''): array
    {
        $items = [];
        foreach ($tree as $row) {
            if (!is_array($row)) {
                continue;
            }
            $label = trim((string) ($row['title'] ?? $row['label'] ?? ''));
            if ($label === '') {
                continue;
            }
            $url = (string) ($row['url'] ?? '#');
            $children = is_array($row['children'] ?? null) ? self::normalizeTreeItems($row['children'], $currentUrl) : [];

            $items[] = [
                'label' => $label,
                'url' => $url,
                'icon' => trim((string) ($row['icon'] ?? '')),
                'target' => (string) ($row['target'] ?? '_self'),
                'badge' => $row['badge'] ?? null,
                'children' => $children,
                'mega_columns' => is_array($row['mega_columns'] ?? null) ? $row['mega_columns'] : [],
                'is_active' => !empty($row['active_trail']) || self::isUrlActive($url, $currentUrl),
            ];
        }

        return $items;
    }

    /**
     * Detects if the current request URL matches the item URL.
     */
    private static function isUrlActive(string $url, ?string $currentUrl = null): bool
    {
        if ($url === '' || $url === '#') {
            return false;
        }

        if ($currentUrl !== null && $currentUrl !== '') {
            $currentPath = rtrim(parse_url($currentUrl, PHP_URL_PATH) ?? '/', '/') ?: '/';
            $itemPath = rtrim(parse_url($url, PHP_URL_PATH) ?? '', '/') ?: '/';
            if ($itemPath === $currentPath) {
                return true;
            }
        }

        if (function_exists('url') && function_exists('request')) {
            try {
                $current = url()->current();
                if ($current === $url || $current === url($url)) {
                    return true;
                }
                $path = ltrim(parse_url($url, PHP_URL_PATH) ?? '', '/');
                if ($path !== '' && request()->is($path)) {
                    return true;
                }
            } catch (\Throwable) {
                return false;
            }
        }

        return false;
    }
}
