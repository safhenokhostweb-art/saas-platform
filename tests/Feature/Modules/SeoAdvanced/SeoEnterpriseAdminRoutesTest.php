<?php

namespace Tests\Feature\Modules\SeoAdvanced;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Phase 18-E — Part 11: reachability + auth guard for every new admin
 * surface added this phase (Search Console, AI Assistant, dynamic
 * Schema Builder).
 */
class SeoEnterpriseAdminRoutesTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::create([
            'mobile' => '0912' . random_int(1000000, 9999999), 'password' => Hash::make('password'),
            'is_admin' => true, 'is_active' => true,
        ]);
    }

    public function test_search_console_page_requires_authentication(): void
    {
        $this->get(route('seo-advanced.admin.search-console', 1))->assertRedirect(route('login'));
    }

    public function test_search_console_page_reachable_for_an_authenticated_admin(): void
    {
        $this->actingAs($this->admin())->get(route('seo-advanced.admin.search-console', 1))->assertOk();
    }

    public function test_search_console_performance_shows_disconnected_state_by_default(): void
    {
        $response = $this->actingAs($this->admin())->get(route('seo-advanced.admin.search-console.performance', 1));

        $response->assertOk();
        $response->assertSee('متصل نیست');
    }

    public function test_ai_assistant_page_requires_authentication(): void
    {
        $this->get(route('seo-advanced.admin.ai-assistant', 1))->assertRedirect(route('login'));
    }

    public function test_ai_assistant_page_reachable_for_an_authenticated_admin(): void
    {
        $this->actingAs($this->admin())->get(route('seo-advanced.admin.ai-assistant', 1))->assertOk();
    }

    public function test_ai_assistant_analyze_shows_not_configured_state(): void
    {
        $response = $this->actingAs($this->admin())->post(route('seo-advanced.admin.ai-assistant.analyze', 1), [
            'page_content' => 'محتوای نمونه صفحه',
        ]);

        $response->assertOk();
        $response->assertSee('پیکربندی نشده');
    }

    public function test_schema_builder_produces_valid_output_for_a_supported_type(): void
    {
        $response = $this->actingAs($this->admin())->post(route('seo-advanced.admin.schema.build', 1), [
            'type' => 'Product',
            'payload' => json_encode(['name' => 'محصول تست', 'price' => 100000]),
        ]);

        $response->assertOk();
        $response->assertSee('Product');
    }

    public function test_schema_builder_shows_an_error_for_incomplete_data(): void
    {
        $response = $this->actingAs($this->admin())->post(route('seo-advanced.admin.schema.build', 1), [
            'type' => 'Product',
            'payload' => json_encode(['price' => 100000]), // missing required "name"
        ]);

        $response->assertOk();
        $response->assertSee('کامل نیست');
    }
}
