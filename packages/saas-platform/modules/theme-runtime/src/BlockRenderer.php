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
            // Part 5 (7-part local audit task, 2026-09-19) root-cause fix:
            // a genuinely legacy/corrupted theme (real repro: Theme ID=56,
            // an old pre-refactor test theme) can contain a block entry
            // that is not an array at all (e.g. a bare string) — calling
            // renderBlock() on it threw an uncaught TypeError
            // ("Argument #1 ($block) must be of type array, string given"),
            // taking down the ENTIRE preview/canvas page for one bad
            // element among otherwise-valid content. Skip only that one
            // entry, visibly (an HTML comment, never a silent drop), so
            // the rest of the theme still renders.
            if (!is_array($block)) {
                $html[] = '<!-- theme block skipped: expected an object, got ' . gettype($block) . ' -->';
                continue;
            }
            $html[] = $this->renderBlock($block);
        }
        return implode("\n", $html);
    }

    public function renderBlock(array $block): string
    {
        $type = $block['type'] ?? 'text';
        $id = $block['id'] ?? Str::random(8);
        $props = $block['props'] ?? $block['data'] ?? [];
        if (!is_array($props)) {
            // Same class of defect as above, one level down: a legacy
            // block whose props is a scalar (e.g. Theme ID=56's
            // header_blocks) crashed every render*($props, ...) method
            // with a TypeError instead of just that block's own markup
            // failing to render.
            $props = [];
        }
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
    // ── Carousel runtime (canonical, shared) ─────────────────────────────
    //
    // Universal Multipurpose Slider Overhaul: this used to exist ONLY as a
    // customer-site-only override (App\Services\ThemeCanvas\
    // ThemeCanvasRenderer::renderCarousel()), so saas-platform's own admin
    // preview never showed real slider behavior for ANY slider-shaped type
    // — a genuine preview/runtime parity gap. This is now the ONE
    // canonical carousel engine for every slider-shaped canonical type in
    // this class (renderSlider, renderTestimonialsSlider, renderClientLogos'
    // carousel mode) and for any host override that wants real carousel
    // behavior without reimplementing it (customer-site's real
    // renderProductSlider()/renderCategorySlider()/renderBlogPosts()
    // override call $this->renderCarousel(...) — no second implementation
    // belongs anywhere).
    //
    // Direction-aware (reads the resolved `direction` CSS property at
    // render time rather than hardcoding RTL, even though this platform is
    // Persian/RTL by default), keyboard-navigable (ArrowLeft/ArrowRight —
    // bound to the same physical side as the prev/next buttons, which are
    // laid out RTL-first: prev on the visual right, next on the visual
    // left, matching this platform's only real deployment direction),
    // touch/swipe-enabled, pausable on hover/focus, and supports a
    // responsive "items visible at once" count (desktop/tablet/mobile) via
    // a CSS custom property so a single mechanism covers both a classic
    // one-slide-at-a-time slider and an N-columns-visible carousel (e.g.
    // testimonials_slider's `columns`, previously honored only by the
    // static-grid render path and silently dropped by the old carousel
    // override — see renderTestimonialsSlider() below).
    //
    // Known simplification: one dot is rendered per underlying slide, not
    // per distinct scroll "page" when itemsPerView > 1 — clicking a dot
    // past the last valid stopping position simply clamps to it. This is a
    // cosmetic simplification, not a functional defect.

    /**
     * @param string[] $slidesHtml Pre-rendered inner HTML for each individual slide/item — already
     *   filtered/validated by the caller (e.g. a deleted product silently excluded upstream).
     * @param array{desktop?:int,tablet?:int,mobile?:int} $itemsPerView How many slides are visible
     *   at once per breakpoint. Omit (or leave at 1/1/1) for a classic single-item slider.
     */
    protected function renderCarousel(
        array $slidesHtml,
        string $id,
        bool $autoplay,
        int $interval,
        string $extraClass = '',
        array $itemsPerView = [],
        bool $pauseOnHover = true,
        bool $fade = false
    ): string {
        $slidesHtml = array_values(array_filter($slidesHtml, fn ($s) => trim((string) $s) !== ''));
        if (empty($slidesHtml)) {
            return '';
        }

        $desktop = max(1, (int) ($itemsPerView['desktop'] ?? 1));
        $tablet  = max(1, min($desktop, (int) ($itemsPerView['tablet'] ?? $desktop)));
        $mobile  = max(1, min($tablet, (int) ($itemsPerView['mobile'] ?? 1)));
        // Fade mode only makes sense one-slide-at-a-time — a multi-item
        // crossfade has no well-defined "current page" to fade between.
        if ($fade) {
            $desktop = $tablet = $mobile = 1;
        }

        if (count($slidesHtml) === 1 && $desktop <= 1) {
            return "<div id=\"tb-{$id}\" class=\"tb-carousel-single {$extraClass}\">{$slidesHtml[0]}</div>";
        }

        $interval = max(1500, $interval);
        $slideCount = count($slidesHtml);
        $slides = [];
        $dots = [];
        // CSS Grid stacking (every slide in the same cell) rather than
        // position:absolute — the grid container auto-sizes to the
        // tallest slide, so fade mode never collapses to zero height the
        // way stacked absolutely-positioned children would without extra
        // JS-measured height.
        $fadeSlideStyle = $fade ? 'grid-column:1;grid-row:1;opacity:0;transition:opacity .6s ease;' : '';
        foreach ($slidesHtml as $i => $html) {
            $num = $i + 1;
            $slides[] = "<div class=\"tb-carousel-slide\" role=\"group\" aria-roledescription=\"slide\" aria-label=\"اسلاید {$num} از {$slideCount}\" style=\"{$fadeSlideStyle}\">{$html}</div>";
            $dots[] = "<button type=\"button\" class=\"tb-carousel-dot\" aria-label=\"رفتن به اسلاید {$num}\" style=\"width:.55rem;height:.55rem;padding:0;border-radius:999px;border:0;background:#d1d5db;cursor:pointer;\"></button>";
        }

        $autoplayAttr = $autoplay ? 'true' : 'false';
        $pauseAttr = $pauseOnHover ? 'true' : 'false';
        $fadeAttr = $fade ? 'true' : 'false';
        $trackStyle = $fade
            ? 'display:grid;'
            : 'display:flex;transition:transform .45s ease;';

        return "<div id=\"tb-{$id}\" class=\"tb-carousel {$extraClass}\" role=\"region\" aria-roledescription=\"carousel\" tabindex=\"0\"
            data-autoplay=\"{$autoplayAttr}\" data-interval=\"{$interval}\" data-pause-hover=\"{$pauseAttr}\" data-fade=\"{$fadeAttr}\"
            style=\"position:relative;outline:none;--tb-items-desktop:{$desktop};--tb-items-tablet:{$tablet};--tb-items-mobile:{$mobile};\">
            <style>
                /* Real bug found via a full-page Playwright screenshot pass
                   (tablet/mobile columns silently stayed at the desktop
                   count): --tb-items must NOT be set inline on the root —
                   an inline style declaration always wins over every rule
                   in this <style> block regardless of media query, so the
                   two overrides below could never take effect. All three
                   assignments now live here, at equal (ID-selector)
                   specificity, so source order + the media queries
                   actually matching decides the final value. */
                #tb-{$id}{--tb-items:var(--tb-items-desktop);}
                #tb-{$id} .tb-carousel-slide{flex:0 0 calc(100% / var(--tb-items,1));min-width:0;}
                @media (max-width:1024px){ #tb-{$id}{--tb-items:var(--tb-items-tablet);} }
                @media (max-width:640px){ #tb-{$id}{--tb-items:var(--tb-items-mobile);} }
            </style>
            <div class=\"tb-carousel-viewport\" style=\"overflow:hidden;\">
                <div class=\"tb-carousel-track\" style=\"{$trackStyle}\">" . implode('', $slides) . "</div>
            </div>
            <button type=\"button\" class=\"tb-carousel-prev\" aria-label=\"اسلاید قبلی\" style=\"position:absolute;top:50%;right:0.25rem;transform:translateY(-50%);width:2.25rem;height:2.25rem;border-radius:999px;border:1px solid #e5e7eb;background:#fff;cursor:pointer;font-size:1.1rem;line-height:1;z-index:2;\">‹</button>
            <button type=\"button\" class=\"tb-carousel-next\" aria-label=\"اسلاید بعدی\" style=\"position:absolute;top:50%;left:0.25rem;transform:translateY(-50%);width:2.25rem;height:2.25rem;border-radius:999px;border:1px solid #e5e7eb;background:#fff;cursor:pointer;font-size:1.1rem;line-height:1;z-index:2;\">›</button>
            <div class=\"tb-carousel-dots\" role=\"tablist\" aria-label=\"انتخاب اسلاید\" style=\"display:flex;justify-content:center;gap:0.4rem;margin-top:0.85rem;\">" . implode('', $dots) . "</div>
            <script>(function(){
                function init(root){
                    if (!root || root.dataset.tbCarouselInit) return;
                    root.dataset.tbCarouselInit = '1';
                    var isFade = root.dataset.fade === 'true';
                    var track = root.querySelector('.tb-carousel-track');
                    var slideEls = Array.prototype.slice.call(root.querySelectorAll('.tb-carousel-slide'));
                    var dots = Array.prototype.slice.call(root.querySelectorAll('.tb-carousel-dot'));
                    var prevBtn = root.querySelector('.tb-carousel-prev');
                    var nextBtn = root.querySelector('.tb-carousel-next');
                    var idx = 0, timer = null;
                    var isRTL = getComputedStyle(root).direction !== 'ltr';
                    var sign = isRTL ? 1 : -1;
                    function itemsPerView(){
                        if (isFade) return 1;
                        var v = parseFloat(getComputedStyle(root).getPropertyValue('--tb-items'));
                        return (v && v > 0) ? v : 1;
                    }
                    function maxIndex(){ return Math.max(0, slideEls.length - itemsPerView()); }
                    function paint(){
                        if (isFade) {
                            slideEls.forEach(function(s, j){ s.style.opacity = (j === idx) ? '1' : '0'; s.style.zIndex = (j === idx) ? '1' : '0'; });
                        } else {
                            var ipv = itemsPerView();
                            track.style.transform = 'translateX(' + (sign * idx * (100 / ipv)) + '%)';
                        }
                        dots.forEach(function(d, j){
                            var active = j === idx;
                            d.style.background = active ? 'var(--tb-primary,#4f46e5)' : '#d1d5db';
                            d.setAttribute('aria-current', active ? 'true' : 'false');
                        });
                    }
                    function go(i){ idx = Math.min(Math.max(0, i), maxIndex()); paint(); }
                    function stepNext(){ go(idx + 1 > maxIndex() ? 0 : idx + 1); }
                    function stepPrev(){ go(idx - 1 < 0 ? maxIndex() : idx - 1); }
                    function stop(){ if (timer) { clearInterval(timer); timer = null; } }
                    function start(){
                        stop();
                        if (root.dataset.autoplay === 'true' && slideEls.length > itemsPerView()) {
                            timer = setInterval(stepNext, parseInt(root.dataset.interval, 10) || 5000);
                        }
                    }
                    if (nextBtn) nextBtn.addEventListener('click', function(){ stepNext(); start(); });
                    if (prevBtn) prevBtn.addEventListener('click', function(){ stepPrev(); start(); });
                    dots.forEach(function(d, j){ d.addEventListener('click', function(){ go(j); start(); }); });
                    root.addEventListener('keydown', function(e){
                        if (e.key === 'ArrowRight') { stepPrev(); start(); e.preventDefault(); }
                        else if (e.key === 'ArrowLeft') { stepNext(); start(); e.preventDefault(); }
                    });
                    if (root.dataset.pauseHover === 'true') {
                        root.addEventListener('mouseenter', stop);
                        root.addEventListener('mouseleave', start);
                        root.addEventListener('focusin', stop);
                        root.addEventListener('focusout', start);
                    }
                    var touchX = null;
                    root.addEventListener('touchstart', function(e){ touchX = e.touches[0].clientX; }, {passive:true});
                    root.addEventListener('touchend', function(e){
                        if (touchX === null) return;
                        var dx = e.changedTouches[0].clientX - touchX;
                        if (Math.abs(dx) > 40) { (dx > 0 ? stepPrev : stepNext)(); start(); }
                        touchX = null;
                    }, {passive:true});
                    window.addEventListener('resize', function(){ go(Math.min(idx, maxIndex())); });
                    paint();
                    start();
                }
                init(document.getElementById('tb-{$id}'));
            })();</script>
        </div>";
    }

    /**
     * A safe, real fallback for a missing or genuinely broken image URL —
     * never a black block, never a raw broken-image browser icon. Empty
     * `$url` renders the fallback immediately (server-side); a non-empty
     * URL that fails to actually load at runtime is caught client-side via
     * `onerror` (there is no safe, fast way to verify an image URL
     * resolves from inside a server-side render call).
     */
    protected function imageWithFallback(string $url, string $alt, string $style = '', string $icon = '🖼️'): string
    {
        $altAttr = $this->escapeAttr($alt);
        if ($url === '') {
            return "<div class=\"tb-img-fallback\" style=\"display:flex;align-items:center;justify-content:center;background:linear-gradient(135deg,#eef2ff,#f5f3ff);color:#a5b4fc;font-size:2rem;{$style}\" role=\"img\" aria-label=\"{$altAttr}\">{$icon}</div>";
        }
        $urlAttr = $this->escapeAttr($url);
        return "<div class=\"tb-img-frame\" style=\"position:relative;overflow:hidden;{$style}\">"
            . "<img src=\"{$urlAttr}\" alt=\"{$altAttr}\" loading=\"lazy\" style=\"width:100%;height:100%;object-fit:cover;display:block;\" onerror=\"this.style.display='none';this.nextElementSibling.style.display='flex';\">"
            . "<div class=\"tb-img-fallback\" style=\"display:none;position:absolute;inset:0;align-items:center;justify-content:center;background:linear-gradient(135deg,#eef2ff,#f5f3ff);color:#a5b4fc;font-size:2rem;\" role=\"img\" aria-label=\"{$altAttr}\">{$icon}</div>"
            . "</div>";
    }

    /**
     * Commercial Productization fase-4: the one shared Badge primitive —
     * previously every "badge"-shaped bit of UI (pricing's "محبوب‌ترین",
     * product cards' "جدید"/"حراج") was its own independently hardcoded
     * inline-style span (e.g. a fixed `#ef4444` red regardless of what the
     * badge actually meant), with no token binding, no size scale, and no
     * shared contrast guarantee. Every semantic color routes through
     * ThemeTokenNormalizer's real WCAG-AA `--tb-{variant}-text`/
     * `--tb-on-{variant}` derivation, the same authority every other
     * token-bound color in this file already uses — never a second,
     * independently-drifting color decision.
     */
    protected function badgeHtml(string $label, string $variant = 'neutral', string $size = 'md', string $icon = ''): string
    {
        $variant = in_array($variant, ['success', 'warning', 'error', 'info', 'neutral', 'primary'], true) ? $variant : 'neutral';
        $size    = in_array($size, ['sm', 'md', 'lg'], true) ? $size : 'md';
        $labelEsc = $this->escape($label);
        if ($labelEsc === '') {
            return '';
        }
        $iconHtml = $icon !== '' ? "<span class=\"tb-badge-icon\" aria-hidden=\"true\">" . $this->escape($icon) . "</span>" : '';

        return "<span class=\"tb-badge tb-badge-{$variant} tb-badge-{$size}\">{$iconHtml}<span class=\"tb-badge-label\">{$labelEsc}</span></span>";
    }

    protected function renderSlider(array $props, string $inner, string $id): string
    {
        $slides = $props['slides'] ?? [];
        if (!is_array($slides)) {
            $slides = [];
        }

        $transitionRaw = $props['transition'] ?? 'slide';
        $transition = in_array($transitionRaw, ['slide', 'fade'], true) ? $transitionRaw : 'slide';
        $boxed      = (bool) ($props['boxed'] ?? false);
        $aspect     = $this->escapeAttr($props['aspect_ratio'] ?? '16/9');
        $overlay    = (bool) ($props['overlay'] ?? true);
        $gradientCss = $overlay ? 'background:linear-gradient(0deg,rgba(0,0,0,.55),rgba(0,0,0,0) 55%);' : '';

        $slidesHtml = [];
        foreach ($slides as $slide) {
            if (!is_array($slide)) {
                continue;
            }
            $image    = trim((string) ($slide['image'] ?? ''));
            $title    = $this->escape($slide['title'] ?? '');
            $subtitle = $this->escape($slide['subtitle'] ?? '');
            $link     = $this->escapeUrl($slide['link'] ?? '', '');
            $ctaText  = $this->escape($slide['cta_text'] ?? '');

            $imgHtml = $this->imageWithFallback($image, (string) ($slide['title'] ?? ''), "position:absolute;inset:0;width:100%;height:100%;");

            $ctaHtml = ($ctaText !== '' && $link !== '')
                ? "<a href=\"{$link}\" class=\"tb-btn tb-btn-primary tb-slide-cta\" style=\"position:relative;z-index:1;margin-top:1rem;display:inline-block;\">{$ctaText}</a>"
                : '';

            $captionHtml = ($title !== '' || $subtitle !== '' || $ctaHtml !== '')
                ? '<div class="tb-slide-caption" style="position:relative;z-index:1;padding:2.5rem 1.5rem;color:#fff;text-align:center;text-shadow:0 1px 4px rgba(0,0,0,.55);">'
                    . ($title !== '' ? "<h2 class=\"tb-slide-title\" style=\"margin:0 0 .5rem;font-size:1.75rem;font-weight:700;\">{$title}</h2>" : '')
                    . ($subtitle !== '' ? "<p class=\"tb-slide-subtitle\" style=\"margin:0;font-size:1.05rem;opacity:.95;\">{$subtitle}</p>" : '')
                    . $ctaHtml
                    . '</div>'
                : '';

            $slideInner = "<div class=\"tb-slide\" style=\"position:relative;aspect-ratio:{$aspect};display:flex;align-items:center;justify-content:center;background:#111827;overflow:hidden;\">"
                . $imgHtml . "<div style=\"position:absolute;inset:0;{$gradientCss}\"></div>" . $captionHtml . '</div>';

            $slidesHtml[] = ($link !== '' && $ctaHtml === '')
                ? '<a href="' . $link . '" class="tb-slide-link" style="display:block;color:inherit;text-decoration:none;">' . $slideInner . '</a>'
                : $slideInner;
        }

        if (empty($slidesHtml)) {
            return '';
        }

        $wrapClass = 'tb-slider' . ($boxed ? ' tb-slider-boxed' : ' tb-slider-full') . ($transition === 'fade' ? ' tb-slider-fade' : '');
        $wrapStyle = $boxed ? 'max-width:72rem;margin:0 auto;' : '';

        $carousel = $this->renderCarousel(
            $slidesHtml,
            $id,
            (bool) ($props['autoplay'] ?? true),
            (int) ($props['interval'] ?? 5000),
            $wrapClass,
            [],
            true,
            $transition === 'fade'
        );

        return $wrapStyle !== '' ? "<div style=\"{$wrapStyle}\">{$carousel}</div>" : $carousel;
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
        // Commercial Productization fase-3 (visual identity audit): a real,
        // confirmed bug — these defaults were hardcoded generic indigo/
        // violet hex values, completely independent of the Theme's own
        // configured --tb-primary/--tb-secondary. Every Theme whose hero
        // used this block's default colors (i.e. never overrode
        // gradient_from/gradient_to) rendered the same generic purple
        // regardless of its actual brand palette — an explicit author
        // override of course still wins.
        $gFrom   = $this->escapeAttr($props['gradient_from'] ?? 'var(--tb-primary, #4f46e5)');
        $gTo     = $this->escapeAttr($props['gradient_to'] ?? 'var(--tb-secondary, #7c3aed)');
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
    /**
     * Logo/Partner Slider. Backward-compatible: existing content authored
     * with only {name} entries and the legacy `scroll` boolean keeps
     * rendering exactly as before (plain text badges in a CSS marquee) —
     * `image`/`url`/`alt`/`mode`/`grayscale`/`speed`/`visible_count` are
     * all new, additive, optional fields.
     */
    protected function renderClientLogos(array $props, string $inner, string $id): string
    {
        $title  = $this->escape($props['title'] ?? 'مشتریان و همکاران');
        $scroll = (bool) ($props['scroll'] ?? true);
        // 'marquee' (legacy default) | 'carousel' (real slider, arrows/dots/autoplay) | 'static' (grid)
        $mode = $props['mode'] ?? ($scroll ? 'marquee' : 'static');
        if (!in_array($mode, ['marquee', 'carousel', 'static'], true)) {
            $mode = 'marquee';
        }
        $grayscale = (bool) ($props['grayscale'] ?? true);
        $speed = max(10, (int) ($props['speed'] ?? 25)); // marquee: seconds per full loop
        $visibleDesktop = max(2, (int) ($props['visible_count'] ?? 5));

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
        $filterCss = $grayscale ? 'filter:grayscale(1);opacity:.75;transition:filter .2s,opacity .2s;' : '';
        $filterHoverAttr = $grayscale ? ' onmouseover="this.style.filter=\'none\';this.style.opacity=1;" onmouseout="this.style.filter=\'grayscale(1)\';this.style.opacity=.75;"' : '';

        $items = [];
        foreach ($logos as $l) {
            if (!is_array($l)) {
                $l = ['name' => (string) $l];
            }
            $name  = (string) ($l['name'] ?? '');
            $image = trim((string) ($l['image'] ?? ''));
            $url   = $this->escapeUrl($l['url'] ?? '', '');
            $alt   = $this->escapeAttr($l['alt'] ?? $name);

            // A genuinely broken logo URL (unreachable host, deleted media)
            // swaps itself for the same text-badge fallback an empty
            // `image` already renders — never a raw broken-image icon.
            // Real-runtime bug found via a full-page Playwright screenshot
            // pass (Universal Multipurpose Slider Overhaul): every other
            // slider's image already had this via imageWithFallback(), but
            // this inline, non-frame logo `<img>` did not. Same safe
            // hide-self/reveal-sibling technique as imageWithFallback(),
            // never string-building HTML inside an onerror attribute.
            $content = $image !== ''
                ? "<span style=\"display:inline-flex;align-items:center;\">"
                    . "<img src=\"" . $this->escapeAttr($image) . "\" alt=\"{$alt}\" loading=\"lazy\" style=\"max-height:2.5rem;max-width:9rem;width:auto;height:auto;object-fit:contain;{$filterCss}\"{$filterHoverAttr} onerror=\"this.style.display='none';this.nextElementSibling.style.display='inline-flex';\">"
                    . "<span class=\"tb-logo-item\" style=\"display:none;\">" . $this->escape($name) . "</span>"
                    . "</span>"
                : "<span class=\"tb-logo-item\">" . $this->escape($name) . "</span>";

            $items[] = $url !== ''
                ? "<a href=\"{$url}\" target=\"_blank\" rel=\"noopener\" style=\"display:inline-flex;align-items:center;\" aria-label=\"{$alt}\">{$content}</a>"
                : "<span style=\"display:inline-flex;align-items:center;\">{$content}</span>";
        }

        if ($mode === 'carousel') {
            $inner = $this->renderCarousel(
                $items,
                $id . '-track',
                true,
                max(1500, $speed * 200),
                'tb-logos-carousel',
                ['desktop' => $visibleDesktop, 'tablet' => max(2, (int) round($visibleDesktop / 2)), 'mobile' => 2],
                true
            );
        } elseif ($mode === 'static') {
            // Reuses the real shared .tb-logos-static rule (theme-runtime-contract.css)
            // loaded by both hosts — no inline layout duplication needed.
            $inner = "<div class=\"tb-logos-static\">" . implode('', $items) . "</div>";
        } else {
            // Reuses the real shared .tb-logos-marquee-wrap/.tb-logos-marquee
            // rules and the tb-marquee keyframe (theme-runtime-contract.css,
            // already loaded by both hosts) — only animation-duration is
            // overridden per-instance for the configurable `speed` prop.
            $logoHtml = implode('', $items);
            $doubled = $logoHtml . $logoHtml;
            $inner   = "<div class=\"tb-logos-marquee-wrap\"><div class=\"tb-logos-marquee\" style=\"animation-duration:{$speed}s;\">{$doubled}</div></div>";
        }

        return "<div id=\"tb-{$id}\" class=\"tb-client-logos\">
            <div class=\"tb-container\">{$titleHtml}{$inner}</div>
        </div>";
    }

    /**
     * Shared product-card markup — used by BOTH this canonical demo-fixture
     * renderer and any host's real-data override (customer-site's
     * ThemeCanvasRenderer::renderProductSlider() calls this too), so the
     * admin preview and the real customer-site render are pixel-identical
     * in card structure and only ever differ in which data feeds them —
     * genuine preview/runtime parity at the markup level, not just "both
     * happen to use a carousel".
     */
    protected function productCardHtml(
        string $title,
        string $image,
        ?float $price,
        ?float $originalPrice,
        ?string $badge,
        ?string $stockLabel,
        string $url,
        string $priceFormatted,
        ?string $originalPriceFormatted = null,
        string $addToCartHtml = ''
    ): string {
        $titleEsc = $this->escape($title);
        $urlAttr  = $this->escapeUrl($url, '#');
        $imgHtml  = $this->imageWithFallback($image, $title, 'aspect-ratio:1/1;', '📦');

        $badgeHtml = $badge ? "<span class=\"tb-product-badge-wrap\" style=\"position:absolute;top:.5rem;inset-inline-start:.5rem;z-index:1;\">" . $this->badgeHtml($badge, 'error', 'sm') . "</span>" : '';

        $discountHtml = ($originalPrice !== null && $price !== null && $originalPrice > $price)
            ? "<span class=\"tb-product-price-old\" style=\"text-decoration:line-through;color:#9ca3af;font-size:.85rem;margin-inline-end:.4rem;\">{$originalPriceFormatted}</span>"
            : '';

        $stockHtml = $stockLabel ? "<div class=\"tb-product-stock\" style=\"font-size:.8rem;color:#6b7280;margin-top:.15rem;\">" . $this->escape($stockLabel) . "</div>" : '';

        return "<div class=\"tb-product-card\" style=\"position:relative;max-width:20rem;margin:0 auto;\">
            {$badgeHtml}
            <a href=\"{$urlAttr}\" class=\"tb-product-card-img\" style=\"display:block;\">{$imgHtml}</a>
            <div class=\"tb-product-card-inner\" style=\"padding-top:.65rem;\">
                <a href=\"{$urlAttr}\" style=\"color:inherit;text-decoration:none;\"><h3 class=\"tb-product-title\">{$titleEsc}</h3></a>
                <div class=\"tb-product-price\">{$discountHtml}{$priceFormatted}</div>
                {$stockHtml}
                {$addToCartHtml}
            </div>
        </div>";
    }

    /**
     * Canonical (shared) Product Slider. This package has no database
     * access by design — real product data is always a host concern
     * (customer-site's ThemeCanvasRenderer::renderProductSlider()
     * overrides this with a real Product query). Rendering a small,
     * realistic Persian demo fixture here — rather than an empty div —
     * closes a real, confirmed defect: the shared package previously had
     * NO renderer for this type at all, so saas-platform's own admin
     * preview showed nothing when this block was selected, while
     * customer-site's real render worked — a genuine preview/runtime
     * parity gap. `source`/`category_id`/`manual_ids` are accepted here
     * only for schema/serialization compatibility with the host's real
     * renderer; this canonical version intentionally ignores them and
     * always shows the demo fixture, since it has nothing real to filter.
     */
    protected function renderProductSlider(array $props, string $inner, string $id): string
    {
        $title = $this->escape($props['title'] ?? 'محصولات منتخب');

        $demo = [
            ['title' => 'کیف چرم دست‌دوز', 'price' => 1250000, 'original' => 1590000, 'badge' => 'تخفیف', 'stock' => 'موجود'],
            ['title' => 'ساعت هوشمند مدل ایکس', 'price' => 3450000, 'original' => null, 'badge' => 'جدید', 'stock' => 'موجود'],
            ['title' => 'هدفون بی‌سیم پرومکس', 'price' => 890000, 'original' => 1100000, 'badge' => null, 'stock' => 'تنها ۳ عدد باقی مانده'],
        ];

        $cards = [];
        foreach ($demo as $p) {
            $cards[] = $this->productCardHtml(
                $p['title'],
                '',
                (float) $p['price'],
                $p['original'] !== null ? (float) $p['original'] : null,
                $p['badge'],
                $p['stock'],
                '#',
                number_format($p['price']) . ' تومان',
                $p['original'] !== null ? number_format($p['original']) . ' تومان' : null
            );
        }

        $titleHtml = $title ? "<h2 class=\"tb-heading tb-section-title\">{$title}</h2>" : '';
        $carousel = $this->renderCarousel(
            $cards,
            $id,
            (bool) ($props['autoplay'] ?? true),
            (int) ($props['interval'] ?? 5000),
            'tb-module-products',
            ['desktop' => (int) ($props['columns'] ?? 3), 'tablet' => 2, 'mobile' => 1]
        );

        return "<div id=\"tb-{$id}-wrap\" class=\"tb-module-products tb-section\">
            <div class=\"tb-container\">{$titleHtml}{$carousel}</div>
        </div>";
    }

    /**
     * Canonical (shared) Product Grid. Real bug found via a full-page
     * Playwright screenshot pass (Commercial Productization phase):
     * `product_grid` is a real registered ECOMMERCE type with no canonical
     * renderer at all — every use of it on saas-platform's own admin
     * preview fell through to renderFallback(), which dumps
     * json_encode($props) as raw visible text (exactly the stray
     * `{"columns":4,"limit":8}`-shaped text found sitting in the middle of
     * the real rendered home page). customer-site already has a real,
     * working Product-bound override (App\Services\ThemeBuilder\
     * BlockRenderer::renderProductGrid()) — this canonical version only
     * closes the admin-preview gap, same demo-fixture convention as
     * renderProductSlider()/renderCategorySlider() above.
     */
    protected function renderProductGrid(array $props, string $inner, string $id): string
    {
        $columns = max(2, min(4, (int) ($props['columns'] ?? 4)));

        $demo = [
            ['title' => 'کیف چرم دست‌دوز', 'price' => 1250000, 'original' => 1590000, 'badge' => 'تخفیف', 'stock' => 'موجود'],
            ['title' => 'ساعت هوشمند مدل ایکس', 'price' => 3450000, 'original' => null, 'badge' => 'جدید', 'stock' => 'موجود'],
            ['title' => 'هدفون بی‌سیم پرومکس', 'price' => 890000, 'original' => 1100000, 'badge' => null, 'stock' => 'تنها ۳ عدد باقی مانده'],
            ['title' => 'کفش ورزشی طرح رانینگ', 'price' => 1680000, 'original' => null, 'badge' => null, 'stock' => 'موجود'],
        ];

        $cards = [];
        foreach ($demo as $p) {
            $cards[] = $this->productCardHtml(
                $p['title'], '', (float) $p['price'], $p['original'] !== null ? (float) $p['original'] : null,
                $p['badge'], $p['stock'], '#',
                number_format($p['price']) . ' تومان',
                $p['original'] !== null ? number_format($p['original']) . ' تومان' : null
            );
        }

        return "<div id=\"tb-{$id}\" class=\"tb-grid tb-grid-cols-{$columns} tb-gap-md tb-product-grid-wrap\">" . implode('', $cards) . "</div>";
    }

    /**
     * Canonical (shared) `video`. Same audit-found class of bug as
     * renderProductGrid() above (registered type, no canonical renderer,
     * silent json_encode($props) text dump) — customer-site already has a
     * real, working override (real <video>, real empty-state message).
     * Mirrored here exactly (same empty-state copy) rather than fabricated
     * "demo" video content: this package has no real local video asset,
     * and inventing one (e.g. relabeling a static image as .mp4) would be
     * a worse defect than an honest, clearly-labeled empty state — see the
     * Commercial Productization phase's own report for this explicit
     * scope decision.
     */
    protected function renderVideo(array $props, string $inner, string $id): string
    {
        $url = trim((string) ($props['url'] ?? ''));
        if ($url === '') {
            return "<div id=\"tb-{$id}\" class=\"tb-video tb-empty-state\"><p class=\"tb-text\">ویدیویی انتخاب نشده است.</p></div>";
        }

        $poster   = trim((string) ($props['poster'] ?? ''));
        $caption  = $this->escape($props['caption'] ?? '');
        $autoplay = (bool) ($props['autoplay'] ?? false);
        $loop     = (bool) ($props['loop'] ?? false);
        $controls = $props['controls'] ?? true;

        $attrs = [];
        if ($controls) $attrs[] = 'controls';
        if ($autoplay) $attrs[] = 'autoplay muted playsinline';
        if ($loop) $attrs[] = 'loop';
        $attrStr = implode(' ', $attrs);
        $posterAttr = $poster !== '' ? ' poster="' . $this->escapeAttr($poster) . '"' : '';
        $captionHtml = $caption ? "<figcaption class=\"tb-video-caption\">{$caption}</figcaption>" : '';

        return "<figure id=\"tb-{$id}\" class=\"tb-video\">
            <video src=\"" . $this->escapeAttr($url) . "\"{$posterAttr} {$attrStr} style=\"width:100%;border-radius:0.5rem;display:block;\"></video>
            {$captionHtml}
        </figure>";
    }

    /** Canonical `cart_icon` — same audit-found gap class as renderVideo() above. */
    protected function renderCartIcon(array $props, string $inner, string $id): string
    {
        $count = (int) ($props['count'] ?? 0);
        $badge = $count > 0 ? "<span class=\"tb-cart-icon-badge\">" . number_format($count) . "</span>" : '';
        return "<a href=\"/cart\" id=\"tb-{$id}\" class=\"tb-cart-icon\" aria-label=\"سبد خرید\" style=\"position:relative;display:inline-flex;\">🛒{$badge}</a>";
    }

    /**
     * Canonical `add_to_cart`. `product_id` is empty by design here (same
     * "will show once available" rule the seeder's own docblock already
     * applies to every dynamic/tenant-data type) — a real add-to-cart POST
     * needs a real host cart service this shared package cannot have.
     */
    protected function renderAddToCart(array $props, string $inner, string $id): string
    {
        $text = $this->escape($props['text'] ?? 'افزودن به سبد');
        $productId = trim((string) ($props['product_id'] ?? ''));
        $disabledAttr = $productId === '' ? ' disabled aria-disabled="true"' : '';
        return "<button type=\"button\" id=\"tb-{$id}\" class=\"tb-btn tb-btn-primary tb-add-to-cart\"{$disabledAttr}>{$text}</button>";
    }

    /**
     * Canonical `language_switcher`. customer-site overrides this with its
     * real <x-language-switcher> component (real SetLocale middleware +
     * lang.switch route) — this package has no such host-specific i18n
     * system, so the canonical preview shows a plain, honest single-
     * language label rather than fabricating a working switcher with
     * nothing behind it.
     */
    protected function renderLanguageSwitcher(array $props, string $inner, string $id): string
    {
        return "<div id=\"tb-{$id}\" class=\"tb-language-switcher\"><span class=\"tb-language-switcher-current\">فارسی</span></div>";
    }

    /**
     * Canonical (shared) Category Slider — new type, was entirely absent
     * from the registry before this change. Demo-fixture-based for the
     * same reason as renderProductSlider() above; customer-site overrides
     * this with a real ProductCategory query (real name + real product
     * count per category).
     */
    protected function renderCategorySlider(array $props, string $inner, string $id): string
    {
        $title = $this->escape($props['title'] ?? 'دسته‌بندی‌ها');
        $layout = ($props['layout'] ?? 'carousel') === 'grid' ? 'grid' : 'carousel';
        $columns = max(2, min(6, (int) ($props['columns'] ?? 4)));

        $demo = [
            ['name' => 'پوشاک', 'count' => 128, 'icon' => '👕'],
            ['name' => 'دیجیتال و لوازم الکترونیکی', 'count' => 76, 'icon' => '📱'],
            ['name' => 'خانه و آشپزخانه', 'count' => 54, 'icon' => '🏠'],
            ['name' => 'زیبایی و سلامت', 'count' => 39, 'icon' => '💄'],
        ];

        $cards = [];
        foreach ($demo as $c) {
            $cards[] = "<a href=\"#\" class=\"tb-category-card\" style=\"display:block;text-align:center;padding:1.5rem 1rem;border:1px solid #e5e7eb;border-radius:.75rem;text-decoration:none;color:inherit;\">
                <div style=\"font-size:2rem;margin-bottom:.5rem;\">" . $this->escape($c['icon']) . "</div>
                <div class=\"tb-category-name\" style=\"font-weight:700;\">" . $this->escape($c['name']) . "</div>
                <div class=\"tb-category-count\" style=\"font-size:.8rem;color:#6b7280;margin-top:.2rem;\">" . number_format($c['count']) . " محصول</div>
            </a>";
        }

        $titleHtml = $title ? "<h2 class=\"tb-heading tb-section-title\">{$title}</h2>" : '';

        $body = $layout === 'grid'
            ? "<div class=\"tb-grid tb-grid-cols-{$columns} tb-gap-md\">" . implode('', $cards) . "</div>"
            : $this->renderCarousel($cards, $id . '-track', (bool) ($props['autoplay'] ?? true), (int) ($props['interval'] ?? 5000), 'tb-category-slider', ['desktop' => $columns, 'tablet' => max(2, (int) round($columns / 2)), 'mobile' => 1]);

        return "<div id=\"tb-{$id}\" class=\"tb-section\">
            <div class=\"tb-container\">{$titleHtml}{$body}</div>
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

    /**
     * Commercial Productization fase-2: Timeline was a real, confirmed gap
     * (no registry entry, no renderer at all) found during the 22-element
     * Builder audit. Supports vertical (default) and horizontal layout,
     * per-item icon OR image (never both empty-broken — falls back to the
     * step index), an optional CTA link per item, and an honest empty
     * placeholder when no items are configured (never a blank section).
     */
    protected function renderTimeline(array $props, string $inner, string $id): string
    {
        $title    = $this->escape($props['title'] ?? '');
        $subtitle = $this->escape($props['subtitle'] ?? '');
        $layout   = ($props['layout'] ?? 'vertical') === 'horizontal' ? 'horizontal' : 'vertical';

        $rawItems = $props['items'] ?? '[]';
        $items    = is_array($rawItems) ? $rawItems : (json_decode($rawItems, true) ?? []);

        $headerHtml = ($title || $subtitle) ? "<div class=\"tb-features-section-header\">
            " . ($title ? "<h2 class=\"tb-features-section-title\">{$title}</h2>" : '') . "
            " . ($subtitle ? "<p class=\"tb-features-section-sub\">{$subtitle}</p>" : '') . "
        </div>" : '';

        if (empty($items)) {
            return "<div id=\"tb-{$id}\" class=\"tb-timeline tb-timeline-{$layout} tb-section\">
                <div class=\"tb-container\">{$headerHtml}
                    <div class=\"tb-timeline-empty\">هنوز رویدادی برای این خط زمانی ثبت نشده است.</div>
                </div>
            </div>";
        }

        $nodes = [];
        foreach ($items as $index => $it) {
            $date  = $this->escape($it['date'] ?? '');
            $itemTitle = $this->escape($it['title'] ?? '');
            $text  = $this->escape($it['text'] ?? '');
            $icon  = $this->escape($it['icon'] ?? '');
            $image = (string) ($it['image'] ?? '');
            $linkLabel = $this->escape($it['link_label'] ?? '');
            $linkUrl   = $this->escapeAttr($it['link_url'] ?? '');

            $mediaHtml = $image !== ''
                ? $this->imageWithFallback($image, $itemTitle, 'width:64px;height:64px;border-radius:50%;', $icon !== '' ? $icon : '🖼️')
                : "<div class=\"tb-timeline-marker\">" . ($icon !== '' ? $icon : ($index + 1)) . "</div>";

            $ctaHtml = ($linkLabel !== '' && $linkUrl !== '')
                ? "<a href=\"{$linkUrl}\" class=\"tb-timeline-cta\">{$linkLabel}</a>"
                : '';

            $nodes[] = "<div class=\"tb-timeline-item\">
                <div class=\"tb-timeline-marker-wrap\">{$mediaHtml}</div>
                <div class=\"tb-timeline-content\">
                    " . ($date ? "<span class=\"tb-timeline-date\">{$date}</span>" : '') . "
                    <h3 class=\"tb-timeline-title\">{$itemTitle}</h3>
                    " . ($text ? "<p class=\"tb-timeline-text\">{$text}</p>" : '') . "
                    {$ctaHtml}
                </div>
            </div>";
        }

        return "<div id=\"tb-{$id}\" class=\"tb-timeline tb-timeline-{$layout} tb-section\">
            <div class=\"tb-container\">{$headerHtml}
                <div class=\"tb-timeline-track\">" . implode('', $nodes) . "</div>
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

    /**
     * Universal Multipurpose Slider Overhaul: fixes a real schema/renderer
     * mismatch found during audit — this canonical (static-grid) renderer
     * DID honor `columns`, but the customer-site host override that made
     * it a real carousel silently dropped `columns` entirely (always one
     * card per slide). Both `mode: 'static'` and `mode: 'carousel'` now
     * honor `columns` identically via renderCarousel()'s itemsPerView, so
     * there is exactly one place columns-per-breakpoint logic lives.
     * Backward-compatible: no `mode` prop → 'carousel' (matches the
     * previously-shipped host behavior everyone already sees live),
     * existing `columns`/`items` content renders unchanged either way.
     */
    protected function renderTestimonialsSlider(array $props, string $inner, string $id): string
    {
        $title   = $this->escape($props['title'] ?? 'نظرات مشتریان');
        $columns = max(1, min(3, (int) ($props['columns'] ?? 3)));
        $mode    = in_array($props['mode'] ?? 'carousel', ['static', 'carousel'], true) ? ($props['mode'] ?? 'carousel') : 'carousel';

        $rawItems = $props['items'] ?? '[]';
        $items    = is_array($rawItems) ? $rawItems : (json_decode($rawItems, true) ?? []);

        $cards = [];
        foreach ($items as $t) {
            if (!is_array($t)) {
                continue;
            }
            $name    = $this->escape($t['name'] ?? '');
            $role    = $this->escape($t['role'] ?? '');
            $company = $this->escape($t['company'] ?? '');
            $text    = $this->escape($t['text'] ?? '');
            $rating  = max(0, min(5, (int) ($t['rating'] ?? 5)));
            $stars   = $rating > 0 ? str_repeat('★', $rating) . str_repeat('☆', 5 - $rating) : '';
            $avatar  = trim((string) ($t['avatar'] ?? $t['image'] ?? ''));

            $avatarHtml = $avatar !== ''
                ? "<img src=\"" . $this->escapeAttr($avatar) . "\" alt=\"" . $this->escapeAttr($t['name'] ?? '') . "\" loading=\"lazy\" class=\"tb-testimonial-avatar\" style=\"width:2.75rem;height:2.75rem;border-radius:999px;object-fit:cover;\" onerror=\"this.outerHTML='<div class=&quot;tb-testimonial-avatar&quot;>" . $this->escapeAttr(mb_substr((string) ($t['name'] ?? ''), 0, 1, 'UTF-8')) . "</div>';\">"
                : "<div class=\"tb-testimonial-avatar\">" . mb_substr((string) ($t['name'] ?? ''), 0, 1, 'UTF-8') . "</div>";

            $roleLine = trim(implode(' · ', array_filter([$role, $company])));

            $cards[] = "<div class=\"tb-testimonial-card\">
                " . ($stars !== '' ? "<div class=\"tb-testimonial-stars\">{$stars}</div>" : '') . "
                <p class=\"tb-testimonial-text\">«{$text}»</p>
                <div class=\"tb-testimonial-author\">
                    {$avatarHtml}
                    <div><div class=\"tb-testimonial-name\">{$name}</div><div class=\"tb-testimonial-role\">" . $this->escape($roleLine) . "</div></div>
                </div>
            </div>";
        }

        if (empty($cards)) {
            return "<div id=\"tb-{$id}\" class=\"tb-testimonials tb-section\"><div class=\"tb-container\"><h2 class=\"tb-features-section-title\">{$title}</h2></div></div>";
        }

        $body = $mode === 'static'
            ? "<div class=\"tb-testimonials-grid cols-{$columns}\">" . implode('', $cards) . "</div>"
            : $this->renderCarousel(
                $cards,
                $id . '-track',
                (bool) ($props['autoplay'] ?? true),
                (int) ($props['interval'] ?? 5000),
                'tb-testimonials-slider',
                ['desktop' => $columns, 'tablet' => max(1, min(2, $columns)), 'mobile' => 1]
            );

        return "<div id=\"tb-{$id}\" class=\"tb-testimonials tb-section\">
            <div class=\"tb-container\">
                <div class=\"tb-testimonials-header\"><h2 class=\"tb-features-section-title\">{$title}</h2></div>
                {$body}
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

            $badgeHtml = $badge ? "<div class=\"tb-plan-badge-wrap\">" . $this->badgeHtml($badge, 'primary', 'md') . "</div>" : '';
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
        // Commercial Productization fase-3: same class of bug as
        // renderHeroGradient() — a hardcoded generic-indigo default
        // independent of the Theme's own --tb-primary.
        $bg         = $this->escapeAttr($props['background'] ?? 'var(--tb-primary, #4f46e5)');
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
    /**
     * One Mega Menu dropdown item — real multi-column layout, an optional
     * promotional banner, hover/focus reveal on desktop (pure CSS, no JS
     * needed there) and a tap-to-toggle fallback on touch devices (the
     * inline script below, scoped to this exact item via a unique id, safe
     * to include once per item since each carries its own idempotency
     * guard matching this file's renderCarousel() convention).
     */
    /**
     * @return array{columns: array<int, array{title: string, links: array<int, array{url: string, label: string}>}>, banner_image?: string, banner_url?: string, banner_text?: string}|null
     */
    private function normalizeMegaMenuData(array $link): ?array
    {
        $common = [
            'banner_image' => (string) ($link['mega_banner_image'] ?? ''),
            'banner_url' => (string) ($link['mega_banner_url'] ?? ''),
            'banner_text' => (string) ($link['mega_banner_text'] ?? ''),
            'show_desktop' => array_key_exists('mega_show_desktop', $link) ? (bool) $link['mega_show_desktop'] : true,
            'mobile_behavior' => in_array($link['mega_mobile_behavior'] ?? '', ['accordion', 'flat', 'hidden'], true) ? $link['mega_mobile_behavior'] : 'accordion',
        ];

        // Commercial Productization fase-2 (real column/group structure):
        // `mega_columns` is a genuine nested repeater — [{title, items:[
        // {label,url,icon,image}]}] — each column an independently authored
        // group, never inferred by matching duplicate text. This is now the
        // primary, preferred shape the Canvas editor writes.
        $columnsRaw = $link['mega_columns'] ?? null;
        if (is_string($columnsRaw) && trim($columnsRaw) !== '') {
            $columnsRaw = json_decode($columnsRaw, true);
        }
        if (is_array($columnsRaw) && !empty($columnsRaw)) {
            $columns = [];
            foreach ($columnsRaw as $col) {
                if (!is_array($col)) {
                    continue;
                }
                $links = [];
                foreach ((array) ($col['items'] ?? []) as $it) {
                    if (!is_array($it)) {
                        continue;
                    }
                    $itLabel = (string) ($it['label'] ?? '');
                    if ($itLabel === '') {
                        continue;
                    }
                    $links[] = [
                        'url' => (string) ($it['url'] ?? '#'),
                        'label' => $itLabel,
                        'icon' => (string) ($it['icon'] ?? ''),
                        'image' => (string) ($it['image'] ?? ''),
                    ];
                }
                if (empty($links) && trim((string) ($col['title'] ?? '')) === '') {
                    continue;
                }
                $columns[] = ['title' => (string) ($col['title'] ?? ''), 'links' => $links];
            }
            if (!empty($columns)) {
                return $common + ['columns' => $columns];
            }
        }

        // Older flat-repeater shape (grouped by duplicate column_title) —
        // kept as a fallback for previously authored content only; the
        // editor no longer writes this shape.
        $flatItems = $link['mega_items'] ?? null;
        if (is_string($flatItems) && trim($flatItems) !== '') {
            $flatItems = json_decode($flatItems, true);
        }
        if (is_array($flatItems) && !empty($flatItems)) {
            $columns = [];
            foreach ($flatItems as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $colTitle = (string) ($row['column_title'] ?? '');
                $linkLabel = (string) ($row['link_label'] ?? '');
                if ($linkLabel === '') {
                    continue;
                }
                if (!isset($columns[$colTitle])) {
                    $columns[$colTitle] = ['title' => $colTitle, 'links' => []];
                }
                $columns[$colTitle]['links'][] = ['url' => (string) ($row['link_url'] ?? '#'), 'label' => $linkLabel];
            }
            if (!empty($columns)) {
                return $common + ['columns' => array_values($columns)];
            }
        }

        // Legacy raw-JSON shape (programmatic/seeder authoring).
        $mega = $link['mega'] ?? null;
        if (is_string($mega) && trim($mega) !== '') {
            $mega = json_decode($mega, true);
        }
        if (is_array($mega) && !empty($mega['columns'])) {
            return $mega + $common;
        }

        return null;
    }

    private function renderMegaMenuItem(string $parentId, string $url, string $label, array $mega): string
    {
        static $counter = 0;
        $itemId = "tb-mega-{$parentId}-" . (++$counter);

        $columnsHtml = '';
        foreach ((array) ($mega['columns'] ?? []) as $col) {
            $colTitle = $this->escape(is_array($col) ? ($col['title'] ?? '') : '');
            $colLinks = is_array($col) ? ($col['links'] ?? []) : [];
            $linksHtml = '';
            foreach ((array) $colLinks as $l) {
                $lUrl = $this->escapeUrl(is_array($l) ? ($l['url'] ?? '') : '', '#');
                $lLabel = $this->escape(is_array($l) ? ($l['label'] ?? '') : (string) $l);
                if ($lLabel === '') {
                    continue;
                }
                $lIcon = $this->escape(is_array($l) ? ($l['icon'] ?? '') : '');
                $lImage = trim((string) (is_array($l) ? ($l['image'] ?? '') : ''));
                $mediaHtml = '';
                if ($lImage !== '') {
                    $mediaHtml = $this->imageWithFallback($lImage, $lLabel, 'width:1.75rem;height:1.75rem;border-radius:0.375rem;flex-shrink:0;');
                } elseif ($lIcon !== '') {
                    $mediaHtml = "<span class=\"tb-mega-menu-link-icon\">{$lIcon}</span>";
                }
                $linksHtml .= "<a href=\"{$lUrl}\" class=\"tb-mega-menu-link\">{$mediaHtml}<span>{$lLabel}</span></a>";
            }
            if ($colTitle === '' && $linksHtml === '') {
                continue;
            }
            $columnsHtml .= "<div class=\"tb-mega-menu-col\">"
                . ($colTitle !== '' ? "<div class=\"tb-mega-menu-col-title\">{$colTitle}</div>" : '')
                . "<div class=\"tb-mega-menu-col-links\">{$linksHtml}</div>"
                . "</div>";
        }

        $bannerImage = trim((string) ($mega['banner_image'] ?? ''));
        $bannerHtml = '';
        if ($bannerImage !== '') {
            $bannerUrl = $this->escapeUrl($mega['banner_url'] ?? '', '#');
            $bannerText = $this->escape($mega['banner_text'] ?? '');
            $bannerImgHtml = $this->imageWithFallback($bannerImage, (string) ($mega['banner_text'] ?? $label), 'width:100%;height:100%;');
            $bannerHtml = "<a href=\"{$bannerUrl}\" class=\"tb-mega-menu-banner\">"
                . "<div class=\"tb-mega-menu-banner-img\">{$bannerImgHtml}</div>"
                . ($bannerText !== '' ? "<div class=\"tb-mega-menu-banner-text\">{$bannerText}</div>" : '')
                . "</a>";
        }

        $showDesktop = ($mega['show_desktop'] ?? true) !== false;
        $mobileBehavior = in_array($mega['mobile_behavior'] ?? 'accordion', ['accordion', 'flat', 'hidden'], true) ? $mega['mobile_behavior'] : 'accordion';
        $itemClasses = 'tb-mega-menu-item tb-mega-mobile-' . $mobileBehavior . ($showDesktop ? '' : ' tb-mega-desktop-hidden');

        return "<div class=\"{$itemClasses}\" id=\"{$itemId}\">
            <a href=\"{$url}\" class=\"tb-nav-header-link tb-mega-menu-trigger\" aria-haspopup=\"true\" aria-expanded=\"false\">{$label}</a>
            <div class=\"tb-mega-menu-panel\" role=\"menu\">
                <div class=\"tb-mega-menu-cols\">{$columnsHtml}</div>
                {$bannerHtml}
            </div>
            <script>(function(){
                var root = document.getElementById('{$itemId}');
                if (!root || root.dataset.tbMegaInit) return;
                root.dataset.tbMegaInit = '1';
                var trigger = root.querySelector('.tb-mega-menu-trigger');
                trigger.addEventListener('click', function(e){
                    if (window.matchMedia('(hover: hover)').matches) return;
                    e.preventDefault();
                    var open = root.classList.toggle('tb-mega-open');
                    trigger.setAttribute('aria-expanded', open ? 'true' : 'false');
                });
                document.addEventListener('click', function(e){
                    if (!root.contains(e.target)) { root.classList.remove('tb-mega-open'); trigger.setAttribute('aria-expanded', 'false'); }
                });
            })();</script>
        </div>";
    }

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

        // TASK 3/3 Phase 6.5B — when nav_source=menu, render the SAME real
        // Menu tree the canonical nav_menu block uses ($context['menu_html'],
        // built by the host from the site's real "primary" Menu location),
        // instead of this block's own nav_links repeater. This is the
        // header half of the Menu <-> Page convergence: a customer editing
        // /admin/menus now genuinely controls the visible header nav for
        // any theme/instance that opts in, rather than maintaining a
        // second, disconnected label+url list with no Page-rename safety.
        if (($props['nav_source'] ?? 'custom') === 'menu') {
            $linkItems = $this->context['menu_html'] ?? '';
        } else {
            $linkItems = '';
            foreach ($links as $link) {
                $url   = $this->escapeUrl(is_array($link) ? ($link['url'] ?? '') : '', '#');
                $label = $this->escape(is_array($link) ? ($link['label'] ?? '') : (string) $link);
                if ($label === '') {
                    continue;
                }
                // Universal Multipurpose Slider Overhaul (Commercial
                // Productization): Mega Menu — a genuinely new,
                // additive-only capability (a plain {url,label} item, the
                // only shape every existing theme/instance has ever
                // authored, renders identically to before). Two authoring
                // shapes are accepted, normalized to the same internal
                // {columns:[{title,links}], banner_*} structure:
                //  - flat `mega_items` (a real visual, non-JSON editor —
                //    the Canvas repeater UI supports exactly one level of
                //    nested repeater, so each row is {column_title,
                //    link_label, link_url} and consecutive rows sharing
                //    the same column_title are grouped into one column at
                //    render time) plus sibling `mega_banner_image/_url/_text`
                //    fields on the same link item — this is what the
                //    editor (App\Services\ThemeCanvas\BlockRegistry) authors.
                //  - legacy raw-JSON `mega: {columns:[...], banner_*}` —
                //    still accepted for programmatic/seeder authoring.
                $mega = $this->normalizeMegaMenuData(is_array($link) ? $link : []);
                if ($mega !== null) {
                    $linkItems .= $this->renderMegaMenuItem($id, $url, $label, $mega);
                } else {
                    $linkItems .= "<a href=\"{$url}\" class=\"tb-nav-header-link\">{$label}</a>";
                }
            }
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
        $darkToggleHtml = ((bool) ($props['show_dark_toggle'] ?? false)) ? $this->renderDarkModeToggle($id) : '';
        // TASK 3/3 Phase 6.5C (BLD-25) — real defect found (via the LOGO
        // golden-journey browser test) and fixed: nav_header's schema only
        // ever supported a TEXT logo — a customer's real, canonical
        // Settings > Logo upload (CustomerMediaService) had no rendering
        // path into the actual live header at all. Rather than adding a
        // second, block-level logo-image field the customer would have to
        // separately author per header preset (a NEW duplicate authority,
        // exactly what this phase exists to avoid), an un-set logo_text
        // falls back to the site's real global logo image
        // ($context['site_logo_html'], the same host-resolved value
        // <x-site-brand> already renders elsewhere) — zero behavior change
        // for any existing theme/instance that already authors logo_text.
        $logoHtml = $logoText
            ? "<a href=\"{$logoUrl}\" class=\"tb-nav-header-logo\">{$logoText}</a>"
            : ($this->context['site_logo_html'] ?? '');

        $stickyStyle = $sticky ? 'position:sticky;top:0;z-index:100;' : '';

        // TASK 3/3 Phase 6 (BLD-18): nav_header — the primary header
        // component every one of the 6 header presets uses in some
        // combination — had ZERO CSS anywhere in either host's real
        // rendering layout (confirmed: not one .tb-nav-header* rule
        // existed), rendering as bare, unstyled <a> tags with no spacing.
        // Real CSS added to the accompanying customer-site commit. No new
        // mobile toggle needed here: both hosts' own page chrome already
        // wraps every header block in a real, working hamburger/mobile-
        // menu mechanism (customer-site: .tb-header-nav-wrap/.tb-mobile-
        // menu in layouts/canvas-theme.blade.php) that hides this block's
        // own inline links below 768px and shows its own real mobile nav
        // instead — a second toggle nested in here would sit inside that
        // already-hidden wrapper and never be reachable.
        //
        // BLD-19 (real axe-core scan): the root element was a literal
        // <header>, and every real consumer of this block already wraps it
        // in its OWN real <header role="banner"> page-chrome element — a
        // <header> inside a <header> is two nested banner landmarks, a
        // genuine WCAG/ARIA violation (landmark-banner-is-top-level,
        // landmark-no-duplicate-banner), not just noise. A plain <div>
        // carries the same classes/styling with no landmark role of its
        // own, leaving the single real banner role on the outer wrapper
        // where it belongs — see renderNavFooter() below for the
        // contentinfo/<footer> equivalent.
        return "<div id=\"tb-{$id}\" class=\"tb-nav-header\" style=\"background:{$bg};color:{$tc};{$stickyStyle}\">
            <div class=\"tb-container tb-nav-header-inner\">
                {$logoHtml}
                <nav class=\"tb-nav-header-links\" aria-label=\"منوی اصلی\">{$linkItems}</nav>
                <div class=\"tb-nav-header-actions\">{$searchHtml}{$phoneHtml}{$cartHtml}{$accountHtml}{$darkToggleHtml}{$ctaHtml}</div>
            </div>
        </div>";
    }

    /**
     * Commercial Productization fase-4: a real, usable dark-mode toggle —
     * not just backend token capability. Wires up to the `window.__tbTheme`
     * API the host page's own head-bootstrap script defines (see
     * layouts/canvas-theme.blade.php on customer-site and canvas-preview.
     * blade.php / canvas-live-preview.blade.php on saas-platform) — that
     * script runs synchronously before first paint to prevent a flash of
     * the wrong theme, and is the single source of truth for the current
     * light/dark/system preference so every toggle instance on the page
     * (this block can appear more than once, e.g. header + a duplicate in
     * a footer) stays in sync via the `tb-theme-change` event.
     */
    private function renderDarkModeToggle(string $parentId): string
    {
        $toggleId = "tb-{$parentId}-theme-toggle";

        return "<button type=\"button\" id=\"{$toggleId}\" class=\"tb-theme-toggle\" aria-label=\"تغییر حالت رنگی\">
            <span class=\"tb-theme-toggle-icon\" aria-hidden=\"true\">🌓</span>
        </button>
        <script>(function(){
            var btn = document.getElementById('{$toggleId}');
            if (!btn || btn.dataset.tbInit) return;
            btn.dataset.tbInit = '1';
            var ICONS = {light:'☀️', dark:'🌙', system:'💻'};
            var LABELS = {
                light: 'حالت رنگی: روشن — برای تغییر به تاریک کلیک کنید',
                dark: 'حالت رنگی: تاریک — برای تغییر به خودکار (سیستم) کلیک کنید',
                system: 'حالت رنگی: هماهنگ با سیستم — برای تغییر به روشن کلیک کنید'
            };
            function render() {
                var p = (window.__tbTheme && window.__tbTheme.get) ? window.__tbTheme.get() : 'system';
                var icon = btn.querySelector('.tb-theme-toggle-icon');
                if (icon) icon.textContent = ICONS[p] || ICONS.system;
                btn.setAttribute('aria-label', LABELS[p] || LABELS.system);
            }
            btn.addEventListener('click', function () {
                if (window.__tbTheme && window.__tbTheme.cycle) window.__tbTheme.cycle();
                render();
            });
            document.addEventListener('tb-theme-change', render);
            render();
        })();</script>";
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

        // TASK 3/3 Phase 6.5C (BLD-26) — footer half of the Menu <-> Header/
        // Footer convergence (see renderNavHeader()'s nav_source for the
        // header equivalent). When nav_source=menu, this footer's own
        // multi-column `columns` repeater is replaced by the real Menu the
        // host resolved for the 'footer' location ($context['footer_menu_html']),
        // rendered by the SAME partials/site-menu.blade.php the header
        // uses (its 'footer' variant — a flat link list, matching this
        // block's own single .tb-nav-footer-cols column shape for a
        // Menu-driven footer). Default stays 'custom' — zero behavior
        // change for every existing theme/instance.
        if (($props['nav_source'] ?? 'custom') === 'menu') {
            // partials/site-menu.blade.php's 'footer' variant renders plain
            // <a> tags (no <li> wrapper — it is meant to sit inside a flex
            // container, not a list), so this uses a <div>, not the
            // custom-columns branch's <ul>, to stay valid HTML. Both are
            // targeted by the exact same .tb-nav-footer-links CSS
            // (`.tb-nav-footer-links a`, not `li a`).
            $menuHtml = $this->context['footer_menu_html'] ?? '';
            $colHtml = $menuHtml !== '' ? "<div class=\"tb-nav-footer-col\"><div class=\"tb-nav-footer-links\">{$menuHtml}</div></div>" : '';
        } else {
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

        // BLD-19 (real axe-core scan): same nested-landmark issue as
        // renderNavHeader() above (landmark-contentinfo-is-top-level /
        // landmark-no-duplicate-contentinfo) — every real consumer already
        // wraps this in its own <footer role="contentinfo">, so the root
        // element here is a plain <div>, not a second nested <footer>.
        return "<div id=\"tb-{$id}\" class=\"tb-nav-footer\" style=\"background:{$bg};color:{$tc};\">
            <div class=\"tb-container\">
                {$brandHtml}
                " . ($colHtml ? "<div class=\"tb-nav-footer-cols\">{$colHtml}</div>" : '') . "
                <div class=\"tb-nav-footer-bottom\">
                    " . ($copyright ? "<p class=\"tb-nav-footer-copyright\">{$copyright}</p>" : '') . "
                    {$legalHtml}
                    {$socialHtml}
                </div>
            </div>
        </div>";
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
