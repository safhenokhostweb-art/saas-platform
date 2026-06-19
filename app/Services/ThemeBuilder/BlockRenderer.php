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
                $html = $renderer($type, $props, $id);
            } else {
                $html = $this->renderModulePlaceholder($type, $props, $id);
            }
            return $this->applyBlockStyle($this->wrapEditorMode($html, $id, $type), $block['style'] ?? []);
        }

        $method = 'render' . Str::studly(str_replace(':', '_', $type));
        if (method_exists($this, $method)) {
            $html = $this->{$method}($props, $inner, $id);
        } else {
            $html = $this->renderFallback($type, $props, $inner, $id);
        }

        return $this->applyBlockStyle($this->wrapEditorMode($html, $id, $type), $block['style'] ?? []);
    }

    private function applyBlockStyle(string $html, array $style): string
    {
        if (empty($style)) {
            return $html;
        }
        $css     = [];
        $classes = ['tb-style-wrap'];

        // Colors
        if (!empty($style['bgColor']))   $css[] = 'background-color:' . $this->escapeAttr($style['bgColor']);
        if (!empty($style['textColor'])) $css[] = 'color:' . $this->escapeAttr($style['textColor']);

        // Gradient (overrides bgColor)
        if (!empty($style['bgGradientFrom']) && !empty($style['bgGradientTo'])) {
            $dir  = $this->escapeAttr($style['bgGradientDir'] ?? 'to bottom');
            $from = $this->escapeAttr($style['bgGradientFrom']);
            $to   = $this->escapeAttr($style['bgGradientTo']);
            $css[] = "background-image:linear-gradient({$dir},{$from},{$to})";
        }

        // Typography
        if (($style['fontSize']      ?? '') !== '') $css[] = 'font-size:'      . (int)$style['fontSize']      . 'px';
        if (!empty($style['fontWeight']))            $css[] = 'font-weight:'    . $this->escapeAttr($style['fontWeight']);
        if (($style['lineHeight']    ?? '') !== '') $css[] = 'line-height:'    . $this->escapeAttr($style['lineHeight']);
        if (!empty($style['textAlign']))             $css[] = 'text-align:'     . $this->escapeAttr($style['textAlign']);
        if (($style['letterSpacing'] ?? '') !== '') $css[] = 'letter-spacing:' . $this->escapeAttr($style['letterSpacing']) . 'px';

        // Individual padding (takes precedence over paddingY/paddingX)
        if (isset($style['paddingTop']) || isset($style['paddingRight']) || isset($style['paddingBottom']) || isset($style['paddingLeft'])) {
            if (($style['paddingTop']    ?? '') !== '') $css[] = 'padding-top:'    . (int)$style['paddingTop']    . 'px';
            if (($style['paddingRight']  ?? '') !== '') $css[] = 'padding-right:'  . (int)$style['paddingRight']  . 'px';
            if (($style['paddingBottom'] ?? '') !== '') $css[] = 'padding-bottom:' . (int)$style['paddingBottom'] . 'px';
            if (($style['paddingLeft']   ?? '') !== '') $css[] = 'padding-left:'   . (int)$style['paddingLeft']   . 'px';
        } else {
            if (($style['paddingY'] ?? '') !== '') $css[] = "padding-top:{$style['paddingY']}px;padding-bottom:{$style['paddingY']}px";
            if (($style['paddingX'] ?? '') !== '') $css[] = "padding-left:{$style['paddingX']}px;padding-right:{$style['paddingX']}px";
        }

        // Margin
        if (($style['marginTop']    ?? '') !== '') $css[] = 'margin-top:'    . (int)$style['marginTop']    . 'px';
        if (($style['marginBottom'] ?? '') !== '') $css[] = 'margin-bottom:' . (int)$style['marginBottom'] . 'px';

        // Border
        if (($style['borderWidth'] ?? '') !== '' && (int)$style['borderWidth'] > 0) {
            $css[] = 'border-width:' . (int)$style['borderWidth'] . 'px';
            $css[] = 'border-style:' . $this->escapeAttr($style['borderStyle'] ?? 'solid');
        }
        if (!empty($style['borderColor'])) $css[] = 'border-color:' . $this->escapeAttr($style['borderColor']);
        if (($style['borderRadius']  ?? '') !== '') $css[] = 'border-radius:' . (int)$style['borderRadius'] . 'px';

        // Shadow presets
        $shadowMap = [
            'sm'   => '0 1px 2px 0 rgba(0,0,0,0.05)',
            'md'   => '0 4px 6px -1px rgba(0,0,0,0.1),0 2px 4px -2px rgba(0,0,0,0.1)',
            'lg'   => '0 10px 15px -3px rgba(0,0,0,0.1),0 4px 6px -4px rgba(0,0,0,0.1)',
            'xl'   => '0 20px 25px -5px rgba(0,0,0,0.1),0 8px 10px -6px rgba(0,0,0,0.1)',
            'none' => 'none',
        ];
        if (!empty($style['shadow'])) {
            $shadow = $shadowMap[$style['shadow']] ?? $style['shadow'];
            $css[] = 'box-shadow:' . $this->escapeAttr($shadow);
        }

        // Opacity
        if (isset($style['opacity']) && $style['opacity'] != 1 && $style['opacity'] !== '') {
            $css[] = 'opacity:' . $style['opacity'];
        }

        // Animation duration CSS var
        if (!empty($style['animationDuration'])) {
            $css[] = '--tb-animate-dur:' . (int)$style['animationDuration'] . 'ms';
        }

        // Custom CSS class (sanitized)
        if (!empty($style['customClass'])) {
            foreach (explode(' ', $style['customClass']) as $cls) {
                $safe = preg_replace('/[^a-zA-Z0-9_\-]/', '', trim($cls));
                if ($safe !== '') $classes[] = $safe;
            }
        }

        // Visibility classes
        if (!empty($style['hideDesktop'])) $classes[] = 'tb-hide-desktop';
        if (!empty($style['hideTablet']))  $classes[] = 'tb-hide-tablet';
        if (!empty($style['hideMobile']))  $classes[] = 'tb-hide-mobile';

        // Animation class
        if (!empty($style['animation']) && $style['animation'] !== 'none') {
            $anim = preg_replace('/[^a-z\-]/', '', $style['animation']);
            $classes[] = 'tb-animate-' . $anim;
        }

        if (empty($css) && count($classes) <= 1) return $html;

        $classAttr = implode(' ', $classes);
        $styleStr  = implode(';', $css);
        $styleAttr = $styleStr ? " style=\"{$styleStr}\"" : '';
        return "<div class=\"{$classAttr}\"{$styleAttr}>{$html}</div>";
    }

    private function editableAttr(string $propKey, string $inlineType = 'text'): string
    {
        if (!($this->context['editor_mode'] ?? false)) return '';
        return " data-tb-editable=\"{$propKey}\" data-tb-inline-type=\"{$inlineType}\"";
    }

    /**
     * In editor_mode, wraps block HTML with canvas selection overlay.
     * No-op in production rendering (editor_mode not set).
     */
    protected function wrapEditorMode(string $html, string $id, string $type): string
    {
        if (!($this->context['editor_mode'] ?? false)) {
            return $html;
        }
        $esc = htmlspecialchars($type, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $idEsc = htmlspecialchars($id, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        return "<div class=\"tb-canvas-block\" data-tb-block-id=\"{$idEsc}\" data-tb-block-type=\"{$esc}\">"
            . "<div class=\"tb-block-badge\">{$esc}</div>"
            . $html
            . "</div>";
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
        $title    = $this->escape($props['title'] ?? '');
        $subtitle = $this->escape($props['subtitle'] ?? '');
        $btnText  = $this->escape($props['button_text'] ?? '');
        $btnUrl   = $this->escapeAttr($props['button_url'] ?? '#');
        $bg    = $props['background'] ?? 'primary';
        $align = $props['align'] ?? 'center';
        $eaTitle = $this->editableAttr('title', 'text');
        $eaSub   = $this->editableAttr('subtitle', 'text');
        $eaBtn   = $this->editableAttr('button_text', 'text');
        $btn = $btnText ? "<a href=\"{$btnUrl}\" class=\"tb-btn tb-btn-primary\"{$eaBtn}>{$btnText}</a>" : '';
        return "<section id=\"tb-{$id}\" class=\"tb-hero tb-bg-{$bg} tb-align-{$align}\"><div class=\"tb-hero-inner\"><h1 class=\"tb-hero-title\"{$eaTitle}>{$title}</h1><p class=\"tb-hero-subtitle\"{$eaSub}>{$subtitle}</p>{$btn}</div></section>";
    }

    protected function renderHeading(array $props, string $inner, string $id): string
    {
        $level = max(1, min(6, (int) ($props['level'] ?? 2)));
        $text  = $this->escape($props['text'] ?? '');
        $ea    = $this->editableAttr('text', 'text');
        return "<h{$level} id=\"tb-{$id}\" class=\"tb-heading\"{$ea}>{$text}</h{$level}>";
    }

    protected function renderText(array $props, string $inner, string $id): string
    {
        $content = $this->sanitizeHtml($props['content'] ?? '');
        $ea = $this->editableAttr('content', 'html');
        return "<div id=\"tb-{$id}\" class=\"tb-text prose\"{$ea}>{$content}</div>";
    }

    protected function renderImage(array $props, string $inner, string $id): string
    {
        $url     = $this->escapeAttr($props['url'] ?? '');
        $alt     = $this->escapeAttr($props['alt'] ?? '');
        $caption = $this->escape($props['caption'] ?? '');
        if ($url === '') {
            return "<figure id=\"tb-{$id}\" class=\"tb-image tb-image-placeholder\"><span>تصویر انتخاب نشده</span></figure>";
        }
        $img   = "<img src=\"{$url}\" alt=\"{$alt}\" loading=\"lazy\" class=\"tb-img\">";
        $eaCap = $this->editableAttr('caption', 'text');
        $cap   = $caption ? "<figcaption class=\"tb-image-caption\"{$eaCap}>{$caption}</figcaption>" : '';
        return "<figure id=\"tb-{$id}\" class=\"tb-image\">{$img}{$cap}</figure>";
    }

    protected function renderButton(array $props, string $inner, string $id): string
    {
        $text  = $this->escape($props['text'] ?? '');
        $url   = $this->escapeAttr($props['url'] ?? '#');
        $style = $props['style'] ?? 'primary';
        $ea    = $this->editableAttr('text', 'text');
        return "<a id=\"tb-{$id}\" href=\"{$url}\" class=\"tb-btn tb-btn-{$style}\"{$ea}>{$text}</a>";
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
        $title    = $this->escape($props['title'] ?? '');
        $subtitle = $this->escape($props['subtitle'] ?? '');
        $btnText  = $this->escape($props['button_text'] ?? '');
        $btnUrl   = $this->escapeAttr($props['button_url'] ?? '#');
        $bg       = $props['background'] ?? 'primary';
        $eaTitle  = $this->editableAttr('title', 'text');
        $eaSub    = $this->editableAttr('subtitle', 'text');
        $eaBtn    = $this->editableAttr('button_text', 'text');
        $btn = $btnText ? "<a href=\"{$btnUrl}\" class=\"tb-btn tb-btn-primary\"{$eaBtn}>{$btnText}</a>" : '';
        return "<section id=\"tb-{$id}\" class=\"tb-cta tb-bg-{$bg}\"><h2 class=\"tb-cta-title\"{$eaTitle}>{$title}</h2><p class=\"tb-cta-subtitle\"{$eaSub}>{$subtitle}</p>{$btn}</section>";
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

    // ── NEXUS PRO PREMIUM BLOCKS ─────────────────────────────────────────────

    protected function renderHeroSplit(array $props, string $inner, string $id): string
    {
        $badge   = $this->escape($props['badge'] ?? '');
        $title   = $this->escape($props['title'] ?? '');
        $sub     = $this->escape($props['subtitle'] ?? '');
        $btn1t   = $this->escape($props['button_text'] ?? '');
        $btn1u   = $this->escapeAttr($props['button_url'] ?? '#');
        $btn2t   = $this->escape($props['button2_text'] ?? '');
        $btn2u   = $this->escapeAttr($props['button2_url'] ?? '#');
        $imgUrl  = $this->escapeAttr($props['image_url'] ?? '');
        $imgSide = $props['image_side'] ?? 'left';
        $bg      = $this->escapeAttr($props['background'] ?? '#ffffff');

        $badgeHtml = $badge ? "<span class=\"tb-hero-split-badge\">{$badge}</span>" : '';
        $btn1Html  = $btn1t ? "<a href=\"{$btn1u}\" class=\"tb-btn tb-btn-primary\">{$btn1t}</a>" : '';
        $btn2Html  = $btn2t ? "<a href=\"{$btn2u}\" class=\"tb-btn tb-btn-secondary\">{$btn2t}</a>" : '';
        $imgHtml   = $imgUrl
            ? "<img src=\"{$imgUrl}\" alt=\"{$title}\" class=\"tb-hero-split-img\" loading=\"eager\">"
            : "<div class=\"tb-hero-split-img-placeholder\">🖼️</div>";

        $textCol = "<div class=\"tb-hero-split-text\">{$badgeHtml}<h1 class=\"tb-hero-split-title\">{$title}</h1><p class=\"tb-hero-split-sub\">{$sub}</p><div class=\"tb-hero-split-actions\">{$btn1Html}{$btn2Html}</div></div>";
        $imgCol  = "<div class=\"tb-hero-split-visual\">{$imgHtml}</div>";

        $cols = $imgSide === 'right' ? $textCol . $imgCol : $imgCol . $textCol;

        return "<section id=\"tb-{$id}\" class=\"tb-section tb-padding-xl\" style=\"background:{$bg};\">
            <div class=\"tb-container\">
                <div class=\"tb-hero-split\">{$cols}</div>
            </div>
        </section>";
    }

    protected function renderHeroGradient(array $props, string $inner, string $id): string
    {
        $badge   = $this->escape($props['badge'] ?? '');
        $title   = $this->escape($props['title'] ?? '');
        $sub     = $this->escape($props['subtitle'] ?? '');
        $btn1t   = $this->escape($props['button_text'] ?? '');
        $btn1u   = $this->escapeAttr($props['button_url'] ?? '#');
        $btn2t   = $this->escape($props['button2_text'] ?? '');
        $btn2u   = $this->escapeAttr($props['button2_url'] ?? '#');
        $gFrom   = $this->escapeAttr($props['gradient_from'] ?? '#4f46e5');
        $gTo     = $this->escapeAttr($props['gradient_to'] ?? '#7c3aed');
        $tColor  = $this->escapeAttr($props['text_color'] ?? '#ffffff');

        $badgeHtml = $badge ? "<span class=\"tb-hero-gradient-badge\">{$badge}</span><br>" : '';
        $btn1Html  = $btn1t ? "<a href=\"{$btn1u}\" class=\"tb-btn\" style=\"background:#fff;color:{$gFrom};font-weight:700;\">{$btn1t}</a>" : '';
        $btn2Html  = $btn2t ? "<a href=\"{$btn2u}\" class=\"tb-btn\" style=\"background:rgba(255,255,255,0.15);color:{$tColor};border:2px solid rgba(255,255,255,0.4);\">{$btn2t}</a>" : '';
        $actions   = ($btn1Html || $btn2Html) ? "<div class=\"tb-hero-split-actions\" style=\"justify-content:center;margin-top:2rem;\">{$btn1Html}{$btn2Html}</div>" : '';

        return "<section id=\"tb-{$id}\" class=\"tb-hero-gradient\" style=\"background:linear-gradient(135deg,{$gFrom} 0%,{$gTo} 100%);color:{$tColor};\">
            <div class=\"tb-hero-gradient-inner\">
                {$badgeHtml}
                <h1 class=\"tb-hero-gradient-title\">{$title}</h1>
                <p class=\"tb-hero-gradient-sub\">{$sub}</p>
                {$actions}
            </div>
        </section>";
    }

    protected function renderFeaturesGrid(array $props, string $inner, string $id): string
    {
        $title    = $this->escape($props['title'] ?? '');
        $subtitle = $this->escape($props['subtitle'] ?? '');
        $columns  = max(2, min(4, (int) ($props['columns'] ?? 3)));
        $style    = in_array($props['style'] ?? 'cards', ['cards', 'bordered', 'minimal']) ? ($props['style'] ?? 'cards') : 'cards';

        $rawFeatures = $props['features'] ?? '[]';
        $features    = is_array($rawFeatures) ? $rawFeatures : (json_decode($rawFeatures, true) ?? []);

        $headerHtml = ($title || $subtitle) ? "<div class=\"tb-features-section-header\">
            " . ($title ? "<h2 class=\"tb-features-section-title\">{$title}</h2>" : '') . "
            " . ($subtitle ? "<p class=\"tb-features-section-sub\">{$subtitle}</p>" : '') . "
        </div>" : '';

        $cards = [];
        foreach ($features as $f) {
            $icon  = $this->escape($f['icon'] ?? '✨');
            $ftitle = $this->escape($f['title'] ?? '');
            $ftext  = $this->escape($f['text'] ?? '');
            $cards[] = "<div class=\"tb-feature-card tb-feature-card-{$style}\">
                <div class=\"tb-feature-icon\">{$icon}</div>
                <h3 class=\"tb-feature-title\">{$ftitle}</h3>
                <p class=\"tb-feature-text\">{$ftext}</p>
            </div>";
        }

        return "<div id=\"tb-{$id}\" class=\"tb-features-grid tb-section\">
            <div class=\"tb-container\">{$headerHtml}
                <div class=\"tb-grid tb-grid-cols-{$columns} tb-gap-md\">" . implode('', $cards) . "</div>
            </div>
        </div>";
    }

    protected function renderStatsRow(array $props, string $inner, string $id): string
    {
        $title    = $this->escape($props['title'] ?? '');
        $bg       = $this->escapeAttr($props['background'] ?? '#f8fafc');
        $dividers = (bool) ($props['dividers'] ?? true);

        $rawStats = $props['stats'] ?? '[]';
        $stats    = is_array($rawStats) ? $rawStats : (json_decode($rawStats, true) ?? []);

        $titleHtml = $title ? "<h2 class=\"tb-features-section-title\" style=\"text-align:center;margin-bottom:1.5rem;\">{$title}</h2>" : '';

        $items = [];
        foreach ($stats as $i => $s) {
            $val    = $this->escape((string) ($s['value'] ?? '0'));
            $suffix = $this->escape($s['suffix'] ?? '');
            $label  = $this->escape($s['label'] ?? '');
            if ($dividers && $i > 0) {
                $items[] = '<div class="tb-stat-divider" aria-hidden="true"></div>';
            }
            $items[] = "<div class=\"tb-stat-item\">
                <span class=\"tb-stat-value\" data-count=\"{$val}\">{$val}<span class=\"tb-stat-suffix\">{$suffix}</span></span>
                <span class=\"tb-stat-label\">{$label}</span>
            </div>";
        }

        return "<div id=\"tb-{$id}\" class=\"tb-stats-row\" style=\"background:{$bg};\">
            <div class=\"tb-container\">{$titleHtml}
                <div class=\"tb-stats-grid\">" . implode('', $items) . "</div>
            </div>
        </div>";
    }

    protected function renderClientLogos(array $props, string $inner, string $id): string
    {
        $title  = $this->escape($props['title'] ?? '');
        $scroll = (bool) ($props['scroll'] ?? true);

        $rawLogos = $props['logos'] ?? '[]';
        $logos    = is_array($rawLogos) ? $rawLogos : (json_decode($rawLogos, true) ?? []);

        if (empty($logos)) {
            return '';
        }

        $titleHtml = $title ? "<p class=\"tb-client-logos-title\">{$title}</p>" : '';

        $logoItems = array_map(fn($l) =>
            "<span class=\"tb-logo-item\">" . $this->escape($l['name'] ?? $l) . "</span>",
            $logos
        );
        $logoHtml = implode('', $logoItems);

        if ($scroll) {
            $doubled = $logoHtml . $logoHtml;
            $inner   = "<div class=\"tb-logos-marquee-wrap\"><div class=\"tb-logos-marquee\">{$doubled}</div></div>";
        } else {
            $inner = "<div class=\"tb-logos-static\">{$logoHtml}</div>";
        }

        return "<div id=\"tb-{$id}\" class=\"tb-client-logos\">
            <div class=\"tb-container\">{$titleHtml}{$inner}</div>
        </div>";
    }

    protected function renderProcessSteps(array $props, string $inner, string $id): string
    {
        $title    = $this->escape($props['title'] ?? '');
        $subtitle = $this->escape($props['subtitle'] ?? '');

        $rawSteps = $props['steps'] ?? '[]';
        $steps    = is_array($rawSteps) ? $rawSteps : (json_decode($rawSteps, true) ?? []);

        $headerHtml = ($title || $subtitle) ? "<div class=\"tb-features-section-header\">
            " . ($title ? "<h2 class=\"tb-features-section-title\">{$title}</h2>" : '') . "
            " . ($subtitle ? "<p class=\"tb-features-section-sub\">{$subtitle}</p>" : '') . "
        </div>" : '';

        $stepItems = [];
        foreach ($steps as $s) {
            $num   = $this->escape($s['number'] ?? '');
            $stitle = $this->escape($s['title'] ?? '');
            $stext  = $this->escape($s['text'] ?? '');
            $stepItems[] = "<div class=\"tb-step-item\">
                <div class=\"tb-step-number\">{$num}</div>
                <h3 class=\"tb-step-title\">{$stitle}</h3>
                <p class=\"tb-step-text\">{$stext}</p>
            </div>";
        }

        $cols = min(4, max(1, count($steps)));

        return "<div id=\"tb-{$id}\" class=\"tb-process-steps tb-section\">
            <div class=\"tb-container\">{$headerHtml}
                <div class=\"tb-grid tb-grid-cols-{$cols} tb-gap-md tb-steps-grid\">" . implode('', $stepItems) . "</div>
            </div>
        </div>";
    }

    protected function renderTeamCards(array $props, string $inner, string $id): string
    {
        $title   = $this->escape($props['title'] ?? 'تیم ما');
        $columns = max(2, min(4, (int) ($props['columns'] ?? 3)));

        $rawMembers = $props['members'] ?? '[]';
        $members    = is_array($rawMembers) ? $rawMembers : (json_decode($rawMembers, true) ?? []);

        $titleHtml = "<div class=\"tb-features-section-header\"><h2 class=\"tb-features-section-title\">{$title}</h2></div>";

        $cards = [];
        foreach ($members as $m) {
            $name  = $this->escape($m['name'] ?? '');
            $role  = $this->escape($m['role'] ?? '');
            $bio   = $this->escape($m['bio'] ?? '');
            $img   = $this->escapeAttr($m['image'] ?? '');
            $avatarHtml = $img
                ? "<img src=\"{$img}\" alt=\"{$name}\" class=\"tb-team-avatar\">"
                : "<div class=\"tb-team-avatar-placeholder\">" . mb_substr($name, 0, 1) . "</div>";
            $cards[] = "<div class=\"tb-team-member\">
                {$avatarHtml}
                <h3 class=\"tb-team-name\">{$name}</h3>
                <p class=\"tb-team-role\">{$role}</p>
                " . ($bio ? "<p class=\"tb-team-bio\">{$bio}</p>" : '') . "
            </div>";
        }

        return "<div id=\"tb-{$id}\" class=\"tb-section\">
            <div class=\"tb-container\">{$titleHtml}
                <div class=\"tb-grid tb-grid-cols-{$columns} tb-gap-md\">" . implode('', $cards) . "</div>
            </div>
        </div>";
    }

    protected function renderSkillsBars(array $props, string $inner, string $id): string
    {
        $title  = $this->escape($props['title'] ?? 'مهارت‌ها');

        $rawSkills = $props['skills'] ?? '[]';
        $skills    = is_array($rawSkills) ? $rawSkills : (json_decode($rawSkills, true) ?? []);

        $bars = [];
        foreach ($skills as $s) {
            $name    = $this->escape($s['name'] ?? '');
            $percent = max(0, min(100, (int) ($s['percent'] ?? 0)));
            $bars[] = "<div class=\"tb-skill-item\">
                <div class=\"tb-skill-header\"><span>{$name}</span><span>{$percent}٪</span></div>
                <div class=\"tb-skill-bar-track\">
                    <div class=\"tb-skill-bar-fill\" style=\"width:{$percent}%;\" data-width=\"{$percent}\"></div>
                </div>
            </div>";
        }

        return "<div id=\"tb-{$id}\" class=\"tb-skills tb-section\">
            <div class=\"tb-container\">
                <h2 class=\"tb-features-section-title\">{$title}</h2>
                <div class=\"tb-skills-list\">" . implode('', $bars) . "</div>
            </div>
        </div>";
    }

    protected function renderTestimonialsSlider(array $props, string $inner, string $id): string
    {
        $title   = $this->escape($props['title'] ?? 'نظرات مشتریان');
        $columns = max(1, min(3, (int) ($props['columns'] ?? 3)));

        $rawItems = $props['items'] ?? '[]';
        $items    = is_array($rawItems) ? $rawItems : (json_decode($rawItems, true) ?? []);

        $cards = [];
        foreach ($items as $t) {
            $name   = $this->escape($t['name'] ?? '');
            $role   = $this->escape($t['role'] ?? '');
            $text   = $this->escape($t['text'] ?? '');
            $rating = max(1, min(5, (int) ($t['rating'] ?? 5)));
            $stars  = str_repeat('★', $rating) . str_repeat('☆', 5 - $rating);
            $initials = mb_substr($name, 0, 1, 'UTF-8');
            $cards[] = "<div class=\"tb-testimonial-card\">
                <div class=\"tb-testimonial-stars\">{$stars}</div>
                <p class=\"tb-testimonial-text\">«{$text}»</p>
                <div class=\"tb-testimonial-author\">
                    <div class=\"tb-testimonial-avatar\">{$initials}</div>
                    <div><div class=\"tb-testimonial-name\">{$name}</div><div class=\"tb-testimonial-role\">{$role}</div></div>
                </div>
            </div>";
        }

        return "<div id=\"tb-{$id}\" class=\"tb-testimonials tb-section\">
            <div class=\"tb-container\">
                <div class=\"tb-testimonials-header\"><h2 class=\"tb-features-section-title\">{$title}</h2></div>
                <div class=\"tb-testimonials-grid cols-{$columns}\">" . implode('', $cards) . "</div>
            </div>
        </div>";
    }

    protected function renderTrustBadges(array $props, string $inner, string $id): string
    {
        $bg = $this->escapeAttr($props['background'] ?? '#f8fafc');

        $rawBadges = $props['badges'] ?? '[]';
        $badges    = is_array($rawBadges) ? $rawBadges : (json_decode($rawBadges, true) ?? []);

        $items = [];
        foreach ($badges as $b) {
            $icon  = $this->escape($b['icon'] ?? '✓');
            $btitle = $this->escape($b['title'] ?? '');
            $btext  = $this->escape($b['text'] ?? '');
            $items[] = "<div class=\"tb-trust-badge\">
                <span class=\"tb-trust-icon\">{$icon}</span>
                <div>
                    <p class=\"tb-trust-info-title\">{$btitle}</p>
                    <p class=\"tb-trust-info-text\">{$btext}</p>
                </div>
            </div>";
        }

        return "<div id=\"tb-{$id}\" class=\"tb-trust-badges\" style=\"background:{$bg};\">
            <div class=\"tb-container\">
                <div class=\"tb-trust-grid\">" . implode('', $items) . "</div>
            </div>
        </div>";
    }

    protected function renderPricingCards(array $props, string $inner, string $id): string
    {
        $title    = $this->escape($props['title'] ?? 'پلن‌های ما');
        $subtitle = $this->escape($props['subtitle'] ?? '');

        $rawPlans = $props['plans'] ?? '[]';
        $plans    = is_array($rawPlans) ? $rawPlans : (json_decode($rawPlans, true) ?? []);

        $colCount = min(4, max(1, count($plans)));
        $headerHtml = "<div class=\"tb-pricing-header\">
            <h2 class=\"tb-features-section-title\">{$title}</h2>
            " . ($subtitle ? "<p class=\"tb-features-section-sub\">{$subtitle}</p>" : '') . "
        </div>";

        $cards = [];
        foreach ($plans as $p) {
            $name      = $this->escape($p['name'] ?? '');
            $price     = $this->escape($p['price'] ?? '');
            $period    = $this->escape($p['period'] ?? '');
            $badge     = $this->escape($p['badge'] ?? '');
            $highlight = (bool) ($p['highlight'] ?? false);
            $ctaText   = $this->escape($p['cta_text'] ?? 'انتخاب');
            $ctaUrl    = $this->escapeAttr($p['cta_url'] ?? '#');
            $features  = $p['features'] ?? [];
            $hlClass   = $highlight ? ' highlighted' : '';

            $badgeHtml = $badge ? "<span class=\"tb-plan-badge\">{$badge}</span>" : '';
            $featureHtml = implode('', array_map(fn($f) => '<li>' . $this->escape($f) . '</li>', $features));

            $cards[] = "<div class=\"tb-pricing-card{$hlClass}\">
                {$badgeHtml}
                <p class=\"tb-plan-name\">{$name}</p>
                <div class=\"tb-plan-price\">{$price}</div>
                <p class=\"tb-plan-period\">{$period}</p>
                <ul class=\"tb-plan-features\">{$featureHtml}</ul>
                <a href=\"{$ctaUrl}\" class=\"tb-btn " . ($highlight ? 'tb-btn-primary' : 'tb-btn-secondary') . "\" style=\"width:100%;text-align:center;\">{$ctaText}</a>
            </div>";
        }

        return "<div id=\"tb-{$id}\" class=\"tb-pricing-cards tb-section\">
            <div class=\"tb-container\">{$headerHtml}
                <div class=\"tb-pricing-grid cols-{$colCount}\">" . implode('', $cards) . "</div>
            </div>
        </div>";
    }

    protected function renderNewsletterForm(array $props, string $inner, string $id): string
    {
        $title      = $this->escape($props['title'] ?? 'در خبرنامه عضو شوید');
        $subtitle   = $this->escape($props['subtitle'] ?? '');
        $placeholder = $this->escapeAttr($props['placeholder'] ?? 'ایمیل یا شماره موبایل');
        $btnText    = $this->escape($props['button_text'] ?? 'عضویت');
        $bg         = $this->escapeAttr($props['background'] ?? '#4f46e5');
        $tc         = $this->escapeAttr($props['text_color'] ?? '#ffffff');

        return "<div id=\"tb-{$id}\" class=\"tb-newsletter\" style=\"background:{$bg};color:{$tc};\">
            <div class=\"tb-container\">
                <h2 class=\"tb-newsletter-title\">{$title}</h2>
                " . ($subtitle ? "<p class=\"tb-newsletter-sub\">{$subtitle}</p>" : '') . "
                <form class=\"tb-newsletter-form\" onsubmit=\"event.preventDefault();this.reset();\" novalidate>
                    <input type=\"text\" class=\"tb-newsletter-input\" placeholder=\"{$placeholder}\" autocomplete=\"off\">
                    <button type=\"submit\" class=\"tb-newsletter-btn tb-btn\" style=\"background:#fff;color:{$bg};\">{$btnText}</button>
                </form>
            </div>
        </div>";
    }

    protected function renderBentoGrid(array $props, string $inner, string $id): string
    {
        $title = $this->escape($props['title'] ?? '');

        $rawItems = $props['items'] ?? '[]';
        $items    = is_array($rawItems) ? $rawItems : (json_decode($rawItems, true) ?? []);

        $titleHtml = $title ? "<div class=\"tb-bento-header\"><h2 class=\"tb-features-section-title\">{$title}</h2></div>" : '';

        $cells = [];
        foreach ($items as $item) {
            $ititle = $this->escape($item['title'] ?? '');
            $itext  = $this->escape($item['text'] ?? '');
            $icon   = $this->escape($item['icon'] ?? '');
            $size   = in_array($item['size'] ?? '', ['large', 'tall', 'featured', 'small']) ? $item['size'] : 'small';
            $bgc    = $this->escapeAttr($item['bg_color'] ?? '#f8fafc');
            $tc     = $this->escapeAttr($item['text_color'] ?? '#0f172a');
            $cells[] = "<div class=\"tb-bento-item {$size}\" style=\"background:{$bgc};color:{$tc};\">
                " . ($icon ? "<div class=\"tb-bento-item-icon\">{$icon}</div>" : '') . "
                <h3 class=\"tb-bento-item-title\">{$ititle}</h3>
                <p class=\"tb-bento-item-text\">{$itext}</p>
            </div>";
        }

        return "<div id=\"tb-{$id}\" class=\"tb-bento tb-section\">
            <div class=\"tb-container\">{$titleHtml}
                <div class=\"tb-bento-grid\">" . implode('', $cells) . "</div>
            </div>
        </div>";
    }

    protected function renderServicesCards(array $props, string $inner, string $id): string
    {
        $title    = $this->escape($props['title'] ?? 'خدمات ما');
        $subtitle = $this->escape($props['subtitle'] ?? '');
        $columns  = max(2, min(4, (int) ($props['columns'] ?? 3)));
        $style    = in_array($props['style'] ?? '', ['bordered', 'filled', 'minimal']) ? ($props['style'] ?? 'bordered') : 'bordered';

        $rawServices = $props['services'] ?? '[]';
        $services    = is_array($rawServices) ? $rawServices : (json_decode($rawServices, true) ?? []);

        $headerHtml = "<div class=\"tb-features-section-header\">
            <h2 class=\"tb-features-section-title\">{$title}</h2>
            " . ($subtitle ? "<p class=\"tb-features-section-sub\">{$subtitle}</p>" : '') . "
        </div>";

        $cards = [];
        foreach ($services as $svc) {
            $icon     = $this->escape($svc['icon'] ?? '');
            $stitle   = $this->escape($svc['title'] ?? '');
            $stext    = $this->escape($svc['text'] ?? '');
            $link     = $this->escapeAttr($svc['link'] ?? '#');
            $linkText = $this->escape($svc['link_text'] ?? 'بیشتر بدانید');
            $linkHtml = $link && $linkText ? "<a href=\"{$link}\" class=\"tb-service-link\">{$linkText} ←</a>" : '';
            $cards[] = "<div class=\"tb-service-card style-{$style}\">
                " . ($icon ? "<div class=\"tb-service-icon\">{$icon}</div>" : '') . "
                <h3 class=\"tb-service-title\">{$stitle}</h3>
                <p class=\"tb-service-text\">{$stext}</p>
                {$linkHtml}
            </div>";
        }

        return "<div id=\"tb-{$id}\" class=\"tb-section\">
            <div class=\"tb-container\">{$headerHtml}
                <div class=\"tb-services-grid tb-grid tb-grid-cols-{$columns} tb-gap-md\">" . implode('', $cards) . "</div>
            </div>
        </div>";
    }

    protected function renderContactSplit(array $props, string $inner, string $id): string
    {
        $title   = $this->escape($props['title'] ?? 'با ما در ارتباط باشید');
        $subtitle = $this->escape($props['subtitle'] ?? '');
        $phone   = $this->escape($props['phone'] ?? '');
        $email   = $this->escape($props['email'] ?? '');
        $address = $this->escape($props['address'] ?? '');
        $hours   = $this->escape($props['hours'] ?? '');
        $showForm = (bool) ($props['show_form'] ?? true);

        $infoItems = [];
        if ($phone) {
            $infoItems[] = "<div class=\"tb-contact-info-item\"><span class=\"tb-contact-info-icon\">📞</span><div><p class=\"tb-contact-info-label\">تلفن</p><p class=\"tb-contact-info-value\">{$phone}</p></div></div>";
        }
        if ($email) {
            $infoItems[] = "<div class=\"tb-contact-info-item\"><span class=\"tb-contact-info-icon\">✉️</span><div><p class=\"tb-contact-info-label\">ایمیل</p><p class=\"tb-contact-info-value\">{$email}</p></div></div>";
        }
        if ($address) {
            $infoItems[] = "<div class=\"tb-contact-info-item\"><span class=\"tb-contact-info-icon\">📍</span><div><p class=\"tb-contact-info-label\">آدرس</p><p class=\"tb-contact-info-value\">{$address}</p></div></div>";
        }
        if ($hours) {
            $infoItems[] = "<div class=\"tb-contact-info-item\"><span class=\"tb-contact-info-icon\">🕐</span><div><p class=\"tb-contact-info-label\">ساعات کاری</p><p class=\"tb-contact-info-value\">{$hours}</p></div></div>";
        }

        $formHtml = $showForm ? "<div class=\"tb-contact-form-wrap\">
            <div class=\"tb-contact-form-simple\">
                <form onsubmit=\"event.preventDefault();\" novalidate>
                    <div style=\"margin-bottom:1rem;\"><input type=\"text\" placeholder=\"نام شما\" style=\"width:100%;padding:0.65rem 1rem;border:1px solid #e5e7eb;border-radius:0.5rem;font-family:inherit;font-size:0.95rem;\"></div>
                    <div style=\"margin-bottom:1rem;\"><input type=\"text\" placeholder=\"شماره موبایل یا ایمیل\" style=\"width:100%;padding:0.65rem 1rem;border:1px solid #e5e7eb;border-radius:0.5rem;font-family:inherit;font-size:0.95rem;\"></div>
                    <div style=\"margin-bottom:1rem;\"><textarea placeholder=\"پیام شما\" rows=\"4\" style=\"width:100%;padding:0.65rem 1rem;border:1px solid #e5e7eb;border-radius:0.5rem;font-family:inherit;font-size:0.95rem;resize:vertical;\"></textarea></div>
                    <button type=\"submit\" class=\"tb-btn tb-btn-primary\" style=\"width:100%;\">ارسال پیام</button>
                </form>
            </div>
        </div>" : '';

        return "<div id=\"tb-{$id}\" class=\"tb-section\">
            <div class=\"tb-container\">
                <div class=\"tb-features-section-header\">
                    <h2 class=\"tb-features-section-title\">{$title}</h2>
                    " . ($subtitle ? "<p class=\"tb-features-section-sub\">{$subtitle}</p>" : '') . "
                </div>
                <div class=\"tb-contact-split\">
                    <div class=\"tb-contact-info\">" . implode('', $infoItems) . "</div>
                    {$formHtml}
                </div>
            </div>
        </div>";
    }

    // ── END NEXUS PRO PREMIUM BLOCKS ─────────────────────────────────────────

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
