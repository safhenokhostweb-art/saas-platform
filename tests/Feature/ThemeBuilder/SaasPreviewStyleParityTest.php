<?php

namespace Tests\Feature\ThemeBuilder;

use App\Models\Role;
use App\Models\Theme;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Commercial Productization fase-3 (visual identity audit) — a real,
 * confirmed defect: `service_cards`, `project_cards`, `about_section`,
 * `contact_info`, `hero_trust`, and `stats_row` (the 7 canonical block
 * types from ADR-026) plus the generic `.tb-btn`/`.tb-btn-secondary`
 * buttons had ZERO CSS in the SaaS Builder's own preview templates
 * (canvas-preview.blade.php / canvas-live-preview.blade.php), even though
 * they render fully styled on customer-site's real runtime
 * (layout.blade.php). Every visitor to the SaaS admin preview saw these
 * blocks as bare, unstyled browser-default text — the exact
 * "SaaS preview ≠ customer runtime" defect this phase requires closed.
 *
 * These tests assert the real class selectors these renderers emit are
 * now genuinely styled in both preview surfaces, not merely present in
 * some unrelated stylesheet.
 */
class SaasPreviewStyleParityTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $user = User::create([
            'mobile' => '0912' . random_int(1000000, 9999999),
            'password' => Hash::make('password'), 'is_admin' => true, 'is_active' => true,
        ]);
        if ($role = Role::where('slug', Role::SUPER_ADMIN)->first()) {
            $user->roles()->attach($role->id);
        }

        return $user;
    }

    private function themeWithPage(array $blocks): Theme
    {
        return Theme::create([
            'name' => 'Style parity test', 'slug' => 'style-parity-' . uniqid(),
            'type' => Theme::TYPE_FULL, 'source' => Theme::SOURCE_SEEDER,
            'status' => Theme::STATUS_DRAFT, 'category' => Theme::CATEGORY_CORPORATE,
            'header_blocks' => [], 'footer_blocks' => [],
            'pages' => ['home' => ['title' => 'Home', 'slug' => '', 'is_home' => true, 'blocks' => $blocks]],
            'global_styles' => [],
            'settings' => [],
        ]);
    }

    public function test_the_seven_adr_026_block_types_and_stats_row_are_genuinely_styled_in_the_saas_preview(): void
    {
        $theme = $this->themeWithPage([
            ['id' => 'b1', 'type' => 'service_cards', 'props' => ['title' => 't', 'services' => [['icon' => '🎨', 'title' => 'x', 'text' => 'y', 'url' => '#']]]],
            ['id' => 'b2', 'type' => 'project_cards', 'props' => ['title' => 't', 'projects' => [['category' => 'c', 'title' => 'x', 'text' => 'y', 'url' => '#']]]],
            ['id' => 'b3', 'type' => 'about_section', 'props' => ['title' => 't', 'description' => 'd', 'features' => ['a'], 'stats' => [['value' => '1', 'label' => 'l']]]],
            ['id' => 'b4', 'type' => 'contact_info', 'props' => ['items' => [['icon' => '📞', 'label' => 'l', 'value' => 'v']]]],
            ['id' => 'b5', 'type' => 'hero_trust', 'props' => ['title' => 't', 'rating' => 4.5, 'phone' => '021']],
            ['id' => 'b6', 'type' => 'stats_row', 'props' => ['stats' => [['value' => '10', 'suffix' => '+', 'label' => 'l']]]],
        ]);

        $response = $this->actingAs($this->admin())
            ->get(route('admin.theme-builder.canvas.page-preview', ['theme' => $theme->id, 'pageKey' => 'home']));

        $response->assertOk();
        foreach ([
            '.tb-service-card', '.tb-project-card', '.tb-about-section', '.tb-contact-info-item',
            '.tb-hero-trust-inner', '.tb-stats-grid', '.tb-stat-value', '.tb-features-section-title',
        ] as $selector) {
            $response->assertSee($selector, false);
        }
        // Every one of these blocks' real HTML markup must actually be
        // present too — not just the CSS existing in isolation.
        $response->assertSee('tb-service-card', false);
        $response->assertSee('tb-hero-trust-inner', false);
    }

    public function test_pricing_testimonials_team_and_process_steps_are_genuinely_styled_in_the_saas_preview(): void
    {
        $theme = $this->themeWithPage([
            ['id' => 'b1', 'type' => 'pricing_cards', 'props' => ['plans' => [['name' => 'x', 'price' => '1', 'features' => ['a']]]]],
            ['id' => 'b2', 'type' => 'team_cards', 'props' => ['members' => [['name' => 'x', 'role' => 'y']]]],
            ['id' => 'b3', 'type' => 'process_steps', 'props' => ['steps' => [['number' => '1', 'title' => 'x', 'text' => 'y']]]],
        ]);

        $response = $this->actingAs($this->admin())
            ->get(route('admin.theme-builder.canvas.page-preview', ['theme' => $theme->id, 'pageKey' => 'home']));

        $response->assertOk();
        foreach (['.tb-pricing-card', '.tb-plan-price', '.tb-team-member', '.tb-team-avatar', '.tb-step-number', '.tb-steps-grid'] as $selector) {
            $response->assertSee($selector, false);
        }
    }

    public function test_the_generic_secondary_button_class_is_styled_in_the_saas_preview(): void
    {
        $theme = $this->themeWithPage([
            ['id' => 'b1', 'type' => 'hero_split', 'props' => ['title' => 't', 'btn1_text' => 'a', 'btn1_url' => '#', 'btn2_text' => 'b', 'btn2_url' => '#']],
        ]);

        $response = $this->actingAs($this->admin())
            ->get(route('admin.theme-builder.canvas.page-preview', ['theme' => $theme->id, 'pageKey' => 'home']));

        $response->assertOk();
        $response->assertSee('.tb-btn-secondary', false);
        $response->assertSee('tb-btn-secondary', false);
    }
}
