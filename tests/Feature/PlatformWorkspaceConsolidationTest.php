<?php

namespace Tests\Feature;

use App\Helpers\AdminMenu;
use App\Helpers\PlatformSettings;
use App\Models\Customer;
use App\Models\Role;
use App\Models\Setting;
use App\Models\Site;
use App\Models\User;
use App\Services\LicenseServerService;
use App\Services\ThemeBuilder\ReverseEngineering\VisualReverseEngineeringService;
use App\Services\ThemeBuilder\ThemeAiAnalyzerService;
use App\Services\ThemeBuilder\ThemeAiGeneratorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PlatformWorkspaceConsolidationTest extends TestCase
{
    use RefreshDatabase;

    private function superAdmin(): User
    {
        $user = User::create([
            'mobile'    => '0912' . random_int(1000000, 9999999),
            'password'  => Hash::make('password'),
            'is_admin'  => true,
            'is_active' => true,
        ]);
        if ($role = Role::where('slug', Role::SUPER_ADMIN)->first()) {
            $user->roles()->attach($role->id);
        }

        return $user;
    }

    public function test_asset_access_menu_removed_and_merged_into_site_edit_and_theme_builder(): void
    {
        $groups = AdminMenu::getGroups();
        $this->assertNotContains('asset_access', $groups['سایت‌ها / پروژه‌ها'] ?? []);
        $this->assertArrayNotHasKey('asset_access', AdminMenu::MENU_KEYS);

        $customer = Customer::create([
            'name'   => 'Test Customer',
            'mobile' => '09120001122',
            'status' => 'active',
        ]);
        $site = Site::create([
            'customer_id' => $customer->id,
            'title'       => 'سایت تست',
            'domain'      => 'merge-test.ir',
            'is_active'   => true,
        ]);

        $this->actingAs($this->superAdmin());

        $this->get(route('admin.sites.edit', $site))
            ->assertOk()
            ->assertSee('site-asset-access-section')
            ->assertSee('business_vertical')
            ->assertDontSee('href="' . route('admin.sites.asset-access', $site) . '"', false);

        $this->put(route('admin.sites.update', $site), [
            'customer_id'       => $customer->id,
            'title'             => 'سایت تست',
            'domain'            => 'merge-test.ir',
            'business_vertical' => 'solar_energy',
            'is_active'         => 1,
        ])->assertRedirect(route('admin.sites.index'));

        $this->assertSame('solar_energy', $site->fresh()->business_vertical);

        $this->get(route('admin.theme-builder.index'))
            ->assertOk()
            ->assertSee('سیاست دسترسی قالب‌ها و ویجت‌ها')
            ->assertSee('asset-access-catalog');
    }

    public function test_infrastructure_is_consolidated_into_ajax_tabbed_workspace(): void
    {
        $groups = AdminMenu::getGroups();
        $this->assertSame(['infrastructure'], $groups['زیرساخت / DevOps'] ?? []);

        $this->actingAs($this->superAdmin());

        $this->get(route('admin.infrastructure.overview'))
            ->assertOk()
            ->assertSee('infra-tabs-root')
            ->assertSee('infraTabsWorkspace');

        $this->getJson(route('admin.infrastructure.servers.index'), ['X-Infra-Tab' => '1'])
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonStructure(['ok', 'html']);

        $this->postJson(route('admin.infrastructure.servers.store'), [
            'name'        => 'Edge Node 1',
            'environment' => 'production',
            'status'      => 'online',
        ], ['X-Infra-Tab' => '1'])
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('flash_success', 'سرور با موفقیت ثبت شد.');

        $this->postJson(route('admin.infrastructure.servers.store'), [
            'environment' => 'invalid',
        ], ['X-Infra-Tab' => '1'])
            ->assertStatus(422)
            ->assertJsonStructure(['errors']);
    }

    public function test_admin_layout_includes_sidebar_scroll_and_preserve_scroll_hooks(): void
    {
        $this->actingAs($this->superAdmin());

        $this->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('saas-admin-sidebar-scroll', false)
            ->assertSee('form[data-preserve-scroll]', false);
    }

    public function test_gapgpt_exclusive_and_encrypted_platform_settings_sync(): void
    {
        $this->assertNull(config('services.deepseek'));
        $this->assertNull(config('services.anthropic'));
        $this->assertNull(config('services.local_ai'));
        $this->assertSame('gapgpt', config('services.theme_ai.provider'));

        $this->assertSame('GapGPT AI', app(ThemeAiAnalyzerService::class)->getProviderLabel());
        $this->assertSame('GAPGPT_API_KEY', app(ThemeAiAnalyzerService::class)->getEnvKeyName());
        $this->assertSame('GapGPT AI', app(ThemeAiGeneratorService::class)->getProviderLabel());
        $this->assertSame('GAPGPT_API_KEY', app(VisualReverseEngineeringService::class)->getEnvKeyName());

        $this->actingAs($this->superAdmin());

        $fakeKey = 'gapgpt-test-key-9876';
        $resp = $this->post(route('admin.platform-settings.update'), array_merge(PlatformSettings::THEME_KEYS, [
            'platform_name'   => 'پلتفرم تست',
            'gapgpt_api_key'  => $fakeKey,
            'gapgpt_base_url' => 'https://api.gapgpt.app/v1',
            'gapgpt_model'    => 'gpt-4o-mini',
        ]));
        $resp->assertRedirect();
        $resp->assertSessionHas('success');

        $storedEncrypted = Setting::get('gapgpt_api_key_encrypted');
        $this->assertNotEmpty($storedEncrypted);
        $this->assertNotSame($fakeKey, $storedEncrypted);

        $status = PlatformSettings::gapGptStatus();
        $this->assertTrue($status['configured']);
        $this->assertSame('panel', $status['source']);
        $this->assertSame('9876', $status['last4']);

        // Settings page must never expose the full key
        $this->get(route('admin.platform-settings.index'))
            ->assertOk()
            ->assertSee('gapgpt-settings-card')
            ->assertSee('9876')
            ->assertDontSee($fakeKey);

        // SiteConfig API syncs GapGPT config to licensed customer-site
        $customer = Customer::create(['name' => 'C', 'mobile' => '09120003344', 'status' => 'active']);
        $site = Site::create(['customer_id' => $customer->id, 'title' => 'Sync Site', 'domain' => 'sync-ai.ir', 'is_active' => true]);
        $licenseKey = app(LicenseServerService::class)->issue($site, 'sync-ai.ir');

        $this->withToken($licenseKey)
            ->getJson('/api/site-config?domain=sync-ai.ir')
            ->assertOk()
            ->assertJsonPath('ai.provider', 'gapgpt')
            ->assertJsonPath('ai.api_key', $fakeKey)
            ->assertJsonPath('ai.model', 'gpt-4o-mini');
    }

    public function test_canvas_structure_tree_supports_three_zone_reparenting_drag_and_drop(): void
    {
        $this->actingAs($this->superAdmin());

        $theme = \App\Models\Theme::create([
            'name'          => 'Tree Drag Theme',
            'slug'          => 'tree-drag-' . uniqid(),
            'status'        => \App\Models\Theme::STATUS_DRAFT,
            'header_blocks' => [],
            'footer_blocks' => [],
            'pages'         => ['home' => ['title' => 'Home', 'slug' => '', 'blocks' => []]],
            'settings'      => [],
        ]);

        $this->get(route('admin.theme-builder.canvas', $theme))
            ->assertOk()
            ->assertSee('computeTreeDropPosition', false)
            ->assertSee('onTreeDrop', false)
            ->assertSee('زیرمجموعه داخل این ویجت');
    }
}
