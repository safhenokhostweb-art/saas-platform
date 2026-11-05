<?php

namespace SaaSPlatform\ThemeRuntime;

use Illuminate\Support\Str;
use SaaSPlatform\ThemeRuntime\Providers\ThemeRuntimeServiceProvider;
use SaaSPlatform\ThemeRuntime\Support\IconLibrary;
use SaaSPlatform\ThemeRuntime\Support\JalaliDate;
use SaaSPlatform\ThemeRuntime\Support\PersianNumber;
use SaaSPlatform\ThemeRuntime\Support\SocialNetworks;

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
    use FoodMartBlockRendererTrait;
    use SleepShimanBlockRendererTrait;
    use DynamicBlockRendererTrait;

    /**
     * Smart Public Rendering — canonical dual-mode contract, mirroring
     * ThemeRuntimeDocument (app/Services/ThemeCanvas) on the saas-platform
     * host. MODE_EDITOR (the historical default) renders the full editor
     * bridge surface; MODE_PUBLIC renders a public, indexable page.
     */
    public const MODE_EDITOR = 'editor';
    public const MODE_PUBLIC = 'public';

    /** Phase 3 (ADR-068): the «پرامتیاز» product badge — see productCard(). */
    public const TOP_RATED_MIN_RATING = 4.5;
    public const TOP_RATED_MIN_REVIEWS = 5;

    protected array $context = [];

    /**
     * Smart Public Rendering — the document mode. NULL means "not forced":
     * rendering then behaves exactly as before (context['editor_mode'] is
     * the single editor-output authority), so every existing caller — and
     * the shared package's own unit tests, which construct the renderer
     * with no context — is untouched.
     */
    protected ?string $mode = null;

    /**
     * Phase 9 / Step 1 — the reference block's client script is emitted at
     * most once per PHP process (see renderReferenceCounter()). Static
     * because a single page can render many reference blocks through
     * several short-lived renderer instances, and each instance would
     * otherwise re-emit the identical script text.
     */
    private static bool $referenceBlockScriptEmitted = false;

    /** Phase 11: one JS/CSS carrier for every rendered modern-header document. */
    private bool $headerModernAssetsEmitted = false;

    /** Phase 0.7 (ADR-066): blocks/site-forms.js is inlined once per render. */
    private bool $siteFormsAssetsEmitted = false;

    /** Phase 1/2 (ADR-067): site-header.js and cart.js, each once per render. */
    private bool $siteHeaderAssetsEmitted = false;
    private bool $cartAssetsEmitted = false;
    private bool $headerNeedsScript = false;

    /** Phase 3b (ADR-069): blocks/pricing.js, once per render. */
    private bool $pricingAssetsEmitted = false;

    /**
     * Phase 9 / Step 2 — same once-per-document rule for the shared
     * carousel asset, and for the same reason: several short-lived renderer
     * instances render the several carousels of one page, and each would
     * otherwise re-inline the identical ~11 KB controller.
     *
     * Keyed BY MODE, not a single flag. A single boolean would let one
     * render starve the next within the same PHP process, and unlike the
     * reference counter a carousel is meaningless without its script: a
     * document that lost the carrier would render its arrows and dots and
     * then do nothing when they were clicked. Anything that renders both
     * modes in one process (a test, a preview inside a canvas response) now
     * gets a carrier for each.
     *
     * @var array<string, bool>
     */
    private static array $carouselScriptEmitted = [];

    /**
     * Phase 9.3 — once-per-document gate for the countdown adapter.
     *
     * A plain boolean, NOT keyed by mode like $carouselScriptEmitted. The
     * carousel needed per-mode keys because its script only carries one mode
     * of behaviour. The countdown adapter ships BOTH carriers (an SDK block
     * for canvas surfaces, a DOM scan for public pages), so every mode is
     * served by the same bytes and there is nothing for a second render to
     * starve.
     */
    private static bool $countdownAdapterEmitted = false;

    /** Test-only: lets a test re-assert the once-per-document emission. */
    public static function resetReferenceBlockScriptEmission(): void
    {
        self::$referenceBlockScriptEmitted = false;
    }

    /** Test-only: lets a test re-assert the once-per-document emission. */
    public static function resetCountdownAdapterEmission(): void
    {
        self::$countdownAdapterEmitted = false;
    }

    /** Test-only: lets a test re-assert the once-per-document emission. */
    public static function resetCarouselScriptEmission(): void
    {
        self::$carouselScriptEmitted = [];
    }

    public function setContext(array $context): self
    {
        $this->context = $context;
        // A forced mode and an editor_mode context flag are two descriptions
        // of the same thing; letting them disagree would make public-mode
        // guarantees depend on call order. The mode always wins, and the
        // flag is re-derived from it at the single point where it is read.
        if (isset($this->context['mode'])) {
            $this->setMode(is_string($this->context['mode']) ? $this->context['mode'] : '');
        }
        return $this;
    }

    /**
     * Force the document mode. MODE_PUBLIC fail-closes every editor-output
     * gate in this class regardless of any editor_mode context flag.
     * Unknown values are rejected — mode is explicit, never fuzzy.
     */
    public function setMode(?string $mode): self
    {
        if ($mode !== null && !in_array($mode, [self::MODE_EDITOR, self::MODE_PUBLIC], true)) {
            throw new \InvalidArgumentException(
                "Unknown BlockRenderer mode \"{$mode}\" (expected 'editor', 'public' or null)"
            );
        }
        $this->mode = $mode;
        return $this;
    }

    /**
     * The authoritative editor-output gate. TRUE only when no mode is forced
     * AND the host opted into editor rendering via its existing context flag.
     * In MODE_PUBLIC this is FALSE unconditionally — the mode is the harder
     * gate, and an editor_mode flag leaking in through setContext() can never
     * re-enable editor output on a public surface.
     */
    protected function isEditorRendering(): bool
    {
        if ($this->mode === self::MODE_PUBLIC) {
            return false;
        }
        return (bool) ($this->context['editor_mode'] ?? false);
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
            return $this->finalizeBlock(
                $this->wrapEditorMode($html, $id, $type, $props),
                $block['style'] ?? [],
                $block['responsive'] ?? [],
                $id,
                $type
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
            return $this->finalizeBlock(
                $this->wrapEditorMode($html, $id, $type, $props),
                $block['style'] ?? [],
                $block['responsive'] ?? [],
                $id,
                $type
            );
        }

        $method = 'render' . Str::studly(str_replace(':', '_', $type));
        if (method_exists($this, $method)) {
            $html = $this->{$method}($props, $inner, $id);
        } else {
            $html = $this->renderFallback($type, $props, $inner, $id);
        }

        $output = $this->finalizeBlock(
            $this->wrapEditorMode($html, $id, $type, $props),
            $block['style'] ?? [],
            $block['responsive'] ?? [],
            $id,
            $type
        );
        return $this->annotateLayoutSlot($output, $block, $id, $type);
    }

    /** Shared by host dispatchers after their normal root/identity decoration. */
    protected function annotateLayoutSlot(string $output, array $block, string $id, string $type): string
    {
        $slot = $block['layout_slot'] ?? null;
        if (in_array($slot, ['header', 'footer'], true) && str_contains($type, $slot)) {
            // Annotate the existing root carrying this block's identity;
            // never add wrappers or alter the child tree.
            // Non-editable chrome in the live iframe has its DOM id but
            // deliberately omits editor identity metadata.
            $safeId = preg_quote($this->escapeAttr($id), '/');
            $pattern = '/<([a-z][a-z0-9-]*)\b(?![^>]*\bdata-layout-slot\s*=)([^>]*(?:\bdata-tb-block-id="' . $safeId . '"|(?<![\w-])id="tb-' . $safeId . '")[^>]*)>/i';
            $output = preg_replace_callback($pattern, static function (array $match) use ($slot): string {
                $role = strtolower($match[1]) === 'div' && !preg_match('/\brole\s*=/i', $match[2])
                    ? ' role="' . ($slot === 'header' ? 'banner' : 'contentinfo') . '"'
                    : '';

                return '<' . $match[1] . $match[2] . ' data-layout-slot="' . $slot . '"' . $role . '>';
            }, $output, 1) ?? $output;
        }

        return $output;
    }

    /**
     * Smart Public Rendering — the ONE shared finalization seam every
     * renderBlock() return passes through.
     *
     * MODE_PUBLIC (task spec): the output MUST retain the structural
     * attributes the JS runtime addresses (data-tb-block-id,
     * data-tb-block-type, data-tb-container) — wrapEditorMode() is a no-op
     * there, so this wrapper IS their only carrier — while every editor-only
     * attribute is stripped (sanitizeEditorOutput()). In editor/default
     * rendering this is exactly the historical
     * applyBlockStyle(wrapEditorMode(html)) composition, byte-identical.
     */
    protected function finalizeBlock(string $html, array $style, array $responsive, string $id, string $type): string
    {
        $styled = $this->applyBlockStyle($html, $style, $responsive, $id);
        if ($this->mode === self::MODE_PUBLIC) {
            return $this->sanitizeEditorOutput($styled, $id, $type);
        }
        return $styled;
    }

    /**
     * Phase 9 / Step 1 — the ONE props carrier for the client runtime.
     *
     * ThemeCanvasRuntime.initBlock() has always read `data-tb-block-props`
     * (canvas/runtime.blade.php) to build `ctx.props`, but no renderer ever
     * emitted the attribute, so ctx.props was permanently `{}`. This is the
     * additive fix: the attribute is emitted on the SAME wrapper that already
     * carries data-tb-block-id/-type, in both editor and public mode, so the
     * two wrappers stay a pair.
     *
     * The payload is the resolved block props MINUS the nested `blocks` child
     * tree, which renderBlocks() copies into $props before dispatch. That
     * tree is already rendered into $inner; re-serialising it would duplicate
     * the whole subtree into a DOM attribute on every block.
     *
     * Empty props emit nothing: an empty attribute would still be re-parsed on
     * every block on every page for no benefit, and omitting it keeps the
     * rendered output byte-identical for every existing block that has no
     * props of its own.
     */
    protected function blockPropsAttr(array $props): string
    {
        $clientProps = $props;
        unset($clientProps['blocks']);

        if (empty($clientProps)) {
            return '';
        }

        $json = json_encode($clientProps, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($json) || $json === '') {
            return '';
        }

        return ' data-tb-block-props="' . htmlspecialchars($json, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"';
    }

    /**
     * Smart Public Rendering — public structural wrapper.
     *
     * Emits the runtime-facing structural attributes ONLY (never the editor
     * selection overlay, drag handle, or block badge), so ThemeCanvasRuntime
     * (replaceNode) and CanvasIdentity can address every block on a public
     * page while no editor chrome is present.
     *
     * ADR-076: data-widget-type repeats the type as the stable hook browser
     * checks and the Sentinel verifier select widgets by; data-tb-block-type
     * stays the runtime's own contract.
     */
    protected function wrapPublicStructural(string $html, string $id, string $type, array $props = []): string
    {
        $esc   = htmlspecialchars($type, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $idEsc = htmlspecialchars($id,   ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $containerAttr = $this->isStructuralContainer($type)
            ? ' data-tb-container="1"' : '';
        return "<div class=\"tb-block\" data-tb-block-id=\"{$idEsc}\" data-tb-block-type=\"{$esc}\" data-widget-type=\"{$esc}\"{$containerAttr}"
            . $this->blockPropsAttr($props)
            . ">{$html}</div>";
    }

    /**
     * Smart Public Rendering — defense-in-depth editor-attribute stripper,
     * run over the FINAL block HTML in MODE_PUBLIC (after all renderers and
     * wrappers have run, so nothing can smuggle an editor attribute past the
     * per-renderer gates).
     *
     * Strips exactly the editor-only family: every `data-tb-edit*` attribute
     * (data-tb-editable, data-tb-inline-type, and any future data-tb-edit-*
     * authoring attribute — the task's data-tb-edit-action names no real
     * attribute in this codebase, but the family prefix covers it), plus the
     * editor selection overlay's drag handle and block badge, which are
     * editor chrome by definition. Runtime attributes are deliberately NOT
     * touched: data-tb-interaction (the interaction dispatcher's hook) and
     * the structural data-tb-block-* family are required on public pages.
     */
    protected function sanitizeEditorOutput(string $html, string $id, string $type): string
    {
        if ($html === '') {
            return $html;
        }

        // data-tb-edit* (data-tb-editable, data-tb-edit-action, any future
        // edit-* authoring attribute) plus data-tb-inline-type, which carries
        // no edit prefix but is editor-only by contract. Quoted values
        // (single or double) and bare boolean forms both match; [^>]* is
        // bounded by the tag close, so a value containing '>' cannot
        // over-match.
        $html = preg_replace('/\s(?:data-tb-edit[^=>\\s]*|data-tb-inline-type[^=>\\s]*)(?:\s*=\s*(?:"[^"]*"|\'[^\']*\'|[^\\s>]+))?/i', '', $html) ?? $html;

        // Editor selection-overlay chrome: the drag handle button and the
        // type badge (only ever emitted by wrapEditorMode()).
        $html = preg_replace('/<button[^>]*\\bclass=["\'][^"\']*\\btb-drag-handle\\b[^"\']*["\'][^>]*>.*?<\\/button>/is', '', $html) ?? $html;
        $html = preg_replace('/<div[^>]*\\bclass=["\'][^"\']*\\btb-block-badge\\b[^"\']*["\'][^>]*>.*?<\\/div>/is', '', $html) ?? $html;

        return $html;
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
        return BlockRegistry::isContainerType($type);
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
                    $mediaCss .= "@media(max-width:1023.98px){{$selector}{" . implode(';', $tabletCss) . "}}";
                }
            }

            $mobileStyle = $responsive['mobile'] ?? [];
            if (!empty($mobileStyle)) {
                $mobileCss = $this->buildCssDeclarations($mobileStyle);
                if (!empty($mobileCss)) {
                    $mediaCss .= "@media(max-width:479.98px){{$selector}{" . implode(';', $mobileCss) . "}}";
                }
            }

            if ($mediaCss !== '') {
                $wrapped .= "<style data-tb-blk=\"{$blkId}\">{$mediaCss}</style>";
            }
        }

        return $wrapped;
    }

    /**
     * Smart Public Rendering — the ONE editor-output authority for the
     * inline-editing hook (data-tb-editable / data-tb-inline-type). Routes
     * through isEditorRendering() so MODE_PUBLIC fail-closes here too: even
     * with an editor_mode flag present in context, a forced public mode can
     * never emit an editor attribute.
     */
    protected function editableAttr(string $propKey, string $inlineType = 'text'): string
    {
        if (!$this->isEditorRendering()) return '';
        return " data-tb-editable=\"{$propKey}\" data-tb-inline-type=\"{$inlineType}\"";
    }

    /**
     * In editor_mode, wraps block HTML with canvas selection overlay.
     * No-op in public/production rendering: in MODE_PUBLIC the structural
     * attributes (data-tb-block-id/-type/-container) are emitted instead by
     * wrapPublicStructural() — same runtime-addressable data, zero editor
     * chrome. Gated through isEditorRendering(), so a leaked editor_mode
     * flag can never re-enable the overlay on a public surface.
     */
    protected function wrapEditorMode(string $html, string $id, string $type, array $props = []): string
    {
        if (!$this->isEditorRendering()) {
            if ($this->mode === self::MODE_PUBLIC) {
                return $this->wrapPublicStructural($html, $id, $type, $props);
            }
            return $html;
        }
        $esc   = htmlspecialchars($type, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $idEsc = htmlspecialchars($id,   ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        // TB4: use the same capability seam as recursive rendering so every
        // registered or host-declared container exposes a nested drop zone.
        $containerAttr = $this->isStructuralContainer($type)
            ? ' data-tb-container="1"' : '';
        return "<div class=\"tb-canvas-block\" data-tb-block-id=\"{$idEsc}\" data-tb-block-type=\"{$esc}\" data-widget-type=\"{$esc}\"{$containerAttr}"
            . $this->blockPropsAttr($props)
            . ">"
            . "<button type=\"button\" class=\"tb-drag-handle\" aria-label=\"Move {$esc}\" title=\"Drag to move\">⋮⋮</button>"
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
        // Phase 0.5: a token reference or rgba() from the colour control is a
        // colour, not a class name (it used to become `tb-bg-var(--…)`).
        $bgColor = (!str_starts_with($bg, '#') && !preg_match('/^[a-z][a-z0-9-]*$/', $bg))
            ? $this->cssColor($props['background'] ?? null, '') : '';
        if ($bgColor !== '') {
            $bg = 'transparent';
        }
        $bgImg = $this->escapeAttr($props['background_image'] ?? '');
        $useContainer = ($props['container'] ?? true);
        $inlineStyles = [];
        if ($bgImg) {
            $inlineStyles[] = "background-image: url({$bgImg})";
            $inlineStyles[] = "background-size: cover";
        }
        if ($bgColor !== '') {
            $inlineStyles[] = 'background-color:' . $this->escapeAttr($bgColor);
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
        // WU-D: .tb-layout-grid tells this layout block apart from the card
        // grids that share .tb-grid, so only its children become tb-slot
        // containers (theme-runtime-contract.css).
        return "<div id=\"tb-{$id}\" class=\"tb-grid tb-grid-cols-{$cols} tb-gap-{$gap} tb-layout-grid\">{$inner}</div>";
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
        // Smart Public Rendering (incidental pre-existing fix): the true
        // branch used to re-read $props['direction'] without a fallback —
        // an "Undefined array key" warning (ErrorException under strict
        // hosts/tests) for every direction-less flex_container. Bind once.
        $requestedDirection = (string) ($props['direction'] ?? 'column');
        $direction = in_array($requestedDirection, $allowDir, true) ? $requestedDirection : 'column';

        $gapMap = ['none' => '0', 'xs' => '4px', 'sm' => '8px', 'md' => '16px', 'lg' => '24px', 'xl' => '40px'];
        $gap = $gapMap[$props['gap'] ?? 'md'] ?? '16px';

        $wrap = ($props['wrap'] ?? false) ? 'wrap' : 'nowrap';

        $allowAlign = ['stretch', 'flex-start', 'center', 'flex-end', 'baseline'];
        $align = in_array($requestedAlign = $props['alignItems'] ?? 'stretch', $allowAlign, true)
            ? $requestedAlign : 'stretch';

        $allowJustify = ['flex-start', 'center', 'flex-end', 'space-between', 'space-around', 'space-evenly'];
        $justify = in_array($requestedJustify = $props['justifyContent'] ?? 'flex-start', $allowJustify, true)
            ? $requestedJustify : 'flex-start';

        $minH = max(0, (int)($props['minHeight'] ?? 0));
        $minHStyle = $minH > 0 ? "min-height:{$minH}px;" : '';

        $emptyPlaceholder = '';
        // Smart Public Rendering: editor-only authoring hint — gated through
        // the mode-aware authority (a forced public mode can never show it).
        if (empty(trim($inner)) && $this->isEditorRendering()) {
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
        $btnUrl   = $this->linkHref($props['button_url'] ?? '', '#');
        // Phase 0.5: both were interpolated into the class attribute
        // unescaped. Same background contract as renderCta().
        [$bgClass, $bgStyle] = $this->themeBackground($props['background'] ?? 'primary');
        $align = $props['align'] ?? 'center';
        $align = in_array($align, ['left', 'center', 'right'], true) ? $align : 'center';
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
        $styles = array_filter([$bgStyle, $bgImg ? "background-image:url({$bgImg});background-size:cover;background-position:center;" : '']);
        $styleAttr = $styles ? ' style="' . implode(';', $styles) . '"' : '';
        return "<section id=\"tb-{$id}\" class=\"tb-hero {$bgClass} tb-align-{$align}\"{$styleAttr}><div class=\"tb-hero-inner\"><h1 class=\"tb-hero-title\"{$eaTitle}>{$title}</h1><p class=\"tb-hero-subtitle\"{$eaSub}>{$subtitle}</p>{$btn}</div></section>";
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
     * @param array{script?:string} $options 'script' selects the client-script carrier.
     *   Both values now emit the SAME carrier: the packaged shared assets
     *   (resources/js/blocks/carousel.js — the controller — plus
     *   resources/js/blocks/legacy_carousel.js — the adapter registering the
     *   formerly inline-carried types against the SDK) ONCE per document.
     *   The parameter survives for call-site compatibility; 'inline' is
     *   accepted and treated as 'shared' (the per-instance inline controller
     *   was deleted in this phase, following its introduction in Phase 9.2).
     *
     *   Both carriers run the SAME controller and share the same
     *   data-tb-carousel-init guard, so a page mixing carriers behaves
     *   correctly and no carousel can be initialised twice.
     */
    protected function renderCarousel(
        array $slidesHtml,
        string $id,
        bool $autoplay,
        int $interval,
        string $extraClass = '',
        array $itemsPerView = [],
        bool $pauseOnHover = true,
        bool $fade = false,
        bool $showProgress = false,
        array $thumbnailUrls = [],
        array $options = []
    ): string {
        $slidesHtml = array_values(array_filter($slidesHtml, fn($s) => trim((string) $s) !== ''));
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
        // WU-B: the slide, track and dot styling lives in the shared sheet
        // (.tb-carousel-*), so a theme token or a reduced-motion rule can
        // reach it. Fade mode stacks every slide in one grid cell (the
        // container auto-sizes to the tallest slide); the first slide and
        // the first dot carry their active state from the server, so the
        // carousel is correct before (or without) its script.
        $countLabel = $this->displayNumber($slideCount);
        foreach ($slidesHtml as $i => $html) {
            $num = $this->displayNumber($i + 1);
            $active = $i === 0;
            $slides[] = "<div class=\"tb-carousel-slide\" role=\"group\" aria-roledescription=\"slide\" aria-label=\"اسلاید {$num} از {$countLabel}\">{$html}</div>";
            $dots[] = '<button type="button" class="tb-carousel-dot' . ($active ? ' is-active' : '') . "\" aria-label=\"رفتن به اسلاید {$num}\" aria-current=\"" . ($active ? 'true' : 'false') . '"></button>';
        }

        $autoplayAttr = $autoplay ? 'true' : 'false';
        $pauseAttr = $pauseOnHover ? 'true' : 'false';
        $fadeAttr = $fade ? 'true' : 'false';
        $fadeClass = $fade ? ' tb-carousel--fade' : '';

        $statusHtml = $showProgress
            ? '<div class="tb-carousel-status"><div class="tb-carousel-progress" aria-hidden="true"><span class="tb-carousel-progress-fill"></span></div><button type="button" class="tb-carousel-pause" aria-pressed="false" aria-label="توقف پخش خودکار">Ⅱ</button></div>'
            : '';
        $thumbnailHtml = '';
        if (!empty($thumbnailUrls)) {
            $buttons = [];
            foreach (array_values($thumbnailUrls) as $i => $url) {
                $num = $this->displayNumber($i + 1);
                $buttons[] = '<button type="button" class="tb-carousel-thumb" aria-label="نمایش اسلاید ' . $num . '"><img src="' . $this->escapeAttr((string) $url) . '" alt="" loading="lazy"></button>';
            }
            $thumbnailHtml = '<div class="tb-carousel-thumbs">' . implode('', $buttons) . '</div>';
        }

        // Phase 2 (ADR-067): arrows on logical sides (previous at the reading
        // start, next at the end) pointing outward, styled by the shared sheet;
        // the LTR mirror flips the glyphs.
        // WU-B: the accessible name is the block's own title when the caller
        // has one ($options['label'], plain text), never the internal id.
        $label = trim(is_string($options['label'] ?? null) ? $options['label'] : '');
        $carouselLabel = $this->escapeAttr($label !== '' ? $label : 'اسلایدر');

        // WU-D: the mobile count starts below 480px (portrait phones), so a
        // 480–767px landscape phone keeps the tablet count instead of one
        // stretched card. Inside a columns/grid slot the slot's own width
        // picks the count (@container tb-slot).
        return "<div id=\"tb-{$id}\" class=\"tb-carousel{$fadeClass} {$extraClass}\" role=\"region\" aria-roledescription=\"carousel\" aria-label=\"{$carouselLabel}\" tabindex=\"0\"
            data-autoplay=\"{$autoplayAttr}\" data-interval=\"{$interval}\" data-pause-hover=\"{$pauseAttr}\" data-fade=\"{$fadeAttr}\"
            style=\"--tb-items-desktop:{$desktop};--tb-items-tablet:{$tablet};--tb-items-mobile:{$mobile};\">
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
                @media (max-width:1023.98px){ #tb-{$id}{--tb-items:var(--tb-items-tablet);} }
                @media (max-width:479.98px){ #tb-{$id}{--tb-items:var(--tb-items-mobile);} }
                @container tb-slot (max-width:1023.98px){ #tb-{$id}{--tb-items:var(--tb-items-tablet);} }
                @container tb-slot (max-width:479.98px){ #tb-{$id}{--tb-items:var(--tb-items-mobile);} }
            </style>
            <div class=\"tb-carousel-viewport\">
                <div class=\"tb-carousel-track\">" . implode('', $slides) . "</div>
            </div>
            {$statusHtml}
            <button type=\"button\" class=\"tb-carousel-prev\" aria-label=\"اسلاید قبلی\">" . $this->iconHtml('chevron-right') . "</button>
            <button type=\"button\" class=\"tb-carousel-next\" aria-label=\"اسلاید بعدی\">" . $this->iconHtml('chevron-left') . "</button>
            <div class=\"tb-carousel-dots\" role=\"group\" aria-label=\"انتخاب اسلاید\">" . implode('', $dots) . "</div>
            {$thumbnailHtml}
            {$this->carouselScript($id,$options)}
        </div>";
    }

    /**
     * The client-script carrier for every carousel.
     *
     * In this phase the packaged shared assets became the ONLY carrier: the
     * controller (carousel.js) plus the legacy adapter (legacy_carousel.js,
     * which registers the formerly inline-carried types against the SDK and
     * gives them the destroy() the inline copy never had) are inlined once
     * per document, then reused for every later carousel, so a page with N
     * carousels pays the cost once instead of N times.
     *
     * A missing file throws rather than falling back: the inline carrier is
     * gone BY DESIGN (it was the SDK-002 leak class — an anonymous window
     * listener nothing could ever remove), and a page that silently loses
     * its only interactivity is the failure mode this whole refactor exists
     * to prevent. A packaging fault must be loud.
     */
    private function carouselScript(string $id, array $options): string
    {
        $emissionKey = $this->mode ?? 'neutral';
        if (self::$carouselScriptEmitted[$emissionKey] ?? false) {
            return '';
        }
        self::$carouselScriptEmitted[$emissionKey] = true;

        $controller = @file_get_contents(ThemeRuntimeServiceProvider::carouselBlockScriptPath());
        if (!is_string($controller) || $controller === '') {
            throw new \RuntimeException(
                'Theme runtime packaging fault: the shared carousel controller is missing at '
                    . ThemeRuntimeServiceProvider::carouselBlockScriptPath()
            );
        }

        $adapter = @file_get_contents(ThemeRuntimeServiceProvider::legacyCarouselScriptPath());
        if (!is_string($adapter) || $adapter === '') {
            throw new \RuntimeException(
                'Theme runtime packaging fault: the legacy carousel adapter is missing at '
                    . ThemeRuntimeServiceProvider::legacyCarouselScriptPath()
            );
        }

        return "<script>{$controller}</script>\n<script>{$adapter}</script>";
    }

    /**
     * The client-script carrier for the countdown block.
     *
     * Phase 9.3: the ticking logic used to be written inline into every
     * countdown by renderCountdown(). It now lives in the packaged adapter
     * (countdown_adapter.js) and is inlined from there, once per document,
     * through the same provider-path seam as the carousel carrier.
     *
     * The rendered MARKUP is unchanged — renderCountdown() still emits a
     * <script> element in the same position with the same surrounding
     * whitespace, it just carries the asset instead of an inline copy. What
     * changes is what those bytes can do:
     *
     *   - the interval handle is finally reachable, so onDestroy() can clear
     *     it. The inline copy could only clear its own timer when the
     *     countdown expired, never when the block was replaced;
     *   - the self-guard is no longer poisoned by an unparseable date;
     *   - the same file registers an SDK block on canvas surfaces and
     *     self-initialises on public pages, so the countdown is no longer a
     *     public-only behaviour with no editor lifecycle at all.
     *
     * A missing file throws, for the same reason carouselScript() throws: a
     * countdown that silently ships markup with no ticker is exactly the
     * failure this refactor exists to surface. A packaging fault must be loud.
     */
    private function countdownAdapterScript(): string
    {
        if (self::$countdownAdapterEmitted) {
            // '' and NOT a bare body: returning only the asset would leave the
            // second countdown in a document wrapping an empty string in its
            // own <script> element. The caller interpolates the complete tag,
            // so 'already emitted' means this instance contributes no script
            // at all. Same contract as carouselScript().
            return '';
        }
        self::$countdownAdapterEmitted = true;

        $adapter = @file_get_contents(ThemeRuntimeServiceProvider::countdownAdapterScriptPath());
        if (!is_string($adapter) || $adapter === '') {
            throw new \RuntimeException(
                'Theme runtime packaging fault: the countdown adapter is missing at '
                    . ThemeRuntimeServiceProvider::countdownAdapterScriptPath()
            );
        }

        return "<script>{$adapter}</script>";
    }

    /**
     * Phase 9 / Step 1 — the reference block for the ThemeBlockSDK.
     *
     * This type is deliberately NOT in BlockRegistry: it is a documentation
     * artifact, not a product block. Registering it would change
     * BlockRegistry::all()'s cardinality and therefore the canonical
     * registry-count and registry-coverage invariants that
     * UniversalMultipurposeQaThemeSeederTest owns, and it would put a demo
     * block in the editor palette. Dispatch is unaffected — renderBlock()
     * resolves render{Studly}() by method existence, so any type with a
     * renderer method renders regardless of registry membership.
     *
     * The markup is server-rendered and complete on its own (the block is
     * usable with JS disabled, as every canonical block is); the SDK script
     * only layers interactivity on top.
     *
     * The client script is inlined from the packaged asset via the same
     * provider-path seam as the block SDK itself, so the file a developer
     * copies and the file this renderer emits can never drift. It is emitted
     * ONCE per document: the asset is self-guarding
     * (__tbReferenceCounterRegistered), but re-inlining identical script
     * text once per block instance would be pure page weight.
     */
    protected function renderReferenceCounter(array $props, string $inner, string $id): string
    {
        $step  = (int) ($props['step'] ?? 1);
        $min   = (int) ($props['min'] ?? 0);
        $max   = (int) ($props['max'] ?? 99);
        $value = (int) ($props['value'] ?? $min);
        $value = max($min, min($max, $value));
        $step  = $step === 0 ? 1 : $step;

        $label = $this->escapeAttr($props['label'] ?? 'Block SDK reference counter');
        $stepAttr = $this->escapeAttr((string) $step);
        $valAttr  = $this->escapeAttr((string) $value);

        $script = '';
        if (!self::$referenceBlockScriptEmitted) {
            self::$referenceBlockScriptEmitted = true;
            $source = @file_get_contents(ThemeRuntimeServiceProvider::referenceBlockScriptPath());
            if (is_string($source) && $source !== '') {
                $script = "<script>{$source}</script>";
            }
        }

        return "<div class=\"tb-ref-counter\" data-step=\"{$stepAttr}\" data-value=\"{$valAttr}\" role=\"group\" aria-label=\"{$label}\""
            . " style=\"display:inline-flex;align-items:center;gap:.5rem;padding:.5rem .75rem;border:1px solid #e5e7eb;border-radius:.5rem;background:#fff;\">"
            . "<button type=\"button\" class=\"tb-ref-counter__decrease\" aria-label=\"کاهش\" style=\"min-width:2rem;height:2rem;border:1px solid #d1d5db;border-radius:.375rem;background:#fff;cursor:pointer;font-size:1.1rem;line-height:1;\">−</button>"
            . "<span class=\"tb-ref-counter__value\" aria-live=\"polite\" style=\"min-width:2.5rem;text-align:center;font-weight:600;font-variant-numeric:tabular-nums;\">{$valAttr}</span>"
            . "<button type=\"button\" class=\"tb-ref-counter__increase\" aria-label=\"افزایش\" style=\"min-width:2rem;height:2rem;border:1px solid #d1d5db;border-radius:.375rem;background:#fff;cursor:pointer;font-size:1.1rem;line-height:1;\">+</button>"
            . "<button type=\"button\" class=\"tb-ref-counter__reset\" style=\"border:1px solid #d1d5db;border-radius:.375rem;background:#fff;cursor:pointer;font-size:.75rem;padding:.25rem .5rem;\">بازنشانی</button>"
            . $script
            . "</div>";
    }

    /**
     * A safe, real fallback for a missing or genuinely broken image URL —
     * never a black block, never a raw broken-image browser icon. Empty
     * `$url` renders the fallback immediately (server-side); a non-empty
     * URL that fails to actually load at runtime is caught client-side via
     * `onerror` (there is no safe, fast way to verify an image URL
     * resolves from inside a server-side render call).
     */
    /**
     * WU-B: `$priority` marks the likely LCP image (a hero slider's first
     * slide) — fetched eagerly at high priority instead of lazily.
     */
    protected function imageWithFallback(string $url, string $alt, string $style = '', string $icon = '', bool $priority = false): string
    {
        // Phase 0.6 (ADR-065): $icon is ready HTML; '' = the SVG image icon.
        if ($icon === '') {
            $icon = $this->iconHtml('image');
        }
        $altAttr = $this->escapeAttr($alt);
        if ($url === '') {
            return "<div class=\"tb-img-fallback\" style=\"display:flex;align-items:center;justify-content:center;background:var(--tb-media-placeholder, linear-gradient(135deg,#eef2ff,#f5f3ff));color:#a5b4fc;font-size:2rem;{$style}\" role=\"img\" aria-label=\"{$altAttr}\">{$icon}</div>";
        }
        $urlAttr = $this->escapeAttr($url);
        $loading = $priority ? 'loading="eager" fetchpriority="high" decoding="async"' : 'loading="lazy"';
        return "<div class=\"tb-img-frame\" style=\"position:relative;overflow:hidden;{$style}\">"
            . "<img src=\"{$urlAttr}\" alt=\"{$altAttr}\" {$loading} style=\"width:100%;height:100%;object-fit:cover;display:block;\" onerror=\"this.style.display='none';this.nextElementSibling.style.display='flex';\">"
            . "<div class=\"tb-img-fallback\" style=\"display:none;position:absolute;inset:0;align-items:center;justify-content:center;background:var(--tb-media-placeholder, linear-gradient(135deg,#eef2ff,#f5f3ff));color:#a5b4fc;font-size:2rem;\" role=\"img\" aria-label=\"{$altAttr}\">{$icon}</div>"
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
        $labelEsc = $this->escape($this->displayNumber($label));
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
        // WU-B: styling lives in the shared sheet (.tb-slide*); only the
        // per-block aspect ratio stays inline. The overlay element is always
        // present (its gradient switched off by a modifier) so the markup
        // shape does not depend on the setting.
        $overlayClass = $overlay ? 'tb-slide-overlay' : 'tb-slide-overlay tb-slide-overlay--off';

        $slidesHtml = [];
        $thumbnailUrls = [];
        foreach ($slides as $slide) {
            if (!is_array($slide)) {
                continue;
            }
            $image    = trim($this->mediaSrc($slide['image'] ?? ''));
            $title    = $this->escape($slide['title'] ?? '');
            $subtitle = $this->escape($slide['subtitle'] ?? '');
            $link     = $this->linkHref($slide['link'] ?? '', '');
            $ctaText  = $this->escape($slide['cta_text'] ?? '');

            // The visible slide heading already names the same content;
            // treating the background image as decorative avoids announcing
            // that title twice to screen-reader users.
            // The first slide is the likely LCP image of a hero slider (B4).
            $imgHtml = $this->imageWithFallback($image, '', "position:absolute;inset:0;width:100%;height:100%;", '', $slidesHtml === []);

            $ctaHtml = ($ctaText !== '' && $link !== '')
                ? "<a href=\"{$link}\" class=\"tb-btn tb-btn-primary tb-slide-cta\">{$ctaText}</a>"
                : '';

            $captionHtml = ($title !== '' || $subtitle !== '' || $ctaHtml !== '')
                ? '<div class="tb-slide-caption">'
                . ($title !== '' ? "<h2 class=\"tb-slide-title\">{$title}</h2>" : '')
                . ($subtitle !== '' ? "<p class=\"tb-slide-subtitle\">{$subtitle}</p>" : '')
                . $ctaHtml
                . '</div>'
                : '';

            $slideInner = "<div class=\"tb-slide\" style=\"aspect-ratio:{$aspect};\">"
                . $imgHtml . "<div class=\"{$overlayClass}\"></div>" . $captionHtml . '</div>';

            $slidesHtml[] = ($link !== '' && $ctaHtml === '')
                ? '<a href="' . $link . '" class="tb-slide-link">' . $slideInner . '</a>'
                : $slideInner;
            if ($image !== '') {
                $thumbnailUrls[] = $image;
            }
        }

        if (empty($slidesHtml)) {
            return '';
        }

        $wrapClass = 'tb-slider' . ($boxed ? ' tb-slider-boxed' : ' tb-slider-full') . ($transition === 'fade' ? ' tb-slider-fade' : '');

        $carousel = $this->renderCarousel(
            $slidesHtml,
            $id,
            (bool) ($props['autoplay'] ?? true),
            (int) ($props['interval'] ?? 5000),
            $wrapClass,
            [],
            true,
            $transition === 'fade',
            true,
            $thumbnailUrls
        );

        return $boxed ? "<div class=\"tb-slider-box\">{$carousel}</div>" : $carousel;
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

    // ─── Phase 3A basic commercial foundation renderers ────────────────
    // Conventions match the canonical set: tb-* contract classes, escape/
    // escapeAttr/escapeUrl/sanitizeHtml, editableAttr() inline edit hook,
    // logical (start/end) alignment that the contract CSS resolves per
    // document direction — no physical left/right persisted.

    protected function renderSpacer(array $props, string $inner, string $id): string
    {
        $h = max(0, min(600, (int) ($props['height'] ?? 48)));
        return "<div id=\"tb-{$id}\" class=\"tb-spacer\" style=\"height:{$h}px\" aria-hidden=\"true\"></div>";
    }

    protected function renderRichText(array $props, string $inner, string $id): string
    {
        $content = $this->sanitizeHtml($props['content'] ?? '');
        $ea = $this->editableAttr('content', 'html');
        return "<div id=\"tb-{$id}\" class=\"tb-rich-text prose\"{$ea}>{$content}</div>";
    }

    protected function renderIconBox(array $props, string $inner, string $id): string
    {
        $icon = $this->iconHtml($props['icon'] ?? '');
        $title = $this->escape($props['title'] ?? '');
        $text = $this->escape($props['text'] ?? '');
        $url = $this->linkHref($props['link'] ?? '', '');
        $alignMap = ['start' => 'tb-align-start', 'center' => 'tb-align-center', 'end' => 'tb-align-end'];
        $align = $alignMap[$props['align'] ?? 'start'] ?? 'tb-align-start';
        $styleMap = ['plain' => 'tb-icon-box-plain', 'card' => 'tb-icon-box-card', 'outline' => 'tb-icon-box-outline', 'solid' => 'tb-icon-box-solid'];
        $style = $styleMap[$props['style'] ?? 'card'] ?? 'tb-icon-box-card';
        $titleHtml = $title !== '' ? "<h3 class=\"tb-icon-box-title\">{$title}</h3>" : '';
        $textHtml = $text !== '' ? "<p class=\"tb-icon-box-text\">{$text}</p>" : '';
        $iconHtml = $icon !== '' ? "<span class=\"tb-icon-box-icon\" aria-hidden=\"true\">{$icon}</span>" : '';
        $body = "{$iconHtml}{$titleHtml}{$textHtml}";
        if ($url !== '') {
            return "<a id=\"tb-{$id}\" href=\"{$url}\" class=\"tb-icon-box {$align} {$style}\">{$body}</a>";
        }
        return "<div id=\"tb-{$id}\" class=\"tb-icon-box {$align} {$style}\">{$body}</div>";
    }

    protected function renderImageBox(array $props, string $inner, string $id): string
    {
        $url = $this->escapeUrl($this->mediaSrc($props['url'] ?? ''), '');
        $title = $this->escape($props['title'] ?? '');
        $text = $this->escape($props['text'] ?? '');
        $link = $this->linkHref($props['link'] ?? '', '');
        $ratioMap = ['16/9' => 'tb-ratio-16-9', '4/3' => 'tb-ratio-4-3', '1/1' => 'tb-ratio-1-1'];
        $ratio = $ratioMap[$props['ratio'] ?? '4/3'] ?? 'tb-ratio-4-3';
        $align = ($props['align'] ?? 'start') === 'center' ? 'tb-align-center' : 'tb-align-start';
        $style = ($props['style'] ?? 'card') === 'plain' ? 'tb-image-box-plain' : 'tb-image-box-card';
        $img = $url !== ''
            ? "<img src=\"{$url}\" alt=\"{$this->escapeAttr($title)}\" loading=\"lazy\" class=\"tb-img\">"
            : '<span class="tb-image-box-placeholder">تصویر انتخاب نشده</span>';
        $imgHtml = "<div class=\"tb-image-box-media {$ratio}\">{$img}</div>";
        $titleHtml = $title !== '' ? "<h3 class=\"tb-image-box-title\">{$title}</h3>" : '';
        $textHtml = $text !== '' ? "<p class=\"tb-image-box-text\">{$text}</p>" : '';
        $body = "{$imgHtml}<div class=\"tb-image-box-body\">{$titleHtml}{$textHtml}</div>";
        if ($link !== '') {
            return "<a id=\"tb-{$id}\" href=\"{$link}\" class=\"tb-image-box {$align} {$style}\">{$body}</a>";
        }
        return "<div id=\"tb-{$id}\" class=\"tb-image-box {$align} {$style}\">{$body}</div>";
    }

    protected function renderBadge(array $props, string $inner, string $id): string
    {
        $text = $this->escape($props['text'] ?? '');
        $rawStyle = $props['style'] ?? 'primary';
        $style = in_array($rawStyle, ['primary', 'secondary', 'accent', 'outline'], true) ? $rawStyle : 'primary';
        return "<span id=\"tb-{$id}\" class=\"tb-badge tb-badge-{$style}\">{$text}</span>";
    }

    // ─── Phase 3B — professional Persian hero family renderers ──────────
    // Additive only: containers receive $inner automatically through
    // isStructuralContainer() → BlockRegistry::isContainerType() — zero
    // changes to renderBlock() dispatch. Every enum value is allowlisted
    // BEFORE interpolation (defense in depth alongside escapeAttr — same
    // discipline as renderButton()'s style fix). All geometry is logical
    // (block/inline; text-align start/end resolves per document direction
    // natively) — no dir attribute, no RTL/LTR branching here; direction
    // projection stays with DirectionProjector + live-preview (3A fix).

    protected function renderHeroSection(array $props, string $inner, string $id): string
    {
        $bgType = in_array($props['background_type'] ?? '', ['color', 'gradient', 'image'], true)
            ? $props['background_type'] : 'gradient';
        // Logical alignment (start/center/end) — direction-resolved by CSS.
        $align = in_array($props['text_align'] ?? '', ['start', 'center', 'end'], true)
            ? $props['text_align'] : 'start';
        $vAlignMap = ['start' => 'tb-hero-vstart', 'center' => 'tb-hero-vcenter', 'end' => 'tb-hero-vend'];
        $vAlign = $vAlignMap[$props['v_align'] ?? 'center'] ?? 'tb-hero-vcenter';
        $widthMap = ['narrow' => 'tb-hero-w-narrow', 'normal' => 'tb-hero-w-normal', 'wide' => 'tb-hero-w-wide', 'full' => 'tb-hero-w-full'];
        $width = $widthMap[$props['content_width'] ?? 'normal'] ?? 'tb-hero-w-normal';

        $style = sprintf(
            'min-height:%dpx;padding-block:%dpx;',
            max(0, min(1200, (int) ($props['min_height'] ?? 520))),
            max(0, min(240, (int) ($props['padding_v'] ?? 96)))
        );
        if ($bgType === 'color') {
            $style .= 'background-color:' . $this->escapeAttr($this->cssColor($props['bg_color'] ?? null, 'var(--tb-primary, #4f46e5)')) . ';';
        } elseif ($bgType === 'gradient') {
            $style .= sprintf(
                'background-image:linear-gradient(115deg,%s,%s);',
                $this->escapeAttr($this->cssColor($props['gradient_from'] ?? null, 'var(--tb-primary, #4f46e5)')),
                $this->escapeAttr($this->cssColor($props['gradient_to'] ?? null, 'var(--tb-secondary, #7c3aed)'))
            );
        } elseif ($bgType === 'image' && $this->mediaSrc($props['bg_image'] ?? '') !== '') {
            // Same inline background pattern renderSection()/renderHero()
            // already use for background_image.
            $style .= 'background-image:url(' . $this->escapeAttr($props['bg_image']) . ');background-size:cover;background-position:center;';
        }
        // Image scrim: fixed dark color, author controls ONLY the opacity —
        // contrast can never be broken by a bad color choice.
        $overlay = '';
        if ($bgType === 'image') {
            $opacity = max(0, min(90, (int) ($props['overlay_opacity'] ?? 40))) / 100;
            $overlay = '<div class="tb-hero-overlay" style="opacity:' . $opacity . '" aria-hidden="true"></div>';
        }

        return "<section id=\"tb-{$id}\" class=\"tb-hero-section tb-hero-align-{$align} {$vAlign} {$width}\" style=\"{$style}\">"
            . $overlay
            . '<div class="tb-hero-frame">' . $inner . '</div>'
            . '</section>';
    }

    protected function renderHeroContainer(array $props, string $inner, string $id): string
    {
        $maxWidth = max(0, min(1600, (int) ($props['max_width'] ?? 1200)));
        $gap = max(0, min(96, (int) ($props['gap'] ?? 32)));
        $style = ($maxWidth > 0 ? "max-width:{$maxWidth}px;" : 'max-width:none;')
            . "padding-inline:24px;gap:{$gap}px;";
        return "<div id=\"tb-{$id}\" class=\"tb-hero-container\" style=\"{$style}\">{$inner}</div>";
    }

    protected function renderHeroContentWrapper(array $props, string $inner, string $id): string
    {
        $gap = max(0, min(64, (int) ($props['gap'] ?? 20)));
        $maxWidth = max(0, min(900, (int) ($props['max_width'] ?? 640)));
        $titleSize = max(1.5, min(6, (float) ($props['title_size'] ?? 3.25)));
        // Title size/color ride CSS custom properties scoped to this
        // subtree — heading/text children remain untouched primitives.
        $style = "gap:{$gap}px;max-width:{$maxWidth}px;"
            . '--tb-hero-title-size:' . $titleSize . 'rem;'
            . '--tb-hero-title-color:' . $this->escapeAttr($this->cssColor($props['title_color'] ?? null, 'var(--tb-on-primary, #ffffff)')) . ';';
        return "<div id=\"tb-{$id}\" class=\"tb-hero-content\" style=\"{$style}\">{$inner}</div>";
    }

    protected function renderHeroActions(array $props, string $inner, string $id): string
    {
        $gap = max(0, min(48, (int) ($props['gap'] ?? 16)));
        $radius = max(0, min(32, (int) ($props['radius'] ?? 12)));
        $align = in_array($requestedAlign = $props['align'] ?? '', ['start', 'center', 'end'], true)
            ? $requestedAlign : 'start';
        // Radius is scoped via --tb-hero-btn-radius; the canonical .tb-btn
        // primitive is untouched outside this subtree.
        $style = "gap:{$gap}px;--tb-hero-btn-radius:{$radius}px;";
        return "<div id=\"tb-{$id}\" class=\"tb-hero-actions tb-hero-actions-{$align}\" style=\"{$style}\">{$inner}</div>";
    }

    protected function renderList(array $props, string $inner, string $id): string
    {
        $items = $props['items'] ?? [];
        if (!is_array($items)) {
            $items = [];
        }
        $rawStyle = $props['style'] ?? 'disc';
        $style = in_array($rawStyle, ['disc', 'ordered', 'check', 'plain'], true) ? $rawStyle : 'disc';
        $lis = '';
        $i = 0;
        foreach ($items as $item) {
            $text = is_array($item) ? ($item['text'] ?? '') : (string) $item;
            $text = $this->escape($text);
            if (trim($text) === '') {
                continue;
            }
            $ea = $this->editableAttr("items.{$i}.text", 'text');
            $lis .= "<li{$ea}>{$text}</li>";
            $i++;
        }
        if ($lis === '') {
            $lis = '<li></li>';
        }
        if ($style === 'ordered') {
            return "<ol id=\"tb-{$id}\" class=\"tb-list tb-list-ordered\">{$lis}</ol>";
        }
        $cls = $style === 'check' ? ' tb-list-check' : ($style === 'plain' ? ' tb-list-plain' : '');
        return "<ul id=\"tb-{$id}\" class=\"tb-list{$cls}\">{$lis}</ul>";
    }

    protected function renderImage(array $props, string $inner, string $id): string
    {
        $url     = $this->escapeUrl($this->mediaSrc($props['url'] ?? ''), '');
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
        $url   = $this->linkHref($props['url'] ?? '', '#');
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
        // Phase 0.2c: allowlisted — the raw value was interpolated into
        // the class attribute unescaped.
        $location = ($props['location'] ?? 'header') === 'footer' ? 'footer' : 'header';
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
        if (isset($props['style'])) {
            return $this->renderStyledSocialIcons(is_array($links) ? $links : [], (string) $props['style'], $id);
        }
        $items = [];
        foreach ($links as $link) {
            $url = $this->linkHref(is_array($link) ? ($link['url'] ?? '') : $link, '#');
            $label = $this->escape(is_array($link) ? ($link['label'] ?? '') : '');
            $items[] = "<a href=\"{$url}\" class=\"tb-social-link\" target=\"_blank\" rel=\"noopener noreferrer\" aria-label=\"{$label}\">{$label}</a>";
        }
        return "<div id=\"tb-{$id}\" class=\"tb-social-icons\">" . implode('', $items) . "</div>";
    }

    /**
     * Phase 0.2c: the `style` variants promoted from customer-site, with
     * its markup unchanged — `icons` shows each label's first letter,
     * `buttons` renders secondary buttons, `text` full labels. Unauthored
     * `style` keeps the original markup above.
     */
    protected function renderStyledSocialIcons(array $links, string $style, string $id): string
    {
        $style = in_array($style, ['buttons', 'text'], true) ? $style : 'icons';
        $items = [];
        foreach ($links as $link) {
            if (!is_array($link)) {
                continue;
            }
            $url   = $this->linkHref($link['url'] ?? '#', '#');
            $label = $this->escape($link['label'] ?? '');
            $shown = $style === 'icons' ? mb_substr($label, 0, 1) : $label;
            $cls = $style === 'buttons' ? 'tb-btn tb-btn-secondary' : 'tb-social-link';
            $items[] = "<a href=\"{$url}\" target=\"_blank\" rel=\"noopener noreferrer\" class=\"{$cls}\" aria-label=\"{$label}\">{$shown}</a>";
        }

        return "<div id=\"tb-{$id}\" class=\"tb-social-icons tb-social-icons-{$style}\" style=\"display:flex;gap:0.5rem;flex-wrap:wrap;\">" . implode('', $items) . "</div>";
    }

    /** Theme colour tokens with a `.tb-bg-*` rule in the shared CSS contracts. */
    private const CTA_BACKGROUND_TOKENS = ['primary', 'secondary', 'surface', 'background', 'text', 'dark', 'light', 'gray'];

    /**
     * The background contract shared by renderCta() and renderHero()
     * (Phase 0.2c, extended in 0.5): a theme token keeps its `tb-bg-*`
     * class; a valid CSS colour — hex, rgba(), or a var(--tb-…) the colour
     * control stores — is applied inline with a readable text colour when
     * one can be computed; anything else falls back to the primary token.
     *
     * @return array{0: string, 1: string} [class, inline style ('' for none)]
     */
    protected function themeBackground(mixed $value): array
    {
        $bg = trim($this->stringify($value));
        if (in_array($bg, self::CTA_BACKGROUND_TOKENS, true)) {
            return ["tb-bg-{$bg}", ''];
        }
        $color = $this->cssColor($bg, '');
        if ($color === '') {
            return ['tb-bg-primary', ''];
        }
        $text = $this->readableTextColorOn($color);
        return ['tb-bg-custom', 'background:' . $this->escapeAttr($color) . ($text ? ';color:' . $text : '')];
    }

    protected function renderCta(array $props, string $inner, string $id): string
    {
        $title    = $this->escape($props['title'] ?? '');
        $subtitle = $this->escape($props['subtitle'] ?? '');
        $btnText  = $this->escape($props['button_text'] ?? '');
        $btnUrl   = $this->linkHref($props['button_url'] ?? '', '#');
        // Phase 0.2c: `background` was interpolated into the class
        // attribute unescaped, and a CSS colour (what customer-site's colour
        // control stores) became a class with no rule, so the chosen colour
        // never showed. See themeBackground().
        [$bgClass, $bgStyle] = $this->themeBackground($props['background'] ?? 'primary');
        $styleAttr = $bgStyle !== '' ? ' style="' . $bgStyle . '"' : '';
        $eaTitle  = $this->editableAttr('title', 'text');
        $eaSub    = $this->editableAttr('subtitle', 'text');
        $eaBtn    = $this->editableAttr('button_text', 'text');
        $btn = $btnText ? "<a href=\"{$btnUrl}\" class=\"tb-btn tb-btn-primary\"{$eaBtn}>{$btnText}</a>" : '';
        return "<section id=\"tb-{$id}\" class=\"tb-cta {$bgClass}\"{$styleAttr}><h2 class=\"tb-cta-title\"{$eaTitle}>{$title}</h2><p class=\"tb-cta-subtitle\"{$eaSub}>{$subtitle}</p>{$btn}</section>";
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
        // Phase 0.6 (ADR-065): a catalog name is an SVG labelled in Persian;
        // a legacy emoji/text keeps its original markup.
        if (IconLibrary::has($name)) {
            $svg = IconLibrary::svg($name, IconLibrary::label($name));
            return "<span id=\"tb-{$id}\" class=\"tb-icon\" style=\"display:inline-block;font-size:{$safeSize};line-height:1;\">{$svg}</span>";
        }
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
            <div class=\"tb-progress-track\" style=\"background:var(--tb-border, #e5e7eb);border-radius:999px;height:0.6rem;overflow:hidden;\" role=\"progressbar\" aria-valuenow=\"{$value}\" aria-valuemin=\"0\" aria-valuemax=\"100\"" . ($label !== '' ? " aria-label=\"{$label}\"" : '') . ">
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
            <div id=\"tb-{$id}-dialog\" class=\"tb-modal-overlay\" style=\"display:none;position:fixed;inset:0;background:var(--tb-backdrop, rgba(15,23,42,.55));z-index:1000;align-items:center;justify-content:center;padding:1rem;\" role=\"dialog\" aria-modal=\"true\">
                <div class=\"tb-modal-panel\" style=\"background:var(--tb-background, #fff);border-radius:0.75rem;max-width:36rem;width:100%;max-height:85vh;overflow-y:auto;padding:1.5rem;position:relative;\">
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
        $btn1u   = $this->linkHref($props['button_url'] ?? '', '#');
        $btn2t   = $this->escape($props['button2_text'] ?? '');
        $btn2u   = $this->linkHref($props['button2_url'] ?? '#');
        $imgUrl  = $this->escapeAttr($props['image_url'] ?? '');
        $imgSide = $props['image_side'] ?? 'left';
        $bg      = $this->escapeAttr($this->cssColor($props['background'] ?? null, 'var(--tb-background, #ffffff)'));

        $badgeHtml = $badge ? "<span class=\"tb-hero-split-badge\">{$badge}</span>" : '';
        $btn1Html  = $btn1t ? "<a href=\"{$btn1u}\" class=\"tb-btn tb-btn-primary\">{$btn1t}</a>" : '';
        $btn2Html  = $btn2t ? "<a href=\"{$btn2u}\" class=\"tb-btn tb-btn-secondary\">{$btn2t}</a>" : '';
        $imgHtml   = $imgUrl
            ? "<img src=\"{$imgUrl}\" alt=\"{$title}\" class=\"tb-hero-split-img\" loading=\"eager\">"
            : "<div class=\"tb-hero-split-img-placeholder\">" . $this->iconHtml('image') . "</div>";

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
        $btn1u   = $this->linkHref($props['button_url'] ?? '', '#');
        $btn2t   = $this->escape($props['button2_text'] ?? '');
        $btn2u   = $this->linkHref($props['button2_url'] ?? '#');
        // Commercial Productization fase-3 (visual identity audit): a real,
        // confirmed bug — these defaults were hardcoded generic indigo/
        // violet hex values, completely independent of the Theme's own
        // configured --tb-primary/--tb-secondary. Every Theme whose hero
        // used this block's default colors (i.e. never overrode
        // gradient_from/gradient_to) rendered the same generic purple
        // regardless of its actual brand palette — an explicit author
        // override of course still wins.
        $gFrom   = $this->escapeAttr($this->cssColor($props['gradient_from'] ?? null, 'var(--tb-primary, #4f46e5)'));
        $gTo     = $this->escapeAttr($this->cssColor($props['gradient_to'] ?? null, 'var(--tb-secondary, #7c3aed)'));
        $tColor  = $this->escapeAttr($this->cssColor($props['text_color'] ?? null, 'var(--tb-on-primary, #ffffff)'));

        $badgeHtml = $badge ? "<span class=\"tb-hero-gradient-badge\">{$badge}</span><br>" : '';
        $btn1Html  = $btn1t ? "<a href=\"{$btn1u}\" class=\"tb-btn\" style=\"background:var(--tb-on-primary, #fff);color:{$gFrom};font-weight:700;\">{$btn1t}</a>" : '';
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

        // Phase 3C: prefer the canonical child tree whenever children are
        // present. The JSON path below is intentionally retained verbatim as
        // the migration-free fallback for existing themes.
        if ($inner !== '') {
            return "<section id=\"tb-{$id}\" class=\"tb-commercial-features-grid tb-commercial-features-{$style}\">
                <div class=\"tb-container\">{$headerHtml}
                    <div class=\"tb-commercial-grid tb-commercial-grid-cols-{$columns}\">{$inner}</div>
                </div>
            </section>";
        }

        $cards = [];
        foreach ($features as $f) {
            $icon  = $this->iconHtml($f['icon'] ?? 'sparkles');
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
        $bg       = $this->escapeAttr($this->cssColor($props['background'] ?? null, 'var(--tb-surface, #f8fafc)'));
        $backgroundImage = $this->escapeUrl($this->mediaSrc($props['background_image'] ?? ''), '');
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
            $shown  = $this->escape($this->displayNumber($s['value'] ?? '0'));
            $suffix = $this->escape($this->displayNumber($s['suffix'] ?? ''));
            $label  = $this->escape($s['label'] ?? '');
            if ($dividers && $i > 0) {
                $items[] = '<div class="tb-stat-divider" aria-hidden="true"></div>';
            }
            $items[] = "<div class=\"tb-stat-item\">
                <span class=\"tb-stat-value\"{$textColorAttr} data-count=\"{$val}\">{$shown}<span class=\"tb-stat-suffix\"{$textColorAttr}>{$suffix}</span></span>
                <span class=\"tb-stat-label\"{$textColorAttr}>{$label}</span>
            </div>";
        }

        $backgroundStyle = $backgroundImage !== ''
            ? "background-color:{$bg};background-image:linear-gradient(90deg,var(--tb-overlay, rgba(5,28,43,.94)),var(--tb-overlay, rgba(5,28,43,.68))),url('{$backgroundImage}');background-size:cover;background-position:center;"
            : "background:{$bg};";

        // Phase 3C compositional path. Existing JSON-authored stats continue
        // through the unchanged fallback below when no children exist.
        if ($inner !== '') {
            $dividerClass = $dividers ? ' tb-commercial-stats-divided' : '';
            return "<section id=\"tb-{$id}\" class=\"tb-commercial-stats-row{$dividerClass}\" style=\"{$backgroundStyle}\">
                <div class=\"tb-container\">{$titleHtml}
                    <div class=\"tb-commercial-stats-grid\">{$inner}</div>
                </div>
            </section>";
        }

        return "<div id=\"tb-{$id}\" class=\"tb-stats-row\" style=\"{$backgroundStyle}\">
            <div class=\"tb-container\">{$titleHtml}
                <div class=\"tb-stats-grid\">" . implode('', $items) . "</div>
            </div>
        </div>";
    }

    protected function renderStatItem(array $props, string $inner, string $id): string
    {
        $value = $this->escape((string) ($props['value'] ?? '100'));
        $shown = $this->escape($this->displayNumber($props['value'] ?? '100'));
        $suffix = $this->escape($this->displayNumber($props['suffix'] ?? '+'));
        $label = $this->escape((string) ($props['label'] ?? ''));
        $emphasis = (bool) ($props['emphasis'] ?? false) ? ' tb-stat-item-emphasis' : '';

        return "<div id=\"tb-{$id}\" class=\"tb-stat-item{$emphasis}\">"
            . "<span class=\"tb-stat-value\" data-count=\"{$value}\">{$shown}<span class=\"tb-stat-suffix\">{$suffix}</span></span>"
            . "<span class=\"tb-stat-label\">{$label}</span>"
            . '</div>';
    }

    protected function renderCtaBand(array $props, string $inner, string $id): string
    {
        $align = in_array($requestedAlign = $props['align'] ?? '', ['start', 'center', 'end'], true)
            ? $requestedAlign : 'center';
        $padding = max(16, min(160, (int) ($props['padding'] ?? 48)));
        $radius = max(0, min(48, (int) ($props['radius'] ?? 16)));
        $background = $this->escapeAttr($this->cssColor($props['background'] ?? null, 'var(--tb-primary, #4f46e5)'));
        $textColor = $this->escapeAttr($this->cssColor($props['text_color'] ?? null, 'var(--tb-on-primary, #ffffff)'));
        $style = "--tb-cta-band-bg:{$background};--tb-cta-band-text:{$textColor};"
            . "--tb-cta-band-padding:{$padding}px;--tb-cta-band-radius:{$radius}px;";

        return "<section id=\"tb-{$id}\" class=\"tb-cta-band tb-cta-band-align-{$align}\" style=\"{$style}\">"
            . '<div class="tb-cta-band-inner">' . $inner . '</div>'
            . '</section>';
    }

    protected function renderTrustBadgesRow(array $props, string $inner, string $id): string
    {
        $columns = max(2, min(4, (int) ($props['columns'] ?? 4)));
        $gap = max(0, min(64, (int) ($props['gap'] ?? 20)));
        $background = $this->escapeAttr($this->cssColor($props['background'] ?? null, 'var(--tb-background, #ffffff)'));
        $style = "--tb-trust-gap:{$gap}px;--tb-trust-bg:{$background};";

        return "<section id=\"tb-{$id}\" class=\"tb-trust-badges-row tb-commercial-grid-cols-{$columns}\" style=\"{$style}\">"
            . $inner
            . '</section>';
    }

    protected function renderTrustItem(array $props, string $inner, string $id): string
    {
        $icon = $this->iconHtml($props['icon'] ?? 'badge-check');
        $label = $this->escape((string) ($props['label'] ?? ''));
        $description = $this->escape((string) ($props['description'] ?? ''));
        $style = in_array($requestedStyle = $props['style'] ?? '', ['plain', 'card', 'outline'], true)
            ? $requestedStyle : 'plain';
        $content = '<span class="tb-trust-item-icon" aria-hidden="true">' . $icon . '</span>'
            . '<span class="tb-trust-item-content">'
            . '<strong class="tb-trust-item-label">' . $label . '</strong>'
            . ($description !== '' ? '<span class="tb-trust-item-description">' . $description . '</span>' : '')
            . '</span>';
        $url = $this->linkValue($props['url'] ?? '');

        if ($url !== '') {
            $content = '<a class="tb-trust-item-link" href="' . $this->escapeUrl($url, '#') . '">' . $content . '</a>';
        } else {
            $content = '<span class="tb-trust-item-body">' . $content . '</span>';
        }

        return "<div id=\"tb-{$id}\" class=\"tb-trust-item tb-trust-item-{$style}\">{$content}</div>";
    }

    // ─── Phase 3C / Milestone 3 — Interaction widgets ──────────────────────
    // Additive: these are host-registered types (not canonical) and render
    // purely as sanitized intention emitters — zero business logic in the
    // output. All targets are revalidated server-side by InteractionPolicy
    // before any side effect executes.

    protected function renderInteractiveForm(array $props, string $inner, string $id): string
    {
        $title = $this->escape($props['title'] ?? '');
        $submitLabel = $this->escape($props['submit_label'] ?? '');
        $endpoint = $this->linkHref($props['endpoint_url'] ?? '', '');
        $successMessage = $this->escape($props['success_message'] ?? '');
        $errorMessage = $this->escape($props['error_message'] ?? '');

        $policy = $this->context['interaction_policy'] ?? null;
        $interactionConfig = $props['interaction_config'] ?? [];

        $allowed = true;
        if ($policy instanceof \SaaSPlatform\ThemeRuntime\Interaction\InteractionPolicy) {
            $event = \SaaSPlatform\ThemeRuntime\Interaction\InteractionEvent::fromArray([
                'event_type' => 'submit',
                'target_id' => $id,
                'payload' => ['target' => $endpoint],
                'action_trigger' => 'call_api',
            ]);
            $allowed = $policy->allows($event);
        }

        $interactionData = [
            'event_type' => 'submit',
            'target_id' => $id,
            'payload' => ['target' => $endpoint],
            'action_trigger' => 'call_api',
        ];
        $interactionJson = htmlspecialchars(json_encode($interactionData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8');

        $blockedAttr = $allowed ? '' : ' data-tb-interaction-blocked="1"';
        $interactionAttr = $allowed ? " data-tb-interaction=\"{$interactionJson}\"" : '';

        $titleHtml = $title ? "<h3 class=\"tb-form-title\">{$title}</h3>" : '';
        $successHtml = $successMessage ? "<div class=\"tb-form-success\" hidden>{$successMessage}</div>" : '';
        $errorHtml = $errorMessage ? "<div class=\"tb-form-error\" hidden>{$errorMessage}</div>" : '';

        $formFields = $inner ?: '
            <div class="tb-form-field">
                <label for="tb-' . $id . '-email" class="tb-form-label">ایمیل</label>
                <input type="email" id="tb-' . $id . '-email" name="email" class="tb-form-input" required>
            </div>
            <div class="tb-form-field">
                <label for="tb-' . $id . '-message" class="tb-form-label">پیام</label>
                <textarea id="tb-' . $id . '-message" name="message" class="tb-form-input" rows="4"></textarea>
            </div>';

        return "<form id=\"tb-{$id}\" class=\"tb-interactive-form\"{$interactionAttr}{$blockedAttr} novalidate>"
            . $titleHtml
            . $formFields
            . $successHtml
            . $errorHtml
            . "<button type=\"submit\" class=\"tb-btn tb-btn-primary\">{$submitLabel}</button>"
            . '</form>';
    }

    protected function renderActionButton(array $props, string $inner, string $id): string
    {
        $label = $this->escape($props['label'] ?? '');
        $actionType = $props['action_type'] ?? 'link';
        $targetValue = $props['target_value'] ?? '#';
        $style = in_array($requestedStyle = $props['style'] ?? '', ['primary', 'secondary', 'outline', 'ghost'], true)
            ? $requestedStyle : 'primary';
        $icon = $this->iconHtml($props['icon'] ?? '');

        $policy = $this->context['interaction_policy'] ?? null;

        $actionTriggerMap = [
            'link' => 'navigate',
            'api_call' => 'call_api',
            'scroll_to' => 'scroll_to',
        ];
        $actionTrigger = $actionTriggerMap[$actionType] ?? 'navigate';

        $allowed = true;
        if ($policy instanceof \SaaSPlatform\ThemeRuntime\Interaction\InteractionPolicy) {
            $event = \SaaSPlatform\ThemeRuntime\Interaction\InteractionEvent::fromArray([
                'event_type' => 'click',
                'target_id' => $id,
                'payload' => ['target' => $targetValue],
                'action_trigger' => $actionTrigger,
            ]);
            $allowed = $policy->allows($event);
        }

        $interactionData = [
            'event_type' => 'click',
            'target_id' => $id,
            'payload' => ['target' => $targetValue],
            'action_trigger' => $actionTrigger,
        ];
        $interactionJson = htmlspecialchars(json_encode($interactionData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8');

        $blockedAttr = $allowed ? '' : ' data-tb-interaction-blocked="1"';
        $interactionAttr = $allowed ? " data-tb-interaction=\"{$interactionJson}\"" : '';

        $iconHtml = $icon ? "<span class=\"tb-btn-icon\" aria-hidden=\"true\">{$icon}</span>" : '';

        $styleClass = match ($style) {
            'secondary' => 'tb-btn-secondary',
            'outline' => 'tb-btn-outline',
            'ghost' => 'tb-btn-ghost',
            default => 'tb-btn-primary',
        };

        return "<button id=\"tb-{$id}\" type=\"button\" class=\"tb-btn {$styleClass} tb-action-button\"{$interactionAttr}{$blockedAttr}>"
            . $iconHtml
            . "<span class=\"tb-btn-label\">{$label}</span>"
            . '</button>';
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
            $image = trim($this->mediaSrc($l['image'] ?? ''));
            $url   = $this->linkHref($l['url'] ?? '', '');
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
                true,
                options: ['label' => html_entity_decode($title, ENT_QUOTES | ENT_HTML5, 'UTF-8')]
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
        // Phase 2 (ADR-067): every product card is productCard(); this
        // positional signature stays for existing callers.
        $stockState = $stockLabel === null ? null : (str_contains($stockLabel, 'ناموجود') ? 'out' : (str_contains($stockLabel, 'باقی') ? 'low' : 'in'));
        return $this->productCard([
            'title' => $title,
            'image' => $image,
            'url' => $url,
            'price' => $price,
            'original' => $originalPrice,
            'price_html' => $priceFormatted,
            'original_html' => $originalPriceFormatted,
            'badge' => $badge,
            'stock' => $stockState,
            'stock_label' => $stockLabel,
            'action_html' => $addToCartHtml !== '' ? $addToCartHtml : null,
        ]);
    }

    /**
     * Phase 2 (ADR-067): the one commercial product card — demo fixtures in
     * the Builder preview, real products on customer-site.
     *
     * Keys: title, image, url, price, original, price_html (Toman, ready
     * HTML), original_html, badge (free text), is_new (bool), stock
     * (in|low|out), stock_label, rating (0–5), reviews, product_id,
     * needs_options (bool), action_html (overrides the button).
     *
     *  - the image carries derived badge overlays (Phase 3, ADR-068), in this
     *    order: the real discount percentage («۲۱٪ تخفیف»), «پرامتیاز» for an
     *    approved-review average of TOP_RATED_MIN_RATING over at least
     *    TOP_RATED_MIN_REVIEWS reviews, «جدید» for is_new, then the free-text
     *    badge — never more than three;
     *  - stock is a coloured pill; out of stock disables the action;
     *  - with host cart endpoints the action is an instant add-to-cart form
     *    (blocks/cart.js posts it with fetch and opens the mini-cart; without
     *    JavaScript it is a plain POST); a product with options links to its
     *    page; the Builder preview shows the same button as a link.
     */
    protected function productCard(array $p): string
    {
        $title = (string) ($p['title'] ?? '');
        $titleEsc = $this->escape($title);
        $urlAttr = $this->escapeUrl($this->linkValue($p['url'] ?? '#'), '#');
        $imgHtml = $this->imageWithFallback($this->mediaSrc($p['image'] ?? ''), $title, 'aspect-ratio:1/1;', $this->iconHtml('package'));
        $price = isset($p['price']) ? (float) $p['price'] : null;
        $original = isset($p['original']) ? (float) $p['original'] : null;

        $percent = ($original !== null && $price !== null && $original > $price && $original > 0) ? (int) round(($original - $price) / $original * 100) : 0;
        $rating = isset($p['rating']) ? max(0.0, min(5.0, (float) $p['rating'])) : 0.0;
        $reviews = max(0, (int) ($p['reviews'] ?? 0));

        // The shared Badge primitive (badgeHtml()), never a second badge style.
        $ribbons = [];
        if ($percent > 0) {
            $ribbons['تخفیف'] = $this->badgeHtml($this->displayNumber($percent . '%') . ' تخفیف', 'error', 'sm');
        }
        if ($rating >= self::TOP_RATED_MIN_RATING && $reviews >= self::TOP_RATED_MIN_REVIEWS) {
            $ribbons['پرامتیاز'] = $this->badgeHtml('پرامتیاز', 'warning', 'sm');
        }
        if (!empty($p['is_new'])) {
            $ribbons['جدید'] = $this->badgeHtml('جدید', 'info', 'sm');
        }
        $badge = trim((string) ($p['badge'] ?? ''));
        if ($badge !== '' && !isset($ribbons[$badge])) {
            $ribbons[$badge] = $this->badgeHtml($badge, 'primary', 'sm');
        }
        $ribbons = implode('', array_slice($ribbons, 0, 3));
        $ribbonsHtml = $ribbons !== '' ? "<div class=\"tb-product-ribbons\">{$ribbons}</div>" : '';

        $oldHtml = ($percent > 0 && !empty($p['original_html']))
            ? '<span class="tb-product-price-old">' . $p['original_html'] . '</span>' : '';

        $stock = in_array($p['stock'] ?? null, ['in', 'low', 'out'], true) ? $p['stock'] : null;
        $stockLabel = trim((string) ($p['stock_label'] ?? ''));
        if ($stock !== null && $stockLabel === '') {
            $stockLabel = ['in' => 'موجود', 'low' => 'موجودی محدود', 'out' => 'ناموجود'][$stock];
        }
        $stockHtml = $stockLabel !== ''
            ? '<span class="tb-product-stock tb-product-stock--' . ($stock ?? 'in') . '">' . $this->escape($this->displayNumber($stockLabel)) . '</span>'
            : '';

        $ratingHtml = '';
        if ($rating > 0) {
            $stars = $this->starsHtml($rating);
            $value = $this->displayNumber(number_format($rating, 1));
            $count = $reviews;
            $label = "امتیاز {$value} از ۵" . ($count > 0 ? ' از ' . $this->displayNumber($count) . ' نظر' : '');
            $ratingHtml = '<div class="tb-product-rating" role="img" aria-label="' . $this->escapeAttr($label) . '">'
                . '<span class="tb-product-stars" aria-hidden="true">' . $stars . '</span>'
                . '<span class="tb-product-rating-value" aria-hidden="true">' . $value . '</span>'
                . ($count > 0 ? '<span class="tb-product-rating-count" aria-hidden="true">(' . $this->displayNumber($count) . ')</span>' : '')
                . '</div>';
        }

        $action = array_key_exists('action_html', $p) && $p['action_html'] !== null
            ? (string) $p['action_html']
            : $this->productActionHtml($p, $stock, $urlAttr, $title);

        return "<div class=\"tb-product-card" . ($stock === 'out' ? ' is-out' : '') . "\">
            {$ribbonsHtml}
            <a href=\"{$urlAttr}\" class=\"tb-product-card-img\" tabindex=\"-1\" aria-hidden=\"true\">{$imgHtml}</a>
            <div class=\"tb-product-card-inner\">
                <a href=\"{$urlAttr}\" class=\"tb-product-card-link\"><h3 class=\"tb-product-title\">{$titleEsc}</h3></a>
                {$ratingHtml}
                <div class=\"tb-product-price\">{$oldHtml}<span class=\"tb-product-price-now\">" . ($p['price_html'] ?? '') . "</span></div>
                {$stockHtml}
                {$action}
            </div>
        </div>";
    }

    /** Five SVG stars for a 0–5 rating, in half steps — product and review cards. */
    protected function starsHtml(float $rating): string
    {
        $stars = '';
        for ($i = 1; $i <= 5; $i++) {
            $state = $rating >= $i - 0.25 ? 'is-on' : ($rating >= $i - 0.75 ? 'is-half' : 'is-off');
            $stars .= IconLibrary::svg($state === 'is-half' ? 'star-half' : 'star', null, "tb-star {$state}");
        }
        return $stars;
    }

    /**
     * Phase 3b (ADR-069): the one review card — testimonials and customer
     * reviews.
     *
     * Keys: name, avatar (URL), show_avatar (bool, default true), role,
     * text, rating (0–5), verified (bool),
     * date (Jalali or Gregorian, shown as «۲ آبان ۱۴۰۵»), title, and
     * `schema`: null (no microdata — authored testimonials are not reviews of
     * an item), 'nested' (a schema.org Review inside the caller's Product,
     * itemprop="review") or 'standalone' with `item` = [name, url] as its
     * itemReviewed Product.
     */
    protected function reviewCardHtml(array $r): string
    {
        $name = trim($this->stringify($r['name'] ?? '')) ?: 'کاربر';
        $rating = max(0.0, min(5.0, (float) ($r['rating'] ?? 0)));
        $schema = in_array($r['schema'] ?? null, ['nested', 'standalone'], true) ? $r['schema'] : null;
        $item = is_array($r['item'] ?? null) ? $r['item'] : [];
        if ($schema === 'standalone' && trim($this->stringify($item['name'] ?? '')) === '') {
            $schema = null;
        }
        $prop = static fn(string $p): string => $schema !== null ? " itemprop=\"{$p}\"" : '';

        $avatar = trim($this->stringify($r['avatar'] ?? ''));
        $avatarHtml = !$this->flag($r['show_avatar'] ?? true) ? '' : ($avatar !== '' && \SaaSPlatform\ThemeRuntime\Security\HtmlSanitizer::isValidUrl($avatar)
            ? '<img class="tb-testimonial-avatar tb-review-avatar" src="' . $this->escapeAttr($avatar) . '" alt="" loading="lazy" width="44" height="44">'
            : '<span class="tb-testimonial-avatar tb-review-avatar" aria-hidden="true">' . $this->escape(mb_substr($name, 0, 1, 'UTF-8')) . '</span>');

        $starsHtml = '';
        if ($rating > 0) {
            $shown = $this->displayNumber(rtrim(rtrim(number_format($rating, 1), '0'), '.'));
            $starsHtml = '<div class="tb-testimonial-stars tb-review-stars" role="img" aria-label="' . $this->escapeAttr("امتیاز {$shown} از ۵") . '">'
                . $this->starsHtml($rating) . '</div>'
                . ($schema !== null ? '<span itemprop="reviewRating" itemscope itemtype="https://schema.org/Rating"><meta itemprop="ratingValue" content="' . $this->escapeAttr((string) $rating) . '"><meta itemprop="bestRating" content="5"><meta itemprop="worstRating" content="1"></span>' : '');
        }

        $date = JalaliDate::parse($this->stringify($r['date'] ?? ''));
        $dateHtml = $date !== null
            ? '<time class="tb-review-date" datetime="' . sprintf('%04d-%02d-%02d', ...$date['gregorian']) . '"' . $prop('datePublished') . '>' . JalaliDate::format(...$date['jalali']) . '</time>'
            : '';
        $verifiedHtml = $this->flag($r['verified'] ?? false)
            ? '<span class="tb-review-verified">' . IconLibrary::svg('badge-check') . 'خریدار تاییدشده</span>'
            : '';
        $role = trim($this->stringify($r['role'] ?? ''));
        $title = trim($this->stringify($r['title'] ?? ''));
        $text = trim($this->stringify($r['text'] ?? ''));

        $itemReviewed = '';
        if ($schema === 'standalone') {
            $url = $this->escapeUrl($this->stringify($item['url'] ?? ''), '');
            $itemReviewed = '<span itemprop="itemReviewed" itemscope itemtype="https://schema.org/Product"><meta itemprop="name" content="' . $this->escapeAttr($item['name']) . '">'
                . ($url !== '' ? '<link itemprop="url" href="' . $url . '">' : '') . '</span>';
        }
        $product = trim($this->stringify($item['name'] ?? ''));
        $productUrl = $this->escapeUrl($this->stringify($item['url'] ?? ''), '');
        $productHtml = $product !== '' && $schema !== 'nested'
            ? '<p class="tb-review-product">' . ($productUrl !== '' ? '<a href="' . $productUrl . '">' . $this->escape($product) . '</a>' : $this->escape($product)) . '</p>'
            : '';

        $scope = $schema !== null ? ($schema === 'nested' ? ' itemprop="review"' : '') . ' itemscope itemtype="https://schema.org/Review"' : '';
        return "<article class=\"tb-testimonial-card tb-review-card\"{$scope}>"
            . $itemReviewed
            . '<div class="tb-review-head">' . $starsHtml . $dateHtml . '</div>'
            . ($title !== '' ? '<h3 class="tb-review-title"' . $prop('name') . '>' . $this->escape($title) . '</h3>' : '')
            . ($text !== '' ? '<p class="tb-testimonial-text"' . $prop('reviewBody') . '>' . $this->escape($text) . '</p>' : '')
            . $productHtml
            . '<div class="tb-testimonial-author">' . $avatarHtml
            . '<div class="tb-review-who"><div class="tb-testimonial-name"' . ($schema !== null ? ' itemprop="author" itemscope itemtype="https://schema.org/Person"><span itemprop="name">' . $this->escape($name) . '</span>' : '>' . $this->escape($name)) . '</div>'
            . ($role !== '' ? '<div class="tb-testimonial-role">' . $this->escape($role) . '</div>' : '')
            . $verifiedHtml . '</div></div>'
            . '</article>';
    }

    /**
     * Phase 3b (ADR-069): the average, star count and a Persian bar per
     * star level («۵ ستاره ۶۰٪»). $counts maps 1–5 to review counts. With
     * $aggregate the figures carry schema.org AggregateRating microdata as
     * itemprop="aggregateRating" — only inside a caller's Product scope.
     * No reviews → ''.
     *
     * @param array<int,int> $counts
     */
    protected function ratingSummaryHtml(array $counts, bool $aggregate = false): string
    {
        $total = 0;
        $sum = 0;
        for ($star = 1; $star <= 5; $star++) {
            $n = max(0, (int) ($counts[$star] ?? 0));
            $total += $n;
            $sum += $n * $star;
        }
        if ($total === 0) {
            return '';
        }
        $avg = round($sum / $total, 1);
        $avgText = $this->displayNumber(number_format($avg, 1));
        $rows = '';
        for ($star = 5; $star >= 1; $star--) {
            $n = max(0, (int) ($counts[$star] ?? 0));
            $pct = (int) round($n / $total * 100);
            $rows .= '<li class="tb-rating-row">'
                . '<span class="tb-rating-row-label">' . $this->displayNumber($star) . ' ستاره</span>'
                . '<span class="tb-rating-bar" aria-hidden="true"><span style="width:' . $pct . '%"></span></span>'
                . '<span class="tb-rating-row-pct">' . $this->displayNumber($pct . '%') . '<span class="tb-visually-hidden"> (' . $this->displayNumber($n) . ' نظر)</span></span>'
                . '</li>';
        }
        $micro = $aggregate
            ? '<span itemprop="aggregateRating" itemscope itemtype="https://schema.org/AggregateRating"><meta itemprop="ratingValue" content="' . $avg . '"><meta itemprop="reviewCount" content="' . $total . '"><meta itemprop="bestRating" content="5"><meta itemprop="worstRating" content="1"></span>'
            : '';
        return '<div class="tb-rating-summary">' . $micro
            . '<div class="tb-rating-score"><span class="tb-rating-average">' . $avgText . '</span>'
            . '<div class="tb-review-stars" role="img" aria-label="' . $this->escapeAttr("میانگین {$avgText} از ۵") . '">' . $this->starsHtml($avg) . '</div>'
            . '<span class="tb-rating-total">از ' . $this->displayNumber($total) . ' نظر</span></div>'
            . '<ul class="tb-rating-rows" aria-label="توزیع امتیازها">' . $rows . '</ul>'
            . '</div>';
    }

    /** The card's call to action — see productCard(). */
    private function productActionHtml(array $p, ?string $stock, string $urlAttr, string $title): string
    {
        $cartIcon = $this->iconHtml('shopping-cart');
        if ($stock === 'out') {
            return '<span class="tb-btn tb-product-add is-disabled" aria-disabled="true">ناموجود</span>';
        }
        if (!empty($p['needs_options'])) {
            return "<a href=\"{$urlAttr}\" class=\"tb-btn tb-btn-secondary tb-product-add\">انتخاب گزینه‌ها</a>";
        }
        $cart = $this->cartEndpoints();
        $productId = trim($this->stringify($p['product_id'] ?? ''));
        if ($cart === null || !preg_match('/^\d{1,20}$/', $productId)) {
            return "<a href=\"{$urlAttr}\" class=\"tb-btn tb-btn-primary tb-product-add\">{$cartIcon}<span>افزودن به سبد خرید</span></a>";
        }
        $csrf = $this->siteEndpoints()['csrf'] ?? null;
        $config = $this->escapeAttr(json_encode(array_diff_key($cart, ['count' => 1]), JSON_UNESCAPED_SLASHES));
        return '<form class="tb-product-add-form" method="post" action="' . $this->escapeAttr($cart['add']) . '" data-tb-cart="' . $config . '">'
            . (is_string($csrf) && $csrf !== '' ? '<input type="hidden" name="_token" value="' . $this->escapeAttr($csrf) . '">' : '')
            . '<input type="hidden" name="product_id" value="' . $productId . '"><input type="hidden" name="quantity" value="1">'
            . '<button type="submit" class="tb-btn tb-btn-primary tb-product-add" data-tb-add-to-cart data-open-cart data-product-id="' . $productId . '" aria-label="' . $this->escapeAttr('افزودن «' . $title . '» به سبد خرید') . '">'
            . $cartIcon . '<span>افزودن به سبد خرید</span></button></form>'
            . $this->cartAssets();
    }

    /**
     * Phase 2 (ADR-067): the one blog card — category pill, title, excerpt,
     * author avatar (or initial), Persian date and reading time.
     *
     * Keys: title, url, image, excerpt, date (display text), date_iso,
     * reading_minutes, category, category_url, author, author_avatar.
     */
    protected function blogCardHtml(array $p): string
    {
        $title = (string) ($p['title'] ?? '');
        $url = $this->escapeUrl($this->linkValue($p['url'] ?? '#'), '#');
        $img = $this->imageWithFallback($this->mediaSrc($p['image'] ?? ''), $title, 'aspect-ratio:16/10;', $this->iconHtml('file-text'));
        $category = trim((string) ($p['category'] ?? ''));
        $catHtml = $category !== ''
            ? '<a class="tb-blog-card-cat" href="' . $this->escapeUrl((string) ($p['category_url'] ?? '#'), '#') . '">' . $this->escape($category) . '</a>'
            : '';
        $excerpt = trim((string) ($p['excerpt'] ?? ''));
        $author = trim((string) ($p['author'] ?? ''));
        $avatar = trim((string) ($p['author_avatar'] ?? ''));
        $avatarHtml = $avatar !== '' && \SaaSPlatform\ThemeRuntime\Security\HtmlSanitizer::isValidUrl($avatar)
            ? '<img class="tb-blog-card-avatar" src="' . $this->escapeAttr($avatar) . '" alt="" loading="lazy" width="32" height="32">'
            : ($author !== '' ? '<span class="tb-blog-card-avatar tb-blog-card-avatar--initial" aria-hidden="true">' . $this->escape(mb_substr($author, 0, 1)) . '</span>' : '');
        $minutes = max(0, (int) ($p['reading_minutes'] ?? 0));
        $date = trim((string) ($p['date'] ?? ''));
        $info = [];
        if ($date !== '') {
            $iso = trim((string) ($p['date_iso'] ?? ''));
            $info[] = '<time' . ($iso !== '' ? ' datetime="' . $this->escapeAttr($iso) . '"' : '') . '>' . $this->escape($this->displayNumber($date)) . '</time>';
        }
        if ($minutes > 0) {
            $info[] = '<span class="tb-blog-card-read">' . $this->iconHtml('clock') . ' ' . $this->displayNumber($minutes) . ' دقیقه مطالعه</span>';
        }

        return '<article class="tb-blog-card">'
            . "<a href=\"{$url}\" class=\"tb-blog-card-media\" tabindex=\"-1\" aria-hidden=\"true\">{$img}</a>"
            . '<div class="tb-blog-card-body">'
            . ($catHtml !== '' ? "<div class=\"tb-blog-card-cats\">{$catHtml}</div>" : '')
            . "<h3 class=\"tb-blog-card-title\"><a href=\"{$url}\">" . $this->escape($title) . '</a></h3>'
            . ($excerpt !== '' ? '<p class="tb-blog-card-excerpt">' . $this->escape($excerpt) . '</p>' : '')
            . (($author !== '' || $info !== []) ? '<div class="tb-blog-card-meta">'
                . ($author !== '' ? '<span class="tb-blog-card-author">' . $avatarHtml . '<span>' . $this->escape($author) . '</span></span>' : '')
                . ($info !== [] ? '<span class="tb-blog-card-info">' . implode('<span aria-hidden="true">·</span>', $info) . '</span>' : '')
                . '</div>' : '')
            . '</div></article>';
    }

    /** Renders a block's blog cards as grid, list or carousel. */
    protected function blogCardsLayout(array $cards, array $props, string $id, string $title): string
    {
        $layout = $props['layout'] ?? 'grid';
        $layout = in_array($layout, ['grid', 'list', 'slider'], true) ? $layout : 'grid';
        $titleHtml = $title !== '' ? "<h2 class=\"tb-heading tb-section-title\">{$title}</h2>" : '';
        if ($layout === 'slider') {
            $body = $this->renderCarousel($cards, $id . '-track', $this->flag($props['autoplay'] ?? true), (int) ($props['interval'] ?? 5000), 'tb-blog-slider', ['desktop' => 3, 'tablet' => 2, 'mobile' => 1], options: ['label' => html_entity_decode($title, ENT_QUOTES | ENT_HTML5, 'UTF-8')]);
        } else {
            $body = '<div class="tb-grid ' . ($layout === 'list' ? 'tb-grid-cols-1 tb-blog-list' : 'tb-grid-cols-3') . ' tb-gap-lg tb-blog-grid">' . implode('', $cards) . '</div>';
        }
        return "<div id=\"tb-{$id}\" class=\"tb-blog-posts\">{$titleHtml}{$body}</div>";
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
        foreach ($demo as $i => $p) {
            $cards[] = $this->productCard($this->demoProductCard($p, $i));
        }

        $titleHtml = $title ? "<h2 class=\"tb-heading tb-section-title\">{$title}</h2>" : '';
        $carousel = $this->renderCarousel(
            $cards,
            $id,
            (bool) ($props['autoplay'] ?? true),
            (int) ($props['interval'] ?? 5000),
            'tb-module-products',
            ['desktop' => (int) ($props['columns'] ?? 3), 'tablet' => 2, 'mobile' => 1],
            true,
            false,
            false,
            [],
            // Phase 9 / Step 2 opted this caller into the shared asset;
            // every caller has emitted it since this phase deleted the
            // per-instance inline controller, so the opt-in is now a
            // no-op kept for call-site compatibility.
            ['script' => 'shared', 'label' => html_entity_decode($title, ENT_QUOTES | ENT_HTML5, 'UTF-8')]
        );

        return "<div id=\"tb-{$id}-wrap\" class=\"tb-module-products tb-section\">
            <div class=\"tb-container\">{$titleHtml}{$carousel}</div>
        </div>";
    }

    /** Phase 2 (ADR-067): a Builder-preview demo product as productCard() data. */
    private function demoProductCard(array $p, int $i): array
    {
        $ratings = [[4.6, 128], [4.2, 37], [4.8, 412], [3.9, 18]];
        return [
            'title' => $p['title'],
            'image' => '',
            'url' => '#',
            'price' => (float) $p['price'],
            'original' => $p['original'] !== null ? (float) $p['original'] : null,
            'price_html' => $this->displayToman($p['price']),
            'original_html' => $p['original'] !== null ? $this->displayToman($p['original']) : null,
            'badge' => in_array($p['badge'], ['تخفیف', 'جدید'], true) ? null : $p['badge'],
            'is_new' => $p['badge'] === 'جدید',
            'stock' => str_contains((string) $p['stock'], 'باقی') ? 'low' : 'in',
            'stock_label' => $p['stock'],
            'rating' => $ratings[$i % 4][0],
            'reviews' => $ratings[$i % 4][1],
        ];
    }

    /**
     * Canonical (shared) Product Grid. Real bug found via a full-page     * Playwright screenshot pass (Commercial Productization phase):
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
        foreach ($demo as $i => $p) {
            $cards[] = $this->productCard($this->demoProductCard($p, $i));
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
        // Phase 0.2c: src/poster go through escapeUrl() (a javascript: or
        // data: URL is refused), a non-string value (an unresolved
        // {media_id}) counts as absent instead of raising an array-to-
        // string error, and the switches read through flag() so a legacy
        // "false" string is false.
        $url = $this->escapeUrl(trim($this->stringify($props['url'] ?? '')), '');
        if ($url === '') {
            return "<div id=\"tb-{$id}\" class=\"tb-video tb-empty-state\"><p class=\"tb-text\">ویدیویی انتخاب نشده است.</p></div>";
        }

        $poster   = $this->escapeUrl(trim($this->stringify($props['poster'] ?? '')), '');
        $caption  = $this->escape($props['caption'] ?? '');
        $autoplay = $this->flag($props['autoplay'] ?? false);
        $loop     = $this->flag($props['loop'] ?? false);
        $controls = $this->flag($props['controls'] ?? true);

        $attrs = [];
        if ($controls) $attrs[] = 'controls';
        if ($autoplay) $attrs[] = 'autoplay muted playsinline';
        if ($loop) $attrs[] = 'loop';
        $attrStr = implode(' ', $attrs);
        $posterAttr = $poster !== '' ? ' poster="' . $poster . '"' : '';
        $captionHtml = $caption ? "<figcaption class=\"tb-video-caption\">{$caption}</figcaption>" : '';

        return "<figure id=\"tb-{$id}\" class=\"tb-video\">
            <video src=\"" . $url . "\"{$posterAttr} {$attrStr} style=\"width:100%;border-radius:0.5rem;display:block;\"></video>
            {$captionHtml}
        </figure>";
    }

    /** Canonical `cart_icon` — same audit-found gap class as renderVideo() above. */
    protected function renderCartIcon(array $props, string $inner, string $id): string
    {
        $count = (int) ($props['count'] ?? 0);
        $badge = $count > 0 ? "<span class=\"tb-cart-icon-badge\">" . $this->displayNumber($count) . "</span>" : '';
        return "<a href=\"/cart\" id=\"tb-{$id}\" class=\"tb-cart-icon\" aria-label=\"سبد خرید\" style=\"position:relative;display:inline-flex;\">" . $this->iconHtml('shopping-cart') . "{$badge}</a>";
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
            ['name' => 'پوشاک', 'count' => 128, 'icon' => 'shirt'],
            ['name' => 'دیجیتال و لوازم الکترونیکی', 'count' => 76, 'icon' => 'smartphone'],
            ['name' => 'خانه و آشپزخانه', 'count' => 54, 'icon' => 'house'],
            ['name' => 'زیبایی و سلامت', 'count' => 39, 'icon' => 'sparkles'],
        ];

        $cards = [];
        foreach ($demo as $c) {
            $cards[] = "<a href=\"#\" class=\"tb-category-card\" style=\"display:block;text-align:center;padding:1.5rem 1rem;border:1px solid var(--tb-border, #e5e7eb);border-radius:.75rem;text-decoration:none;color:inherit;\">
                <div class=\"tb-category-card-icon\" style=\"font-size:2rem;margin-bottom:.5rem;\">" . $this->iconHtml($c['icon']) . "</div>
                <div class=\"tb-category-name\" style=\"font-weight:700;\">" . $this->escape($c['name']) . "</div>
                <div class=\"tb-category-count\" style=\"font-size:.8rem;color:var(--tb-text-muted, #6b7280);margin-top:.2rem;\">" . $this->displayNumber($c['count']) . " محصول</div>
            </a>";
        }

        $titleHtml = $title ? "<h2 class=\"tb-heading tb-section-title\">{$title}</h2>" : '';

        $body = $layout === 'grid'
            ? "<div class=\"tb-grid tb-grid-cols-{$columns} tb-gap-md\">" . implode('', $cards) . "</div>"
            : $this->renderCarousel($cards, $id . '-track', (bool) ($props['autoplay'] ?? true), (int) ($props['interval'] ?? 5000), 'tb-category-slider', ['desktop' => $columns, 'tablet' => max(2, (int) round($columns / 2)), 'mobile' => 1], options: ['label' => html_entity_decode($title, ENT_QUOTES | ENT_HTML5, 'UTF-8')]);

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
            $icon  = $this->iconHtml($it['icon'] ?? '');
            $image = $this->mediaSrc($it['image'] ?? '');
            $linkLabel = $this->escape($it['link_label'] ?? '');
            $linkUrl   = $this->linkHref($it['link_url'] ?? '', '');

            $mediaHtml = $image !== ''
                ? $this->imageWithFallback($image, $itemTitle, 'width:64px;height:64px;border-radius:50%;', $icon !== '' ? $icon : '')
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

        // Phase 3b (ADR-069): every card is reviewCardHtml() — SVG stars,
        // verified-buyer badge, Jalali date, avatar or initial. Authored
        // testimonials carry no Review microdata: they review no item, and
        // reviews of the site's own business are not eligible.
        $cards = [];
        $counts = [];
        foreach ($items as $t) {
            if (!is_array($t)) {
                continue;
            }
            $rating = max(0, min(5, (int) ($t['rating'] ?? 5)));
            if ($rating > 0) {
                $counts[$rating] = ($counts[$rating] ?? 0) + 1;
            }
            $cards[] = $this->reviewCardHtml([
                'name' => $t['name'] ?? '',
                'avatar' => $t['avatar'] ?? $t['image'] ?? '',
                'role' => implode(' · ', array_filter([trim($this->stringify($t['role'] ?? '')), trim($this->stringify($t['company'] ?? ''))])),
                'text' => $t['text'] ?? '',
                'rating' => $rating,
                'verified' => $t['verified'] ?? false,
                'date' => $t['date'] ?? '',
            ]);
        }
        $summary = $this->flag($props['show_summary'] ?? false) ? $this->ratingSummaryHtml($counts) : '';

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
                ['desktop' => $columns, 'tablet' => max(1, min(2, $columns)), 'mobile' => 1],
                options: ['label' => html_entity_decode($title, ENT_QUOTES | ENT_HTML5, 'UTF-8')]
            );

        return "<div id=\"tb-{$id}\" class=\"tb-testimonials tb-section\">
            <div class=\"tb-container\">
                <div class=\"tb-testimonials-header\"><h2 class=\"tb-features-section-title\">{$title}</h2></div>
                {$summary}
                {$body}
            </div>
        </div>";
    }

    protected function renderTrustBadges(array $props, string $inner, string $id): string
    {
        $bg = $this->escapeAttr($this->cssColor($props['background'] ?? null, 'var(--tb-surface, #f8fafc)'));

        $rawBadges = $props['badges'] ?? '[]';
        $badges    = is_array($rawBadges) ? $rawBadges : (json_decode($rawBadges, true) ?? []);

        $items = [];
        foreach ($badges as $b) {
            $icon  = $this->iconHtml($b['icon'] ?? 'badge-check');
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

        // Phase 3b (ADR-069): the monthly / yearly switch shows only when it
        // is on and some plan has a yearly price; plans without one keep
        // their monthly price in both modes. Both prices are in the markup
        // (yearly `hidden`), so without JavaScript the monthly ones show.
        $plans = array_values(array_filter($plans, 'is_array'));
        $hasYearly = false;
        foreach ($plans as $p) {
            $hasYearly = $hasYearly || trim($this->stringify($p['price_yearly'] ?? '')) !== '';
        }
        $toggle = $hasYearly && $this->flag($props['billing_toggle'] ?? true);
        $toggleHtml = '';
        if ($toggle) {
            $pill = trim($this->stringify($props['yearly_discount_label'] ?? ''));
            $toggleHtml = '<div class="tb-billing-toggle" role="group" aria-label="دورهٔ پرداخت">'
                . '<button type="button" class="tb-billing-option is-active" data-tb-billing="monthly" aria-pressed="true">ماهانه</button>'
                . '<button type="button" class="tb-billing-option" data-tb-billing="yearly" aria-pressed="false">سالانه'
                . ($pill !== '' ? '<span class="tb-billing-pill">' . $this->escape($this->displayNumber($pill)) . '</span>' : '')
                . '</button></div>';
        }

        $cards = [];
        foreach ($plans as $pi => $p) {
            $name      = $this->escape($p['name'] ?? '');
            $price     = $this->escape($this->displayNumber($p['price'] ?? ''));
            $period    = $this->escape($p['period'] ?? '');
            $badge     = trim($this->stringify($p['badge'] ?? ''));
            $highlight = $this->flag($p['highlight'] ?? false);
            $ctaText   = $this->escape($p['cta_text'] ?? 'انتخاب');
            $ctaUrl    = $this->linkHref($p['cta_url'] ?? '#', '#');
            $rawFeat   = $p['features'] ?? [];
            $features  = is_array($rawFeat) ? $rawFeat : (json_decode((string) $rawFeat, true) ?? []);
            $hlClass   = $highlight ? ' highlighted' : '';

            // The popular plan wears a ribbon on the card edge; any other
            // badge stays the shared Badge primitive.
            $badgeHtml = $highlight
                ? '<div class="tb-plan-ribbon">' . $this->escape($this->displayNumber($badge !== '' ? $badge : 'محبوب‌ترین')) . '</div>'
                : ($badge !== '' ? "<div class=\"tb-plan-badge-wrap\">" . $this->badgeHtml($badge, 'primary', 'md') . "</div>" : '');

            $yearly = trim($this->stringify($p['price_yearly'] ?? ''));
            if ($toggle && $yearly !== '') {
                $yearlyPeriod = $this->escape(trim($this->stringify($p['period_yearly'] ?? '')) ?: '/ سال');
                $priceHtml = "<div class=\"tb-plan-price\" data-tb-cycle=\"monthly\">{$price}</div><p class=\"tb-plan-period\" data-tb-cycle=\"monthly\">{$period}</p>"
                    . '<div class="tb-plan-price" data-tb-cycle="yearly" hidden>' . $this->escape($this->displayNumber($yearly)) . '</div>'
                    . "<p class=\"tb-plan-period\" data-tb-cycle=\"yearly\" hidden>{$yearlyPeriod}</p>";
            } else {
                $priceHtml = "<div class=\"tb-plan-price\">{$price}</div><p class=\"tb-plan-period\">{$period}</p>";
            }

            $featureHtml = '';
            foreach ($features as $fi => $f) {
                // Canvas saves {text, included, tooltip}; a bare string is
                // legacy data and counts as included.
                $f = is_array($f) ? $f : ['text' => $f];
                $text = trim($this->stringify($f['text'] ?? ''));
                if ($text === '') {
                    continue;
                }
                $included = $this->flag($f['included'] ?? true);
                $tip = trim($this->stringify($f['tooltip'] ?? ''));
                $tipId = "tb-{$id}-tip-{$pi}-{$fi}";
                $tipHtml = $tip !== ''
                    ? '<span class="tb-tip"><button type="button" class="tb-tip-trigger" aria-label="' . $this->escapeAttr('توضیح «' . $text . '»') . '" aria-describedby="' . $tipId . '">' . IconLibrary::svg('info') . '</button>'
                    . '<span class="tb-tip-bubble" role="tooltip" id="' . $tipId . '">' . $this->escape($this->displayNumber($tip)) . '</span></span>'
                    : '';
                $featureHtml .= '<li class="tb-plan-feature ' . ($included ? 'is-included' : 'is-excluded') . '">'
                    . '<span class="tb-plan-feature-icon">' . IconLibrary::svg($included ? 'check' : 'x') . '</span>'
                    . '<span class="tb-plan-feature-text">' . $this->escape($this->displayNumber($text))
                    . ($included ? '' : '<span class="tb-visually-hidden"> (در این پلن نیست)</span>') . '</span>'
                    . $tipHtml . '</li>';
            }

            $cards[] = "<div class=\"tb-pricing-card{$hlClass}\">
                {$badgeHtml}
                <p class=\"tb-plan-name\">{$name}</p>
                {$priceHtml}
                <ul class=\"tb-plan-features\">{$featureHtml}</ul>
                <a href=\"{$ctaUrl}\" class=\"tb-btn " . ($highlight ? 'tb-btn-primary' : 'tb-btn-secondary') . "\" style=\"width:100%;text-align:center;\">{$ctaText}</a>
            </div>";
        }

        $tips = str_contains(implode('', $cards), 'tb-tip-trigger');
        return "<div id=\"tb-{$id}\" class=\"tb-pricing-cards tb-section\"" . ($toggle || $tips ? ' data-tb-pricing' : '') . ">
            <div class=\"tb-container\">{$headerHtml}{$toggleHtml}
                <div class=\"tb-pricing-grid cols-{$colCount}\">" . implode('', $cards) . "</div>
            </div>
        </div>" . ($toggle || $tips ? $this->pricingAssets() : '');
    }

    /** Phase 3b (ADR-069): blocks/pricing.js — once per render. */
    private function pricingAssets(): string
    {
        if ($this->pricingAssetsEmitted) {
            return '';
        }
        $js = @file_get_contents(ThemeRuntimeServiceProvider::pricingScriptPath());
        if (!is_string($js) || $js === '') {
            throw new \RuntimeException('Theme runtime packaging fault: pricing.js is missing.');
        }
        $this->pricingAssetsEmitted = true;
        return '<script data-tb-block-asset="pricing">' . $js . '</script>';
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
        $bg         = $this->escapeAttr($this->cssColor($props['background'] ?? null, 'var(--tb-primary, #4f46e5)'));
        $tc         = $this->escapeAttr($this->cssColor($props['text_color'] ?? null, 'var(--tb-on-primary, #ffffff)'));

        $form = $this->newsletterFormHtml($props, $id, $placeholder, $btnText, $bg);

        return "<div id=\"tb-{$id}\" class=\"tb-newsletter\" style=\"background:{$bg};color:{$tc};\">
            <div class=\"tb-container\">
                <h2 class=\"tb-newsletter-title\">{$title}</h2>
                " . ($subtitle ? "<p class=\"tb-newsletter-sub\">{$subtitle}</p>" : '') . "
                {$form}
            </div>
        </div>";
    }

    /**
     * Phase 0.7 (ADR-066): the newsletter form. With a host endpoint it is a
     * real POST (CSRF via the page token, a honeypot, a live status line;
     * blocks/site-forms.js sends it with fetch). Without one — the Builder
     * preview — it stays the inert form it always was.
     */
    private function newsletterFormHtml(array $props, string $id, string $placeholder, string $btnText, string $bg, ?string $buttonStyle = 'block'): string
    {
        $label = $this->visuallyHiddenLabel("tb-{$id}-newsletter-email", $props['placeholder'] ?? 'ایمیل یا شماره موبایل');
        // The block's button is white on its coloured band; other placements
        // (the footer, $buttonStyle null) style it from the stylesheet.
        $style = $buttonStyle === 'block' ? " style=\"background:var(--tb-on-primary, #fff);color:{$bg};\"" : '';
        $button = "<button type=\"submit\" class=\"tb-newsletter-btn tb-btn\"{$style}>{$btnText}</button>";
        $endpoints = $this->siteEndpoints();
        $endpoint = is_string($endpoints['newsletter'] ?? null) ? trim($endpoints['newsletter']) : '';
        $action = $endpoint !== '' && \SaaSPlatform\ThemeRuntime\Security\HtmlSanitizer::isValidUrl($endpoint) ? $this->escapeAttr($endpoint) : '';
        if ($action === '') {
            return "<form class=\"tb-newsletter-form\" onsubmit=\"event.preventDefault();this.reset();\" novalidate>
                    {$label}
                    <input id=\"tb-{$id}-newsletter-email\" type=\"text\" class=\"tb-newsletter-input\" placeholder=\"{$placeholder}\" autocomplete=\"off\">
                    {$button}
                </form>";
        }
        $token = is_string($endpoints['csrf'] ?? null) && $endpoints['csrf'] !== ''
            ? '<input type="hidden" name="_token" value="' . $this->escapeAttr($endpoints['csrf']) . '">' : '';
        $status = "tb-{$id}-newsletter-status";

        return "<form class=\"tb-newsletter-form\" method=\"post\" action=\"{$action}\" data-tb-newsletter novalidate>"
            . $token
            . '<input type="hidden" name="source" value="' . $this->escapeAttr($id) . '">'
            . $label
            . "<input id=\"tb-{$id}-newsletter-email\" type=\"text\" name=\"contact\" inputmode=\"email\" autocomplete=\"email\" maxlength=\"190\" required dir=\"auto\" class=\"tb-newsletter-input\" placeholder=\"{$placeholder}\" aria-describedby=\"{$status}\">"
            . '<span class="tb-hp" aria-hidden="true"><input type="text" name="website" tabindex="-1" autocomplete="off"></span>'
            . $button
            . "<p id=\"{$status}\" class=\"tb-newsletter-status\" role=\"status\" aria-live=\"polite\"></p>"
            . '</form>'
            . $this->siteFormsAssets();
    }

    /**
     * Phase 0.7 (ADR-066): where this site's forms go. Hosts pass
     * context['site_endpoints'] (customer-site overrides this with its real
     * routes); none — the Builder preview — keeps every form inert.
     *
     *   newsletter: POST URL        csrf: token for the no-JS fallback
     *   search:     ['all'|'products'|'posts' => ['action' => URL, 'params' => [...], 'param' => 'q']]
     *   suggest:    GET URL answering {items: [{type, title, url, image, price}]}
     *
     * @return array<string, mixed>
     */
    protected function siteEndpoints(): array
    {
        $endpoints = $this->context['site_endpoints'] ?? null;
        return is_array($endpoints) ? $endpoints : [];
    }

    /** The search routes per scope, each URL checked; [] when the site has no search. */
    private function searchRoutes(): array
    {
        $routes = [];
        $search = $this->siteEndpoints()['search'] ?? null;
        foreach (['all', 'products', 'posts'] as $scope) {
            $route = is_array($search) ? ($search[$scope] ?? null) : null;
            $action = is_array($route) ? (string) ($route['action'] ?? '') : '';
            if ($action === '' || !\SaaSPlatform\ThemeRuntime\Security\HtmlSanitizer::isValidUrl($action)) {
                continue;
            }
            $params = [];
            foreach ((array) ($route['params'] ?? []) as $k => $v) {
                if (is_string($k) && preg_match('/^[a-z_]{1,32}$/', $k) && is_scalar($v)) {
                    $params[$k] = (string) $v;
                }
            }
            $param = preg_match('/^[a-z_]{1,32}$/', (string) ($route['param'] ?? 'q')) ? (string) ($route['param'] ?? 'q') : 'q';
            $routes[$scope] = ['action' => $action, 'params' => $params, 'param' => $param];
        }
        return $routes;
    }

    /**
     * The attributes, hidden inputs and assets a search form needs. With
     * host routes it is a real GET form that blocks/site-forms.js upgrades
     * to the search dialog; without, the inert preview form.
     *
     * @return array{0: string, 1: string, 2: string, 3: string} attrs, hidden inputs, assets, field name
     */
    private function searchForm(string $scope): array
    {
        $routes = $this->searchRoutes();
        if ($routes === []) {
            return [' action="#" onsubmit="event.preventDefault();"', '', '', 'q'];
        }
        $scope = isset($routes[$scope]) ? $scope : (isset($routes['all']) ? 'all' : (string) array_key_first($routes));
        $primary = $routes[$scope];
        $suggest = (string) ($this->siteEndpoints()['suggest'] ?? '');
        $attrs = ' method="get" action="' . $this->escapeAttr($primary['action']) . '"'
            . ' data-tb-search="' . $this->escapeAttr(json_encode($routes, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) . '"'
            . ' data-tb-scope="' . $this->escapeAttr($scope) . '"'
            . ($suggest !== '' && \SaaSPlatform\ThemeRuntime\Security\HtmlSanitizer::isValidUrl($suggest) ? ' data-tb-suggest="' . $this->escapeAttr($suggest) . '"' : '');
        $hidden = '';
        foreach ($primary['params'] as $k => $v) {
            $hidden .= '<input type="hidden" name="' . $this->escapeAttr($k) . '" value="' . $this->escapeAttr($v) . '">';
        }
        return [$attrs, $hidden, $this->siteFormsAssets(), $primary['param']];
    }

    private function siteFormsAssets(): string
    {
        if ($this->siteFormsAssetsEmitted) {
            return '';
        }
        $js = @file_get_contents(ThemeRuntimeServiceProvider::siteFormsScriptPath());
        if (!is_string($js) || $js === '') {
            throw new \RuntimeException('Theme runtime packaging fault: site-forms.js is missing.');
        }
        $this->siteFormsAssetsEmitted = true;
        return '<script data-tb-block-asset="site_forms">' . $js . '</script>';
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
            $icon   = $this->iconHtml($item['icon'] ?? '');
            $image  = $this->escapeUrl($this->mediaSrc($item['image'] ?? ''), '');
            $linkUrl = $this->escapeUrl($this->linkValue($item['link_url'] ?? ''), '');
            $linkLabel = $this->escape($item['link_label'] ?? 'مشاهده');
            // Strict allowlist — the only classes the CSS contract defines.
            // Anything else (incl. the legacy 'normal') renders as small.
            $size   = in_array($item['size'] ?? '', ['large', 'tall', 'featured', 'small', 'half', 'full'], true) ? $item['size'] : 'small';
            // Same colour allowlist as the gradient stops: an escaped but
            // unvalidated value could still inject extra CSS declarations.
            $bgc    = $this->escapeAttr($this->bentoColorToken($item['bg_color'] ?? '', '#f8fafc') ?: '#f8fafc');
            $tc     = $this->escapeAttr($this->bentoColorToken($item['text_color'] ?? '', '#0f172a') ?: '#0f172a');

            // ── Card background: image + dual-stop gradient overlay ──────
            // Every value is sanitized before it reaches the inline style:
            // the URL must be a renderable asset reference and each colour
            // must be a legal CSS colour token, so a corrupted/malicious
            // authored value degrades to "no image / no gradient" instead of
            // breaking out of the style attribute.
            $bgImage = trim($this->mediaSrc($item['bg_image'] ?? ''));
            if ($bgImage !== '' && (!$this->isValidUrl($bgImage) || preg_match('/[\'\"()<>\\\\]/', $bgImage) === 1)) {
                $bgImage = '';
            }
            $fromColor = $this->bentoColorToken($item['bg_color_from'] ?? '');
            $toColor   = $this->bentoColorToken($item['bg_color_to'] ?? '');

            // The gradient is an overlay for a background image (it exists to
            // keep text readable over a photo). On a plain colour card it would
            // only muddy the authored bg_color/text_color pair — e.g. darken a
            // light card under dark text — so it never applies without an image.
            if ($bgImage === '') {
                $fromColor = $toColor = '';
            }

            $gradientOverlay = '';
            if ($fromColor !== '' && $toColor !== '') {
                $gradientOverlay = "linear-gradient(to bottom, {$fromColor}, {$toColor})";
            } elseif ($fromColor !== '') {
                $gradientOverlay = "linear-gradient(to bottom, {$fromColor}, transparent)";
            } elseif ($toColor !== '') {
                $gradientOverlay = "linear-gradient(to bottom, transparent, {$toColor})";
            }

            if ($bgImage !== '' && $gradientOverlay !== '') {
                // The gradient is the top layer, the image sits beneath it.
                $backgroundStyle = "background: {$gradientOverlay}, url('{$bgImage}') center/cover no-repeat;";
            } elseif ($bgImage !== '') {
                $backgroundStyle = "background: url('{$bgImage}') center/cover no-repeat;";
            } elseif ($gradientOverlay !== '') {
                // No image: the overlay composites over the card's own colour
                // instead of replacing it (bg_color stays authored, visible).
                $backgroundStyle = "background: {$gradientOverlay}, {$bgc};";
            } else {
                $backgroundStyle = "background: {$bgc};";
            }

            // ── Pulse border glow: tolerant boolean + bounded speed ──────
            // Persisted values arrive as real booleans, 1/0 or '1'/'0'
            // (JSON round-trips, legacy string props) — all are accepted;
            // anything else stays off. Speed must be a positive number.
            $glowVal = $item['glow_enabled'] ?? false;
            $isGlow = filter_var($glowVal, FILTER_VALIDATE_BOOLEAN) || $glowVal === 1 || $glowVal === '1';

            $glowColor = $this->escapeAttr($this->bentoColorToken($item['glow_color'] ?? '') ?: '#6366f1');
            $glowSpeed = floatval($item['glow_speed'] ?? 2);
            if ($glowSpeed <= 0) {
                $glowSpeed = 2;
            }

            $glowClass = $isGlow ? ' tb-bento-pulse-card' : '';
            $glowStyle = $isGlow ? "--tb-glow-color:{$glowColor};--tb-glow-speed:{$glowSpeed}s;" : '';

            $altText = is_scalar($item['title'] ?? null) ? (string) $item['title'] : '';
            $mediaHtml = $image !== '' ? $this->imageWithFallback($image, $altText, '', 'تصویر') : '';
            $mediaClass = $image !== '' ? ' has-media' : '';

            // Whole-card link: `link` (Canvas field, plain URL or structured
            // {url,…} link object), falling back to the legacy `link_url`.
            // Scheme-validated through escapeUrl() — '' fallback means an
            // unsafe or empty URL simply yields a non-link card. Both fields
            // go through linkValue(): an imported card can hold link_url as
            // a structured {url,…} too, which a string cast turned into a
            // whole-block "Array to string conversion".
            $rawLink = trim($this->linkValue($item['link'] ?? ''));
            if ($rawLink === '') {
                $rawLink = trim($this->linkValue($item['link_url'] ?? ''));
            }
            $cardHref = $rawLink !== '' ? $this->escapeUrl($rawLink, '') : '';
            $hasLink = $cardHref !== '';

            // The label reads as a link affordance; the card itself is the one
            // anchor, so no <a> is ever nested inside another <a>.
            $linkHtml = ($hasLink && $linkLabel !== '' && $linkUrl !== '')
                ? "<span class=\"tb-bento-item-link\">{$linkLabel}<span aria-hidden=\"true\"> ←</span></span>"
                : '';

            // Phase 3b (ADR-069): dynamic cells. A product cell holds its own
            // links and add-to-cart form, so it is never itself a link.
            $kind = $item['kind'] ?? 'content';
            $kind = in_array($kind, ['content', 'product', 'countdown', 'metric'], true) ? $kind : 'content';
            if ($kind === 'product') {
                $hasLink = false;
                $linkHtml = '';
            }

            $tag = $hasLink ? 'a' : 'div';
            $hrefAttr = $hasLink ? " href=\"{$cardHref}\"" : '';
            $cursorStyle = $hasLink ? 'cursor:pointer;text-decoration:none;' : '';
            $pointerClass = $hasLink ? ' cursor-pointer group' : '';
            $iconBlock = $icon ? "<div class=\"tb-bento-item-icon\">{$icon}</div>" : '';
            $textHtml = $itext !== '' ? "<p class=\"tb-bento-item-text\">{$itext}</p>" : '';

            $body = match ($kind) {
                'product' => ($ititle !== '' ? "<p class=\"tb-bento-eyebrow\">{$ititle}</p>" : '') . $this->bentoProductHtml($item),
                'countdown' => "{$iconBlock}<h3 class=\"tb-bento-item-title\">{$ititle}</h3>{$textHtml}"
                    . $this->renderCountdown(['end_date' => $item['end_date'] ?? '', 'end_time' => $item['end_time'] ?? '', 'show_end_date' => true], '', "{$id}-cd" . count($cells)),
                'metric' => $iconBlock
                    . '<p class="tb-bento-metric-value">' . $this->escape($this->displayNumber($item['metric_value'] ?? '')) . '</p>'
                    . "<h3 class=\"tb-bento-item-title\">{$ititle}</h3>{$textHtml}",
                // Byte-identical to the pre-3b content cell.
                default => "{$iconBlock}
                <h3 class=\"tb-bento-item-title\">{$ititle}</h3>
                <p class=\"tb-bento-item-text\">{$itext}</p>",
            };
            $kindClass = $kind !== 'content' ? " kind-{$kind}" : '';

            $cells[] = "<{$tag}{$hrefAttr} class=\"tb-bento-item {$size}{$kindClass}{$mediaClass}{$pointerClass}{$glowClass}\" style=\"{$backgroundStyle}color:{$tc};{$cursorStyle}{$glowStyle}\">
                {$mediaHtml}
                <div class=\"tb-bento-item-content\">
                {$body}
                {$linkHtml}
                </div>
            </{$tag}>";
        }

        // Scoped to this block's own markup: the pulse keyframes travel with
        // the block so the live Canvas preview and the published tenant page
        // animate identically, with the card's own colour/speed custom
        // properties (--tb-glow-color / --tb-glow-speed) driving each card.
        $styleTag = "<style data-tb-block-asset=\"bento_grid\">
@keyframes tb-bento-pulse {
    0%, 100% { box-shadow: 0 0 0 0 rgba(0, 0, 0, 0); border-color: rgba(226, 232, 240, 0.8); }
    50% { box-shadow: 0 0 16px 2px var(--tb-glow-color, rgba(99, 102, 241, 0.5)); border-color: var(--tb-glow-color, rgba(99, 102, 241, 0.9)); }
}
.tb-bento-pulse-card {
    position: relative;
    border: 2px solid transparent !important;
    animation: tb-bento-pulse var(--tb-glow-speed, 2s) infinite ease-in-out;
}
@media (prefers-reduced-motion: reduce) {
    .tb-bento-pulse-card { animation: none; }
}
</style>";

        return $styleTag . "<div id=\"tb-{$id}\" class=\"tb-bento tb-section\">
            <div class=\"tb-container\">{$titleHtml}
                <div class=\"tb-bento-grid\">" . implode('', $cells) . "</div>
            </div>
        </div>";
    }

    /**
     * Phase 3b (ADR-069): a bento cell's live product. This package has no
     * products, so the Builder preview shows a labelled sample card;
     * customer-site overrides this with the real product (`product_id`, or
     * the newest when 0).
     */
    protected function bentoProductHtml(array $item): string
    {
        $card = $this->productCard($this->demoProductCard(['title' => 'محصول نمونه', 'price' => 1250000, 'original' => 1590000, 'badge' => null, 'stock' => 'موجود'], 2));
        return $card . '<p class="tb-bento-note">نمونه؛ در سایت، محصول انتخاب‌شده نمایش داده می‌شود.</p>';
    }

    /**
     * Sanitize a persisted Bento card colour into a token that is safe to
     * interpolate into an inline style attribute: hex, a bare keyword
     * (transparent/currentColor/…), an rgb()/hsl()-family function or a
     * var(--token[, fallback]) reference. Anything else — quotes,
     * semicolons, `url(...)`, `expression(...)` — returns `$fallback`
     * ('' meaning "treat as not authored"), so a corrupted or malicious
     * authored value degrades to a missing gradient instead of breaking out
     * of the style attribute. Mirrors InspectorFieldRegistry::normalizeColor
     * at render time for values that were saved before that boundary existed.
     */
    protected function bentoColorToken(mixed $value, string $fallback = ''): string
    {
        if (!is_scalar($value)) {
            return $fallback;
        }

        $token = trim((string) $value);
        if ($token === '') {
            return '';
        }

        $numberish = '[0-9a-zA-Z%,.\s/*+-]+';
        // A token's fallback may be a hex colour: the registry's own default
        // glow_color is `var(--tb-primary, #6366f1)`, which this allowlist
        // used to reject (Unit 5, ADR-075). `#` cannot leave a style value.
        $pattern = '~^(?:#[0-9a-f]{3,8}|transparent|currentcolor|inherit|initial|unset|'
            . '(?:rgb|rgba|hsl|hsla|hwb|lab|lch|oklab|oklch)\(\s*' . $numberish . '\)|'
            . 'var\(\s*--[A-Za-z0-9_-]+(?:\s*,\s*(?:#[0-9a-f]{3,8}|' . $numberish . '))?\s*\))$~i';

        return preg_match($pattern, $token) === 1 ? $token : $fallback;
    }

    /**
     * Phase 0.5 (ADR-064) — the one reader for an author colour prop. It
     * accepts what the Canvas colour control stores: a hex value, an
     * rgb()/rgba()/hsl()-family function, a theme token reference
     * `var(--tb-…)` (optionally with a colour fallback), `transparent`, or a
     * named colour. Anything else (quotes, `;`, `url(…)`, arrays) returns
     * $fallback, so an authored value can never break out of a style
     * attribute. $fallback is trusted renderer code, e.g.
     * 'var(--tb-primary, #4f46e5)'.
     */
    protected function cssColor(mixed $value, string $fallback): string
    {
        if (!is_string($value)) {
            return $fallback;
        }
        $color = trim($value);
        return $color !== '' && \SaaSPlatform\ThemeRuntime\Security\HtmlSanitizer::isCssColor($color) ? $color : $fallback;
    }

    /**
     * Phase 0.5 (ADR-064): a counter, badge or count as Persian pages read
     * it (Support\PersianNumber::localize — Persian digits, ٬/٫, ٪). A host
     * rendering a Latin-digit site sets the context key `digits` => 'latin'.
     */
    protected function displayNumber(mixed $value): string
    {
        $text = $this->stringify($value);
        return ($this->context['digits'] ?? null) === 'latin' ? $text : PersianNumber::localize($text);
    }

    /** A price in Toman: «۱٬۲۵۰٬۰۰۰ تومان» (or Latin digits, see displayNumber()). */
    protected function displayToman(int|float $amount): string
    {
        return ($this->context['digits'] ?? null) === 'latin'
            ? number_format($amount) . ' ' . PersianNumber::TOMAN
            : PersianNumber::toman($amount);
    }

    /**
     * Phase 0.6 (ADR-065): an icon prop. A Support\IconLibrary name renders
     * as inline SVG (stroke = currentColor); anything else — a legacy emoji
     * or text — is escaped exactly as before, so stored content is
     * unchanged. '' stays '' (callers test it for "no icon").
     */
    protected function iconHtml(mixed $value): string
    {
        return IconLibrary::has($value) ? IconLibrary::svg((string) $value) : $this->escape($value);
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
            $icon     = $this->iconHtml($svc['icon'] ?? '');
            $stitle   = $this->escape($svc['title'] ?? '');
            $stext    = $this->escape($svc['text'] ?? '');
            $link     = $this->linkHref($svc['link'] ?? '#', '');
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
            $infoItems[] = "<div class=\"tb-contact-info-item\"><span class=\"tb-contact-info-icon\">" . $this->iconHtml('phone') . "</span><div><p class=\"tb-contact-info-label\">تلفن</p><p class=\"tb-contact-info-value\">{$phone}</p></div></div>";
        }
        if ($email) {
            $infoItems[] = "<div class=\"tb-contact-info-item\"><span class=\"tb-contact-info-icon\">" . $this->iconHtml('mail') . "</span><div><p class=\"tb-contact-info-label\">ایمیل</p><p class=\"tb-contact-info-value\">{$email}</p></div></div>";
        }
        if ($address) {
            $infoItems[] = "<div class=\"tb-contact-info-item\"><span class=\"tb-contact-info-icon\">" . $this->iconHtml('map-pin') . "</span><div><p class=\"tb-contact-info-label\">آدرس</p><p class=\"tb-contact-info-value\">{$address}</p></div></div>";
        }
        if ($hours) {
            $infoItems[] = "<div class=\"tb-contact-info-item\"><span class=\"tb-contact-info-icon\">" . $this->iconHtml('clock') . "</span><div><p class=\"tb-contact-info-label\">ساعات کاری</p><p class=\"tb-contact-info-value\">{$hours}</p></div></div>";
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
            . "<input id=\"{$nameFieldId}\" type=\"text\" placeholder=\"نام شما\" style=\"width:100%;padding:0.65rem 1rem;border:1px solid var(--tb-border, #e5e7eb);border-radius:0.5rem;font-family:inherit;font-size:0.95rem;\"></div>
                    <div style=\"margin-bottom:1rem;\">"
            . $this->visuallyHiddenLabel($contactFieldId, 'شماره موبایل یا ایمیل')
            . "<input id=\"{$contactFieldId}\" type=\"text\" placeholder=\"شماره موبایل یا ایمیل\" style=\"width:100%;padding:0.65rem 1rem;border:1px solid var(--tb-border, #e5e7eb);border-radius:0.5rem;font-family:inherit;font-size:0.95rem;\"></div>
                    <div style=\"margin-bottom:1rem;\">"
            . $this->visuallyHiddenLabel($messageFieldId, 'پیام شما')
            . "<textarea id=\"{$messageFieldId}\" placeholder=\"پیام شما\" rows=\"4\" style=\"width:100%;padding:0.65rem 1rem;border:1px solid var(--tb-border, #e5e7eb);border-radius:0.5rem;font-family:inherit;font-size:0.95rem;resize:vertical;\"></textarea></div>
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
            $url = $this->escapeUrl($this->mediaSrc(is_array($item) ? ($item['url'] ?? '') : $item), '');
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
        $link = $this->linkHref($props['link'] ?? '#', '');
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
            'banner_image' => $this->mediaSrc($link['mega_banner_image'] ?? ''),
            'banner_url' => $this->linkValue($link['mega_banner_url'] ?? ''),
            'banner_text' => (string) ($link['mega_banner_text'] ?? ''),
            'banner_cta' => (string) ($link['mega_banner_cta'] ?? ''),
            'show_desktop' => array_key_exists('mega_show_desktop', $link) ? $this->flag($link['mega_show_desktop']) : true,
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
                        'url' => $this->linkValue($it['url'] ?? '#'),
                        'label' => $itLabel,
                        'icon' => (string) ($it['icon'] ?? ''),
                        'image' => $this->mediaSrc($it['image'] ?? ''),
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
                $lUrl = $this->linkHref(is_array($l) ? ($l['url'] ?? '') : '', '#');
                $lLabel = $this->escape(is_array($l) ? ($l['label'] ?? '') : (string) $l);
                if ($lLabel === '') {
                    continue;
                }
                $lIcon = $this->iconHtml(is_array($l) ? ($l['icon'] ?? '') : '');
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

        // Phase 1 (ADR-067): the promo slot — image, title and an optional
        // call to action, all from the nested repeater item.
        $bannerImage = trim((string) ($mega['banner_image'] ?? ''));
        $bannerHtml = '';
        if ($bannerImage !== '') {
            $bannerUrl = $this->linkHref($mega['banner_url'] ?? '', '#');
            $bannerText = $this->escape($mega['banner_text'] ?? '');
            $bannerCta = $this->escape($mega['banner_cta'] ?? '');
            $bannerImgHtml = $this->imageWithFallback($bannerImage, (string) ($mega['banner_text'] ?? $label), 'width:100%;height:100%;');
            $bannerHtml = "<a href=\"{$bannerUrl}\" class=\"tb-mega-menu-banner\">"
                . "<div class=\"tb-mega-menu-banner-img\">{$bannerImgHtml}</div>"
                . ($bannerText !== '' ? "<div class=\"tb-mega-menu-banner-text\">{$bannerText}</div>" : '')
                . ($bannerCta !== '' ? "<span class=\"tb-mega-menu-banner-cta\">{$bannerCta} " . $this->iconHtml('arrow-left') . '</span>' : '')
                . "</a>";
        }

        $showDesktop = ($mega['show_desktop'] ?? true) !== false;
        $mobileBehavior = $mega['mobile_behavior'] ?? 'accordion';
        $mobileBehavior = in_array($mobileBehavior, ['accordion', 'flat', 'hidden'], true) ? $mobileBehavior : 'accordion';
        $itemClasses = 'tb-mega-menu-item tb-mega-mobile-' . $mobileBehavior . ($showDesktop ? '' : ' tb-mega-desktop-hidden');
        $this->headerNeedsScript = true;

        // Toggling (touch, keyboard, Esc, outside click) is delegated in
        // blocks/site-header.js, so a cloned copy (the host's mobile menu)
        // works too — no per-item inline script.
        return "<div class=\"{$itemClasses}\" id=\"{$itemId}\">
            <a href=\"{$url}\" class=\"tb-nav-header-link tb-mega-menu-trigger\" aria-haspopup=\"true\" aria-expanded=\"false\" aria-controls=\"{$itemId}-panel\">{$label}<span class=\"tb-mega-menu-caret\" aria-hidden=\"true\">" . $this->iconHtml('chevron-down') . "</span></a>
            <div class=\"tb-mega-menu-panel\" id=\"{$itemId}-panel\">
                <div class=\"tb-mega-menu-cols\">{$columnsHtml}</div>
                {$bannerHtml}
            </div>
        </div>";
    }

    protected function renderHeaderModernV1(array $props, string $inner, string $id): string
    {
        $bgColor = $this->headerModernColor($props['bgColor'] ?? null, '#ffffff');
        $textColor = $this->headerModernColor($props['textColor'] ?? null, '#172033');
        $ctaColor = $this->headerModernColor($props['ctaColor'] ?? null, '#4f46e5');
        $ctaTextColor = $this->readableTextColorOn($ctaColor) ?? '#ffffff';

        $brandName = trim($this->stringify($props['brandName'] ?? 'برند شما'));
        $brandName = $this->escape($brandName !== '' ? $brandName : 'برند شما');
        $homeUrl = $this->linkHref($props['homeUrl'] ?? '/', '/');
        $logoUrl = trim($this->stringify($props['logoUrl'] ?? ''));
        $logoSrc = $logoUrl !== '' ? $this->escapeUrl($logoUrl, '') : '';
        $mobileLogoUrl = trim($this->stringify($props['mobileLogoUrl'] ?? ''));
        $mobileLogoSrc = $mobileLogoUrl !== '' ? $this->escapeUrl($mobileLogoUrl, '') : '';
        $logo = $logoSrc !== ''
            ? '<img class="tb-header-modern__logo" src="' . $logoSrc . '" alt="' . $brandName . '">'
            : '<span>' . $brandName . '</span>';
        $mobileLogo = $mobileLogoSrc !== ''
            ? '<img class="tb-header-modern__logo tb-header-modern__logo--mobile" src="' . $mobileLogoSrc . '" alt="' . $brandName . '">'
            : '';

        $menuHtml = '';
        $menuItems = $props['menuItems'] ?? [];
        foreach (is_array($menuItems) ? $menuItems : [] as $item) {
            $label = trim($this->stringify(is_array($item) ? ($item['label'] ?? '') : ''));
            if ($label === '') {
                continue;
            }
            $url = $this->escapeUrl($this->stringify(is_array($item) ? ($item['url'] ?? '#') : '#'), '#');
            $menuHtml .= '<a class="tb-header-modern__link" href="' . $url . '">' . $this->escape($label) . '</a>';
        }

        $ctaText = trim($this->stringify($props['ctaText'] ?? 'شروع کنید'));
        $ctaUrl = $this->linkHref($props['ctaUrl'] ?? '#', '#');
        $ctaHtml = '';
        if ($ctaText !== '' && $this->showsLink($ctaUrl)) {
            $ctaGlow = $this->flag($props['ctaGlow'] ?? false);
            $ctaGlowClass = $ctaGlow ? ' is-glow' : '';
            $ctaHtml = '<a class="tb-header-modern__cta' . $ctaGlowClass . '" href="' . $ctaUrl . '"' . $this->deadLinkMark($ctaUrl) . '>' . $this->escape($ctaText) . '</a>';
        }

        $phone = trim($this->stringify($props['phone'] ?? ''));
        $phoneHtml = '';
        if ($phone !== '') {
            $phoneHref = preg_replace('/[^0-9+]/', '', strtr($phone, ['۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4', '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9', '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9'])) ?: '';
            $safePhoneUrl = $phoneHref !== '' ? $this->linkHref('tel:' . $phoneHref, '') : '';
            if ($safePhoneUrl !== '') {
                $phoneGlow = $this->flag($props['phoneGlow'] ?? false);
                $phoneGlowClass = $phoneGlow ? ' is-glow' : '';
                $phoneHtml = '<a class="tb-header-modern__phone' . $phoneGlowClass . '" href="' . $safePhoneUrl . '">' . $this->escape($phone) . '</a>';
            }
        }

        $safeId = $this->escapeAttr($id);
        $menuId = 'tb-header-modern-menu-' . $safeId;
        $style = '--tb-header-bg:' . $bgColor
            . ';--tb-header-text:' . $textColor
            . ';--tb-header-cta:' . $ctaColor
            . ';--tb-header-cta-text:' . $ctaTextColor . ';';

        $assets = $this->headerModernAssets();

        return '<div id="tb-' . $safeId . '" class="tb-header-modern" style="' . $style . '">'
            . '<div class="tb-header-modern__inner">'
            . '<a class="tb-header-modern__brand" href="' . $homeUrl . '" aria-label="' . $brandName . '">' . $logo . ($mobileLogo !== '' ? $mobileLogo : '') . '</a>'
            . '<nav id="' . $menuId . '" class="tb-header-modern__menu" aria-label="منوی اصلی">' . $menuHtml . '</nav>'
            . '<div class="tb-header-modern__actions">' . $ctaHtml . $phoneHtml
            . '<button type="button" class="tb-header-modern__toggle" aria-expanded="false" aria-controls="' . $menuId . '" aria-label="باز کردن منو">'
            . '<span class="tb-header-modern__toggle-lines" aria-hidden="true"></span></button></div>'
            . '</div></div>' . $assets;
    }

    private function headerModernColor(mixed $value, string $fallback): string
    {
        $color = trim($this->stringify($value));
        if (preg_match('/^#[0-9a-f]{3}$/i', $color)) {
            return '#' . strtolower($color[1] . $color[1] . $color[2] . $color[2] . $color[3] . $color[3]);
        }
        return preg_match('/^#[0-9a-f]{6}$/i', $color) ? strtolower($color) : $fallback;
    }

    private function headerModernAssets(): string
    {
        if ($this->headerModernAssetsEmitted) {
            return '';
        }
        $this->headerModernAssetsEmitted = true;

        $css = @file_get_contents(ThemeRuntimeServiceProvider::headerModernStylePath());
        $js = @file_get_contents(ThemeRuntimeServiceProvider::headerModernScriptPath());
        if (!is_string($css) || $css === '' || !is_string($js) || $js === '') {
            throw new \RuntimeException('Theme runtime packaging fault: header_modern_v1 assets are missing.');
        }

        return '<style data-tb-block-asset="header_modern_v1">' . $css . '</style>'
            . '<script data-tb-block-asset="header_modern_v1">' . $js . '</script>';
    }

    public function renderNavHeader(array $props, string $inner, string $id): string
    {
        $safeId = preg_replace('/[^A-Za-z0-9_-]/', '-', $id) ?: 'header';
        $rootId = 'tb-' . $safeId;
        $menuPanelId = $rootId . '-menu-panel';
        $searchFormId = $rootId . '-search-form';
        $searchInputId = $rootId . '-search';

        $background = $this->escapeAttr($this->cssColor($props['bg_color'] ?? null, 'var(--tb-surface, #fff)'));
        $textColor = $this->escapeAttr($this->cssColor($props['text_color'] ?? null, 'var(--tb-text, #172033)'));
        $rawGlowColor = trim($this->stringify($props['cta_glow_color'] ?? ''));
        $glowEnabled = $this->flag($props['cta_glow_enabled'] ?? false) || $rawGlowColor !== '';
        $glowColor = $this->escapeAttr($this->cssColor($rawGlowColor, 'var(--tb-primary, #c2410c)'));

        $logoText = $this->escape($props['logo_text'] ?? '');
        $logoAlt = $this->escapeAttr($props['logo_alt'] ?? ($props['logo_text'] ?? 'لوگو'));
        $logoUrl = $this->linkHref($props['logo_url'] ?? '/', '/');
        $desktopLogoImage = $this->escapeUrl($this->mediaSrc($props['logo_image'] ?? ''), '');
        $mobileLogoImage = $this->escapeUrl($this->mediaSrc($props['mobile_logo'] ?? ''), '');
        $rawLogoHeight = $props['logo_height'] ?? 40;
        $rawMobileLogoHeight = $props['logo_height_mobile'] ?? 32;
        $logoHeight = max(20, min(120, is_numeric($rawLogoHeight) ? (int) $rawLogoHeight : 40));
        $mobileLogoHeight = max(16, min(96, is_numeric($rawMobileLogoHeight) ? (int) $rawMobileLogoHeight : 32));

        $logoImageHtml = '';
        if ($desktopLogoImage !== '') {
            $logoImageHtml = '<img src="' . $desktopLogoImage . '" alt="' . $logoAlt . '" class="tb-nav-header-logo-img" height="' . $logoHeight . '" decoding="async">';
        }
        $siteLogoHtml = '';
        if ($logoImageHtml === '' && $logoText === '' && is_string($this->context['site_logo_html'] ?? null)) {
            $siteLogoHtml = $this->context['site_logo_html'];
        }
        $desktopLogoContent = $logoImageHtml
            . ($logoText !== '' && $desktopLogoImage !== '' ? '<span class="tb-nav-header-logo-text">' . $logoText . '</span>' : '')
            . ($logoImageHtml === '' && $siteLogoHtml === '' ? $logoText : '');
        $desktopLogo = '';
        if ($siteLogoHtml !== '') {
            $desktopLogo = '<div class="tb-nav-header-logo-slot tb-nav-header-logo-slot--desktop">' . $siteLogoHtml . '</div>';
        } elseif ($desktopLogoContent !== '') {
            $desktopLogo = '<div class="tb-nav-header-logo-slot tb-nav-header-logo-slot--desktop"><a href="' . $logoUrl . '" class="tb-nav-header-logo' . ($desktopLogoImage !== '' ? ' tb-nav-header-logo--image' : '') . '"' . ($desktopLogoImage !== '' ? ' style="--tb-logo-h:' . $logoHeight . 'px;--tb-logo-h-m:' . $mobileLogoHeight . 'px"' : '') . ' data-widget-slot="logo">' . $desktopLogoContent . '</a></div>';
        }

        $mobileLogo = '';
        if ($mobileLogoImage !== '') {
            $mobileLogo = '<div class="tb-nav-header-logo-slot tb-nav-header-logo-slot--mobile"><a href="' . $logoUrl . '" class="tb-nav-header-logo tb-nav-header-logo--image tb-nav-header-logo--mobile" style="--tb-logo-h:' . $mobileLogoHeight . 'px;--tb-logo-h-m:' . $mobileLogoHeight . 'px" data-widget-slot="logo"><img src="' . $mobileLogoImage . '" alt="' . $logoAlt . '" class="tb-nav-header-logo-img" height="' . $mobileLogoHeight . '" decoding="async"></a></div>';
        }
        if ($desktopLogo === '' && $mobileLogo === '') {
            $desktopLogo = '<div class="tb-nav-header-logo-slot tb-nav-header-logo-slot--desktop"><span class="tb-nav-header-logo" data-widget-slot="logo">' . ($logoText !== '' ? $logoText : 'خانه') . '</span></div>';
        }

        $menuHtml = '';
        $hasMegaMenu = false;
        if (trim($inner) !== '') {
            $menuHtml = $inner;
        } elseif (($props['nav_source'] ?? 'custom') === 'menu' && is_string($this->context['menu_html'] ?? null) && trim($this->context['menu_html']) !== '') {
            $menuHtml = $this->context['menu_html'];
        } else {
            $links = $props['nav_links'] ?? $props['links'] ?? [];
            if (is_string($links)) {
                $decodedLinks = json_decode($links, true);
                $links = is_array($decodedLinks) ? $decodedLinks : [];
            }
            if (!is_array($links) || $links === []) {
                $links = [
                    ['label' => 'خانه', 'url' => '/'],
                    ['label' => 'فروشگاه', 'url' => '/shop'],
                    ['label' => 'تماس با ما', 'url' => '/contact'],
                ];
            }
            foreach ($links as $link) {
                if (!is_array($link)) {
                    continue;
                }
                $label = $this->escape($link['label'] ?? '');
                if ($label === '') {
                    continue;
                }
                $url = $this->linkHref($link['url'] ?? '#', '#');
                $mega = $this->normalizeMegaMenuData($link);
                if ($mega !== null) {
                    $menuHtml .= $this->renderMegaMenuItem($safeId, $url, $label, $mega);
                    $hasMegaMenu = true;
                } else {
                    $menuHtml .= '<a href="' . $url . '" class="tb-nav-header-link"' . $this->deadLinkMark($url) . '>' . $label . '</a>';
                }
            }
        }
        $hasMegaMenu = $hasMegaMenu || str_contains($menuHtml, 'tb-mega-menu');

        $menuAlignment = in_array($props['menu_align'] ?? 'end', ['start', 'center'], true)
            ? ' tb-nav-header--menu-' . $props['menu_align']
            : '';
        $menuHtml = '<nav class="tb-nav-header-links" aria-label="منوی اصلی" data-widget-slot="menu">'
            . '<div id="' . $this->escapeAttr($menuPanelId) . '" class="tb-nav-header-menu-panel">'
            . '<button type="button" class="tb-nav-header-menu-close" aria-label="بستن منو">' . $this->iconHtml('x') . '</button>'
            . $menuHtml
            . '</div></nav>';

        $actions = '';
        $searchHtml = '';
        $searchAssets = '';
        if ($this->flag($props['show_search'] ?? false)) {
            $requestedScope = $props['search_scope'] ?? 'all';
            $scope = in_array($requestedScope, ['all', 'products', 'posts'], true) ? $requestedScope : 'all';
            [$searchAttrs, $hiddenSearchInputs, $searchAssets, $searchParam] = $this->searchForm($scope);
            $placeholder = $this->escapeAttr($props['search_placeholder'] ?? 'جستجو...');
            $searchInput = '<label class="tb-nav-header-visually-hidden" for="' . $this->escapeAttr($searchInputId) . '">' . $placeholder . '</label>'
                . '<input id="' . $this->escapeAttr($searchInputId) . '" type="search" name="' . $this->escapeAttr($searchParam) . '" class="tb-nav-header-search-input" placeholder="' . $placeholder . '" autocomplete="off" enterkeyhint="search">'
                . $hiddenSearchInputs;
            $searchMode = ($props['search_mode'] ?? 'input') === 'icon' ? 'icon' : 'input';
            if ($searchMode === 'icon') {
                $searchHtml = '<div class="tb-nav-header-search-wrap tb-nav-header-search-wrap--icon">'
                    . '<button type="button" class="tb-nav-header-search-toggle tb-nav-header-icon-link" aria-label="باز کردن جستجو" aria-expanded="false" aria-controls="' . $this->escapeAttr($searchFormId) . '">' . $this->iconHtml('search') . '</button>'
                    . '<form id="' . $this->escapeAttr($searchFormId) . '" class="tb-nav-header-search tb-nav-header-search-popover" role="search" hidden' . $searchAttrs . '>' . $searchInput . '<button type="submit" class="tb-nav-header-search-submit" aria-label="جستجو">' . $this->iconHtml('search') . '</button></form>'
                    . '</div>';
            } else {
                $searchHtml = '<form id="' . $this->escapeAttr($searchFormId) . '" class="tb-nav-header-search" role="search"' . $searchAttrs . '>'
                    . $searchInput
                    . '</form>';
            }
        }

        $socialLinks = is_array($props['social_links'] ?? null) ? $props['social_links'] : [];
        $socialStyle = ($props['social_style'] ?? '') === 'brand';
        $socialClasses = 'tb-nav-header-social' . ($this->flag($props['social_show_mobile'] ?? false) ? ' tb-nav-header-social--mobile' : '');
        $socialHtml = $this->socialIconsHtml($socialLinks, $safeId . '-quick', 'quick-access', $socialStyle, $socialClasses, true);

        $phoneHtml = '';
        $phone = trim($this->stringify($props['phone'] ?? ''));
        if ($phone !== '') {
            $phoneHref = strtr($phone, ['۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4', '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9', '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9']);
            $phoneHref = preg_replace('/[^0-9+]/', '', $phoneHref) ?: '';
            $safePhoneUrl = $phoneHref !== '' ? $this->linkHref('tel:' . $phoneHref, '') : '';
            if ($safePhoneUrl !== '') {
                $rawPhoneGlowColor = trim($this->stringify($props['phone_glow_color'] ?? ''));
                $phoneGlow = $glowEnabled || $this->flag($props['phone_glow_enabled'] ?? false) || $rawPhoneGlowColor !== '';
                $phoneGlowColor = $this->escapeAttr($this->cssColor($rawPhoneGlowColor !== '' ? $rawPhoneGlowColor : $rawGlowColor, 'var(--tb-primary, #c2410c)'));
                $phoneGlowClass = $phoneGlow ? ' tb-phone--glow tb-nav-header-glow' : '';
                $phoneGlowStyle = $rawPhoneGlowColor !== '' ? ' style="--tb-nav-header-glow:' . $phoneGlowColor . ';--phone-glow-color:' . $phoneGlowColor . '"' : '';
                $phoneHtml = '<a href="' . $safePhoneUrl . '" class="tb-nav-header-phone' . $phoneGlowClass . '"' . $phoneGlowStyle . ' data-widget-slot="contact">' . $this->escape($phone) . '</a>';
            }
        }

        $cartHtml = '';
        if ($this->flag($props['show_cart'] ?? false)) {
            $cartHtml = $this->cartTriggerHtml('tb-nav-header-icon-link', $this->iconHtml('shopping-cart'));
        }
        $accountHtml = '';
        if ($this->flag($props['show_account'] ?? false)) {
            $accountUrl = $this->linkHref($this->context['account_url'] ?? '/account', '/account');
            $accountHtml = '<a href="' . $accountUrl . '" class="tb-nav-header-icon-link" aria-label="حساب کاربری">' . $this->iconHtml('user') . '</a>';
        }
        $darkToggleHtml = '';
        if ($this->flag($props['show_dark_toggle'] ?? false)) {
            $darkToggleHtml = $this->renderDarkModeToggle($safeId);
        }

        $ctaHtml = '';
        $ctaText = $this->escape($props['cta_text'] ?? '');
        $ctaUrl = $this->linkHref($props['cta_url'] ?? '', '#');
        if ($ctaText !== '') {
            $rawCtaGlowColor = trim($this->stringify($props['cta_glow_color'] ?? $rawGlowColor));
            $ctaGlow = $glowEnabled || $rawCtaGlowColor !== '';
            $ctaGlowColor = $this->escapeAttr($this->cssColor($rawCtaGlowColor, 'var(--tb-primary, #c2410c)'));
            $ctaGlowClass = $ctaGlow ? ' tb-cta--glow tb-nav-header-glow' : '';
            $ctaGlowStyle = $ctaGlow ? ' style="--tb-nav-header-glow:' . $ctaGlowColor . ';--cta-glow-color:' . $ctaGlowColor . '"' : '';
            $ctaHtml = '<a href="' . $ctaUrl . '" class="tb-btn tb-btn-primary tb-nav-header-cta' . $ctaGlowClass . '"' . $ctaGlowStyle . $this->deadLinkMark($ctaUrl) . '>' . $ctaText . '</a>';
        }

        $burger = $menuHtml !== ''
            ? '<button type="button" class="tb-nav-header-toggle tb-nav-header-icon-link" aria-label="باز کردن منو" aria-expanded="false" aria-controls="' . $this->escapeAttr($menuPanelId) . '">' . $this->iconHtml('menu') . '</button>'
            : '';

        $headerOrderRaw = $props['header_order'] ?? null;
        $headerOrder = [];
        if (is_string($headerOrderRaw)) {
            $decoded = json_decode($headerOrderRaw, true);
            $headerOrder = is_array($decoded) ? $decoded : [];
        } elseif (is_array($headerOrderRaw)) {
            $headerOrder = $headerOrderRaw;
        }
        $defaultOrder = ['logo', 'menu', 'search', 'social', 'phone', 'cta', 'cart', 'account', 'dark_toggle', 'burger'];
        // The stored order positions elements; it never hides one. Unknown
        // keys are dropped, and every known element the stored order omits
        // (an empty value, or a template saved before the element existed)
        // follows in default order, matching the Canvas drag list. Whether an
        // element renders at all stays with its own show_* / content props.
        $headerOrder = array_values(array_unique(array_filter(
            array_map(fn($k) => is_scalar($k) ? (string) $k : '', $headerOrder),
            fn($k) => in_array($k, $defaultOrder, true),
        )));
        $headerOrder = array_merge($headerOrder, array_values(array_diff($defaultOrder, $headerOrder)));

        $sectionHtml = [
            'logo' => $desktopLogo . $mobileLogo,
            'menu' => $menuHtml,
            'search' => $searchHtml,
            'social' => $socialHtml,
            'phone' => $phoneHtml,
            'cta' => $ctaHtml,
            'cart' => $cartHtml,
            'account' => $accountHtml,
            'dark_toggle' => $darkToggleHtml,
            'burger' => $burger,
        ];

        $actionKeys = ['search', 'social', 'phone', 'cta', 'cart', 'account', 'dark_toggle', 'burger'];
        $orderedInner = '';
        $actionsInner = '';
        foreach ($headerOrder as $key) {
            $html = $sectionHtml[$key] ?? '';
            if ($html === '') {
                continue;
            }
            if (in_array($key, $actionKeys, true)) {
                $actionsInner .= $html;
            } else {
                if ($actionsInner !== '') {
                    $orderedInner .= '<div class="tb-nav-header-actions" data-widget-slot="actions">' . $actionsInner . '</div>';
                    $actionsInner = '';
                }
                $orderedInner .= $html;
            }
        }
        if ($actionsInner !== '') {
            $orderedInner .= '<div class="tb-nav-header-actions" data-widget-slot="actions">' . $actionsInner . '</div>';
        }
        $headerFlags = [];
        if ($this->flag($props['sticky'] ?? false)) {
            $headerFlags[] = 'sticky';
            if ($this->flag($props['sticky_hide_on_scroll'] ?? false)) {
                $headerFlags[] = 'smart';
            }
        }
        if ($this->flag($props['transparent_over_hero'] ?? false)) {
            $headerFlags[] = 'overlay';
        }
        $overlayText = trim($this->stringify($props['transparent_text_color'] ?? ''));
        $overlayText = $overlayText !== '' && $this->cssColor($overlayText, '') !== '' ? $this->escapeAttr($overlayText) : 'auto';
        // On the outer .tb-nav-header element: site-header.js initialises
        // '.tb-nav-header[data-tb-header]' and makes the page banner around it
        // sticky. On any inner element the selector matches nothing and the
        // sticky / hide-on-scroll / overlay settings silently do nothing.
        $headerAttrs = $headerFlags !== []
            ? ' data-tb-header="' . implode(' ', $headerFlags) . '" data-tb-overlay-text="' . $overlayText . '"'
            : '';

        $scripts = $headerFlags !== [] || $hasMegaMenu ? $this->siteHeaderAssets() : '';
        $scriptRootId = json_encode($rootId, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_SLASHES) ?: '""';
        $interactionScript = '<script>(function(){var host=document.getElementById(' . $scriptRootId . ');var root=host&&host.querySelector(".tb-nav-header-root");if(!root||root.dataset.tbNavInit)return;root.dataset.tbNavInit="1";var menu=root.querySelector(".tb-nav-header-links"),panel=root.querySelector(".tb-nav-header-menu-panel"),toggle=root.querySelector(".tb-nav-header-toggle"),close=root.querySelector(".tb-nav-header-menu-close"),overlay=root.querySelector(".tb-nav-header-overlay"),searchToggle=root.querySelector(".tb-nav-header-search-toggle"),searchForm=root.querySelector(".tb-nav-header-search-popover");var previousFocus=null,oldOverflow="";function setMenu(open,restore){if(!menu||!toggle)return;root.setAttribute("data-menu-open",open?"true":"false");toggle.setAttribute("aria-expanded",open?"true":"false");if(overlay)overlay.hidden=!open;if(open){previousFocus=document.activeElement;oldOverflow=document.body.style.overflow;document.body.style.overflow="hidden";close&&close.focus();}else{document.body.style.overflow=oldOverflow;if(restore&&previousFocus&&typeof previousFocus.focus==="function")previousFocus.focus();}}function setSearch(open){if(!searchForm||!searchToggle)return;searchForm.hidden=!open;searchToggle.setAttribute("aria-expanded",open?"true":"false");if(open){var input=searchForm.querySelector("input[type=search]");input&&input.focus();}}toggle&&toggle.addEventListener("click",function(){setMenu(root.getAttribute("data-menu-open")!=="true",true);});close&&close.addEventListener("click",function(){setMenu(false,true);});overlay&&overlay.addEventListener("click",function(){setMenu(false,true);});searchToggle&&searchToggle.addEventListener("click",function(){setSearch(searchForm.hidden);});document.addEventListener("click",function(event){if(!root.contains(event.target)){if(root.getAttribute("data-menu-open")==="true")setMenu(false,false);if(searchForm&&!searchForm.hidden)setSearch(false);}});document.addEventListener("keydown",function(event){if(event.key==="Escape"){if(searchForm&&!searchForm.hidden){setSearch(false);searchToggle&&searchToggle.focus();}else if(root.getAttribute("data-menu-open")==="true"){setMenu(false,true);}return;}if(event.key!=="Tab"||root.getAttribute("data-menu-open")!=="true"||!panel)return;var items=panel.querySelectorAll("a[href],button:not([disabled]),input:not([disabled]),[tabindex]:not([tabindex=\"-1\"])");if(!items.length)return;var first=items[0],last=items[items.length-1];if(event.shiftKey&&document.activeElement===first){event.preventDefault();last.focus();}else if(!event.shiftKey&&document.activeElement===last){event.preventDefault();first.focus();}});var mq=window.matchMedia("(min-width: 768px)");var reset=function(){if(mq.matches&&root.getAttribute("data-menu-open")==="true")setMenu(false,false);};if(mq.addEventListener)mq.addEventListener("change",reset);else mq.addListener(reset);})();</script>';

        $styles = '<style>'
            . '.tb-nav-header-root{position:relative;isolation:isolate;width:100%;direction:rtl;color:inherit;pointer-events:auto;}'
            . '.tb-nav-header-root *{box-sizing:border-box;}'
            . '.tb-nav-header-root .tb-nav-header-inner{display:flex;align-items:center;justify-content:space-between;direction:rtl;gap:clamp(.65rem,2vw,1.5rem);width:min(100%,80rem);min-width:0;min-height:4.25rem;margin-inline:auto;padding:.5rem 1rem;flex-wrap:nowrap;}'
            . '.tb-nav-header-root .tb-nav-header-logo-slot{flex:0 1 auto;min-width:0;}'
            . '.tb-nav-header-root .tb-nav-header-logo-slot--mobile{display:none;}'
            . '.tb-nav-header-root .tb-nav-header-logo{display:inline-flex;align-items:center;gap:.55rem;max-width:100%;min-width:0;color:inherit;font-weight:800;text-decoration:none;}'
            . '.tb-nav-header-root .tb-nav-header-logo-img{display:block;width:auto;max-width:min(14rem,35vw);height:var(--tb-logo-h,40px);object-fit:contain;}'
            . '.tb-nav-header-root .tb-nav-header-logo-text{min-width:0;overflow-wrap:anywhere;}'
            . '.tb-nav-header-root .tb-nav-header-links{display:flex;flex:1 1 auto;align-items:center;justify-content:center;min-width:0;}'
            . '.tb-nav-header-root .tb-nav-header-menu-panel{display:flex;align-items:center;justify-content:center;gap:clamp(.75rem,1.7vw,1.5rem);min-width:0;}'
            . '.tb-nav-header-root .tb-nav-header-link,.tb-nav-header-root .tb-nav-header-menu-panel>a,.tb-nav-header-root .tb-nav-header-links>a{color:inherit;text-decoration:none;white-space:nowrap;}'
            . '.tb-nav-header--menu-start .tb-nav-header-root .tb-nav-header-links{justify-content:flex-start;}'
            . '.tb-nav-header-root .tb-nav-header-actions{display:flex;flex:0 1 auto;align-items:center;justify-content:flex-end;gap:.55rem;min-width:0;}'
            . '.tb-nav-header-root .tb-nav-header-search-wrap{position:relative;}'
            . '.tb-nav-header-root .tb-nav-header-search{display:flex;align-items:center;gap:.35rem;}'
            . '.tb-nav-header-root .tb-nav-header-search-popover{position:absolute;z-index:10003;inset-block-start:calc(100% + .55rem);inset-inline-start:0;width:min(22rem,calc(100vw - 2rem));padding:.65rem;background:var(--tb-surface,#fff);color:var(--tb-text,#172033);border:1px solid var(--tb-border,#d1d5db);border-radius:.5rem;box-shadow:0 12px 32px rgb(15 23 42 / 18%);}'
            . '.tb-nav-header-root .tb-nav-header-search[hidden]{display:none!important;}'
            . '.tb-nav-header-root .tb-nav-header-search-input{width:min(12rem,32vw);min-width:0;min-height:2.5rem;padding:.5rem .75rem;border:1px solid var(--tb-border,#d1d5db);border-radius:.35rem;background:var(--tb-surface,#fff);color:inherit;font:inherit;}'
            . '.tb-nav-header-root .tb-nav-header-search-popover .tb-nav-header-search-input{width:auto;flex:1;}'
            . '.tb-nav-header-root .tb-nav-header-icon-link,.tb-nav-header-root .tb-nav-header-search-submit,.tb-nav-header-root .tb-nav-header-menu-close{display:inline-flex;align-items:center;justify-content:center;flex:0 0 auto;min-width:2.5rem;min-height:2.5rem;padding:.45rem;border:0;border-radius:.375rem;background:transparent;color:inherit;cursor:pointer;}'
            . '.tb-nav-header-root .tb-nav-header-icon-link:hover,.tb-nav-header-root .tb-nav-header-search-submit:hover,.tb-nav-header-root .tb-nav-header-menu-close:hover{background:color-mix(in srgb,currentColor 9%,transparent);}'
            . '.tb-nav-header-root .tb-nav-header-toggle,.tb-nav-header-root .tb-nav-header-menu-close{display:none;}'
            . '.tb-nav-header-root .tb-nav-header-menu-close{align-self:flex-end;}'
            . '.tb-nav-header-root .tb-nav-header-overlay{display:none;}'
            . '.tb-nav-header-root .tb-nav-header-overlay[hidden]{display:none!important;}'
            . '.tb-nav-header-root .tb-nav-header-phone{display:inline-flex;align-items:center;min-height:2.5rem;padding-inline:.35rem;color:inherit;font-weight:700;text-decoration:none;white-space:nowrap;transition:box-shadow .25s ease,filter .25s ease;}'
            . '.tb-nav-header-root .tb-nav-header-social .tb-social-icon-svg{display:block;width:auto;height:1.2rem;max-width:100%;object-fit:contain;}'
            . '.tb-nav-header-root .tb-nav-header-social .tb-social-icon--eitaa,.tb-nav-header-root .tb-nav-header-social .tb-social-icon--bale,.tb-nav-header-root .tb-nav-header-social .tb-social-icon--rubika,.tb-nav-header-root .tb-nav-header-social .tb-social-icon--soroush{background:var(--tb-surface,#fff);color:inherit;border:1px solid var(--tb-border,#d1d5db);}'
            . '.tb-nav-header-root .tb-nav-header-social .tb-social-icon--rubika{width:3.75rem;border-radius:.375rem;}'
            . '.tb-nav-header-root .tb-nav-header-social .tb-social-icon--rubika .tb-social-icon-svg{height:1.15rem;}'
            . '.tb-nav-header-root .tb-nav-header-cta{display:inline-flex;align-items:center;justify-content:center;min-height:2.5rem;padding:.55rem 1rem;border-radius:.375rem;text-decoration:none;white-space:nowrap;transition:box-shadow .25s ease,filter .25s ease;}'
            . '.tb-nav-header-root .tb-nav-header-glow{animation:tb-glow-pulse 2.2s ease-in-out infinite;}'
            . '@keyframes tb-glow-pulse{0%,100%{box-shadow:0 0 0 0 color-mix(in srgb,var(--tb-nav-header-glow) 0%,transparent);}50%{box-shadow:0 0 0 .45rem color-mix(in srgb,var(--tb-nav-header-glow) 24%,transparent);}}'
            . '.tb-nav-header-root .tb-nav-header-visually-hidden{position:absolute;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap;border:0;}'
            . '@media(max-width:767.98px){.tb-nav-header-root .tb-nav-header-inner{min-height:3.75rem;gap:.5rem;padding-inline:.75rem;}.tb-nav-header-root--has-mobile-logo .tb-nav-header-logo-slot--desktop{display:none;}.tb-nav-header-root--has-mobile-logo .tb-nav-header-logo-slot--mobile{display:block;}.tb-nav-header-root .tb-nav-header-links{display:none;}.tb-nav-header-root[data-menu-open="true"]{z-index:10001;}.tb-nav-header-root[data-menu-open="true"] .tb-nav-header-links{display:flex;position:fixed;z-index:10002;inset-block:0;inset-inline:0 auto;width:min(22rem,88vw);max-width:100%;padding:1rem;background:var(--tb-surface,#fff);color:var(--tb-text,#172033);box-shadow:-12px 0 32px rgb(15 23 42 / 18%);overflow-y:auto;overscroll-behavior:contain;align-items:stretch;justify-content:flex-start;}.tb-nav-header-root .tb-nav-header-menu-panel{width:100%;flex-direction:column;align-items:stretch;justify-content:flex-start;gap:.5rem;}.tb-nav-header-root .tb-nav-header-menu-close{display:inline-flex;}.tb-nav-header-root .tb-nav-header-menu-panel .tb-nav-header-link,.tb-nav-header-root .tb-nav-header-menu-panel>a{display:flex;align-items:center;min-height:2.75rem;padding:.6rem .5rem;white-space:normal;border-bottom:1px solid var(--tb-border,#e5e7eb);}.tb-nav-header-root .tb-nav-header-overlay:not([hidden]){display:block;position:fixed;z-index:10000;inset:0;width:100%;height:100%;padding:0;border:0;border-radius:0;background:rgb(15 23 42 / 52%);}.tb-nav-header-root .tb-nav-header-toggle{display:inline-flex;}.tb-nav-header-root[data-menu-open="true"] .tb-nav-header-actions{position:relative;z-index:10001;}.tb-nav-header-root .tb-nav-header-actions{gap:.3rem;}.tb-nav-header-root .tb-nav-header-search-input{width:min(9rem,32vw);}.tb-nav-header-root .tb-nav-header-phone{max-width:7rem;overflow:hidden;text-overflow:ellipsis;font-size:.8rem;}.tb-nav-header-root .tb-nav-header-cta{padding-inline:.65rem;font-size:.82rem;}.tb-nav-header-root .tb-nav-header-social:not(.tb-nav-header-social--mobile){display:none;}}'
            . '@media(prefers-reduced-motion:reduce){.tb-nav-header-root .tb-nav-header-glow{animation:none;}.tb-nav-header-root *{scroll-behavior:auto!important;}}'
            . '</style>';

        $rootClass = 'tb-nav-header-root' . ($mobileLogo !== '' ? ' tb-nav-header-root--has-mobile-logo' : '');
        $headerClass = 'tb-nav-header' . $menuAlignment;

        return $styles
            . '<div id="' . $this->escapeAttr($rootId) . '" class="' . $headerClass . '" style="background:' . $background . ';color:' . $textColor . ';--tb-nav-header-glow:' . $glowColor . ';" data-tb-block-id="' . $this->escapeAttr($id) . '" data-canvas-block-id="' . $this->escapeAttr($id) . '" data-tb-block-type="nav_header"' . $headerAttrs . '>'
            . '<div class="' . $rootClass . '">'
            . '<div class="tb-nav-header-inner">'
            . $orderedInner
            . '<button type="button" class="tb-nav-header-overlay" aria-label="بستن منو" hidden></button>'
            . '</div></div></div>'
            . $searchAssets
            . $scripts
            . $interactionScript;
    }

    private function siteHeaderAssets(): string
    {
        if ($this->siteHeaderAssetsEmitted) {
            return '';
        }
        $js = @file_get_contents(ThemeRuntimeServiceProvider::siteHeaderScriptPath());
        if (!is_string($js) || $js === '') {
            throw new \RuntimeException('Theme runtime packaging fault: site-header.js is missing.');
        }
        $this->siteHeaderAssetsEmitted = true;
        return '<script data-tb-block-asset="site_header">' . $js . '</script>';
    }

    /**
     * Phase 1 (ADR-067): a cart icon. With the host's cart endpoints
     * (siteEndpoints()['cart']) it links to the cart page and opens the
     * mini-cart drawer, with a live count badge; without them (the Builder
     * preview) it is the plain `/cart` link it always was.
     */
    protected function cartTriggerHtml(string $class, string $inner, string $extraAttrs = ''): string
    {
        $cart = $this->cartEndpoints();
        if ($cart === null) {
            return "<a href=\"/cart\" class=\"{$class}\"{$extraAttrs} aria-label=\"سبد خرید\">{$inner}</a>";
        }
        $count = $cart['count'];
        $badge = '<span class="tb-cart-badge" data-tb-cart-count' . ($count > 0 ? '' : ' hidden') . '>' . $this->displayNumber($count) . '</span>';

        return '<a href="' . $this->escapeAttr($cart['page']) . "\" class=\"{$class} tb-cart-trigger\"{$extraAttrs} data-tb-cart-open"
            . ' data-tb-cart="' . $this->escapeAttr(json_encode(array_diff_key($cart, ['count' => 1]), JSON_UNESCAPED_SLASHES)) . '"'
            . ' aria-haspopup="dialog" aria-label="سبد خرید">' . $inner . $badge . '</a>'
            . $this->cartAssets();
    }

    /** @return array{page:string,mini:string,add:string,remove:string,count:int}|null */
    protected function cartEndpoints(): ?array
    {
        $cart = $this->siteEndpoints()['cart'] ?? null;
        if (!is_array($cart)) {
            return null;
        }
        $out = [];
        foreach (['page', 'mini', 'add', 'remove'] as $key) {
            $url = is_string($cart[$key] ?? null) ? trim($cart[$key]) : '';
            if ($url === '' || !\SaaSPlatform\ThemeRuntime\Security\HtmlSanitizer::isValidUrl($url)) {
                return null;
            }
            $out[$key] = $url;
        }
        // Phase 3 (ADR-068): optional — without it the drawer has no stepper.
        $update = is_string($cart['update'] ?? null) ? trim($cart['update']) : '';
        if ($update !== '' && \SaaSPlatform\ThemeRuntime\Security\HtmlSanitizer::isValidUrl($update)) {
            $out['update'] = $update;
        }
        $out['count'] = max(0, (int) ($cart['count'] ?? 0));
        return $out;
    }

    /** Phase 1/2 (ADR-067): blocks/cart.js — drawer + add-to-cart — once per render. */
    protected function cartAssets(): string
    {
        if ($this->cartAssetsEmitted) {
            return '';
        }
        $js = @file_get_contents(ThemeRuntimeServiceProvider::cartScriptPath());
        if (!is_string($js) || $js === '') {
            throw new \RuntimeException('Theme runtime packaging fault: cart.js is missing.');
        }
        $this->cartAssetsEmitted = true;
        return '<script data-tb-block-asset="cart">' . $js . '</script>';
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

        // Phase 1 (ADR-067): catalog SVG icons instead of ☀️ 🌙 💻.
        $icons = json_encode(['light' => IconLibrary::svg('sun'), 'dark' => IconLibrary::svg('moon'), 'system' => IconLibrary::svg('monitor')], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_SLASHES);

        return "<button type=\"button\" id=\"{$toggleId}\" class=\"tb-theme-toggle\" aria-label=\"تغییر حالت رنگی\">
            <span class=\"tb-theme-toggle-icon\" aria-hidden=\"true\">" . IconLibrary::svg('monitor') . "</span>
        </button>
        <script>(function(){
            var btn = document.getElementById('{$toggleId}');
            if (!btn || btn.dataset.tbInit) return;
            btn.dataset.tbInit = '1';
            var ICONS = {$icons};
            var LABELS = {
                light: 'حالت رنگی: روشن — برای تغییر به تاریک کلیک کنید',
                dark: 'حالت رنگی: تاریک — برای تغییر به خودکار (سیستم) کلیک کنید',
                system: 'حالت رنگی: هماهنگ با سیستم — برای تغییر به روشن کلیک کنید'
            };
            function render() {
                var p = (window.__tbTheme && window.__tbTheme.get) ? window.__tbTheme.get() : 'system';
                var icon = btn.querySelector('.tb-theme-toggle-icon');
                if (icon) icon.innerHTML = ICONS[p] || ICONS.system;
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
        $bg        = $this->escapeAttr($this->cssColor($props['bg_color'] ?? null, '#111827'));
        $tc        = $this->escapeAttr($this->cssColor($props['text_color'] ?? null, '#e5e7eb'));
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
                    $url   = $this->linkHref(is_array($link) ? ($link['url'] ?? '') : '', '#');
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

        // Phase 1 (ADR-067): social links as catalog icons (network chosen or
        // recognised from the URL; an unknown one keeps its text label).
        $socialHtml = $socialLinks !== [] ? $this->socialIconsHtml($socialLinks, "{$id}-social", 'social', ($props['social_style'] ?? 'mono') === 'brand') : '';
        $extrasHtml = $this->footerExtrasHtml($props, $id);

        $legalHtml = '';
        foreach ($legalLinks as $link) {
            $url   = $this->linkHref(is_array($link) ? ($link['url'] ?? '') : '', '#');
            $label = $this->escape(is_array($link) ? ($link['label'] ?? '') : (string) $link);
            if ($label === '') {
                continue;
            }
            $legalHtml .= "<a href=\"{$url}\" class=\"tb-nav-footer-legal-link\">{$label}</a>";
        }

        $brandHtml = ($logoText || $tagline)
            ? "<div class=\"tb-nav-footer-brand\" data-widget-slot=\"brand\">"
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
                " . ($colHtml ? "<div class=\"tb-nav-footer-cols\" data-widget-slot=\"columns\">{$colHtml}</div>" : '') . "
                {$extrasHtml}
                <div class=\"tb-nav-footer-bottom\" data-widget-slot=\"bottom\">
                    " . ($copyright ? "<p class=\"tb-nav-footer-copyright\">{$copyright}</p>" : '') . "
                    {$legalHtml}
                    {$socialHtml}
                </div>
            </div>
        </div>";
    }

    /**
     * The network a social link points at: its `network`, else recognised
     * from the URL. ADR-076: the list lives in Support\SocialNetworks, shared
     * with the registry's `network` options.
     */
    protected function socialNetwork(array $link): ?string
    {
        $chosen = (string) ($link['network'] ?? '');
        if (SocialNetworks::has($chosen)) {
            return $chosen;
        }
        $url = $link['url'] ?? '';
        $url = trim(is_array($url) ? $this->linkValue($url['url'] ?? '') : $this->stringify($url));
        return SocialNetworks::detect($url);
    }

    /**
     * ADR-076: `$slot` names the container's data-widget-slot; `$brand`
     * fills each icon with its network colour (white glyph, AA contrast)
     * instead of following the text colour.
     */
    private function socialIconsHtml(array $links, string $id, string $slot = 'social', bool $brand = false, string $extraClass = '', bool $officialVectors = false): string
    {
        $items = '';
        foreach ($links as $link) {
            if (!is_array($link)) {
                continue;
            }
            $url = $this->linkHref($link['url'] ?? '', '');
            if ($url === '') {
                continue;
            }
            $network = $this->socialNetwork($link);
            $label = trim($this->stringify($link['label'] ?? ''));
            $name = $label !== '' ? $label : ($network !== null ? SocialNetworks::label($network) : '');
            if ($network === null && $name === '') {
                continue;
            }
            $officialSvg = $officialVectors && $network !== null ? SocialNetworks::officialSvgDataUri($network) : '';
            $inner = $officialSvg !== ''
                ? '<img class="tb-social-icon-svg" src="' . $this->escapeAttr($officialSvg) . '" alt="" aria-hidden="true">'
                : ($network !== null ? SocialNetworks::svg($network) : $this->escape($name));
            $external = $network === null || SocialNetworks::opensExternally($network) ? ' target="_blank" rel="noopener noreferrer"' : '';
            $items .= "<a href=\"{$url}\" class=\"tb-social-icon" . ($network === null ? ' tb-social-icon--text' : " tb-social-icon--{$network}") . "\"{$external} aria-label=\"" . $this->escapeAttr($name) . "\" title=\"" . $this->escapeAttr($name) . "\">{$inner}</a>";
        }
        $class = 'tb-social-icons tb-social-icons--svg' . ($brand ? ' tb-social-icons--brand' : '') . ($extraClass !== '' ? ' ' . $extraClass : '');
        return $items === '' ? '' : "<div id=\"tb-{$id}\" class=\"{$class}\" data-widget-slot=\"" . $this->escapeAttr($slot) . "\">{$items}</div>";
    }

    /** store => [catalog icon, prefix, store name] */
    private const APP_STORES = [
        'google_play' => ['play', 'دریافت از', 'گوگل‌پلی'],
        'app_store' => ['smartphone', 'دانلود از', 'اپ‌استور'],
        'bazaar' => ['shopping-bag', 'دریافت از', 'کافه‌بازار'],
        'myket' => ['store', 'دریافت از', 'مایکت'],
        'direct' => ['download', 'دانلود', 'مستقیم اپلیکیشن'],
    ];

    /**
     * Phase 1 (ADR-067): the footer's conversion row — newsletter (the
     * Phase 0.7 endpoint), app badges and trust seals. '' when the footer
     * uses none of them, so existing footers render unchanged.
     */
    private function footerExtrasHtml(array $props, string $id): string
    {
        $newsletter = '';
        if ($this->flag($props['newsletter_enabled'] ?? false)) {
            $title = $this->escape($props['newsletter_title'] ?? 'عضویت در خبرنامه');
            $text = $this->escape($props['newsletter_text'] ?? '');
            $newsletter = '<div class="tb-nav-footer-newsletter">'
                . ($title !== '' ? "<p class=\"tb-nav-footer-newsletter-title\">{$title}</p>" : '')
                . ($text !== '' ? "<p class=\"tb-nav-footer-newsletter-text\">{$text}</p>" : '')
                . $this->newsletterFormHtml(
                    ['placeholder' => $props['newsletter_placeholder'] ?? 'ایمیل یا شماره موبایل'],
                    "{$id}-footer",
                    $this->escapeAttr($props['newsletter_placeholder'] ?? 'ایمیل یا شماره موبایل'),
                    $this->escape($props['newsletter_button'] ?? 'عضویت'),
                    '',
                    null,
                )
                . '</div>';
        }

        $apps = '';
        foreach ((array) ($props['app_links'] ?? []) as $app) {
            $store = is_array($app) ? (string) ($app['store'] ?? '') : '';
            $url = is_array($app) ? $this->linkHref($app['url'] ?? '', '') : '';
            if (!isset(self::APP_STORES[$store]) || $url === '' || $url === '#') {
                continue;
            }
            [$icon, $prefix, $name] = self::APP_STORES[$store];
            $apps .= "<a href=\"{$url}\" class=\"tb-app-badge tb-app-badge--{$store}\" target=\"_blank\" rel=\"noopener noreferrer\">"
                . '<span class="tb-app-badge__icon">' . IconLibrary::svg($icon) . '</span>'
                . "<span class=\"tb-app-badge__text\"><small>{$prefix}</small><strong>{$name}</strong></span></a>";
        }
        $apps = $apps !== '' ? "<div class=\"tb-nav-footer-apps\" aria-label=\"دریافت اپلیکیشن\">{$apps}</div>" : '';
        $seals = $this->trustSealsHtml($props);

        if ($newsletter === '' && $apps === '' && $seals === '') {
            return '';
        }
        return "<div class=\"tb-nav-footer-extras\">{$newsletter}"
            . ($apps !== '' || $seals !== '' ? "<div class=\"tb-nav-footer-badges\">{$apps}{$seals}</div>" : '')
            . '</div>';
    }

    /**
     * Phase 1 (ADR-067): Enamad / Samandehi seals. Only the numeric id and
     * the alphanumeric code are taken from the author (or parsed out of a
     * pasted official snippet); the markup and the two official hosts are
     * ours, so a pasted snippet can never inject script. The seals verify
     * the page's Referer, hence referrerpolicy="origin" and no iframe.
     */
    protected function trustSealsHtml(array $props): string
    {
        $html = '';
        $enamad = $this->sealIds($props['enamad_id'] ?? '', $props['enamad_code'] ?? '', 'Code');
        if ($enamad !== null) {
            [$sid, $code] = $enamad;
            $q = "id={$sid}&amp;Code={$code}";
            $html .= "<a class=\"tb-trust-seal\" href=\"https://trustseal.enamad.ir/?{$q}\" target=\"_blank\" rel=\"noopener\" referrerpolicy=\"origin\">"
                . "<img src=\"https://trustseal.enamad.ir/logo.aspx?{$q}\" alt=\"نماد اعتماد الکترونیکی\" referrerpolicy=\"origin\" loading=\"lazy\" width=\"86\" height=\"94\"></a>";
        }
        $samandehi = $this->sealIds($props['samandehi_id'] ?? '', $props['samandehi_code'] ?? '', 'p');
        if ($samandehi !== null) {
            [$sid, $code] = $samandehi;
            $q = "id={$sid}&amp;p={$code}";
            $html .= "<a class=\"tb-trust-seal\" href=\"https://logo.samandehi.ir/Verify.aspx?{$q}\" target=\"_blank\" rel=\"noopener\" referrerpolicy=\"origin\">"
                . "<img src=\"https://logo.samandehi.ir/logo.aspx?{$q}\" alt=\"نشان ملی ثبت رسانه‌های دیجیتال\" referrerpolicy=\"origin\" loading=\"lazy\" width=\"86\" height=\"94\"></a>";
        }
        return $html !== '' ? "<div class=\"tb-trust-seals\" aria-label=\"نمادهای اعتماد\">{$html}</div>" : '';
    }

    /** [id, code] from plain fields or a pasted official snippet; null when either is missing. */
    private function sealIds(mixed $idField, mixed $codeField, string $codeParam): ?array
    {
        $raw = html_entity_decode($this->stringify($idField), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $code = trim($this->stringify($codeField));
        if (preg_match('~[?&]id=(\d{1,20})~i', $raw, $m)) {
            $id = $m[1];
            if (preg_match('~[?&]' . $codeParam . '=([A-Za-z0-9]{1,64})~i', $raw, $c)) {
                $code = $c[1];
            }
        } else {
            $id = trim(\SaaSPlatform\ThemeRuntime\Support\PersianNumber::latinDigits($raw));
        }
        return preg_match('/^\d{1,20}$/', $id) && preg_match('/^[A-Za-z0-9]{1,64}$/', $code) ? [$id, $code] : null;
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
        $btn1Url  = $this->linkHref($props['btn_primary_url'] ?? '#');
        $btn2Text = $this->escape($props['btn_secondary_text'] ?? '');
        $btn2Url  = $this->linkHref($props['btn_secondary_url'] ?? '#');

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
        $btnUrl   = $this->linkHref($props['btn_url'] ?? '#', '#');
        $features = is_array($props['features'] ?? null) ? $props['features'] : [];
        $stats    = is_array($props['stats'] ?? null) ? $props['stats'] : [];

        $badgeHtml = $badge ? "<span class=\"tb-hero-split-badge\">{$badge}</span>" : '';

        $featuresHtml = '';
        foreach ($features as $f) {
            $label = $this->escape(is_array($f) ? ($f['label'] ?? '') : (string) $f);
            if ($label === '') {
                continue;
            }
            $featuresHtml .= "<li class=\"tb-about-feature\"><span aria-hidden=\"true\">" . $this->iconHtml('check') . "</span> {$label}</li>";
        }

        $statsHtml = '';
        foreach ($stats as $s) {
            $val   = $this->escape($this->displayNumber(is_array($s) ? ($s['value'] ?? '') : ''));
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
            $icon   = $this->iconHtml(is_array($card) ? ($card['icon'] ?? '') : '');
            $ctitle = $this->escape(is_array($card) ? ($card['title'] ?? '') : '');
            $desc   = $this->escape(is_array($card) ? ($card['description'] ?? '') : '');
            $url    = $this->linkHref(is_array($card) ? ($card['url'] ?? '') : '', '');
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
            $url    = $this->linkHref(is_array($card) ? ($card['url'] ?? '') : '', '');
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
        $bg       = $this->escapeAttr($this->cssColor($props['background'] ?? null, ''));
        $rawItems = $props['items'] ?? [];
        $items    = is_array($rawItems) ? $rawItems : [];

        $headerHtml = ($title || $subtitle)
            ? "<div class=\"tb-features-section-header\">" . ($title ? "<h2 class=\"tb-features-section-title\">{$title}</h2>" : '') . ($subtitle ? "<p class=\"tb-features-section-sub\">{$subtitle}</p>" : '') . "</div>"
            : '';

        $itemsHtml = '';
        foreach ($items as $item) {
            $icon  = $this->iconHtml(is_array($item) ? ($item['icon'] ?? '') : '');
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
     *
     * Phase 3 (ADR-068): the commercial accordion, and the one FAQ renderer
     * on both hosts (customer-site's override is merged here):
     *  - `exclusive` names every <details> alike, so opening one closes the
     *    rest (native exclusive accordion; older browsers simply allow
     *    several open); `open_first` opens the first answer;
     *  - answers keep their line breaks; an answer with markup goes through
     *    the shared rich-HTML sanitizer;
     *  - `title`/`content` are read as aliases of `question`/`answer`;
     *  - no questions → the editing hint, not an empty box.
     * FAQPage JSON-LD is page-level and host-owned (customer-site
     * CanvasStructuredData reads these same items), so a page with several
     * FAQ blocks still publishes exactly one FAQPage.
     */
    protected function renderFaq(array $props, string $inner, string $id): string
    {
        $title = $this->escape($props['title'] ?? '');
        $titleHtml = $title !== '' ? "<h2 class=\"tb-features-section-title\">{$title}</h2>" : '';
        $rawItems = $props['items'] ?? [];
        $items = is_array($rawItems) ? $rawItems : (json_decode((string) $rawItems, true) ?? []);
        $items = is_array($items) ? $items : [];
        $exclusive = $this->flag($props['exclusive'] ?? false);
        $openFirst = $this->flag($props['open_first'] ?? false);

        $itemsHtml = '';
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            $q = $this->escape($item['question'] ?? $item['title'] ?? '');
            if ($q === '') {
                continue;
            }
            $a = $this->faqAnswerHtml($this->stringify($item['answer'] ?? $item['content'] ?? ''));
            $attrs = ($exclusive ? " name=\"tb-faq-{$id}\"" : '') . ($openFirst && $itemsHtml === '' ? ' open' : '');
            $itemsHtml .= "<details class=\"tb-faq-item\"{$attrs}><summary class=\"tb-faq-question\"><span class=\"tb-faq-question-text\">{$q}</span>"
                . '<span class="tb-faq-chevron" aria-hidden="true">' . $this->iconHtml('chevron-down') . '</span></summary>'
                . "<div class=\"tb-faq-answer\">{$a}</div></details>";
        }

        $body = $itemsHtml !== ''
            ? "<div class=\"tb-faq-list\">{$itemsHtml}</div>"
            : '<p class="tb-text tb-faq-empty">سوالات متداول از پنل مدیریت قابل ویرایش است.</p>';

        return "<div id=\"tb-{$id}\" class=\"tb-section tb-faq\">
            <div class=\"tb-container\">
                {$titleHtml}
                {$body}
            </div>
        </div>";
    }

    /** An FAQ answer: plain text keeps its line breaks; markup is sanitized. */
    private function faqAnswerHtml(string $answer): string
    {
        $answer = trim($answer);
        if ($answer === '') {
            return '';
        }
        return preg_match('/<[a-z][^>]*>/i', $answer) === 1
            ? $this->sanitizeHtml($answer)
            : nl2br($this->escape($answer), false);
    }

    protected function renderSearchBar(array $props, string $inner, string $id): string
    {
        $placeholder = $props['placeholder'] ?? 'جستجو...';
        $fieldId = "tb-{$id}-search";
        // Phase 0.7 (ADR-066): a real GET search (host routes, scope →
        // products/posts) upgraded to the search dialog on live pages.
        $scope = $props['scope'] ?? 'all';
        $scope = in_array($scope, ['all', 'products', 'posts'], true) ? $scope : 'all';
        [$attrs, $hidden, $assets, $param] = $this->searchForm($scope);
        return "<form id=\"tb-{$id}\" class=\"tb-search-bar-wrap\" role=\"search\"{$attrs}>"
            . $this->visuallyHiddenLabel($fieldId, $placeholder)
            . '<span class="tb-search-bar-icon" aria-hidden="true">' . $this->iconHtml('search') . '</span>'
            . "<input id=\"{$fieldId}\" type=\"search\" name=\"" . $this->escapeAttr($param) . "\" class=\"tb-search-bar-input\" placeholder=\"" . $this->escapeAttr($placeholder) . "\" autocomplete=\"off\" enterkeyhint=\"search\">"
            . $hidden
            . '<button type="submit" class="tb-search-bar-btn">جستجو</button>'
            . "</form>" . $assets;
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
        $shown  = $this->escape($this->displayNumber($props['value'] ?? ''));
        $suffix = $this->escape($this->displayNumber($props['suffix'] ?? ''));
        $label  = $this->escape($props['label'] ?? '');

        return "<div id=\"tb-{$id}\" class=\"tb-stat-item\">
            <span class=\"tb-stat-value\" data-count=\"{$value}\">{$shown}<span class=\"tb-stat-suffix\">{$suffix}</span></span>
            <span class=\"tb-stat-label\">{$label}</span>
        </div>";
    }

    protected function renderLoginRegister(array $props, string $inner, string $id): string
    {
        $loginText    = $this->escape($props['login_text'] ?? 'ورود');
        $registerText = $this->escape($props['register_text'] ?? 'ثبت‌نام');
        $loginUrl     = $this->escapeUrl(is_string($props['login_url'] ?? null) ? $props['login_url'] : '/login', '/login');
        $registerUrl  = $this->escapeUrl(is_string($props['register_url'] ?? null) ? $props['register_url'] : '/register', '/register');

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
        // Phase 2 (ADR-067): the Builder preview shows the real card and
        // layout with clearly-labelled sample posts, so grid/list/slider can
        // be judged before any post exists; the host renders real posts
        // through the same blogCardHtml()/blogCardsLayout().
        $title = $this->escape($props['title'] ?? 'وبلاگ');
        $samples = [
            ['title' => 'راهنمای انتخاب بهترین محصول برای خانه', 'category' => 'راهنمای خرید', 'author' => 'تیم تحریریه', 'date' => '۵ آبان ۱۴۰۵', 'reading_minutes' => 6],
            ['title' => 'ده نکته برای خریدی مطمئن‌تر از فروشگاه‌های اینترنتی', 'category' => 'آموزش', 'author' => 'سارا محمدی', 'date' => '۲۸ مهر ۱۴۰۵', 'reading_minutes' => 4],
            ['title' => 'تازه‌های فصل: محصولاتی که نباید از دست بدهید', 'category' => 'اخبار', 'author' => 'علی رضایی', 'date' => '۲۰ مهر ۱۴۰۵', 'reading_minutes' => 3],
        ];
        $cards = array_map(fn($s) => $this->blogCardHtml($s + ['url' => '#', 'excerpt' => 'متن نمونه؛ پس از انتشار مطالب، نوشته‌های واقعی سایت در این بخش نمایش داده می‌شوند.']), $samples);
        return $this->blogCardsLayout($cards, $props, $id, $title);
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
     * dependency-free ticking countdown.
     *
     * Phase 9.3: the ticker itself was moved out of this string into the
     * packaged adapter (resources/js/blocks/countdown_adapter.js) and is
     * carried in by countdownAdapterScript(). The markup below — including
     * the <script> element's position and the surrounding whitespace — is
     * deliberately unchanged, so the render snapshots pinned for both the
     * with-date and without-date shapes still hold.
     *
     * Phase 3 (ADR-068): a campaign countdown.
     *  - `end_date` may be Jalali («۱۴۰۵/۰۸/۱۵», any digit script, - / or .
     *    separators). It ends at `end_time` (HH:MM) or, without one, at the
     *    end of that day, Tehran time (+03:30). The target ships as an ISO
     *    8601 instant with that offset, so every visitor's clock agrees.
     *  - A Gregorian value without `end_time` is emitted exactly as stored,
     *    as before this phase, so existing countdowns keep their instant.
     *  - Digits are Persian, the target is shown as «پایان: ۱۵ آبان ۱۴۰۵»,
     *    `expired_text` replaces the expiry line, and `style` is a card or a
     *    promo banner. The markup moved from inline styles to the shared
     *    sheet, so the structural snapshots were re-pinned on purpose.
     */
    protected function renderCountdown(array $props, string $inner, string $id): string
    {
        $label = $this->escape($props['label'] ?? '');
        $end = trim($this->stringify($props['end_date'] ?? ''));
        $labelHtml = $label !== '' ? "<span class=\"tb-countdown__label\">{$label}</span>" : '';
        $style = $props['style'] ?? 'card';
        $class = 'tb-countdown' . ($style === 'banner' ? ' tb-countdown--banner' : '');

        $target = $end !== '' ? $this->countdownTarget($end, trim($this->stringify($props['end_time'] ?? ''))) : null;
        if ($target === null) {
            $hint = $end === ''
                ? 'تاریخ پایان را در تنظیمات المان وارد کنید تا شمارش معکوس فعال شود.'
                : 'تاریخ پایان معتبر نیست؛ آن را به شکل ۱۴۰۵/۰۸/۱۵ وارد کنید.';
            return "<div id=\"tb-{$id}\" class=\"{$class}\">{$labelHtml}<span class=\"tb-countdown__hint\">{$hint}</span></div>";
        }

        $units = '';
        foreach (['d' => 'روز', 'h' => 'ساعت', 'm' => 'دقیقه', 's' => 'ثانیه'] as $key => $word) {
            $units .= "<div class=\"tb-countdown__unit\"><span class=\"tb-countdown__num tb-countdown__{$key}\">--</span><small class=\"tb-countdown__word\">{$word}</small></div>";
        }
        $endHtml = ($target['display'] !== null && $this->flag($props['show_end_date'] ?? true))
            ? '<p class="tb-countdown__end">پایان: <time datetime="' . $this->escapeAttr($target['iso']) . '">' . $target['display'] . '</time></p>'
            : '';
        $expired = trim($this->stringify($props['expired_text'] ?? ''));
        $expiredHtml = $this->escape($expired !== '' ? $expired : 'این پیشنهاد به پایان رسیده است.');

        $safeEnd = $this->escapeAttr($target['iso']);
        return "<div id=\"tb-{$id}\" class=\"{$class}\" data-end-date=\"{$safeEnd}\">
            {$labelHtml}
            <div class=\"tb-countdown__timer\" role=\"timer\" aria-label=\"زمان باقی‌مانده\">{$units}</div>
            {$endHtml}
            <p class=\"tb-countdown__expired\" hidden>{$expiredHtml}</p>
            {$this->countdownAdapterScript()}
        </div>";
    }

    /**
     * The countdown's target — see renderCountdown().
     *
     * @return array{iso:string, display:?string}|null null when the date is
     *   recognisably a date but not a real one (e.g. ۱۴۰۵/۱۳/۰۱).
     */
    private function countdownTarget(string $end, string $time): ?array
    {
        $clockOf = static fn(string $h, string $m): ?array => ((int) $h < 24 && (int) $m < 60) ? [(int) $h, (int) $m, 0] : null;
        $timeClock = preg_match('/^(\d{1,2}):(\d{2})$/', PersianNumber::latinDigits($time), $t) === 1 ? $clockOf($t[1], $t[2]) : null;
        if (preg_match('#^(\d{4})[-/.](\d{1,2})[-/.](\d{1,2})(?:[ T](\d{1,2}):(\d{2}))?$#', PersianNumber::latinDigits($end), $m) !== 1) {
            return ['iso' => $end, 'display' => null]; // full ISO and other legacy strings: as stored
        }
        [$y, $mo, $d] = [(int) $m[1], (int) $m[2], (int) $m[3]];

        if ($y >= 1700) {
            if (!checkdate($mo, $d, $y)) {
                return null;
            }
            // Gregorian: only an explicit end_time opts into Tehran time.
            if ($timeClock === null) {
                return ['iso' => $end, 'display' => null];
            }
            $clock = $timeClock;
            [$gy, $gm, $gd] = [$y, $mo, $d];
            [$jy, $jm, $jd] = JalaliDate::fromGregorian($y, $mo, $d);
        } else {
            if (!JalaliDate::isValid($y, $mo, $d)) {
                return null;
            }
            $clock = $timeClock ?? (isset($m[4]) ? $clockOf($m[4], $m[5]) : null);
            [$jy, $jm, $jd] = [$y, $mo, $d];
            [$gy, $gm, $gd] = JalaliDate::toGregorian($y, $mo, $d);
        }

        $display = JalaliDate::format($jy, $jm, $jd);
        if ($clock !== null) {
            $display .= '، ساعت ' . PersianNumber::digits(sprintf('%02d:%02d', $clock[0], $clock[1]));
        }
        $clock ??= [23, 59, 59];
        $iso = sprintf('%04d-%02d-%02dT%02d:%02d:%02d', $gy, $gm, $gd, ...$clock) . JalaliDate::TEHRAN_OFFSET;

        return ['iso' => $iso, 'display' => $display];
    }

    // ── END PHASE 2E CANONICAL BLOCKS ────────────────────────────────────────

    protected function renderFallback(string $type, array $props, string $inner, string $id): string
    {
        $content = $inner ?: '';
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

    /**
     * Canvas overhaul Unit 2c: read an `image` control value. The host
     * resolves a Media Library pick ({media_id}) to a URL before rendering;
     * if one still arrives unresolved (deleted media, a render path without
     * a resolver, the platform preview), it counts as "no image" instead of
     * raising "Array to string conversion" and replacing the whole block
     * with an error placeholder (bento_grid, nav_header mega banner,
     * production log 2026-10-30/31).
     */
    protected function mediaSrc(mixed $value): string
    {
        return trim($this->stringify($value));
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
     * Phase 0.2b (ADR-061): the one reader for a `link` field. A link is
     * authored either as a plain URL string or as a structured {url, …}
     * object (Canvas keeps whichever shape the value already has); any
     * other shape is no link at all.
     */
    protected function linkValue(mixed $value): string
    {
        if (is_array($value)) {
            $value = $value['url'] ?? '';
        }
        return is_string($value) ? $value : '';
    }

    /**
     * Sentinel DEAD_LINK (Constitution §16, honest interactions): a header
     * CTA without a destination is left out of the site. The editor keeps
     * it, marked by deadLinkMark(), so the author sees what is missing.
     */
    protected function showsLink(string $href): bool
    {
        return $href !== '#' || $this->isEditorRendering();
    }

    protected function deadLinkMark(string $href): string
    {
        return $href === '#' ? ' data-tb-dead-link title="لینک مقصد تنظیم نشده است"' : '';
    }

    /** A `link` field as a validated, attribute-safe href (see escapeUrl()). */
    protected function linkHref(mixed $value, string $fallback = '#'): string
    {
        return $this->escapeUrl($this->linkValue($value), $fallback);
    }

    /**
     * A `boolean` field value. Older authoring stored these as free text,
     * so "false", "0" and "" must read as false — a bare (bool) cast reads
     * every non-empty string, "false" included, as true.
     */
    protected function flag(mixed $value): bool
    {
        return is_bool($value) ? $value : filter_var($value, FILTER_VALIDATE_BOOL);
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
