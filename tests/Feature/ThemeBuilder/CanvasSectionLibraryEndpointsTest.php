<?php

namespace Tests\Feature\ThemeBuilder;

use App\Models\Theme;
use App\Models\ThemeTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Phase 3A — the template/section endpoints were shipped broken at HEAD
 * (controller/service signature mismatches discovered during the 3A
 * preflight: listForCanvas called with named args it never had,
 * canvasInsertTemplate calling insertIntoPage(int, null) against a
 * (ThemeTemplate, array, ?int) signature, save-as-template storing the
 * request envelope as blocks). These endpoints are the Section Library's
 * foundation, so 3A fixed them and pins them here.
 */
class CanvasSectionLibraryEndpointsTest extends TestCase
{
    use RefreshDatabase;

    private Theme $theme;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs($this->admin());
        $this->theme = $this->theme();
    }

    protected function tearDown(): void
    {
        $this->theme->forceDelete();
        parent::tearDown();
    }

    private function admin(): User
    {
        $user = User::create([
            'mobile' => '0912' . random_int(1000000, 9999999),
            'password' => Hash::make('password'),
            'is_admin' => true,
            'is_active' => true,
        ]);

        // Canvas routes sit behind the wizard middleware group
        // (EnsureSuperAdminForWizard) — is_admin alone does not pass.
        $role = \App\Models\Role::where('slug', \App\Models\Role::SUPER_ADMIN)->first();
        if ($role) {
            $user->roles()->attach($role->id);
        }

        return $user;
    }

    private function theme(): Theme
    {
        return Theme::create([
            'name' => 'کتابخانه بخش تست',
            'slug' => 'canvas-section-e2e-' . uniqid(),
            'type' => Theme::TYPE_FULL,
            'pages' => ['home' => ['title' => 'خانه', 'slug' => '', 'blocks' => []]],
            'header_blocks' => [],
            'footer_blocks' => [],
            'settings' => ['extension_selections' => []],
        ]);
    }

    /** @test */
    public function canvas_templates_endpoint_lists_platform_templates(): void
    {
        ThemeTemplate::create([
            'uuid' => (string) Str::uuid(),
            'type' => 'section',
            'category' => 'hero',
            'name' => 'قهرمان تست',
            'blocks' => [],
            'is_active' => true,
        ]);

        $response = $this->getJson(route('admin.theme-builder.canvas.templates', $this->theme) . '?type=section');

        $response->assertOk()
            ->assertJsonStructure(['templates' => [['id', 'name', 'type', 'category']]]);
    }

    /** @test */
    public function canvas_insert_template_returns_regen_ids_for_known_types(): void
    {
        $template = ThemeTemplate::create([
            'uuid' => (string) Str::uuid(),
            'type' => 'section',
            'category' => 'hero',
            'name' => 'بخش تستی',
            'blocks' => [[
                'id' => 'orig_1',
                'type' => 'section',
                'props' => ['padding' => 'md'],
                'children' => [
                    ['id' => 'orig_2', 'type' => 'heading', 'props' => ['text' => 'سلام']],
                ],
            ]],
            'is_active' => true,
        ]);

        $response = $this->postJson(route('admin.theme-builder.canvas.insert-template', $this->theme), [
            'template_id' => $template->id,
        ]);

        $response->assertOk()->assertJsonPath('success', true);
        $blocks = $response->json('blocks');
        $this->assertCount(1, $blocks);
        $this->assertNotSame('orig_1', $blocks[0]['id']);
        $this->assertNotSame('orig_2', $blocks[0]['children'][0]['id']);
        $this->assertSame('heading', $blocks[0]['children'][0]['type']);
        // The stored template itself is never mutated by an insert.
        $this->assertSame('orig_1', $template->fresh()->blocks[0]['id']);
    }

    /** @test */
    public function canvas_insert_template_rejects_unknown_widget_types(): void
    {
        $template = ThemeTemplate::create([
            'uuid' => (string) Str::uuid(),
            'type' => 'section',
            'category' => 'hero',
            'name' => 'بخش با ویجت ناشناخته',
            'blocks' => [[
                'id' => 'x1',
                'type' => 'totally_unknown_widget',
                'props' => [],
            ]],
            'is_active' => true,
        ]);

        $response = $this->postJson(route('admin.theme-builder.canvas.insert-template', $this->theme), [
            'template_id' => $template->id,
        ]);

        $response->assertStatus(422);
        $this->assertStringContainsString('totally_unknown_widget', $response->json('message'));
    }

    /** @test */
    public function save_as_template_persists_block_tree_not_request_envelope(): void
    {
        $response = $this->postJson(route('admin.theme-builder.canvas.save-as-template', $this->theme), [
            'name' => 'ذخیره تست',
            'type' => 'section',
            'category' => 'hero',
            'scope' => 'block',
            'blocks' => [[
                'id' => 'sel_1',
                'type' => 'heading',
                'props' => ['text' => 'متن انتخابی'],
            ]],
        ]);

        $response->assertOk()->assertJsonPath('success', true);

        $template = ThemeTemplate::findOrFail($response->json('template.id'));
        $this->assertSame('section', $template->type);
        $this->assertSame('heading', $template->blocks[0]['type'], 'Stored blocks must be the block tree, not the request payload');
        $this->assertSame('متن انتخابی', $template->blocks[0]['props']['text']);
        // saveFromCanvas regenerates ids so a later insert never collides.
        $this->assertNotSame('sel_1', $template->blocks[0]['id']);
    }
}
