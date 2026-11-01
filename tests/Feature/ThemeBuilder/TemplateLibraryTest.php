<?php

namespace Tests\Feature\ThemeBuilder;

use App\Models\Theme;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use SaaSPlatform\ThemeRuntime\BlockRegistry;
use SaaSPlatform\ThemeRuntime\TemplateLibrary;
use Tests\TestCase;

/**
 * Canvas overhaul Unit 5 (ADR-075): the ready-made template library.
 *
 * "Inserting any template must cleanly inject its validated schema and
 * immediately render perfectly in the editor canvas, resolving all internal
 * media assets and entity links": every template is checked against the
 * registry schema, then driven through the real editor path — insert, save
 * into its own zone, render the canvas iframe — with no placeholder and
 * every library image and route link present.
 */
class TemplateLibraryTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $user = User::create([
            'mobile' => '0912' . random_int(1000000, 9999999),
            'password' => Hash::make('password'), 'is_admin' => true, 'is_active' => true,
        ]);
        if ($role = \App\Models\Role::where('slug', \App\Models\Role::SUPER_ADMIN)->first()) {
            $user->roles()->attach($role->id);
        }
        return $user;
    }

    private function theme(): Theme
    {
        return Theme::create([
            'name' => 'Template library', 'slug' => 'template-library-' . uniqid(),
            'type' => Theme::TYPE_FULL, 'source' => Theme::SOURCE_SEEDER,
            'status' => Theme::STATUS_DRAFT, 'category' => Theme::CATEGORY_SAAS,
            'header_blocks' => [], 'footer_blocks' => [],
            'pages' => ['home' => ['title' => 'Home', 'slug' => '', 'blocks' => []]],
            'settings' => [],
        ]);
    }

    /** @return array<string, int> "type" and "category:x" => template count */
    private function census(): array
    {
        $count = [];
        foreach (TemplateLibrary::list() as $card) {
            $count[$card['type']] = ($count[$card['type']] ?? 0) + 1;
            $count['category:' . $card['category']] = ($count['category:' . $card['category']] ?? 0) + 1;
        }
        return $count;
    }

    /** @return list<array> every block of a template, children included */
    private function flatten(array $blocks): array
    {
        $all = [];
        foreach ($blocks as $block) {
            $all[] = $block;
            $all = array_merge($all, $this->flatten($block['children'] ?? []));
        }
        return $all;
    }

    public function test_the_library_covers_the_commercial_set_the_owner_asked_for(): void
    {
        $count = $this->census();

        $this->assertGreaterThanOrEqual(3, $count['header'] ?? 0, 'headers');
        $this->assertGreaterThanOrEqual(3, $count['footer'] ?? 0, 'footers');
        $this->assertGreaterThanOrEqual(3, $count['category:hero'] ?? 0, 'heroes');
        $this->assertGreaterThanOrEqual(3, $count['category:features'] ?? 0, 'bento grids');
        $this->assertGreaterThanOrEqual(2, $count['category:product'] ?? 0, 'product showcases');
        $this->assertGreaterThanOrEqual(2, $count['category:blog'] ?? 0, 'blog sections');
        $this->assertGreaterThanOrEqual(3, $count['category:testimonials'] ?? 0, 'trust and stats');
        $this->assertGreaterThanOrEqual(1, $count['category:pricing'] ?? 0, 'pricing');
        $this->assertGreaterThanOrEqual(1, $count['category:faq'] ?? 0, 'FAQ');
        $this->assertGreaterThanOrEqual(2, $count['page'] ?? 0, 'full pages');

        $types = [];
        foreach (TemplateLibrary::list() as $card) {
            $types = array_merge($types, array_column($this->flatten(TemplateLibrary::find($card['id'])['blocks']), 'type'));
        }
        foreach (['bento_grid', 'testimonials_slider', 'pricing_cards', 'stats_row', 'faq', 'product_slider', 'blog_posts', 'nav_header', 'nav_footer'] as $required) {
            $this->assertContains($required, $types, "the library uses {$required}");
        }
    }

    public function test_every_template_conforms_to_the_registry_schema(): void
    {
        $registry = BlockRegistry::all();
        $errors = [];
        foreach (TemplateLibrary::list() as $card) {
            foreach ($this->flatten(TemplateLibrary::find($card['id'])['blocks']) as $block) {
                $where = $card['id'] . ' ' . $block['type'];
                if (!isset($registry[$block['type']])) {
                    $errors[] = "{$where}: not a registered block type";
                    continue;
                }
                $this->checkProps($registry[$block['type']]['schema'] ?? [], $block['props'], $where, $errors);
            }
        }

        $this->assertSame([], $errors);
    }

    /** Every prop is declared; selects use a declared option; media and links are portable. */
    private function checkProps(array $schema, array $props, string $where, array &$errors): void
    {
        foreach ($props as $key => $value) {
            $def = $schema[$key] ?? null;
            if (!is_array($def)) {
                $errors[] = "{$where}.{$key}: not in the schema";
                continue;
            }
            $type = $def['type'] ?? null;
            if ($type === 'select' && isset($def['options']) && $value !== '') {
                $options = array_is_list($def['options']) ? $def['options'] : array_keys($def['options']);
                if (!in_array((string) $value, array_map('strval', $options), true)) {
                    $errors[] = "{$where}.{$key}: '{$value}' is not an option";
                }
            }
            if ($type === 'image' && $value !== '' && !preg_match('~^/theme-runtime/library/[a-z0-9-]+\.webp\?v=[0-9a-f]{12}$~', (string) $value)) {
                $errors[] = "{$where}.{$key}: image is not a library media URL";
            }
            if ($type === 'link' && !$this->portableLink($value)) {
                $errors[] = "{$where}.{$key}: link is neither a library route nor an absolute external URL";
            }
            if ($type === 'repeater') {
                $items = is_string($value) ? json_decode($value, true) : $value;
                if (($def['storage'] ?? 'array') === 'json' && !is_string($value)) {
                    $errors[] = "{$where}.{$key}: declared json storage but stored as an array";
                }
                foreach (is_array($items) ? $items : [] as $i => $item) {
                    $this->checkProps($def['fields'] ?? [], is_array($item) ? $item : [], "{$where}.{$key}[{$i}]", $errors);
                }
            }
        }
    }

    private function portableLink(mixed $value): bool
    {
        if (is_array($value)) {
            return ($value['type'] ?? null) === 'entity' && ($value['entity'] ?? null) === 'route'
                && isset(TemplateLibrary::ROUTES[$value['id'] ?? ''])
                && ($value['url'] ?? null) === TemplateLibrary::ROUTES[$value['id']][1];
        }
        return is_string($value) && preg_match('~^(https://|mailto:)~', $value) === 1;
    }

    public function test_media_is_served_by_the_package_immutably_and_only_from_the_whitelist(): void
    {
        $files = TemplateLibrary::mediaFiles();
        $this->assertGreaterThanOrEqual(9, count($files));

        $used = [];
        foreach (TemplateLibrary::list() as $card) {
            $used[] = basename(parse_url((string) $card['thumbnail_url'], PHP_URL_PATH));
            $tpl = TemplateLibrary::find($card['id'])['blocks'];
            array_walk_recursive($tpl, function ($v) use (&$used) {
                if (is_string($v) && str_starts_with($v, '/theme-runtime/library/')) {
                    $used[] = basename(parse_url($v, PHP_URL_PATH));
                }
            });
        }
        $this->assertSame([], array_values(array_diff(array_keys($files), $used)), 'every shipped file is used');

        foreach ($files as $file => $path) {
            $response = $this->get(TemplateLibrary::mediaUrl($file));
            $response->assertOk();
            $this->assertSame('image/webp', $response->headers->get('Content-Type'));
            $this->assertStringContainsString('immutable', (string) $response->headers->get('Cache-Control'));
            $this->assertSame(realpath($path), realpath($response->baseResponse->getFile()->getPathname()));
        }

        $this->get('/theme-runtime/library/missing.webp')->assertNotFound();
        $this->get('/theme-runtime/library/' . rawurlencode('../../src/TemplateLibrary.php'))->assertNotFound();
        $this->get('/theme-runtime/library/derive-media.php')->assertNotFound();
    }

    public function test_the_modal_lists_the_library_first_and_filters_it(): void
    {
        $theme = $this->theme();
        $this->actingAs($this->admin());

        $all = $this->getJson(route('admin.theme-builder.canvas.templates', $theme))->assertOk()->json('templates');
        $this->assertSame(TemplateLibrary::list()[0]['id'], $all[0]['id']);
        $this->assertSame('library', $all[0]['source']);

        $headers = $this->getJson(route('admin.theme-builder.canvas.templates', $theme) . '?type=header')->json('templates');
        $this->assertNotEmpty($headers);
        $this->assertSame(['header'], array_values(array_unique(array_column($headers, 'zone'))));

        $search = $this->getJson(route('admin.theme-builder.canvas.templates', $theme) . '?search=' . urlencode('مگامنو'))->json('templates');
        $this->assertContains('lib:header-mega-store', array_column($search, 'id'));
    }

    public function test_every_template_inserts_saves_into_its_zone_and_renders_in_the_canvas(): void
    {
        $theme = $this->theme();
        $this->actingAs($this->admin());
        $failures = [];

        foreach (TemplateLibrary::list() as $card) {
            $insert = $this->postJson(route('admin.theme-builder.canvas.insert-template', $theme), ['template_id' => $card['id']]);
            if ($insert->status() !== 200 || $insert->json('success') !== true) {
                $failures[] = "{$card['id']}: insert {$insert->status()} " . $insert->json('message');
                continue;
            }
            $zone = $insert->json('zone');
            $blocks = $insert->json('blocks');
            if ($zone !== $card['zone']) {
                $failures[] = "{$card['id']}: zone {$zone}";
            }
            foreach ($this->flatten($blocks) as $block) {
                if (!str_starts_with($block['id'], 'blk_')) {
                    $failures[] = "{$card['id']}: block id {$block['id']} was not regenerated";
                }
            }

            $save = $this->putJson(route('admin.theme-builder.canvas.save', $theme), array_filter([
                'target' => $zone, 'page_key' => $zone === 'page' ? 'home' : null, 'blocks' => $blocks,
            ], fn ($v) => $v !== null));
            if ($save->status() !== 200) {
                $failures[] = "{$card['id']}: save {$save->status()} " . json_encode($save->json(), JSON_UNESCAPED_UNICODE);
                continue;
            }

            $html = $this->get(route('admin.theme-builder.canvas.page-preview', [$theme, 'home']) . '?target=' . $zone)->assertOk()->getContent();
            // The class attribute, not the inlined stylesheet's own rules.
            if (preg_match('/class="[^"]*\btb-placeholder\b/', $html)) {
                $failures[] = "{$card['id']}: canvas renders a placeholder";
            }
            array_walk_recursive($blocks, function ($v) use (&$failures, $html, $card) {
                if (is_string($v) && str_starts_with($v, '/theme-runtime/library/') && !str_contains($html, parse_url($v, PHP_URL_PATH))) {
                    $failures[] = "{$card['id']}: media {$v} missing from the canvas";
                }
            });
            foreach ($this->flatten($blocks) as $block) {
                array_walk_recursive($block['props'], function ($v, $k) use (&$failures, $html, $card) {
                    if ($k === 'url' && is_string($v) && in_array($v, array_column(TemplateLibrary::ROUTES, 1), true) && !str_contains($html, 'href="' . $v . '"')) {
                        $failures[] = "{$card['id']}: route link {$v} missing from the canvas";
                    }
                });
            }
        }

        $this->assertSame([], array_values(array_unique($failures)));
    }

    public function test_bento_colours_may_follow_a_theme_token_with_a_hex_fallback(): void
    {
        $html = app(\SaaSPlatform\ThemeRuntime\BlockRenderer::class)->renderBlock(['id' => 'b', 'type' => 'bento_grid', 'props' => ['items' => [
            ['kind' => 'metric', 'title' => 'x', 'metric_value' => '۹۸٪', 'bg_color' => 'var(--tb-primary, #4f46e5)', 'glow_enabled' => true, 'glow_color' => 'var(--tb-primary, #6366f1)'],
            ['kind' => 'content', 'title' => 'y', 'bg_color' => 'var(--x, #fff);color:red'],
        ]]]);

        $this->assertStringContainsString('background: var(--tb-primary, #4f46e5)', $html);
        $this->assertStringContainsString('--tb-glow-color:var(--tb-primary, #6366f1)', $html);
        $this->assertStringNotContainsString('color:red', $html, 'anything beyond one token is still rejected');
    }

    public function test_an_unknown_library_id_is_rejected(): void
    {
        $this->actingAs($this->admin())
            ->postJson(route('admin.theme-builder.canvas.insert-template', $this->theme()), ['template_id' => 'lib:does-not-exist'])
            ->assertStatus(422);
    }
}
