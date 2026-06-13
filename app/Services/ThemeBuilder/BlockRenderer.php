<?php
namespace App\Services\ThemeBuilder;

use Illuminate\Support\Str;

/**
 * Renders block tree to HTML. Sanitized, SEO-friendly.
 * Used for preview in admin and for front-end when customer-site consumes theme payload.
 */
class BlockRenderer
{
    protected array $context = [];

    public function setContext(array $context): self
    {
        $this->context = $context;
        return $this;
    }

    /**
     * Render a list of blocks (e.g. header_blocks or footer_blocks).
     */
    public function renderBlocks(?array $blocks): string
    {
        if (empty($blocks) || !is_array($blocks)) {
            return '';
        }
        $html = [];
        foreach ($blocks as $block) {
            $html[] = $this->renderBlock($block);
        }
        return implode("\n", $html);
    }

    public function renderBlock(array $block): string
    {
        $type = $block['type'] ?? 'text';
        $id = $block['id'] ?? Str::random(8);
        $props = $block['props'] ?? $block['data'] ?? [];
        $children = $block['blocks'] ?? $block['children'] ?? [];

        $inner = '';
        if (in_array($type, ['section', 'container', 'grid', 'columns'], true) && !empty($children)) {
            $inner = $this->renderBlocks($children);
        }
        if ($type === 'tabs' && !empty($props['tabs'])) {
            $inner = $this->renderTabs($props['tabs']);
        }
        if ($type === 'accordion' && !empty($props['items'])) {
            $inner = $this->renderAccordion($props['items']);
        }
        if ($type === 'slider' && !empty($props['slides'])) {
            $inner = $this->renderBlocks($props['slides']);
        }

        // Module blocks: render as styled placeholder (admin preview) or delegate (customer-site)
        if (str_starts_with($type, 'module:')) {
            $renderer = $this->context['module_renderer'] ?? null;
            if (is_callable($renderer)) {
                return $renderer($type, $props, $id);
            }
            return $this->renderModulePlaceholder($type, $props, $id);
        }

        $method = 'render' . Str::studly(str_replace(':', '_', $type));
        if (method_exists($this, $method)) {
            return $this->{$method}($props, $inner, $id);
        }

        return $this->renderFallback($type, $props, $inner, $id);
    }

    protected function renderSection(array $props, string $inner, string $id): string
    {
        $padding = $this->spacingClass($props['padding'] ?? 'md');
        $bg = $this->escapeAttr($props['background'] ?? 'transparent');
        $bgImg = $this->escapeAttr($props['background_image'] ?? '');
        $useContainer = ($props['container'] ?? true);
        $style = $bgImg ? " background-image: url({$bgImg}); background-size: cover;" : '';
        $class = "tb-section tb-padding-{$padding}";
        if ($bg !== 'transparent' && !str_starts_with($bg, '#')) {
            $class .= " tb-bg-{$bg}";
        }
        $styleAttr = ($bg !== 'transparent' && str_starts_with($bg, '#')) ? " style=\"background-color:{$bg}{$style}\"" : ($style ? " style=\"{$style}\"" : '');
        $open = $useContainer ? '<div class="tb-container">' : '';
        $close = $useContainer ? '</div>' : '';
        return "<section id=\"tb-{$id}\" class=\"{$class}\"{$styleAttr}><div class=\"tb-section-inner\">{$open}{$inner}{$close}</div></section>";
    }

    protected function renderContainer(array $props, string $inner, string $id): string
    {
        $max = $this->escapeAttr($props['maxWidth'] ?? '7xl');
        return "<div id=\"tb-{$id}\" class=\"tb-container tb-max-{$max}\">{$inner}</div>";
    }

    protected function renderGrid(array $props, string $inner, string $id): string
    {
        $cols = (int) ($props['columns'] ?? 3);
        $gap = $this->spacingClass($props['gap'] ?? 'md');
        return "<div id=\"tb-{$id}\" class=\"tb-grid tb-grid-cols-{$cols} tb-gap-{$gap}\">{$inner}</div>";
    }

    protected function renderColumns(array $props, string $inner, string $id): string
    {
        $ratios = $props['ratios'] ?? '1/2,1/2';
        $parts = array_map('trim', explode(',', $ratios));
        $classes = [];
        foreach ($parts as $i => $r) {
            $classes[] = "tb-col-" . ($i + 1);
        }
        $colClass = implode(' ', $classes);
        return "<div id=\"tb-{$id}\" class=\"tb-columns {$colClass}\">{$inner}</div>";
    }

    protected function renderHero(array $props, string $inner, string $id): string
    {
        $title = $this->escape($props['title'] ?? '');
        $subtitle = $this->escape($props['subtitle'] ?? '');
        $btnText = $this->escape($props['button_text'] ?? '');
        $btnUrl = $this->escapeAttr($props['button_url'] ?? '#');
        $bg = $props['background'] ?? 'primary';
        $align = $props['align'] ?? 'center';
        $btn = $btnText ? "<a href=\"{$btnUrl}\" class=\"tb-btn tb-btn-primary\">{$btnText}</a>" : '';
        return "<section id=\"tb-{$id}\" class=\"tb-hero tb-bg-{$bg} tb-align-{$align}\"><div class=\"tb-hero-inner\"><h1 class=\"tb-hero-title\">{$title}</h1><p class=\"tb-hero-subtitle\">{$subtitle}</p>{$btn}</div></section>";
    }

    protected function renderHeading(array $props, string $inner, string $id): string
    {
        $level = max(1, min(6, (int) ($props['level'] ?? 2)));
        $text = $this->escape($props['text'] ?? '');
        return "<h{$level} id=\"tb-{$id}\" class=\"tb-heading\">{$text}</h{$level}>";
    }

    protected function renderText(array $props, string $inner, string $id): string
    {
        $content = $props['content'] ?? '';
        $content = $this->sanitizeHtml($content);
        return "<div id=\"tb-{$id}\" class=\"tb-text prose\">{$content}</div>";
    }

    protected function renderImage(array $props, string $inner, string $id): string
    {
        $url = $this->escapeAttr($props['url'] ?? '');
        $alt = $this->escapeAttr($props['alt'] ?? '');
        $caption = $this->escape($props['caption'] ?? '');
        if ($url === '') {
            return "<figure id=\"tb-{$id}\" class=\"tb-image tb-image-placeholder\"><span>تصویر</span></figure>";
        }
        $img = "<img src=\"{$url}\" alt=\"{$alt}\" loading=\"lazy\" class=\"tb-img\">";
        $cap = $caption ? "<figcaption class=\"tb-image-caption\">{$caption}</figcaption>" : '';
        return "<figure id=\"tb-{$id}\" class=\"tb-image\">{$img}{$cap}</figure>";
    }

    protected function renderButton(array $props, string $inner, string $id): string
    {
        $text = $this->escape($props['text'] ?? '');
        $url = $this->escapeAttr($props['url'] ?? '#');
        $style = $props['style'] ?? 'primary';
        return "<a id=\"tb-{$id}\" href=\"{$url}\" class=\"tb-btn tb-btn-{$style}\">{$text}</a>";
    }

    protected function renderDivider(array $props, string $inner, string $id): string
    {
        return "<hr id=\"tb-{$id}\" class=\"tb-divider\">";
    }

    protected function renderNav_menu(array $props, string $inner, string $id): string
    {
        $location = $props['location'] ?? 'header';
        $menuHtml = $this->context['menu_html'] ?? '';
        return "<nav id=\"tb-{$id}\" class=\"tb-nav tb-nav-{$location}\" aria-label=\"منوی اصلی\">{$menuHtml}</nav>";
    }

    protected function renderSocial_icons(array $props, string $inner, string $id): string
    {
        $links = $props['links'] ?? [];
        $items = [];
        foreach ($links as $link) {
            $url = $this->escapeAttr($link['url'] ?? '#');
            $label = $this->escape($link['label'] ?? '');
            $icon = $link['icon'] ?? 'link';
            $items[] = "<a href=\"{$url}\" class=\"tb-social-link\" aria-label=\"{$label}\">{$label}</a>";
        }
        return "<div id=\"tb-{$id}\" class=\"tb-social-icons\">" . implode('', $items) . "</div>";
    }

    protected function renderCta(array $props, string $inner, string $id): string
    {
        $title = $this->escape($props['title'] ?? '');
        $subtitle = $this->escape($props['subtitle'] ?? '');
        $btnText = $this->escape($props['button_text'] ?? '');
        $btnUrl = $this->escapeAttr($props['button_url'] ?? '#');
        $bg = $props['background'] ?? 'primary';
        $btn = $btnText ? "<a href=\"{$btnUrl}\" class=\"tb-btn tb-btn-primary\">{$btnText}</a>" : '';
        return "<section id=\"tb-{$id}\" class=\"tb-cta tb-bg-{$bg}\"><h2 class=\"tb-cta-title\">{$title}</h2><p class=\"tb-cta-subtitle\">{$subtitle}</p>{$btn}</section>";
    }

    protected function renderTabs(array $tabs): string
    {
        $out = [];
        $idx = 0;
        foreach ($tabs as $i => $tab) {
            $title = $this->escape($tab['title'] ?? "تب " . (++$idx));
            $blocks = $tab['blocks'] ?? [];
            $content = $this->renderBlocks($blocks);
            $out[] = "<div class=\"tb-tab-panel\" data-tab=\"{$i}\"><h3 class=\"tb-tab-title\">{$title}</h3><div class=\"tb-tab-content\">{$content}</div></div>";
        }
        return "<div class=\"tb-tabs\">" . implode('', $out) . "</div>";
    }

    protected function renderAccordion(array $items): string
    {
        $out = [];
        foreach ($items as $i => $item) {
            $title = $this->escape($item['title'] ?? '');
            $content = $this->renderBlocks($item['blocks'] ?? []);
            $out[] = "<details class=\"tb-accordion-item\"><summary>{$title}</summary><div class=\"tb-accordion-body\">{$content}</div></details>";
        }
        return "<div class=\"tb-accordion\">" . implode('', $out) . "</div>";
    }

    protected function renderModulePlaceholder(string $type, array $props, string $id): string
    {
        $moduleLabels = \App\Services\ThemeBuilder\BlockRegistry::all();
        $def = $moduleLabels[$type] ?? null;
        $name = $def['name'] ?? $type;
        $requires = $def['requires'] ?? null;

        $moduleColors = [
            'blog'            => ['bg' => '#dbeafe', 'border' => '#3b82f6', 'text' => '#1d4ed8', 'badge' => '#eff6ff'],
            'shop'            => ['bg' => '#d1fae5', 'border' => '#10b981', 'text' => '#065f46', 'badge' => '#ecfdf5'],
            'courses'         => ['bg' => '#ede9fe', 'border' => '#7c3aed', 'text' => '#4c1d95', 'badge' => '#f5f3ff'],
            'forms'           => ['bg' => '#fef3c7', 'border' => '#f59e0b', 'text' => '#92400e', 'badge' => '#fffbeb'],
            'subscription'    => ['bg' => '#fce7f3', 'border' => '#ec4899', 'text' => '#9d174d', 'badge' => '#fdf2f8'],
            'reviews'         => ['bg' => '#fff7ed', 'border' => '#f97316', 'text' => '#7c2d12', 'badge' => '#fff7ed'],
            'whatsapp'        => ['bg' => '#dcfce7', 'border' => '#22c55e', 'text' => '#14532d', 'badge' => '#f0fdf4'],
            'referral'        => ['bg' => '#fef9c3', 'border' => '#eab308', 'text' => '#713f12', 'badge' => '#fefce8'],
            'social_links'    => ['bg' => '#e0f2fe', 'border' => '#0ea5e9', 'text' => '#0c4a6e', 'badge' => '#f0f9ff'],
            'advanced_search' => ['bg' => '#f1f5f9', 'border' => '#64748b', 'text' => '#1e293b', 'badge' => '#f8fafc'],
        ];

        $c = $moduleColors[$requires] ?? ['bg' => '#f3f4f6', 'border' => '#6366f1', 'text' => '#312e81', 'badge' => '#eef2ff'];

        $title = $this->escape($props['title'] ?? $name);
        $reqLabel = $requires ? '<span style="font-size:10px;background:' . $c['badge'] . ';color:' . $c['text'] . ';border:1px solid ' . $c['border'] . ';border-radius:4px;padding:1px 6px;margin-right:6px;">' . $this->escape($requires) . '</span>' : '';

        $extraInfo = '';
        if (!empty($props['limit'])) $extraInfo .= '<span style="font-size:11px;color:#6b7280;margin-left:8px;">تعداد: ' . (int)$props['limit'] . '</span>';
        if (!empty($props['layout'])) $extraInfo .= '<span style="font-size:11px;color:#6b7280;margin-left:8px;">چیدمان: ' . $this->escape($props['layout']) . '</span>';
        if (!empty($props['style']))  $extraInfo .= '<span style="font-size:11px;color:#6b7280;margin-left:8px;">استایل: ' . $this->escape($props['style']) . '</span>';

        return '<div id="tb-' . $id . '" style="border:2px dashed ' . $c['border'] . ';background:' . $c['bg'] . ';border-radius:10px;padding:20px 16px;text-align:center;direction:rtl;" data-module-block="' . $this->escape($type) . '">'
            . '<div style="margin-bottom:8px;">' . $reqLabel . '<strong style="color:' . $c['text'] . ';font-size:14px;">' . $title . '</strong></div>'
            . '<div style="font-size:12px;color:#9ca3af;">' . $this->escape($name) . ($extraInfo ? ' &nbsp;·&nbsp; ' . $extraInfo : '') . '</div>'
            . '</div>';
    }

    protected function renderFallback(string $type, array $props, string $inner, string $id): string
    {
        $content = $inner ?: $this->escape(json_encode($props, JSON_UNESCAPED_UNICODE));
        return "<div id=\"tb-{$id}\" class=\"tb-block tb-block-{$type}\" data-type=\"{$type}\">{$content}</div>";
    }

    protected function spacingClass(string $v): string
    {
        $map = ['none' => 'none', 'sm' => 'sm', 'md' => 'md', 'lg' => 'lg', 'xl' => 'xl'];
        return $map[$v] ?? 'md';
    }

    protected function escape(string $s): string
    {
        return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
    }

    protected function escapeAttr(string $s): string
    {
        return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
    }

    protected function sanitizeHtml(string $s): string
    {
        return strip_tags($s, '<p><br><a><strong><em><ul><ol><li><h2><h3>');
    }
}
