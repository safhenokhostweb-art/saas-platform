<?php
namespace App\Services\ThemeBuilder\QualityGate\Checks;

use App\Models\Theme;
use App\Services\ThemeBuilder\BlockRegistry;
use App\Services\ThemeBuilder\BlockRenderer;
use App\Services\ThemeBuilder\QualityGate\ThemeQualityCheck;
use App\Services\ThemeBuilder\QualityGate\ThemeQualityCheckResult;
use Illuminate\Support\Str;
use Throwable;

/**
 * Uses the real rendering path (BlockRenderer — the same class that renders
 * theme output for the front end and the admin preview), not a JSON-shape
 * check, per Phase 2a's explicit requirement to validate actual render
 * output.
 *
 * Severity policy (deliberately not uniform — see each branch below):
 *   - A renderer exception is BLOCKING: it proves the theme cannot render at
 *     all in production.
 *   - A block type with no real renderer is WARNING, not blocking — see
 *     ADR-026's fallback-policy decision: this stays visible and reported
 *     rather than hidden, but does not retroactively fail every theme with
 *     a legacy/renamed type.
 *   - Empty output severity depends on BlockRegistry::emptyOutputPolicy()
 *     (Phase 2f, ADR-027) — see the branch below.
 *
 * Phase 2e (ADR-026) correction: this check used to test only "is the type
 * present in BlockRegistry::all()", which is a WEAKER guarantee than
 * "renderBlock() will actually produce real markup for it" — a type can be
 * registered (authorable in the Builder) with no renderer method at all,
 * which the old check silently missed (proven by
 * CanonicalBlockRegistryContractTest: nav_menu/social_icons/breadcrumb/faq/
 * search_bar/email_capture/counter/login_register/blog_posts/testimonials
 * were all registered yet fell through to renderFallback()). This check now
 * mirrors BlockRenderer::renderBlock()'s own dispatch logic exactly — a
 * `module:`-prefixed type is the one sanctioned extension escape hatch
 * (never "unsupported"); everything else must have a real render method.
 */
class RenderIntegrityCheck implements ThemeQualityCheck
{
    private const CATEGORY = 'render_integrity';

    public function __construct(private readonly BlockRenderer $renderer)
    {
    }

    public function run(Theme $theme, string $version, ?array $dependencies): array
    {
        $results = [];

        $this->checkBlockList($theme->header_blocks ?? [], 'header', $results);
        $this->checkBlockList($theme->footer_blocks ?? [], 'footer', $results);

        foreach (($theme->pages ?? []) as $pageKey => $page) {
            $this->checkBlockList($page['blocks'] ?? [], "page:{$pageKey}", $results);
        }

        if (empty($results)) {
            $results[] = ThemeQualityCheckResult::pass(self::CATEGORY, 'render.ok', 'All blocks rendered without error.');
        }

        return $results;
    }

    /**
     * True mirror of renderBlock()'s own dispatch decision — never a
     * separate approximation of it, so this check can never drift out of
     * sync with what actually happens at render time.
     */
    private function isRenderable(string $type): bool
    {
        if (str_starts_with($type, 'module:')) {
            return true;
        }
        $method = 'render' . Str::studly(str_replace(':', '_', $type));
        return method_exists($this->renderer, $method);
    }

    /**
     * Phase 2f (ADR-027) — the durable accidental-vs-expected-vs-optional
     * distinction, driven entirely by BlockRegistry::emptyOutputPolicy();
     * no block-type name is ever special-cased here.
     */
    private function classifyEmptyOutput(string $type, string $location, int|string $index): ThemeQualityCheckResult
    {
        $policy = BlockRegistry::emptyOutputPolicy($type);

        if ($policy === BlockRegistry::EMPTY_POLICY_OPTIONAL) {
            return ThemeQualityCheckResult::pass(
                self::CATEGORY,
                'render.intentionally_empty',
                "Block \"{$type}\" in {$location} rendered no output — this type is a deliberately optional accent left unused here, not a defect.",
                ['location' => $location, 'index' => $index, 'type' => $type]
            );
        }

        if ($policy === BlockRegistry::EMPTY_POLICY_PLACEHOLDER) {
            return ThemeQualityCheckResult::fail(
                self::CATEGORY,
                'render.empty_output',
                "Block \"{$type}\" in {$location} rendered to empty output — this type's contract guarantees a non-fabricated placeholder when real content is unavailable, so true emptiness means the renderer's own contract broke, not that content is legitimately absent.",
                ['location' => $location, 'index' => $index, 'type' => $type]
            );
        }

        // EMPTY_POLICY_FLAGGED (the default): this type's content is fully
        // author-supplied — a canonical commercial block with nothing to
        // show is missing required content, not a passable state.
        return ThemeQualityCheckResult::fail(
            self::CATEGORY,
            'render.empty_output',
            "Block \"{$type}\" in {$location} rendered to empty output — required authored content appears to be missing.",
            ['location' => $location, 'index' => $index, 'type' => $type]
        );
    }

    private function checkBlockList(array $blocks, string $location, array &$results): void
    {
        foreach ($blocks as $index => $block) {
            $type = $block['type'] ?? null;

            if (empty($type)) {
                $results[] = ThemeQualityCheckResult::fail(
                    self::CATEGORY,
                    'render.missing_block_type',
                    "Block #{$index} in {$location} has no \"type\".",
                    ['location' => $location, 'index' => $index]
                );
                continue;
            }

            if (!$this->isRenderable($type)) {
                $results[] = ThemeQualityCheckResult::warn(
                    self::CATEGORY,
                    'render.unsupported_block_type',
                    "Block type \"{$type}\" in {$location} has no real renderer — it will render via the generic fallback, not real markup for that type.",
                    ['location' => $location, 'index' => $index, 'type' => $type]
                );
            }

            try {
                $html = $this->renderer->renderBlock($block);
            } catch (Throwable $e) {
                $results[] = ThemeQualityCheckResult::fail(
                    self::CATEGORY,
                    'render.exception',
                    "Rendering block \"{$type}\" in {$location} threw: {$e->getMessage()}",
                    ['location' => $location, 'index' => $index, 'type' => $type, 'exception' => $e::class]
                );
                continue;
            }

            if (trim($html) === '') {
                $results[] = $this->classifyEmptyOutput($type, $location, $index);
            }

            // Phase 2e (ADR-026): recurse into nested block trees — a
            // container's own renderBlock() call already renders its
            // children internally (via renderBlocks($children)), silently
            // absorbing any nested unsupported/broken type into the
            // container's own successful-looking output above. Without
            // this, a block two levels deep inside a section/grid/columns
            // (or a tabs/accordion panel) could be unrenderable and this
            // check would never see it — exactly the blind spot
            // CanonicalBlockRegistryContractTest proved existed.
            $props = $block['props'] ?? $block['data'] ?? [];
            $children = $block['children'] ?? $block['blocks'] ?? ($props['blocks'] ?? []);
            if (is_array($children)) {
                $this->checkBlockList($children, "{$location} > {$type}", $results);
            }
            if (is_array($props['tabs'] ?? null)) {
                foreach ($props['tabs'] as $tab) {
                    $this->checkBlockList(is_array($tab) ? ($tab['blocks'] ?? []) : [], "{$location} > {$type}:tab", $results);
                }
            }
            if ($type === 'accordion' && is_array($props['items'] ?? null)) {
                foreach ($props['items'] as $item) {
                    if (is_array($item) && isset($item['blocks'])) {
                        $this->checkBlockList($item['blocks'], "{$location} > accordion", $results);
                    }
                }
            }
            if (isset($props['slides']) && is_array($props['slides'])) {
                $this->checkBlockList($props['slides'], "{$location} > {$type}:slide", $results);
            }
        }
    }
}
