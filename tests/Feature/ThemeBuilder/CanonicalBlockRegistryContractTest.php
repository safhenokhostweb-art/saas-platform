<?php

namespace Tests\Feature\ThemeBuilder;

use App\Models\Theme;
use App\Services\ThemeBuilder\BlockRegistry;
use App\Services\ThemeBuilder\BlockRenderer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 2e — Builder ↔ Registry ↔ Renderer Contract (ADR-026).
 *
 * The reusable invariant test the task explicitly asked for: a canonical
 * Theme must never contain a block type unknown to BlockRegistry. Walks
 * every header/footer/page block tree of the real, rebuilt 28-theme
 * catalog recursively (blocks nest via children/blocks/props.blocks/tabs/
 * accordion/slider — the same shapes BlockRenderer::renderBlock() itself
 * recurses through) and asserts every type is either REGISTERED
 * (BlockRegistry::all()) or explicitly extension-owned (the sanctioned
 * `module:` prefix escape hatch — see BlockRegistry's class docblock).
 *
 * If this test ever fails again in the future, it means a new Theme/block
 * was authored with a type the registry does not know about — exactly the
 * silent-regression this test exists to catch before it becomes another
 * render.unsupported_block_type backlog.
 */
class CanonicalBlockRegistryContractTest extends TestCase
{
    use RefreshDatabase;

    /** @return string[] every block type used anywhere in the theme, deduplicated */
    private function collectBlockTypes(Theme $theme): array
    {
        $types = [];
        $walk = function (array $blocks) use (&$walk, &$types): void {
            foreach ($blocks as $block) {
                if (!is_array($block) || !isset($block['type'])) {
                    continue;
                }
                $types[$block['type']] = true;
                $props = $block['props'] ?? $block['data'] ?? [];
                $children = $block['children'] ?? $block['blocks'] ?? ($props['blocks'] ?? []);
                if (is_array($children)) {
                    $walk($children);
                }
                if (is_array($props['tabs'] ?? null)) {
                    foreach ($props['tabs'] as $tab) {
                        $walk(is_array($tab) ? ($tab['blocks'] ?? []) : []);
                    }
                }
                if ($block['type'] === 'accordion' && is_array($props['items'] ?? null)) {
                    foreach ($props['items'] as $item) {
                        if (is_array($item) && isset($item['blocks'])) {
                            $walk($item['blocks']);
                        }
                    }
                }
                if (isset($props['slides']) && is_array($props['slides'])) {
                    $walk($props['slides']);
                }
            }
        };

        $walk($theme->header_blocks ?? []);
        $walk($theme->footer_blocks ?? []);
        foreach (($theme->pages ?? []) as $page) {
            $walk($page['blocks'] ?? []);
        }

        return array_keys($types);
    }

    private function isKnownType(string $type): bool
    {
        return array_key_exists($type, BlockRegistry::all()) || str_starts_with($type, 'module:');
    }

    // ── 1/2. exact inventory known, every type registered ─────────────────

    public function test_every_canonical_theme_block_type_is_registered_or_explicitly_extension_owned(): void
    {
        $this->artisan('theme-catalog:rebuild')->assertExitCode(0);

        $unknown = [];
        foreach (Theme::whereNull('site_id')->get() as $theme) {
            foreach ($this->collectBlockTypes($theme) as $type) {
                if (!$this->isKnownType($type)) {
                    $unknown[] = "{$theme->slug}: {$type}";
                }
            }
        }

        $this->assertSame([], $unknown, "Unregistered block type(s) found in the canonical catalog:\n" . implode("\n", $unknown));
    }

    // ── 4. no canonical core block uses fallback rendering ────────────────

    public function test_no_canonical_theme_block_falls_through_to_the_generic_fallback_renderer(): void
    {
        $this->artisan('theme-catalog:rebuild')->assertExitCode(0);

        $renderer = new BlockRenderer();
        $offenders = [];

        foreach (Theme::whereNull('site_id')->get() as $theme) {
            foreach ($this->collectBlockTypes($theme) as $type) {
                if (str_starts_with($type, 'module:')) {
                    continue; // module blocks intentionally delegate/placeholder — not "unsupported"
                }
                $method = 'render' . \Illuminate\Support\Str::studly($type);
                if (!method_exists($renderer, $method)) {
                    $offenders[] = "{$theme->slug}: {$type}";
                }
            }
        }

        $this->assertSame([], $offenders, "Block type(s) with no real renderer (would fall through to renderFallback()):\n" . implode("\n", $offenders));
    }

    // ── 5. obsolete aliases cannot silently return ─────────────────────────

    public function test_the_retired_industry_specific_block_types_are_not_registered(): void
    {
        // hvac_hero and site_footer were retired in favor of the reusable
        // hero_trust / nav_footer types (ADR-026) — registering them again
        // (even as harmless aliases) would silently reopen a naming
        // inconsistency this phase deliberately closed.
        $this->assertNull(BlockRegistry::get('hvac_hero'));
        $this->assertNull(BlockRegistry::get('site_footer'));
    }

    public function test_the_canonical_acool_theme_uses_the_retired_types_nowhere(): void
    {
        $this->artisan('theme-catalog:rebuild')->assertExitCode(0);

        $theme = Theme::where('slug', 'acool-hvac-service')->firstOrFail();
        $types = $this->collectBlockTypes($theme);

        $this->assertNotContains('hvac_hero', $types);
        $this->assertNotContains('site_footer', $types);
        $this->assertContains('hero_trust', $types);
        $this->assertContains('nav_footer', $types);
    }

    // ── 13. canonical reconstruction remains deterministic/idempotent ─────

    public function test_rebuilt_catalog_block_type_inventory_is_deterministic(): void
    {
        $this->artisan('theme-catalog:rebuild')->assertExitCode(0);
        $first = Theme::where('slug', 'acool-hvac-service')->firstOrFail();
        $firstTypes = $this->collectBlockTypes($first);
        sort($firstTypes);

        Theme::query()->delete();
        $this->artisan('theme-catalog:rebuild')->assertExitCode(0);
        $second = Theme::where('slug', 'acool-hvac-service')->firstOrFail();
        $secondTypes = $this->collectBlockTypes($second);
        sort($secondTypes);

        $this->assertSame($firstTypes, $secondTypes);
    }
}
