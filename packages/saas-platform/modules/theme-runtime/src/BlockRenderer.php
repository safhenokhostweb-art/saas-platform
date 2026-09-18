<?php
namespace SaaSPlatform\ThemeRuntime;

use Illuminate\Support\Str;

/**
 * Phase 2h (ADR-029) — the ONE canonical structural Theme block renderer
 * shared by the saas-platform Builder preview and customer-site's tenant
 * runtime. Sanitized, SEO-friendly, RTL-aware.
 *
 * This class owns NO host-specific data access. Two extension seams a host
 * uses instead of forking this class:
 *   - `setContext(['module_renderer' => callable])` for `module:*` types
 *     (see ModuleRendererContract) — or a host may override renderBlock()
 *     itself for a richer dispatch (see customer-site's ThemeCanvasRenderer).
 *   - Plain method overriding for genuinely data-driven canonical types
 *     (renderBlogPosts()/renderTestimonials() below render an honest,
 *     non-fabricated "will show once available" placeholder by default —
 *     a host with real data overrides the method, exactly like any other
 *     protected method in a base class).
 *
 * A host's own BlockRenderer should `extends \SaaSPlatform\ThemeRuntime\BlockRenderer`
 * and add ONLY its own genuinely host-specific block types/overrides —
 * never re-declare a method already defined here.
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
        // TB4: children may be at top-level (block.children), legacy top-level (block.blocks),
        // or inside props (block.props.blocks) — check all three for backward compat.
        $children = $block['children'] ?? $block['blocks'] ?? ($props['blocks'] ?? []);
        // TASK 3/3 Phase 5 (BLD-16 follow-up): a dispatch method like
        // renderTabs() below only ever receives $props (never the raw
        // $block), but needs the real per-child data (e.g. each tab_panel's
        // own title) to build a nav bar — not just $inner's already-
        // rendered HTML. Writing the resolved children back into
        // $props['blocks'] makes that ONE guaranteed channel regardless of
        // which of the three shapes above the block actually used —
        // exactly the class of gap real browser certification caught:
        // canonical Canvas-authored content (top-level `children`) built a
        // nav bar with no labels because renderTabs() only ever checked
        // `props.blocks`, the seeder's own (different, also-legitimate)
        // shape.
        if (!empty($children)) {
            $props['blocks'] = $children;
        }

        $inner = '';
        if ($this->isStructuralContainer($type) && !empty($children)) {
            $inner = $this->renderBlocks($children);
        }
        // BLD-01 (Full Product Commercial Audit, TASK 1/3): the generic
        // dispatch below (`$method = 'render'.Str::studly(...); $this->
        // {$method}($props, $inner, $id)`) used to ALSO resolve to
        // `renderAccordion(array $items)` and re-invoke it with 3
        // positional args — PHP silently discards the extra two against the
        // single-array signature, rebinding the WHOLE `$props` (not
        // `$props['items']`) to the method's only parameter and discarding
        // the correct `$inner` computed here. This special case still
        // short-circuits straight to the same style/editor-mode wrapping
        // every other type goes through below, instead of falling into
        // that re-dispatch.
        //
        // `tabs` no longer needs this special case (Full Product
        // Commercial Audit, TASK 2A — tabs container authoring fix): it is
        // now a real structural container (see isStructuralContainer()
        // below), so it falls through to the normal dispatch, where
        // renderTabs() receives the standard ($props, $inner, $id) — $inner
        // already holding every real tab_panel child's rendered HTML.
        if ($type === 'accordion') {
            $html = !empty($props['items']) ? $this->renderAccordion($props['items']) : '';
            return $this->applyBlockStyle(
                $this->wrapEditorMode($html, $id, $type),
                $block['style'] ?? [],
                $block['responsive'] ?? [],
                $id
            );
        }
        // Builder integrity fix (2026-09-12): a `slider` slide is plain
        // {image,title,subtitle,link} data, never a self-describing block
        // (it has no `type` key) — recursing through renderBlocks() here
        // fed each slide into schema-aware validation elsewhere and, at
        // best, produced blank per-slide output nothing downstream used.
        // renderSlider() below builds each slide directly from its own
        // fields instead.

        // Module blocks: render as styled placeholder (admin preview) or delegate (customer-site)
        if (str_starts_with($type, 'module:')) {
            $renderer = $this->context['module_renderer'] ?? null;
            if (is_callable($renderer)) {
                $html = $renderer($type, $props, $id);
            } else {
                $html = $this->renderModulePlaceholder($type, $props, $id);
            }
            return $this->applyBlockStyle(
                $this->wrapEditorMode($html, $id, $type),
                $block['style'] ?? [],
                $block['responsive'] ?? [],
                $id
            );
        }

        $method = 'render' . Str::studly(str_replace(':', '_', $type));
        if (method_exists($this, $method)) {
            $html = $this->{$method}($props, $inner, $id);
        } else {
            $html = $this->renderFallback($type, $props, $inner, $id);
        }

        return $this->applyBlockStyle(
            $this->wrapEditorMode($html, $id, $type),
            $block['style'] ?? [],
            $block['responsive'] ?? [],
            $id
        );
    }

    /**
     * True if `$type`'s children should be rendered as a plain nested block
     * list (the `$inner` this class's canonical container types — section/
     * container/grid/columns/flex_container — all pass through renderBlocks()
     * for). A host extending this class (e.g. saas-platform's own
     * BlockRenderer subclass, for its 3G-B-6 Extension elements) overrides
     * this to also recognize its own host/extension container types,
     * exactly the same override seam this class's docblock already
     * documents for host-specific behavior — never by duplicating this
     * method's canonical list, only by widening it.
     */
    protected function isStructuralContainer(string $type): bool
    {
        // BLD-04..09: `modal` added — its `blocks` prop is real nested
        // content (a dialog body), the same shape every other container
        // type here already has; it needs the same automatic $inner
        // population, not a bespoke re-implementation in renderModal().
        //
        // TASK 2A (tabs container authoring fix): `tabs`/`tab_panel` added
        // — each tab is a real tab_panel child block, and each tab_panel's
        // own content is its own children, so both need the identical
        // automatic $inner population every other container gets.
        return in_array($type, ['section', 'container', 'grid', 'columns', 'flex_container', 'modal', 'tabs', 'tab_panel'], true);
    }

    /**
     * Phase 2j — the delegation seam Section 2 asked for: an extension
     * layer (customer-site's Element SDK, or any future one) that wants to
     * keep its own validation/schema/dynamic-data/asset-collection pipeline
     * but must NOT independently reimplement canonical structural markup
     * calls this instead of maintaining a second render() body. Returns
     * null (never a placeholder) when no canonical method exists for
     * $type, so the caller's own fallback semantics decide what happens —
     * this method makes no assumption about what "unknown type" means for
     * a caller that isn't the block-tree dispatcher above.
     */
    public function dispatchStructural(string $type, array $props, string $inner, string $id): ?string
    {
        $method = 'render' . Str::studly(str_replace(':', '_', $type));
        if (!method_exists($this, $method)) {
            return null;
        }
        return $this->{$method}($props, $inner, $id);
    }

    // TB5: Build CSS declaration array from a style object (shared by desktop + responsive breakpoints)
    // Phase 2i: was `private` — a private method is invisible to a subclass,
    // which is exactly why customer-site had to keep its own full copy of
    // applyBlockStyle() (which calls this) even though the two had drifted
    // to different CSS class names (tb-anim- vs tb-animate-). `protected`
    // makes this genuinely inheritable/overridable, closing that gap.
    protected function buildCssDeclarations(array $style): array
    {
        $css = [];
        $shadowMap = [
            'sm'   => '0 1px 2px 0 rgba(0,0,0,0.05)',
            'md'   => '0 4px 6px -1px rgba(0,0,0,0.1),0 2px 4px -2px rgba(0,0,0,0.1)',
            'lg'   => '0 10px 15px -3px rgba(0,0,0,0.1),0 4px 6px -4px rgba(0,0,0,0.1)',
            'xl'   => '0 20px 25px -5px rgba(0,0,0,0.1),0 8px 10px -6px rgba(0,0,0,0.1)',
            'none' => 'none',
        ];

        if (!empty($style['bgColor']))   $css[] = 'background-color:' . $this->escapeAttr($style['bgColor']);
        if (!empty($style['textColor'])) $css[] = 'color:' . $this->escapeAttr($style['textColor']);

        if (!empty($style['bgGradientFrom']) && !empty($style['bgGradientTo'])) {
            $dir  = $this->escapeAttr($style['bgGradientDir'] ?? 'to bottom');
            $from = $this->escapeAttr($style['bgGradientFrom']);
            $to   = $this->escapeAttr($style['bgGradientTo']);
            $css[] = "background-image:linear-gradient({$dir},{$from},{$to})";
        }

        if (($style['fontSize']      ?? '') !== '') $css[] = 'font-size:'      . (int)$style['fontSize']      . 'px';
        if (!empty($style['fontWeight']))            $css[] = 'font-weight:'    . $this->escapeAttr($style['fontWeight']);
        if (($style['lineHeight']    ?? '') !== '') $css[] = 'line-height:'    . $this->escapeAttr($style['lineHeight']);
        if (!empty($style['textAlign']))             $css[] = 'text-align:'     . $this->escapeAttr($style['textAlign']);
        if (($style['letterSpacing'] ?? '') !== '') $css[] = 'letter-spacing:' . $this->escapeAttr($style['letterSpacing']) . 'px';

        if (isset($style['paddingTop']) || isset($style['paddingRight']) || isset($style['paddingBottom']) || isset($style['paddingLeft'])) {
            if (($style['paddingTop']    ?? '') !== '') $css[] = 'padding-top:'    . (int)$style['paddingTop']    . 'px';
            if (($style['paddingRight']  ?? '') !== '') $css[] = 'padding-right:'  . (int)$style['paddingRight']  . 'px';
            if (($style['paddingBottom'] ?? '') !== '') $css[] = 'padding-bottom:' . (int)$style['paddingBottom'] . 'px';
            if (($style['paddingLeft']   ?? '') !== '') $css[] = 'padding-left:'   . (int)$style['paddingLeft']   . 'px';
        } else {
            if (($style['paddingY'] ?? '') !== '') $css[] = "padding-top:{$style['paddingY']}px;padding-bottom:{$style['paddingY']}px";
            if (($style['paddingX'] ?? '') !== '') $css[] = "padding-left:{$style['paddingX']}px;padding-right:{$style['paddingX']}px";
        }

        if (($style['marginTop']    ?? '') !== '') $css[] = 'margin-top:'    . (int)$style['marginTop']    . 'px';
        if (($style['marginBottom'] ?? '') !== '') $css[] = 'margin-bottom:' . (int)$style['marginBottom'] . 'px';

        if (($style['borderWidth'] ?? '') !== '' && (int)$style['borderWidth'] > 0) {
            $css[] = 'border-width:' . (int)$style['borderWidth'] . 'px';
            $css[] = 'border-style:' . $this->escapeAttr($style['borderStyle'] ?? 'solid');
        }
        if (!empty($style['borderColor'])) $css[] = 'border-color:' . $this->escapeAttr($style['borderColor']);
        if (($style['borderRadius']  ?? '') !== '') $css[] = 'border-radius:' . (int)$style['borderRadius'] . 'px';

        if (!empty($style['shadow'])) {
            $shadow = $shadowMap[$style['shadow']] ?? $style['shadow'];
            $css[] = 'box-shadow:' . $this->escapeAttr($shadow);
        }

        if (isset($style['opacity']) && $style['opacity'] != 1 && $style['opacity'] !== '') {
            $css[] = 'opacity:' . $style['opacity'];
        }

        if (!empty($style['animationDuration'])) {
            $css[] = '--tb-animate-dur:' . (int)$style['animationDuration'] . 'ms';
        }

        // TB5: responsive-only — display:none for hidden breakpoint
        if (!empty($style['hidden'])) {
            $css[] = 'display:none';
        }

        return $css;
    }

    // TB5: Apply desktop + responsive breakpoint styles to a block's HTML wrapper
    // Phase 2i: was `private` — see buildCssDeclarations()'s note above;
    // customer-site's own renderBlock() override calls this directly.
    protected function applyBlockStyle(string $html, array $style, array $responsive = [], string $id = ''): string
    {
        $desktopCss = $this->buildCssDeclarations($style);
        $classes    = ['tb-style-wrap'];

        // Custom class (sanitized)
        if (!empty($style['customClass'])) {
            foreach (explode(' ', $style['customClass']) as $cls) {
                $safe = preg_replace('/[^a-zA-Z0-9_\-]/', '', trim($cls));
                if ($safe !== '') $classes[] = $safe;
            }
        }

        // Visibility classes (desktop/tablet/mobile hide)
        if (!empty($style['hideDesktop'])) $classes[] = 'tb-hide-desktop';
        if (!empty($style['hideTablet']))  $classes[] = 'tb-hide-tablet';
        if (!empty($style['hideMobile']))  $classes[] = 'tb-hide-mobile';

        // Animation class
        if (!empty($style['animation']) && $style['animation'] !== 'none') {
            $anim = preg_replace('/[^a-z\-]/', '', $style['animation']);
            $classes[] = 'tb-animate-' . $anim;
        }

        // TB5: add block-specific class for responsive targeting
        $blkClass = '';
        if ($id !== '' && !empty($responsive)) {
            $blkId    = preg_replace('/[^a-zA-Z0-9_\-]/', '', $id);
            $blkClass = " tb-blk-{$blkId}";
            $classes[] = "tb-blk-{$blkId}";
        }

        $hasContent = !empty($desktopCss) || count($classes) > 1;
        if (!$hasContent && empty($responsive)) return $html;

        $classAttr = implode(' ', $classes);
        $styleStr  = implode(';', $desktopCss);
        $styleAttr = $styleStr ? " style=\"{$styleStr}\"" : '';
        $wrapped   = "<div class=\"{$classAttr}\"{$styleAttr}>{$html}</div>";

        // TB5: inject responsive breakpoint <style> block
        if (!empty($responsive) && $id !== '') {
            $blkId      = preg_replace('/[^a-zA-Z0-9_\-]/', '', $id);
            $selector   = ".tb-blk-{$blkId}";
            $mediaCss   = '';

            $tabletStyle = $responsive['tablet'] ?? [];
            if (!empty($tabletStyle)) {
                $tabletCss = $this->buildCssDeclarations($tabletStyle);
                if (!empty($tabletCss)) {
                    $mediaCss .= "@media(max-width:1024px){{$selector}{" . implode(';', $tabletCss) . "}}";
                }
            }

            $mobileStyle = $responsive['mobile'] ?? [];
            if (!empty($mobileStyle)) {
                $mobileCss = $this->buildCssDeclarations($mobileStyle);
                if (!empty($mobileCss)) {
                    $mediaCss .= "@media(max-width:480px){{$selector}{" . implode(';', $mobileCss) . "}}";
                }
            }

            if ($mediaCss !== '') {
                $wrapped .= "<style data-tb-blk=\"{$blkId}\">{$mediaCss}</style>";
            }
        }

        return $wrapped;
    }

    protected function editableAttr(string $propKey, string $inlineType = 'text'): string
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
        $esc   = htmlspecialchars($type, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $idEsc = htmlspecialchars($id,   ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        // TB4: container blocks get data-tb-container so the iframe can show nested drop zones
        $containerAttr = in_array($type, ['section', 'container', 'grid', 'columns', 'flex_container'], true)
            ? ' data-tb-container="1"' : '';
        return "<div class=\"tb-canvas-block\" data-tb-block-id=\"{$idEsc}\" data-tb-block-type=\"{$esc}\"{$containerAttr}>"
            . "<div class=\"tb-block-badge\">{$esc}</div>"
            . $html
            . "</div>";
    }

    /**
     * Phase 2j: absorbed the `_style.background/layout/effects` custom-
     * styling capability — found, identically, duplicated in customer-site's
     * own BlockRenderer::renderSection() override AND in the Element SDK's
     * SectionElement, both now redundant and deletable.
     */
    protected function renderSection(array $props, string $inner, string $id): string
    {
        $padding = $this->spacingClass($props['padding'] ?? 'md');
        $bg = $this->escapeAttr($props['background'] ?? 'transparent');
        $bgImg = $this->escapeAttr($props['background_image'] ?? '');
        $useContainer = ($props['container'] ?? true);
        $inlineStyles = [];
        if ($bgImg) {
            $inlineStyles[] = "background-image: url({$bgImg})";
            $inlineStyles[] = "background-size: cover";
        }
        $customBg = $props['_style']['background']['color'] ?? null;
        if ($customBg) {
            $inlineStyles[] = "background-color:" . $this->escapeAttr($customBg);
        }
        $class = "tb-section tb-padding-{$padding}";
        if ($bg !== 'transparent' && !str_starts_with($bg, '#')) {
            $class .= " tb-bg-{$bg}";
        }
        if ($bg !== 'transparent' && str_starts_with($bg, '#')) {
            $inlineStyles[] = "background-color:{$bg}";
        }
        $layoutStyle = $props['_style']['layout'] ?? [];
        $effectsStyle = $props['_style']['effects'] ?? [];
        if (in_array($layoutStyle['radius'] ?? null, ['sm', 'md', 'lg'], true)) {
            $class .= " tb-radius-" . $layoutStyle['radius'];
        }
        if (in_array($layoutStyle['shadow'] ?? null, ['sm', 'md', 'lg'], true)) {
            $class .= " tb-shadow-" . $layoutStyle['shadow'];
        }
        if (($effectsStyle['hover'] ?? null) === 'lift') {
            $class .= " tb-hover-lift";
        }
        $styleAttr = $inlineStyles ? ' style="' . implode(';', $inlineStyles) . '"' : '';
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
        $cols = max(1, min(6, (int) ($props['columns'] ?? 3)));
        $gap = $this->spacingClass($props['gap'] ?? 'md');
        return "<div id=\"tb-{$id}\" class=\"tb-grid tb-grid-cols-{$cols} tb-gap-{$gap}\">{$inner}</div>";
    }

    /**
     * Builder integrity audit (2026-09-11): .tb-columns/.tb-col-N carried
     * ZERO CSS anywhere in the shared contract — children rendered as
     * plain stacked block divs with no side-by-side layout at all, and
     * 'gap' (a real, long-declared schema field) had no effect. Fixed
     * with a scoped inline <style> block (this method only receives
     * $inner as an already-flattened HTML string, not the raw children
     * array, so real per-child weighting from 'ratios' would need a
     * base-class change to how structural containers receive their
     * children — out of scope here; documented, not hidden). Equal-width
     * flex distribution with a real, working gap is a strict improvement
     * over the previous "no layout at all" state, not a regression.
     */
    protected function renderColumns(array $props, string $inner, string $id): string
    {
        $ratios = $props['ratios'] ?? '1/2,1/2';
        $count = max(1, count(array_map('trim', explode(',', $ratios))));
        $gap = ['none' => '0', 'xs' => '0.5rem', 'sm' => '0.75rem', 'md' => '1.5rem', 'lg' => '2rem', 'xl' => '3rem'][$props['gap'] ?? 'md'] ?? '1.5rem';
        $safeId = preg_replace('/[^a-zA-Z0-9_\-]/', '', $id);
        $style = "<style>#tb-{$safeId}{display:flex;flex-wrap:wrap;gap:{$gap};}#tb-{$safeId}>*{flex:1 1 0;min-width:0;}@media(max-width:768px){#tb-{$safeId}{flex-direction:column;}}</style>";
        return "{$style}<div id=\"tb-{$id}\" class=\"tb-columns\" data-columns=\"{$count}\">{$inner}</div>";
    }

    /**
     * Phase 2i: was `renderFlex_container` — Str::studly('flex_container')
     * produces 'FlexContainer' (no underscore), so this method was never
     * actually dispatchable in either host; a flex_container block silently
     * fell through to renderFallback(). Not currently authored by any of
     * the 28 canonical themes (RenderIntegrityCheck's real-renderability
     * contract test would have caught it otherwise), so this is a
     * dead-code fix, not a behavior change to any existing content.
     */
    protected function renderFlexContainer(array $props, string $inner, string $id): string
    {
        $allowDir = ['column', 'row', 'column-reverse', 'row-reverse'];
        $direction = in_array($props['direction'] ?? 'column', $allowDir, true) ? $props['direction'] : 'column';

        $gapMap = ['none' => '0', 'xs' => '4px', 'sm' => '8px', 'md' => '16px', 'lg' => '24px', 'xl' => '40px'];
        $gap = $gapMap[$props['gap'] ?? 'md'] ?? '16px';

        $wrap = ($props['wrap'] ?? false) ? 'wrap' : 'nowrap';

        $allowAlign = ['stretch', 'flex-start', 'center', 'flex-end', 'baseline'];
        $align = in_array($props['alignItems'] ?? 'stretch', $allowAlign, true) ? $props['alignItems'] : 'stretch';

        $allowJustify = ['flex-start', 'center', 'flex-end', 'space-between', 'space-around', 'space-evenly'];
        $justify = in_array($props['justifyContent'] ?? 'flex-start', $allowJustify, true) ? $props['justifyContent'] : 'flex-start';

        $minH = max(0, (int)($props['minHeight'] ?? 0));
        $minHStyle = $minH > 0 ? "min-height:{$minH}px;" : '';

        $emptyPlaceholder = '';
        if (empty(trim($inner)) && ($this->context['editor_mode'] ?? false)) {
            $emptyPlaceholder = '<div class="tb-fc-empty"><span>کانتینر خالی — بلوک اضافه کنید</span></div>';
        }

        $style = "display:flex;flex-direction:{$direction};gap:{$gap};flex-wrap:{$wrap};align-items:{$align};justify-content:{$justify};{$minHStyle}";
        return "<div id=\"tb-{$id}\" class=\"tb-flex-container\" style=\"{$style}\">{$inner}{$emptyPlaceholder}</div>";
    }

    protected function renderHero(array $props, string $inner, string $id): string
    {
        $title    = $this->escape($props['title'] ?? '');
        $subtitle = $this->escape($props['subtitle'] ?? '');
        $btnText  = $this->escape($props['button_text'] ?? '');
        $btnUrl   = $this->escapeUrl($props['button_url'] ?? '', '#');
        $bg    = $props['background'] ?? 'primary';
        $align = $props['align'] ?? 'center';
        $eaTitle = $this->editableAttr('title', 'text');
        $eaSub   = $this->editableAttr('subtitle', 'text');
        $eaBtn   = $this->editableAttr('button_text', 'text');
        $btn = $btnText ? "<a href=\"{$btnUrl}\" class=\"tb-btn tb-btn-primary\"{$eaBtn}>{$btnText}</a>" : '';
        // Builder integrity audit (2026-09-11): background_image is a real,
        // long-declared schema field this method never consumed — a
        // background image selected/uploaded through the Builder silently
        // had zero runtime effect. Same inline-style pattern renderSection()
        // already uses for the identical field.
        $bgImg = $this->escapeAttr($props['background_image'] ?? '');
        $styleAttr = $bgImg ? " style=\"background-image:url({$bgImg});background-size:cover;background-position:center;\"" : '';
        return "<section id=\"tb-{$id}\" class=\"tb-hero tb-bg-{$bg} tb-align-{$align}\"{$styleAttr}><div class=\"tb-hero-inner\"><h1 class=\"tb-hero-title\"{$eaTitle}>{$title}</h1><p class=\"tb-hero-subtitle\"{$eaSub}>{$subtitle}</p>{$btn}</div></section>";
    }

    /**
     * Builder integrity fix (2026-09-12): `slider` had no canonical renderer
     * at all — `renderBlock()` fell through to `renderFallback()`, which (via
     * the pre-`$inner` computation just removed above) showed blank/empty
     * slide boxes in the master-authoring preview. This canonical version
     * builds each slide directly from its own {image,title,subtitle,link}
     * fields — never through renderBlock()/schema validation, which slide
     * records don't satisfy (no `type` key). It is a static, non-interactive
     * row by design: a host that needs a real interactive carousel overrides
     * this method with one, exactly the seam this class's docblock already
     * documents for renderTestimonials()/renderBlogPosts() (see
     * customer-site's ThemeCanvasRenderer::renderSlider() for that override).
     */
    protected function renderSlider(array $props, string $inner, string $id): string
    {
        $slides = $props['slides'] ?? [];
        if (!is_array($slides)) {
            $slides = [];
        }

        $cards = [];
        foreach ($slides as $slide) {
            if (!is_array($slide)) {
                continue;
            }
            $image    = $this->escapeAttr($slide['image'] ?? '');
            $title    = $this->escape($slide['title'] ?? '');
            $subtitle = $this->escape($slide['subtitle'] ?? '');
            $link     = $this->escapeUrl($slide['link'] ?? '', '');

            $imgHtml = $image !== ''
                ? "<img src=\"{$image}\" alt=\"{$title}\" style=\"width:100%;height:12rem;object-fit:cover;border-radius:.5rem;display:block;\" loading=\"lazy\">"
                : '';
            $textHtml = ($title !== '' || $subtitle !== '')
                ? '<div style="padding:.75rem .25rem;">'
                    . ($title !== '' ? "<h3 style=\"margin:0 0 .25rem;font-size:1.1rem;\">{$title}</h3>" : '')
                    . ($subtitle !== '' ? "<p style=\"margin:0;color:#6b7280;\">{$subtitle}</p>" : '')
                    . '</div>'
                : '';

            $card = "<div class=\"tb-slide\" style=\"flex:0 0 16rem;\">{$imgHtml}{$textHtml}</div>";
            $cards[] = $link !== '' ? "<a href=\"{$link}\" style=\"color:inherit;text-decoration:none;\">{$card}</a>" : $card;
        }

        if (empty($cards)) {
            return '';
        }

        return "<div id=\"tb-{$id}\" class=\"tb-slider\" style=\"display:flex;gap:1rem;overflow-x:auto;padding-bottom:.5rem;\">" . implode('', $cards) . "</div>";
    }

    /**
     * Phase 2j: absorbed the `_style.typography` (color/textAlign)
     * capability from customer-site's BlockRenderer override / HeadingElement
     * (both identical, both now redundant).
     */
    protected function renderHeading(array $props, string $inner, string $id): string
    {
        $level = max(1, min(6, (int) ($props['level'] ?? 2)));
        $text  = $this->escape($props['text'] ?? '');
        $style = $props['_style']['typography'] ?? [];
        $inline = [];
        if (!empty($style['color'])) {
            $inline[] = 'color:' . $this->escapeAttr($style['color']);
        }
        if (!empty($style['textAlign']) && in_array($style['textAlign'], ['left', 'right', 'center'], true)) {
            $inline[] = 'text-align:' . $this->escapeAttr($style['textAlign']);
        }
        // display:block;width:100% — preserved from the Element SDK's
        // HeadingElement (a real defensive rule for headings nested inside
        // a flex layout, e.g. .tb-column, where they'd otherwise shrink to
        // content width); harmless for a normal block-level heading.
        $inline[] = 'display:block';
        $inline[] = 'width:100%';
        $styleAttr = ' style="' . implode(';', $inline) . '"';
        $ea    = $this->editableAttr('text', 'text');
        return "<h{$level} id=\"tb-{$id}\" class=\"tb-heading\"{$styleAttr}{$ea}>{$text}</h{$level}>";
    }

    /**
     * Phase 2j: absorbed the `_style.typography` capability (see
     * renderHeading()) — kept the canonical "prose" class this time (the
     * customer-site override had silently dropped it) and only adds the
     * inner wrapper div when an inline style actually needs to attach to
     * the text itself, instead of unconditionally restructuring the markup.
     */
    protected function renderText(array $props, string $inner, string $id): string
    {
        $content = $this->sanitizeHtml($props['content'] ?? '');
        $style = $props['_style']['typography'] ?? [];
        $wrapperStyles = [];
        $innerStyles = [];
        if (!empty($style['color'])) {
            $wrapperStyles[] = 'color:' . $this->escapeAttr($style['color']);
        }
        if (!empty($style['textAlign']) && in_array($style['textAlign'], ['left', 'right', 'center'], true)) {
            $wrapperStyles[] = 'text-align:' . $this->escapeAttr($style['textAlign']);
            $innerStyles[] = 'text-align:' . $this->escapeAttr($style['textAlign']);
        }
        $wrapperAttr = $wrapperStyles ? ' style="' . implode(';', $wrapperStyles) . '"' : '';
        $ea = $this->editableAttr('content', 'html');
        if (empty($innerStyles)) {
            return "<div id=\"tb-{$id}\" class=\"tb-text prose\"{$wrapperAttr}{$ea}>{$content}</div>";
        }
        $innerAttr = ' style="' . implode(';', $innerStyles) . '"';
        return "<div id=\"tb-{$id}\" class=\"tb-text prose\"{$wrapperAttr}><div{$innerAttr}{$ea}>{$content}</div></div>";
    }

    protected function renderImage(array $props, string $inner, string $id): string
    {
        $url     = $this->escapeUrl($props['url'] ?? '', '');
        $alt     = $this->escapeAttr($props['alt'] ?? '');
        $caption = $this->escape($props['caption'] ?? '');
        if ($url === '') {
            return "<figure id=\"tb-{$id}\" class=\"tb-image tb-image-placeholder\"><span>تصویر انتخاب نشده</span></figure>";
        }
        // Builder integrity audit (2026-09-11): align is a real, long-
        // declared schema field this method never consumed — the
        // .tb-img-center/.tb-img-right classes it needs already existed in
        // the shared CSS contract (theme-runtime-contract.css) with no
        // renderer ever emitting them.
        $align = $props['align'] ?? 'center';
        $alignClass = $align === 'right' ? ' tb-img-right' : ($align === 'left' ? '' : ' tb-img-center');
        $img   = "<img src=\"{$url}\" alt=\"{$alt}\" loading=\"lazy\" class=\"tb-img{$alignClass}\">";
        $eaCap = $this->editableAttr('caption', 'text');
        $cap   = $caption ? "<figcaption class=\"tb-image-caption\"{$eaCap}>{$caption}</figcaption>" : '';
        return "<figure id=\"tb-{$id}\" class=\"tb-image\">{$img}{$cap}</figure>";
    }

    protected function renderButton(array $props, string $inner, string $id): string
    {
        $text  = $this->escape($props['text'] ?? '');
        $url   = $this->escapeUrl($props['url'] ?? '', '#');
        // Phase 2j: was interpolated into a class attribute with no
        // validation/escaping at all — an arbitrary prop value could break
        // out of the attribute. Element SDK's ButtonElement already
        // allowlisted this; canonical did not.
        $rawStyle = $props['style'] ?? 'primary';
        $style = in_array($rawStyle, ['primary', 'secondary'], true) ? $rawStyle : 'primary';
        $ea    = $this->editableAttr('text', 'text');
        return "<a id=\"tb-{$id}\" href=\"{$url}\" class=\"tb-btn tb-btn-{$style}\"{$ea}>{$text}</a>";
    }

    protected function renderDivider(array $props, string $inner, string $id): string
    {
        return "<hr id=\"tb-{$id}\" class=\"tb-divider\">";
    }

    // Phase 2e (ADR-026): renamed from renderNav_menu — Str::studly('nav_menu')
    // produces 'NavMenu', not 'Nav_menu', so the underscore-retaining name
    // was never actually reachable by renderBlock()'s dispatch; every
    // nav_menu block silently fell through to renderFallback() until this
    // fix (caught by the new Builder↔Registry↔Renderer contract test).
    protected function renderNavMenu(array $props, string $inner, string $id): string
    {
        $location = $props['location'] ?? 'header';
        $menuHtml = $this->context['menu_html'] ?? '';
        return "<nav id=\"tb-{$id}\" class=\"tb-nav tb-nav-{$location}\" aria-label=\"منوی اصلی\">{$menuHtml}</nav>";
    }

    // Phase 2e (ADR-026): renamed from renderSocial_icons — same dispatch
    // mismatch as renderNavMenu above.
    /**
     * Phase 2i: absorbed customer-site's richer (previously dead, due to an
     * unreachable `renderSocial_icons` naming bug) implementation — a
     * `context['social_links']` fallback for host-injected link lists, and
     * `target="_blank" rel="noopener noreferrer"` on outbound links.
     */
    protected function renderSocialIcons(array $props, string $inner, string $id): string
    {
        $links = $props['links'] ?? [];
        if (empty($links) && !empty($this->context['social_links'])) {
            $links = $this->context['social_links'];
        }
        $items = [];
        foreach ($links as $link) {
            $url = $this->escapeUrl(is_array($link) ? ($link['url'] ?? '') : (string) $link, '#');
            $label = $this->escape(is_array($link) ? ($link['label'] ?? '') : '');
            $items[] = "<a href=\"{$url}\" class=\"tb-social-link\" target=\"_blank\" rel=\"noopener noreferrer\" aria-label=\"{$label}\">{$label}</a>";
        }
        return "<div id=\"tb-{$id}\" class=\"tb-social-icons\">" . implode('', $items) . "</div>";
    }

    protected function renderCta(array $props, string $inner, string $id): string
    {
        $title    = $this->escape($props['title'] ?? '');
        $subtitle = $this->escape($props['subtitle'] ?? '');
        $btnText  = $this->escape($props['button_text'] ?? '');
        $btnUrl   = $this->escapeUrl($props['button_url'] ?? '', '#');
        $bg       = $props['background'] ?? 'primary';
        $eaTitle  = $this->editableAttr('title', 'text');
        $eaSub    = $this->editableAttr('subtitle', 'text');
        $eaBtn    = $this->editableAttr('button_text', 'text');
        $btn = $btnText ? "<a href=\"{$btnUrl}\" class=\"tb-btn tb-btn-primary\"{$eaBtn}>{$btnText}</a>" : '';
        return "<section id=\"tb-{$id}\" class=\"tb-cta tb-bg-{$bg}\"><h2 class=\"tb-cta-title\"{$eaTitle}>{$title}</h2><p class=\"tb-cta-subtitle\"{$eaSub}>{$subtitle}</p>{$btn}</section>";
    }

    /**
     * BLD-04 (Full Product Commercial Audit, TASK 1/3): `icon` (name, size)
     * was registered with no renderer anywhere — always empty. `name`
     * follows this codebase's own existing convention for icon-shaped
     * fields elsewhere (e.g. `features_grid`/`services_cards` item icons,
     * all authored as a literal emoji character, never an icon-font
     * class name) — rendering it as literal text content is therefore the
     * correct match for how every other icon field in this Builder is
     * already authored, not a shortcut.
     */
    protected function renderIcon(array $props, string $inner, string $id): string
    {
        $name = trim((string) ($props['name'] ?? ''));
        if ($name === '') {
            return '';
        }
        $sizeMap = ['small' => '1.25rem', 'medium' => '2rem', 'large' => '3rem'];
        $rawSize = (string) ($props['size'] ?? 'medium');
        $size = $sizeMap[$rawSize] ?? (preg_match('/^[\d.]+(px|rem|em)$/', $rawSize) ? $rawSize : $sizeMap['medium']);
        $safeName = $this->escape($name);
        $safeSize = $this->escapeAttr($size);
        return "<span id=\"tb-{$id}\" class=\"tb-icon\" style=\"display:inline-block;font-size:{$safeSize};line-height:1;\" role=\"img\" aria-label=\"{$safeName}\">{$safeName}</span>";
    }

    /**
     * BLD-05 (Full Product Commercial Audit, TASK 1/3): `progress_bar`
     * (value, label) was registered with no renderer anywhere — always
     * empty. Real, accessible progress bar: value is clamped to 0-100 and
     * reflected both visually (track width) and via `aria-valuenow` so the
     * control has genuine behavioral effect, not just a saved number.
     */
    protected function renderProgressBar(array $props, string $inner, string $id): string
    {
        $value = max(0, min(100, (int) ($props['value'] ?? 0)));
        $label = $this->escape($props['label'] ?? '');
        $labelHtml = $label !== '' ? "<div class=\"tb-progress-label\" style=\"display:flex;justify-content:space-between;margin-bottom:0.35rem;font-size:0.85rem;\"><span>{$label}</span><span>{$value}%</span></div>" : '';
        return "<div id=\"tb-{$id}\" class=\"tb-progress-bar\">
            {$labelHtml}
            <div class=\"tb-progress-track\" style=\"background:#e5e7eb;border-radius:999px;height:0.6rem;overflow:hidden;\" role=\"progressbar\" aria-valuenow=\"{$value}\" aria-valuemin=\"0\" aria-valuemax=\"100\"" . ($label !== '' ? " aria-label=\"{$label}\"" : '') . ">
                <div class=\"tb-progress-fill\" style=\"background:var(--tb-primary,#4f46e5);height:100%;width:{$value}%;transition:width .3s ease;\"></div>
            </div>
        </div>";
    }

    /**
     * BLD-08 (Full Product Commercial Audit, TASK 1/3): `modal` (trigger_text,
     * blocks) was registered with a real container schema (`is_container`,
     * see BlockRegistry) and no renderer anywhere — always empty. `$inner`
     * (this type's `blocks` rendered through the standard container path,
     * per isStructuralContainer()) becomes the dialog body. Dependency-free
     * vanilla JS open/close, matching this file's existing inline-`<script>`
     * pattern for renderCarousel() rather than introducing a UI library.
     */
    protected function renderModal(array $props, string $inner, string $id): string
    {
        $triggerText = $this->escape($props['trigger_text'] ?? 'باز کردن');
        return "<div id=\"tb-{$id}\" class=\"tb-modal-wrap\">
            <button type=\"button\" class=\"tb-btn tb-btn-primary tb-modal-trigger\" data-modal-trigger=\"tb-{$id}-dialog\" aria-haspopup=\"dialog\">{$triggerText}</button>
            <div id=\"tb-{$id}-dialog\" class=\"tb-modal-overlay\" style=\"display:none;position:fixed;inset:0;background:rgba(15,23,42,.55);z-index:1000;align-items:center;justify-content:center;padding:1rem;\" role=\"dialog\" aria-modal=\"true\">
                <div class=\"tb-modal-panel\" style=\"background:#fff;border-radius:0.75rem;max-width:36rem;width:100%;max-height:85vh;overflow-y:auto;padding:1.5rem;position:relative;\">
                    <button type=\"button\" class=\"tb-modal-close\" aria-label=\"بستن\" style=\"position:absolute;top:0.75rem;left:0.75rem;background:none;border:0;font-size:1.25rem;cursor:pointer;line-height:1;\">×</button>
                    <div class=\"tb-modal-body\">{$inner}</div>
                </div>
            </div>
            <script>(function(){
                var wrap = document.currentScript.closest('.tb-modal-wrap');
                if (!wrap || wrap.dataset.tbModalInit) return;
                wrap.dataset.tbModalInit = '1';
                var dialog = wrap.querySelector('.tb-modal-overlay');
                var openBtn = wrap.querySelector('.tb-modal-trigger');
                var closeBtn = wrap.querySelector('.tb-modal-close');
                function open(){ dialog.style.display = 'flex'; document.body.style.overflow = 'hidden'; }
                function close(){ dialog.style.display = 'none'; document.body.style.overflow = ''; }
                if (openBtn) openBtn.addEventListener('click', open);
                if (closeBtn) closeBtn.addEventListener('click', close);
                dialog.addEventListener('click', function(e){ if (e.target === dialog) close(); });
                document.addEventListener('keydown', function(e){ if (e.key === 'Escape' && dialog.style.display === 'flex') close(); });
            })();</script>
        </div>";
    }

    /**
     * TASK 2A (Full Product Commercial Audit — tabs container authoring
     * fix): `tabs` is now a real structural container (isStructuralContainer()
     * above) — each tab is a real `tab_panel` child block, so $inner already
     * holds every tab's fully-rendered HTML (title + its own children,
     * built by renderTabPanel() below) by the time this method runs, the
     * exact same contract every other container renderer here has.
     *
     * The `props.tabs` branch is a READ-ONLY compatibility fallback for
     * content saved before this fix that a customer has never reopened in
     * the Canvas editor (which migrates it to real tab_panel children on
     * load — canvas-editor.blade.php's normalizeBlock()). It reproduces
     * the exact previous markup for that legacy shape; it is never written
     * by this renderer and is not a second authoring path — once an author
     * touches the block again, the editor saves real children and this
     * branch is never consulted again for that block.
     */
    /**
     * TASK 3/3 Phase 5 (BLD-16): before this fix, `.tb-tabs { }` had no CSS
     * rule and no script anywhere rendered — every tab_panel's own
     * <h3>+content rendered permanently visible, stacked one after another,
     * with no click-to-switch mechanism at all. Real browser certification
     * (not the PHPUnit/render-unit checks that already passed against this
     * exact markup) caught it: "Tabs" never behaved like tabs on any real
     * page. Fixed the same way this file already handles `renderModal()`'s
     * open/close and `renderCarousel()`'s prev/next/dots — a self-
     * initializing inline `<script>`, vanilla JS, one instance per block
     * id, no global hook chain, no framework. A real nav-button row is
     * built here from each panel's own title (still real prop-authored
     * metadata, unchanged); only the FIRST panel is visible until a nav
     * button is clicked, matching real tab semantics. Legacy `props.tabs`
     * fallback (pre-TASK-2A content never reopened in the Canvas editor)
     * gets the same real nav bar and switching, not just the panels.
     */
    protected function renderTabs(array $props, string $inner, string $id): string
    {
        $titles = [];
        if ($inner === '' && !empty($props['tabs']) && is_array($props['tabs'])) {
            $out = [];
            $idx = 0;
            foreach ($props['tabs'] as $tab) {
                $title = $this->escape(is_array($tab) ? ($tab['title'] ?? "تب " . (++$idx)) : "تب " . (++$idx));
                $titles[] = $title;
                $blocks = is_array($tab) ? ($tab['blocks'] ?? []) : [];
                $content = $this->renderBlocks($blocks);
                $out[] = "<div class=\"tb-tab-panel\"><div class=\"tb-tab-content\">{$content}</div></div>";
            }
            $inner = implode('', $out);
        } else {
            foreach (($props['blocks'] ?? []) as $child) {
                $childProps = $child['props'] ?? $child['data'] ?? [];
                $titles[] = $this->escape($childProps['title'] ?? 'تب');
            }
        }

        if ($titles === []) {
            return "<div id=\"tb-{$id}\" class=\"tb-tabs\">{$inner}</div>";
        }

        $navButtons = [];
        foreach ($titles as $i => $title) {
            $selected = $i === 0 ? 'true' : 'false';
            $navButtons[] = "<button type=\"button\" class=\"tb-tab-nav-btn\" role=\"tab\" aria-selected=\"{$selected}\" data-tab-index=\"{$i}\">{$title}</button>";
        }
        $nav = '<div class="tb-tabs-nav" role="tablist">' . implode('', $navButtons) . '</div>';

        return "<div id=\"tb-{$id}\" class=\"tb-tabs\">{$nav}<div class=\"tb-tabs-panels\">{$inner}</div>
            <script>(function(){
                function init(root){
                    if (!root || root.dataset.tbTabsInit) return;
                    root.dataset.tbTabsInit = '1';
                    var buttons = Array.prototype.slice.call(root.querySelectorAll(':scope > .tb-tabs-nav > .tb-tab-nav-btn'));
                    var panels = Array.prototype.slice.call(root.querySelectorAll(':scope > .tb-tabs-panels > .tb-tab-panel'));
                    function activate(i){
                        panels.forEach(function(p, j){ p.style.display = (j === i) ? '' : 'none'; });
                        buttons.forEach(function(b, j){ b.setAttribute('aria-selected', j === i ? 'true' : 'false'); });
                    }
                    buttons.forEach(function(b){
                        b.addEventListener('click', function(){ activate(parseInt(b.dataset.tabIndex, 10) || 0); });
                        b.addEventListener('keydown', function(e){
                            if (e.key !== 'ArrowRight' && e.key !== 'ArrowLeft') return;
                            e.preventDefault();
                            var idx = buttons.indexOf(document.activeElement);
                            var dir = e.key === 'ArrowRight' ? 1 : -1;
                            var next = buttons[((idx + dir) % buttons.length + buttons.length) % buttons.length];
                            next.focus(); next.click();
                        });
                    });
                    activate(0);
                }
                init(document.getElementById('tb-{$id}'));
            })();</script>
        </div>";
    }

    /**
     * One real Canvas child block per tab — `title` is real prop-panel-
     * authored metadata (a plain `string` field, same as any other block's
     * text prop); the tab's actual content is this block's own `children`,
     * rendered into $inner by the exact same generic container mechanism
     * every other container type uses — never a second, JSON-shaped
     * content path. The nav bar/switching behavior lives in the parent
     * `renderTabs()` above (it needs every sibling panel's title at once);
     * this method stays a plain panel body, unaware of its siblings, the
     * same contract every other container child in this class has.
     */
    protected function renderTabPanel(array $props, string $inner, string $id): string
    {
        return "<div id=\"tb-{$id}\" class=\"tb-tab-panel\"><div class=\"tb-tab-content\">{$inner}</div></div>";
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
        $moduleLabels = BlockRegistry::all();
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
        // Phase 2g: was hardcoded #6b7280 (gray-500) regardless of $c['bg'] —
        // failed WCAG AA (3.96:1) against lighter category backgrounds like
        // blog's #dbeafe. $c['text'] is already the per-category color tuned
        // for contrast against $c['bg'] (used for the <strong> title above).
        if (!empty($props['limit'])) $extraInfo .= '<span style="font-size:11px;color:' . $c['text'] . ';margin-left:8px;">تعداد: ' . (int)$props['limit'] . '</span>';
        if (!empty($props['layout'])) $extraInfo .= '<span style="font-size:11px;color:' . $c['text'] . ';margin-left:8px;">چیدمان: ' . $this->escape($props['layout']) . '</span>';
        if (!empty($props['style']))  $extraInfo .= '<span style="font-size:11px;color:' . $c['text'] . ';margin-left:8px;">استایل: ' . $this->escape($props['style']) . '</span>';

        return '<div id="tb-' . $id . '" style="border:2px dashed ' . $c['border'] . ';background:' . $c['bg'] . ';border-radius:10px;padding:20px 16px;text-align:center;direction:rtl;" data-module-block="' . $this->escape($type) . '">'
            . '<div style="margin-bottom:8px;">' . $reqLabel . '<strong style="color:' . $c['text'] . ';font-size:14px;">' . $title . '</strong></div>'
            // Same fix as $extraInfo above: was a flat #9ca3af (gray-400)
            // regardless of $c['bg'], failing WCAG AA far worse (~2.1:1)
            // than the gray-500 case — $name wrapped in its own span so it
            // carries $c['text'] rather than inheriting a non-leaf div color.
            . '<div style="font-size:12px;">' . '<span style="color:' . $c['text'] . ';">' . $this->escape($name) . '</span>' . ($extraInfo ? ' &nbsp;·&nbsp; ' . $extraInfo : '') . '</div>'
            . '</div>';
    }

    // ── NEXUS PRO PREMIUM BLOCKS ─────────────────────────────────────────────

    protected function renderHeroSplit(array $props, string $inner, string $id): string
    {
        $badge   = $this->escape($props['badge'] ?? '');
        $title   = $this->escape($props['title'] ?? '');
        $sub     = $this->escape($props['subtitle'] ?? '');
        $btn1t   = $this->escape($props['button_text'] ?? '');
        $btn1u   = $this->escapeUrl($props['button_url'] ?? '', '#');
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
        $btn1u   = $this->escapeUrl($props['button_url'] ?? '', '#');
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
        // Phase 2k: applied as an INLINE style on every text-bearing child
        // element directly, not just the wrapper — .tb-stat-value/
        // .tb-stat-label carry their own explicit CSS color rules, which
        // win over a color merely inherited from an ancestor's inline
        // style (CSS specificity, not inheritance, decides here). A real
        // browser-measured failure on acool-hvac-service proved the
        // wrapper-only version silently did nothing for these children.
        $onBg = $this->readableTextColorOn($props['background'] ?? '');
        $textColorAttr = $onBg ? " style=\"color:{$onBg};\"" : '';

        $rawStats = $props['stats'] ?? '[]';
        $stats    = is_array($rawStats) ? $rawStats : (json_decode($rawStats, true) ?? []);

        $titleColorStyle = $onBg ? "color:{$onBg};" : '';
        $titleHtml = $title ? "<h2 class=\"tb-features-section-title\" style=\"text-align:center;margin-bottom:1.5rem;{$titleColorStyle}\">{$title}</h2>" : '';

        $items = [];
        foreach ($stats as $i => $s) {
            $val    = $this->escape((string) ($s['value'] ?? '0'));
            $suffix = $this->escape($s['suffix'] ?? '');
            $label  = $this->escape($s['label'] ?? '');
            if ($dividers && $i > 0) {
                $items[] = '<div class="tb-stat-divider" aria-hidden="true"></div>';
            }
            $items[] = "<div class=\"tb-stat-item\">
                <span class=\"tb-stat-value\"{$textColorAttr} data-count=\"{$val}\">{$val}<span class=\"tb-stat-suffix\"{$textColorAttr}>{$suffix}</span></span>
                <span class=\"tb-stat-label\"{$textColorAttr}>{$label}</span>
            </div>";
        }

        return "<div id=\"tb-{$id}\" class=\"tb-stats-row\" style=\"background:{$bg};\">
            <div class=\"tb-container\">{$titleHtml}
                <div class=\"tb-stats-grid\">" . implode('', $items) . "</div>
            </div>
        </div>";
    }

    /**
     * Phase 2f (ADR-027) root-cause fix: this used to `return ''` when no
     * logos were authored — 4 canonical themes have a real title
     * ("مشتریان"/"برندهای ما"/...) but never got a logos array, so the
     * whole section (title included) silently vanished, tripping
     * render.empty_output. Real client/partner names are never invented
     * (see BlockRegistry's emptyOutputPolicy: 'placeholder' contract for
     * this type) — an honest, styled hint is shown instead, exactly
     * mirroring renderTeam()'s established precedent for the same
     * situation.
     */
    protected function renderClientLogos(array $props, string $inner, string $id): string
    {
        $title  = $this->escape($props['title'] ?? 'مشتریان و همکاران');
        $scroll = (bool) ($props['scroll'] ?? true);

        $rawLogos = $props['logos'] ?? '[]';
        $logos    = is_array($rawLogos) ? $rawLogos : (json_decode($rawLogos, true) ?? []);

        if (empty($logos)) {
            return "<div id=\"tb-{$id}\" class=\"tb-client-logos tb-client-logos-empty\">
                <div class=\"tb-container\">
                    <p class=\"tb-client-logos-title\">{$title}</p>
                    <p class=\"tb-text\" style=\"text-align:center;color:var(--tb-text-muted,#4b5563);\">لوگوی مشتریان/همکاران پس از افزودن در تنظیمات المان در این‌جا نمایش داده می‌شود.</p>
                </div>
            </div>";
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
            $rawFeat   = $p['features'] ?? [];
            $features  = is_array($rawFeat) ? $rawFeat : (json_decode((string) $rawFeat, true) ?? []);
            $hlClass   = $highlight ? ' highlighted' : '';
            // Canvas repeater UI now saves each feature as {text: "..."};
            // a bare string remains supported for already-saved legacy data.
            $features  = array_map(fn($f) => is_array($f) ? ($f['text'] ?? '') : $f, $features);

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
                    " . $this->visuallyHiddenLabel("tb-{$id}-newsletter-email", $props['placeholder'] ?? 'ایمیل یا شماره موبایل') . "
                    <input id=\"tb-{$id}-newsletter-email\" type=\"text\" class=\"tb-newsletter-input\" placeholder=\"{$placeholder}\" autocomplete=\"off\">
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

        // Stable, block-scoped field ids ("tb-{$id}-...") — $id is already
        // unique per block instance (see renderBlock()), so two
        // renderContactSplit() blocks on the same page can never collide.
        $nameFieldId = "tb-{$id}-name";
        $contactFieldId = "tb-{$id}-contact";
        $messageFieldId = "tb-{$id}-message";

        $formHtml = $showForm ? "<div class=\"tb-contact-form-wrap\">
            <div class=\"tb-contact-form-simple\">
                <form onsubmit=\"event.preventDefault();\" novalidate>
                    <div style=\"margin-bottom:1rem;\">"
                        . $this->visuallyHiddenLabel($nameFieldId, 'نام شما')
                        . "<input id=\"{$nameFieldId}\" type=\"text\" placeholder=\"نام شما\" style=\"width:100%;padding:0.65rem 1rem;border:1px solid #e5e7eb;border-radius:0.5rem;font-family:inherit;font-size:0.95rem;\"></div>
                    <div style=\"margin-bottom:1rem;\">"
                        . $this->visuallyHiddenLabel($contactFieldId, 'شماره موبایل یا ایمیل')
                        . "<input id=\"{$contactFieldId}\" type=\"text\" placeholder=\"شماره موبایل یا ایمیل\" style=\"width:100%;padding:0.65rem 1rem;border:1px solid #e5e7eb;border-radius:0.5rem;font-family:inherit;font-size:0.95rem;\"></div>
                    <div style=\"margin-bottom:1rem;\">"
                        . $this->visuallyHiddenLabel($messageFieldId, 'پیام شما')
                        . "<textarea id=\"{$messageFieldId}\" placeholder=\"پیام شما\" rows=\"4\" style=\"width:100%;padding:0.65rem 1rem;border:1px solid #e5e7eb;border-radius:0.5rem;font-family:inherit;font-size:0.95rem;resize:vertical;\"></textarea></div>
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

    // Ported from customer-site's app/Services/ThemeBuilder/BlockRenderer.php
    // (already-proven working implementations) so admin preview/screenshots
    // stop dumping raw JSON for these block types.
    protected function renderGallery(array $props, string $inner, string $id): string
    {
        $title = $this->escape($props['title'] ?? '');
        $items = $props['items'] ?? [];
        $h = $title ? "<h2 class=\"tb-heading tb-section-title\">{$title}</h2>" : '';
        $cells = [];
        foreach ($items as $item) {
            $url = $this->escapeUrl(is_array($item) ? ($item['url'] ?? '') : (string) $item, '');
            $alt = $this->escape(is_array($item) ? ($item['alt'] ?? '') : '');
            if ($url === '') {
                continue;
            }
            $cells[] = "<div class=\"tb-gallery-cell\"><img src=\"{$url}\" alt=\"{$alt}\" loading=\"lazy\" class=\"tb-gallery-img\"></div>";
        }
        if ($cells === []) {
            return "<div id=\"tb-{$id}\" class=\"tb-gallery tb-gallery-empty\"><p class=\"tb-text\">تصاویر گالری را در تنظیمات المان اضافه کنید.</p></div>";
        }

        return "<div id=\"tb-{$id}\" class=\"tb-gallery\">{$h}<div class=\"tb-gallery-grid\">" . implode('', $cells) . '</div></div>';
    }

    /**
     * Phase 2f (ADR-027): a promo banner with no text is deliberately
     * different from client_logos above — it has no other field an author
     * could have set to signal "I want a banner here"; an empty one is the
     * ordinary, expected way to leave this optional accent unused this
     * page. This is the registry's `emptyOutputPolicy: 'optional'`
     * contract for this type — RenderIntegrityCheck reports it as an
     * explicit, non-accidental empty state, never a defect, and no
     * placeholder is invented for an element the author may simply not
     * want visible at all.
     */
    protected function renderPromoBanner(array $props, string $inner, string $id): string
    {
        $text = $this->escape($props['text'] ?? '');
        $link = $this->escapeAttr($props['link'] ?? '#');
        $variant = $this->escapeAttr($props['style'] ?? 'info');
        if ($text === '') {
            return '';
        }

        return "<div id=\"tb-{$id}\" class=\"tb-promo-banner tb-promo-{$variant}\"><a href=\"{$link}\" class=\"tb-promo-banner__link\">{$text}</a></div>";
    }

    // No 'team' data source exists anywhere in the platform today (unlike
    // team_cards, which carries its own members JSON in props) — only a
    // title/limit. Rendering fabricated member names would misrepresent the
    // template, so this renders an honest, styled placeholder (reusing the
    // same section-header classes every other premium block uses) instead of
    // a raw JSON dump.
    protected function renderTeam(array $props, string $inner, string $id): string
    {
        $title = $this->escape($props['title'] ?? 'تیم ما');
        $limit = max(0, (int) ($props['limit'] ?? 6));

        return "<div id=\"tb-{$id}\" class=\"tb-section\">
            <div class=\"tb-container\">
                <div class=\"tb-features-section-header\"><h2 class=\"tb-features-section-title\">{$title}</h2></div>
                <p class=\"tb-text\" style=\"text-align:center;color:var(--tb-text-muted,#4b5563);\">اعضای تیم ({$limit} نفر) پس از افزودن در تنظیمات نمایش داده می‌شوند.</p>
            </div>
        </div>";
    }

    // ── END NEXUS PRO PREMIUM BLOCKS ─────────────────────────────────────────

    // ── PHASE 2E — CANONICAL BLOCK RENDERING COVERAGE ────────────────────────
    // Closes the render.unsupported_block_type backlog for real, legitimate
    // block types actually authored by the canonical commercial catalog
    // (see ADR-026). Every one of these has real data behind it in the
    // catalog — no fabricated defaults are invented for missing content;
    // an absent optional field simply omits that piece of markup.

    /**
     * The site header/navigation bar used by 24 of the 28 canonical themes.
     * Accepts `nav_links` (canonical) or `links` (defensive alias — some
     * historical data used this key; never rejected, never fabricated).
     * Optional fields (phone, cta, search/cart/account toggles) render only
     * when actually supplied — a `show_search` toggle with no other data
     * needed still renders a real, functional (if data-less) search field,
     * since search UI chrome is not "content" that could be fabricated.
     */
    protected function renderNavHeader(array $props, string $inner, string $id): string
    {
        $logoText = $this->escape($props['logo_text'] ?? '');
        $logoUrl  = $this->escapeAttr($props['logo_url'] ?? '/');
        $bg       = $this->escapeAttr($props['bg_color'] ?? '#ffffff');
        $tc       = $this->escapeAttr($props['text_color'] ?? '#111827');
        $sticky   = (bool) ($props['sticky'] ?? false);

        $rawLinks = $props['nav_links'] ?? $props['links'] ?? [];
        $links    = is_array($rawLinks) ? $rawLinks : [];

        $phone       = $this->escape($props['phone'] ?? '');
        $ctaText     = $this->escape($props['cta_text'] ?? '');
        $ctaUrl      = $this->escapeAttr($props['cta_url'] ?? '#');
        $showSearch  = (bool) ($props['show_search'] ?? false);
        $searchPh    = $props['search_placeholder'] ?? 'جستجو...';
        $showCart    = (bool) ($props['show_cart'] ?? false);
        $showAccount = (bool) ($props['show_account'] ?? false);

        $linkItems = '';
        foreach ($links as $link) {
            $url   = $this->escapeUrl(is_array($link) ? ($link['url'] ?? '') : '', '#');
            $label = $this->escape(is_array($link) ? ($link['label'] ?? '') : (string) $link);
            if ($label === '') {
                continue;
            }
            $linkItems .= "<a href=\"{$url}\" class=\"tb-nav-header-link\">{$label}</a>";
        }

        $searchHtml = '';
        if ($showSearch) {
            $searchFieldId = "tb-{$id}-search";
            $searchHtml = "<form class=\"tb-nav-header-search\" role=\"search\" action=\"#\" onsubmit=\"event.preventDefault();\">"
                . $this->visuallyHiddenLabel($searchFieldId, $searchPh)
                . "<input id=\"{$searchFieldId}\" type=\"search\" placeholder=\"" . $this->escapeAttr($searchPh) . "\" class=\"tb-nav-header-search-input\">"
                . "</form>";
        }

        $phoneHtml   = $phone ? "<a href=\"tel:" . $this->escapeAttr(preg_replace('/[^+0-9]/', '', $props['phone'])) . "\" class=\"tb-nav-header-phone\">{$phone}</a>" : '';
        $cartHtml    = $showCart ? "<a href=\"/cart\" class=\"tb-nav-header-icon-link\" aria-label=\"سبد خرید\">🛒</a>" : '';
        $accountHtml = $showAccount ? "<a href=\"/account\" class=\"tb-nav-header-icon-link\" aria-label=\"حساب کاربری\">👤</a>" : '';
        $ctaHtml     = $ctaText ? "<a href=\"{$ctaUrl}\" class=\"tb-btn tb-btn-primary tb-nav-header-cta\">{$ctaText}</a>" : '';
        $logoHtml    = $logoText ? "<a href=\"{$logoUrl}\" class=\"tb-nav-header-logo\">{$logoText}</a>" : '';

        $stickyStyle = $sticky ? 'position:sticky;top:0;z-index:100;' : '';

        return "<header id=\"tb-{$id}\" class=\"tb-nav-header\" style=\"background:{$bg};color:{$tc};{$stickyStyle}\">
            <div class=\"tb-container tb-nav-header-inner\">
                {$logoHtml}
                <nav class=\"tb-nav-header-links\" aria-label=\"منوی اصلی\">{$linkItems}</nav>
                <div class=\"tb-nav-header-actions\">{$searchHtml}{$phoneHtml}{$cartHtml}{$accountHtml}{$ctaHtml}</div>
            </div>
        </header>";
    }

    /**
     * The site footer used by 23 of the 28 canonical themes. `columns`
     * carries the real link groups every theme authors; `social_links` (a
     * real array of {url, icon, label}) is rendered via the existing
     * renderSocialIcons() — reused, not duplicated. `show_socials` alone,
     * with no social_links array behind it, is a real, honest state some
     * themes are currently in (no social data was ever authored for them)
     * and renders nothing for that section rather than fabricating profile
     * links that do not exist.
     */
    protected function renderNavFooter(array $props, string $inner, string $id): string
    {
        $bg        = $this->escapeAttr($props['bg_color'] ?? '#111827');
        $tc        = $this->escapeAttr($props['text_color'] ?? '#e5e7eb');
        $copyright = $this->escape($props['copyright'] ?? '');
        $tagline   = $this->escape($props['tagline'] ?? '');
        $logoText  = $this->escape($props['logo_text'] ?? '');

        $rawColumns = $props['columns'] ?? [];
        $columns    = is_array($rawColumns) ? $rawColumns : [];
        $rawLegal   = $props['links'] ?? [];
        $legalLinks = is_array($rawLegal) ? $rawLegal : [];
        $socialLinks = is_array($props['social_links'] ?? null) ? $props['social_links'] : [];

        $colHtml = '';
        foreach ($columns as $col) {
            $ctitle = $this->escape(is_array($col) ? ($col['title'] ?? '') : '');
            $clinks = is_array($col) ? ($col['links'] ?? []) : [];
            $clinks = is_array($clinks) ? $clinks : [];

            $linkItems = '';
            foreach ($clinks as $link) {
                $url   = $this->escapeUrl(is_array($link) ? ($link['url'] ?? '') : '', '#');
                $label = $this->escape(is_array($link) ? ($link['label'] ?? '') : (string) $link);
                if ($label === '') {
                    continue;
                }
                $linkItems .= "<li><a href=\"{$url}\">{$label}</a></li>";
            }
            if ($linkItems === '' && $ctitle === '') {
                continue;
            }
            $colHtml .= "<div class=\"tb-nav-footer-col\">"
                . ($ctitle ? "<h3 class=\"tb-nav-footer-col-title\">{$ctitle}</h3>" : '')
                . "<ul class=\"tb-nav-footer-links\">{$linkItems}</ul></div>";
        }

        $socialHtml = $socialLinks !== [] ? $this->renderSocialIcons(['links' => $socialLinks], '', "{$id}-social") : '';

        $legalHtml = '';
        foreach ($legalLinks as $link) {
            $url   = $this->escapeUrl(is_array($link) ? ($link['url'] ?? '') : '', '#');
            $label = $this->escape(is_array($link) ? ($link['label'] ?? '') : (string) $link);
            if ($label === '') {
                continue;
            }
            $legalHtml .= "<a href=\"{$url}\" class=\"tb-nav-footer-legal-link\">{$label}</a>";
        }

        $brandHtml = ($logoText || $tagline)
            ? "<div class=\"tb-nav-footer-brand\">"
                . ($logoText ? "<p class=\"tb-nav-footer-logo\">{$logoText}</p>" : '')
                . ($tagline ? "<p class=\"tb-nav-footer-tagline\">{$tagline}</p>" : '')
                . "</div>"
            : '';

        return "<footer id=\"tb-{$id}\" class=\"tb-nav-footer\" style=\"background:{$bg};color:{$tc};\">
            <div class=\"tb-container\">
                {$brandHtml}
                " . ($colHtml ? "<div class=\"tb-nav-footer-cols\">{$colHtml}</div>" : '') . "
                <div class=\"tb-nav-footer-bottom\">
                    " . ($copyright ? "<p class=\"tb-nav-footer-copyright\">{$copyright}</p>" : '') . "
                    {$legalHtml}
                    {$socialHtml}
                </div>
            </div>
        </footer>";
    }

    /**
     * A hero variant with a trust signal (rating) and a direct-contact
     * (phone) call to action alongside the standard dual-button pattern —
     * genuinely distinct from hero/hero_split/hero_gradient's schemas, not
     * a rename of any of them. Reuses the already-styled
     * .tb-hero-split-badge/.tb-hero-split-title/.tb-hero-split-sub/
     * .tb-hero-split-actions classes rather than inventing parallel ones.
     */
    protected function renderHeroTrust(array $props, string $inner, string $id): string
    {
        $badge    = $this->escape($props['badge'] ?? '');
        $title    = $this->escape($props['title'] ?? '');
        $subtitle = $this->escape($props['subtitle'] ?? '');
        $rating      = $props['rating'] ?? null;
        $ratingLabel = $this->escape($props['rating_label'] ?? '');
        $phone    = $this->escape($props['phone'] ?? '');
        $btn1Text = $this->escape($props['btn_primary_text'] ?? '');
        $btn1Url  = $this->escapeAttr($props['btn_primary_url'] ?? '#');
        $btn2Text = $this->escape($props['btn_secondary_text'] ?? '');
        $btn2Url  = $this->escapeAttr($props['btn_secondary_url'] ?? '#');

        $badgeHtml = $badge ? "<span class=\"tb-hero-split-badge\">{$badge}</span>" : '';
        $btn1Html  = $btn1Text ? "<a href=\"{$btn1Url}\" class=\"tb-btn tb-btn-primary\">{$btn1Text}</a>" : '';
        $btn2Html  = $btn2Text ? "<a href=\"{$btn2Url}\" class=\"tb-btn tb-btn-secondary\">{$btn2Text}</a>" : '';

        $ratingHtml = '';
        if ($rating !== null && $rating !== '') {
            $full  = (int) round(max(0, min(5, (float) $rating)));
            $stars = str_repeat('★', $full) . str_repeat('☆', 5 - $full);
            $ratingText = $this->escape((string) $rating) . '/5' . ($ratingLabel ? ' · ' . $ratingLabel : '');
            $ratingHtml = "<div class=\"tb-hero-trust-rating\"><span aria-hidden=\"true\">{$stars}</span><span>{$ratingText}</span></div>";
        }
        $phoneHtml = $phone ? "<a href=\"tel:" . $this->escapeAttr(preg_replace('/[^+0-9]/', '', $props['phone'])) . "\" class=\"tb-hero-trust-phone\">{$phone}</a>" : '';
        $metaHtml  = ($ratingHtml || $phoneHtml) ? "<div class=\"tb-hero-trust-meta\">{$ratingHtml}{$phoneHtml}</div>" : '';

        return "<section id=\"tb-{$id}\" class=\"tb-hero-split-single tb-section\">
            <div class=\"tb-container\">
                <div class=\"tb-hero-trust-inner\">
                    {$badgeHtml}
                    <h1 class=\"tb-hero-split-title\">{$title}</h1>
                    <p class=\"tb-hero-split-sub\">{$subtitle}</p>
                    <div class=\"tb-hero-split-actions\">{$btn1Html}{$btn2Html}</div>
                    {$metaHtml}
                </div>
            </div>
        </section>";
    }

    /**
     * A composite "about us" section (badge + title + description + bullet
     * features + optional mini stats + CTA) — richer than stats_row alone
     * and structurally distinct from it, so it is not merged into that
     * type. Reuses the already-styled .tb-stats-grid/.tb-stat-item classes
     * for the optional stats strip.
     */
    protected function renderAboutSection(array $props, string $inner, string $id): string
    {
        $badge    = $this->escape($props['badge'] ?? '');
        $title    = $this->escape($props['title'] ?? '');
        $description = $this->sanitizeHtml($props['description'] ?? '');
        $btnText  = $this->escape($props['btn_text'] ?? '');
        $btnUrl   = $this->escapeAttr($props['btn_url'] ?? '#');
        $features = is_array($props['features'] ?? null) ? $props['features'] : [];
        $stats    = is_array($props['stats'] ?? null) ? $props['stats'] : [];

        $badgeHtml = $badge ? "<span class=\"tb-hero-split-badge\">{$badge}</span>" : '';

        $featuresHtml = '';
        foreach ($features as $f) {
            $label = $this->escape(is_array($f) ? ($f['label'] ?? '') : (string) $f);
            if ($label === '') {
                continue;
            }
            $featuresHtml .= "<li class=\"tb-about-feature\"><span aria-hidden=\"true\">✓</span> {$label}</li>";
        }

        $statsHtml = '';
        foreach ($stats as $s) {
            $val   = $this->escape((string) (is_array($s) ? ($s['value'] ?? '') : ''));
            $label = $this->escape(is_array($s) ? ($s['label'] ?? '') : '');
            if ($val === '' && $label === '') {
                continue;
            }
            $statsHtml .= "<div class=\"tb-stat-item\"><span class=\"tb-stat-value\">{$val}</span><span class=\"tb-stat-label\">{$label}</span></div>";
        }

        $btnHtml = $btnText ? "<a href=\"{$btnUrl}\" class=\"tb-btn tb-btn-primary\">{$btnText}</a>" : '';

        return "<section id=\"tb-{$id}\" class=\"tb-section\">
            <div class=\"tb-container tb-about-section\">
                {$badgeHtml}
                " . ($title ? "<h2 class=\"tb-features-section-title\">{$title}</h2>" : '') . "
                " . ($description ? "<div class=\"tb-text prose\">{$description}</div>" : '') . "
                " . ($featuresHtml ? "<ul class=\"tb-about-features\">{$featuresHtml}</ul>" : '') . "
                {$btnHtml}
                " . ($statsHtml ? "<div class=\"tb-stats-grid tb-about-stats\">{$statsHtml}</div>" : '') . "
            </div>
        </section>";
    }

    /**
     * A card grid for services (icon + title + description + optional
     * link), structurally distinct from the existing `services_cards`
     * (which authors its list as a single JSON-string textarea field with
     * different field names — text/link/link_text vs description/url
     * here). Deliberately kept as its own type rather than forced into
     * services_cards, per the "never merge semantically different blocks"
     * rule — see ADR-026. Reuses the already-styled .tb-service-card
     * family of classes.
     */
    protected function renderServiceCards(array $props, string $inner, string $id): string
    {
        $title    = $this->escape($props['title'] ?? '');
        $subtitle = $this->escape($props['subtitle'] ?? '');
        $columns  = max(2, min(4, (int) ($props['columns'] ?? 3)));
        $rawCards = $props['cards'] ?? [];
        $cards    = is_array($rawCards) ? $rawCards : [];

        $headerHtml = ($title || $subtitle)
            ? "<div class=\"tb-features-section-header\">" . ($title ? "<h2 class=\"tb-features-section-title\">{$title}</h2>" : '') . ($subtitle ? "<p class=\"tb-features-section-sub\">{$subtitle}</p>" : '') . "</div>"
            : '';

        $items = '';
        foreach ($cards as $card) {
            $icon   = $this->escape(is_array($card) ? ($card['icon'] ?? '') : '');
            $ctitle = $this->escape(is_array($card) ? ($card['title'] ?? '') : '');
            $desc   = $this->escape(is_array($card) ? ($card['description'] ?? '') : '');
            $url    = $this->escapeUrl(is_array($card) ? ($card['url'] ?? '') : '', '');
            if ($ctitle === '' && $desc === '') {
                continue;
            }
            $body = ($icon ? "<div class=\"tb-service-icon\">{$icon}</div>" : '')
                . "<h3 class=\"tb-service-title\">{$ctitle}</h3><p class=\"tb-service-text\">{$desc}</p>";
            $items .= $url !== ''
                ? "<a href=\"{$url}\" class=\"tb-service-card\">{$body}</a>"
                : "<div class=\"tb-service-card\">{$body}</div>";
        }

        return "<div id=\"tb-{$id}\" class=\"tb-section\">
            <div class=\"tb-container\">{$headerHtml}
                <div class=\"tb-services-grid tb-grid tb-grid-cols-{$columns} tb-gap-md\">{$items}</div>
            </div>
        </div>";
    }

    /**
     * A card grid for portfolio/case-study entries (category + title +
     * description + link) — a distinct content shape from service_cards
     * (no icon, has a category badge) and from every other registered
     * card-grid type.
     */
    protected function renderProjectCards(array $props, string $inner, string $id): string
    {
        $title    = $this->escape($props['title'] ?? '');
        $subtitle = $this->escape($props['subtitle'] ?? '');
        $columns  = max(2, min(4, (int) ($props['columns'] ?? 2)));
        $rawCards = $props['cards'] ?? [];
        $cards    = is_array($rawCards) ? $rawCards : [];

        $headerHtml = ($title || $subtitle)
            ? "<div class=\"tb-features-section-header\">" . ($title ? "<h2 class=\"tb-features-section-title\">{$title}</h2>" : '') . ($subtitle ? "<p class=\"tb-features-section-sub\">{$subtitle}</p>" : '') . "</div>"
            : '';

        $items = '';
        foreach ($cards as $card) {
            $cat    = $this->escape(is_array($card) ? ($card['category'] ?? '') : '');
            $ctitle = $this->escape(is_array($card) ? ($card['title'] ?? '') : '');
            $desc   = $this->escape(is_array($card) ? ($card['description'] ?? '') : '');
            $url    = $this->escapeUrl(is_array($card) ? ($card['url'] ?? '') : '', '');
            if ($ctitle === '' && $desc === '') {
                continue;
            }
            $body = ($cat ? "<span class=\"tb-project-card-category\">{$cat}</span>" : '')
                . "<h3 class=\"tb-project-card-title\">{$ctitle}</h3><p class=\"tb-project-card-text\">{$desc}</p>";
            $items .= $url !== ''
                ? "<a href=\"{$url}\" class=\"tb-project-card\">{$body}</a>"
                : "<div class=\"tb-project-card\">{$body}</div>";
        }

        return "<div id=\"tb-{$id}\" class=\"tb-section\">
            <div class=\"tb-container\">{$headerHtml}
                <div class=\"tb-project-cards-grid tb-grid tb-grid-cols-{$columns} tb-gap-md\">{$items}</div>
            </div>
        </div>";
    }

    /**
     * A simple contact-details display (icon + label + value per item) —
     * distinct from contact_split, which is a two-column form block.
     * Reuses the already-styled .tb-contact-info-item/-icon/-label/-value
     * classes contact_split's own info column already established.
     */
    protected function renderContactInfo(array $props, string $inner, string $id): string
    {
        $title    = $this->escape($props['title'] ?? '');
        $subtitle = $this->escape($props['subtitle'] ?? '');
        $bg       = $this->escapeAttr($props['background'] ?? '');
        $rawItems = $props['items'] ?? [];
        $items    = is_array($rawItems) ? $rawItems : [];

        $headerHtml = ($title || $subtitle)
            ? "<div class=\"tb-features-section-header\">" . ($title ? "<h2 class=\"tb-features-section-title\">{$title}</h2>" : '') . ($subtitle ? "<p class=\"tb-features-section-sub\">{$subtitle}</p>" : '') . "</div>"
            : '';

        $itemsHtml = '';
        foreach ($items as $item) {
            $icon  = $this->escape(is_array($item) ? ($item['icon'] ?? '') : '');
            $label = $this->escape(is_array($item) ? ($item['label'] ?? '') : '');
            $value = $this->escape(is_array($item) ? ($item['value'] ?? '') : '');
            if ($label === '' && $value === '') {
                continue;
            }
            $itemsHtml .= "<div class=\"tb-contact-info-item\">"
                . ($icon ? "<span class=\"tb-contact-info-icon\">{$icon}</span>" : '')
                . "<div>" . ($label ? "<p class=\"tb-contact-info-label\">{$label}</p>" : '') . "<p class=\"tb-contact-info-value\">{$value}</p></div></div>";
        }

        $style = $bg !== '' ? " style=\"background:{$bg};\"" : '';

        return "<div id=\"tb-{$id}\" class=\"tb-section\"{$style}>
            <div class=\"tb-container\">{$headerHtml}
                <div class=\"tb-contact-info-grid\">{$itemsHtml}</div>
            </div>
        </div>";
    }

    /**
     * Phase 2e follow-up: the Builder↔Registry↔Renderer contract test
     * (CanonicalBlockRegistryContractTest) proved that "registered in
     * BlockRegistry" and "actually renderable" were not the same guarantee
     * — these 7 types are genuinely authored by the canonical catalog and
     * had no renderer at all (not merely a naming mismatch, unlike
     * nav_menu/social_icons above). Closing them here, honestly: none
     * invents data it doesn't have.
     */

    /**
     * Always authored with empty props — real page-hierarchy context isn't
     * available to a standalone Theme Builder render (this class has no
     * concept of "current page"), so this never fabricates a fake trail. A
     * caller that has real hierarchy can inject it via
     * setContext(['breadcrumb_html' => ...]), mirroring nav_menu's
     * menu_html pattern; without it, only the one link that's always
     * honestly true (the home page) is rendered.
     */
    protected function renderBreadcrumb(array $props, string $inner, string $id): string
    {
        $injected = $this->context['breadcrumb_html'] ?? null;
        if ($injected !== null) {
            return "<nav id=\"tb-{$id}\" class=\"tb-breadcrumb\" aria-label=\"مسیر صفحه\">{$injected}</nav>";
        }
        return "<nav id=\"tb-{$id}\" class=\"tb-breadcrumb\" aria-label=\"مسیر صفحه\"><a href=\"/\">خانه</a></nav>";
    }

    /**
     * A real, statically-authored FAQ list (question/answer pairs) — a
     * simpler, top-level counterpart to the generic `accordion` container
     * (which holds arbitrary nested blocks, not just Q/A text). Uses the
     * native <details>/<summary> disclosure widget for a real accessible
     * expand/collapse with zero JavaScript, matching renderAccordion()'s
     * own approach.
     */
    protected function renderFaq(array $props, string $inner, string $id): string
    {
        $title = $this->escape($props['title'] ?? '');
        $rawItems = $props['items'] ?? [];
        $items = is_array($rawItems) ? $rawItems : (json_decode($rawItems, true) ?? []);

        $itemsHtml = '';
        foreach ($items as $item) {
            $q = $this->escape(is_array($item) ? ($item['question'] ?? '') : '');
            $a = $this->escape(is_array($item) ? ($item['answer'] ?? '') : '');
            if ($q === '') {
                continue;
            }
            $itemsHtml .= "<details class=\"tb-faq-item\"><summary class=\"tb-faq-question\">{$q}</summary><div class=\"tb-faq-answer\">{$a}</div></details>";
        }

        return "<div id=\"tb-{$id}\" class=\"tb-section\">
            <div class=\"tb-container\">
                " . ($title ? "<h2 class=\"tb-features-section-title\">{$title}</h2>" : '') . "
                <div class=\"tb-faq-list\">{$itemsHtml}</div>
            </div>
        </div>";
    }

    protected function renderSearchBar(array $props, string $inner, string $id): string
    {
        $placeholder = $props['placeholder'] ?? 'جستجو...';
        $fieldId = "tb-{$id}-search";
        return "<form id=\"tb-{$id}\" class=\"tb-search-bar-wrap\" role=\"search\" action=\"#\" onsubmit=\"event.preventDefault();\">"
            . $this->visuallyHiddenLabel($fieldId, $placeholder)
            . "<input id=\"{$fieldId}\" type=\"search\" placeholder=\"" . $this->escapeAttr($placeholder) . "\">"
            . "</form>";
    }

    /**
     * Same real-world purpose as newsletter_form (collect an email/phone
     * for a mailing list) authored under a different type name by a later
     * theme generation — delegates rather than duplicating the markup.
     */
    protected function renderEmailCapture(array $props, string $inner, string $id): string
    {
        return $this->renderNewsletterForm($props, $inner, $id);
    }

    /**
     * A single labeled statistic (value + suffix + label) — the
     * one-at-a-time counterpart to stats_row's own internal item markup,
     * meant to be placed individually inside a grid/columns layout by the
     * author rather than supplied as one aggregate list.
     */
    protected function renderCounter(array $props, string $inner, string $id): string
    {
        $value  = $this->escape((string) ($props['value'] ?? ''));
        $suffix = $this->escape($props['suffix'] ?? '');
        $label  = $this->escape($props['label'] ?? '');

        return "<div id=\"tb-{$id}\" class=\"tb-stat-item\">
            <span class=\"tb-stat-value\" data-count=\"{$value}\">{$value}<span class=\"tb-stat-suffix\">{$suffix}</span></span>
            <span class=\"tb-stat-label\">{$label}</span>
        </div>";
    }

    protected function renderLoginRegister(array $props, string $inner, string $id): string
    {
        $loginText    = $this->escape($props['login_text'] ?? 'ورود');
        $registerText = $this->escape($props['register_text'] ?? 'ثبت‌نام');
        $loginUrl     = $this->escapeAttr($props['login_url'] ?? '/login');
        $registerUrl  = $this->escapeAttr($props['register_url'] ?? '/register');

        return "<div id=\"tb-{$id}\" class=\"tb-login-register\">
            <a href=\"{$loginUrl}\" class=\"tb-nav-header-icon-link\">{$loginText}</a>
            <a href=\"{$registerUrl}\" class=\"tb-btn tb-btn-secondary\">{$registerText}</a>
        </div>";
    }

    /**
     * Authored only with `limit`/`title` (a query, not static content) —
     * real post data is sourced by the Blog module at actual render time
     * on customer-site (see module:blog_posts), which a standalone Theme
     * Builder render has no access to. Renders an honest placeholder
     * exactly like renderTeam() does for the same reason, never fabricated
     * post titles/excerpts.
     */
    protected function renderBlogPosts(array $props, string $inner, string $id): string
    {
        $title = $this->escape($props['title'] ?? 'وبلاگ');
        $limit = max(0, (int) ($props['limit'] ?? 3));

        return "<div id=\"tb-{$id}\" class=\"tb-section\">
            <div class=\"tb-container\">
                <div class=\"tb-features-section-header\"><h2 class=\"tb-features-section-title\">{$title}</h2></div>
                <p class=\"tb-text\" style=\"text-align:center;color:var(--tb-text-muted,#4b5563);\">{$limit} نوشته اخیر پس از انتشار محتوا در این‌جا نمایش داده می‌شود.</p>
            </div>
        </div>";
    }

    /**
     * Same query-not-content shape and honest-placeholder reasoning as
     * blog_posts above — real testimonials are customer-submitted content
     * this Theme has never captured (unlike testimonials_slider, which
     * carries its own static `items` prop).
     */
    protected function renderTestimonials(array $props, string $inner, string $id): string
    {
        $title = $this->escape($props['title'] ?? 'نظرات مشتریان');
        $limit = max(0, (int) ($props['limit'] ?? 3));

        return "<div id=\"tb-{$id}\" class=\"tb-section\">
            <div class=\"tb-container\">
                <div class=\"tb-features-section-header\"><h2 class=\"tb-features-section-title\">{$title}</h2></div>
                <p class=\"tb-text\" style=\"text-align:center;color:var(--tb-text-muted,#4b5563);\">{$limit} نظر مشتری پس از ثبت نظرات در این‌جا نمایش داده می‌شود.</p>
            </div>
        </div>";
    }

    /**
     * Authored with only a `product_id` (often empty — a template
     * placeholder for a page that renders per-product at runtime, see
     * malltina-international's `page:product`). No fabricated price/name
     * is invented; a real product_id resolved via setContext() would be
     * the correct future extension point (mirroring nav_menu's pattern),
     * not implemented here since no such context exists anywhere yet.
     */
    protected function renderPriceBox(array $props, string $inner, string $id): string
    {
        $productId = $props['product_id'] ?? '';
        $injected  = $this->context['price_box_html'] ?? null;
        if ($injected !== null) {
            return "<div id=\"tb-{$id}\" class=\"tb-price-box\">{$injected}</div>";
        }
        if ($productId === '' || $productId === null) {
            return "<div id=\"tb-{$id}\" class=\"tb-price-box tb-price-box-empty\"><p class=\"tb-text\">قیمت پس از انتخاب محصول نمایش داده می‌شود.</p></div>";
        }
        return "<div id=\"tb-{$id}\" class=\"tb-price-box tb-price-box-empty\"><p class=\"tb-text\">قیمت محصول " . $this->escape((string) $productId) . " پس از اتصال به فروشگاه نمایش داده می‌شود.</p></div>";
    }

    /**
     * Placeholder re-evaluation, `countdown` (Full Product Commercial
     * Audit, TASK 2A): previously rendered a static hint span and NO
     * JavaScript anywhere ever read its `data-end` attribute — the "hint"
     * text showed forever, even once a real `end_date` was authored, and
     * no actual countdown ever ran. No fabricated end date is invented
     * here either (a real countdown still needs a real target date the
     * author sets) — but once one IS set, this now runs an honest, real,
     * dependency-free ticking countdown, matching this file's existing
     * inline-`<script>` convention (renderCarousel()/renderModal()).
     */
    protected function renderCountdown(array $props, string $inner, string $id): string
    {
        $label = $this->escape($props['label'] ?? '');
        $end = trim((string) ($props['end_date'] ?? ''));
        $labelHtml = $label !== '' ? "<span class=\"tb-countdown__label\">{$label}</span>" : '';

        if ($end === '') {
            return "<div id=\"tb-{$id}\" class=\"tb-countdown\">{$labelHtml}<span class=\"tb-countdown__hint\">تاریخ پایان را در تنظیمات المان وارد کنید تا شمارش معکوس فعال شود.</span></div>";
        }

        $safeEnd = $this->escapeAttr($end);
        return "<div id=\"tb-{$id}\" class=\"tb-countdown\" data-end-date=\"{$safeEnd}\">
            {$labelHtml}
            <div class=\"tb-countdown__timer\" style=\"display:flex;gap:0.75rem;justify-content:center;margin-top:0.5rem;\">
                <div class=\"tb-countdown__unit\"><span class=\"tb-countdown__d\" style=\"font-size:1.5rem;font-weight:700;\">--</span><small style=\"display:block;\">روز</small></div>
                <div class=\"tb-countdown__unit\"><span class=\"tb-countdown__h\" style=\"font-size:1.5rem;font-weight:700;\">--</span><small style=\"display:block;\">ساعت</small></div>
                <div class=\"tb-countdown__unit\"><span class=\"tb-countdown__m\" style=\"font-size:1.5rem;font-weight:700;\">--</span><small style=\"display:block;\">دقیقه</small></div>
                <div class=\"tb-countdown__unit\"><span class=\"tb-countdown__s\" style=\"font-size:1.5rem;font-weight:700;\">--</span><small style=\"display:block;\">ثانیه</small></div>
            </div>
            <p class=\"tb-countdown__expired\" style=\"display:none;\">این پیشنهاد به پایان رسیده است.</p>
            <script>(function(){
                var root = document.currentScript.closest('.tb-countdown');
                if (!root || root.dataset.tbCountdownInit) return;
                root.dataset.tbCountdownInit = '1';
                var end = new Date(root.dataset.endDate).getTime();
                if (isNaN(end)) return;
                var timerEl = root.querySelector('.tb-countdown__timer');
                var expiredEl = root.querySelector('.tb-countdown__expired');
                var dEl = root.querySelector('.tb-countdown__d'), hEl = root.querySelector('.tb-countdown__h');
                var mEl = root.querySelector('.tb-countdown__m'), sEl = root.querySelector('.tb-countdown__s');
                function pad(n){ return String(n).padStart(2, '0'); }
                function tick(){
                    var diff = end - Date.now();
                    if (diff <= 0) {
                        if (timerEl) timerEl.style.display = 'none';
                        if (expiredEl) expiredEl.style.display = 'block';
                        clearInterval(interval);
                        return;
                    }
                    var d = Math.floor(diff / 86400000);
                    var h = Math.floor((diff % 86400000) / 3600000);
                    var m = Math.floor((diff % 3600000) / 60000);
                    var s = Math.floor((diff % 60000) / 1000);
                    if (dEl) dEl.textContent = pad(d);
                    if (hEl) hEl.textContent = pad(h);
                    if (mEl) mEl.textContent = pad(m);
                    if (sEl) sEl.textContent = pad(s);
                }
                tick();
                var interval = setInterval(tick, 1000);
            })();</script>
        </div>";
    }

    // ── END PHASE 2E CANONICAL BLOCKS ────────────────────────────────────────

    protected function renderFallback(string $type, array $props, string $inner, string $id): string
    {
        $content = $inner ?: $this->escape(json_encode($props, JSON_UNESCAPED_UNICODE));
        return "<div id=\"tb-{$id}\" class=\"tb-block tb-block-{$type}\" data-type=\"{$type}\">{$content}</div>";
    }

    protected function spacingClass(string $v): string
    {
        // Phase 2i: 'xs' was only in customer-site's copy — the shared CSS
        // contract's .tb-padding-xs rule already exists (Phase 2h), so this
        // was a real, silent gap rather than an intentional difference.
        $map = ['none' => 'none', 'xs' => 'xs', 'sm' => 'sm', 'md' => 'md', 'lg' => 'lg', 'xl' => 'xl'];
        return $map[$v] ?? 'md';
    }

    /**
     * The single reusable accessible-labeling helper for every hand-authored
     * form field this renderer produces (renderContactSplit(),
     * renderNewsletterForm(), and any future block that needs one) — fixed
     * here once, at the render boundary, so every Theme using these blocks
     * inherits the fix automatically instead of needing per-Theme data
     * patches (Phase 2b root-cause fix for accessibility.form_field_missing_label).
     *
     * Renders a real <label for="..."> associated with the field's id — not
     * a fake/aria-only trick — visually hidden via inline CSS (not a class,
     * so it works regardless of what stylesheet the host page loads) so the
     * placeholder-driven visual design is completely unchanged. This is the
     * standard, W3C-documented "visually hidden but screen-reader visible"
     * pattern, not a hack to satisfy a checker: a screen reader announces
     * the field's real purpose exactly as a sighted user infers it from the
     * placeholder.
     */
    protected function visuallyHiddenLabel(string $forId, string $text): string
    {
        $forId = $this->escapeAttr($forId);
        $text = $this->escape($text);
        return "<label for=\"{$forId}\" style=\"position:absolute;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap;border:0;\">{$text}</label>";
    }

    /**
     * Phase 2g: a block that accepts an arbitrary author-chosen background
     * color (e.g. stats_row) but never sets a matching text color relies
     * entirely on inherited body text color — invisible-on-dark-background
     * text the moment an author picks a dark background, since nothing in
     * this codebase defines per-component color rules for these classes.
     * WCAG relative-luminance threshold (same formula quality-check.mjs
     * uses browser-side) picks white text on a dark background, or leaves
     * the existing inherited default alone on a light one.
     */
    /**
     * Phase 2k: was a brightness-threshold heuristic (luminance < 0.35 =>
     * white, else null/"leave it alone") — real browser-measured WCAG AA
     * failures on acool-hvac-service proved this insufficient: it never
     * covered the *label* text (a separate, lighter CSS-class-driven
     * color), and "null" for a mid-luminance background just left whatever
     * color was already there, unverified. Delegates to
     * ThemeTokenNormalizer's real WCAG contrast-ratio math (shared with the
     * CSS token derivation, not a second algorithm) and ALWAYS returns a
     * real answer for a parseable background — never a silent "do nothing".
     */
    protected function readableTextColorOn(string $hexBg): ?string
    {
        if (!preg_match('/^#?[0-9a-f]{6}$/i', trim($hexBg))) {
            return null;
        }
        return (new \SaaSPlatform\ThemeRuntime\ThemeTokenNormalizer())->pickReadableOn($hexBg);
    }

    /**
     * Phase 2j: accepts `mixed`, not `string` — a real crash found by
     * ThemeRuntimeSecurityTest: a malformed authored `_style` value (an
     * array where a color string is expected) previously threw a TypeError
     * instead of degrading gracefully. Corrupted authored data must never
     * 500 a real tenant's page.
     */
    protected function escape(mixed $s): string
    {
        return htmlspecialchars($this->stringify($s), ENT_QUOTES, 'UTF-8');
    }

    protected function escapeAttr(mixed $s): string
    {
        return htmlspecialchars($this->stringify($s), ENT_QUOTES, 'UTF-8');
    }

    private function stringify(mixed $s): string
    {
        if (is_string($s)) {
            return $s;
        }
        if (is_scalar($s) || $s === null) {
            return (string) $s;
        }
        return ''; // array/object/resource: never guess a string representation
    }

    /**
     * Phase 3E-C: delegates to the single shared sanitization authority
     * (SaaSPlatform\ThemeRuntime\Security\HtmlSanitizer) rather than
     * maintaining its own copy of this logic — kept as a thin protected
     * wrapper so every existing internal call site (`$this->isValidUrl()`)
     * and BlockRenderer's public protected API contract are unchanged.
     */
    protected function isValidUrl(string $url): bool
    {
        return \SaaSPlatform\ThemeRuntime\Security\HtmlSanitizer::isValidUrl($url);
    }

    /**
     * Phase 3E-C: delegates to HtmlSanitizer::escapeUrl() — see isValidUrl().
     */
    protected function escapeUrl(string $url, string $fallback = '#'): string
    {
        return \SaaSPlatform\ThemeRuntime\Security\HtmlSanitizer::escapeUrl($url, $fallback);
    }

    /**
     * Phase 3E-C: delegates to HtmlSanitizer::sanitizeRichHtml() — the
     * Phase 3C attribute/URL-scheme hardening now lives in exactly one
     * place, reachable by both this renderer and the Element SDK (which
     * cannot inherit from this class), instead of being duplicated.
     */
    protected function sanitizeHtml(string $s): string
    {
        return \SaaSPlatform\ThemeRuntime\Security\HtmlSanitizer::sanitizeRichHtml($s);
    }
}
