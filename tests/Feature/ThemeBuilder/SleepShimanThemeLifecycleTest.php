<?php

namespace Tests\Feature\ThemeBuilder;

use App\Models\Customer;
use App\Models\Site;
use App\Models\Theme;
use App\Services\ThemeBuilder\ThemeInstallGateway;
use Database\Seeders\SleepShimanHomeBlueThemeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class SleepShimanThemeLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_is_idempotent_and_keeps_the_theme_draft(): void
    {
        $this->seed(SleepShimanHomeBlueThemeSeeder::class);
        $this->seed(SleepShimanHomeBlueThemeSeeder::class);

        $theme = Theme::where('slug', SleepShimanHomeBlueThemeSeeder::SLUG)->sole();
        self::assertSame(Theme::STATUS_DRAFT, $theme->status);
        self::assertNull($theme->site_id);
        self::assertNull($theme->current_release_id);
        self::assertCount(1, $theme->getPageList());
        self::assertCount(19, $theme->pages['home']['blocks']);
        self::assertCount(2, $theme->header_blocks);
        self::assertCount(1, $theme->footer_blocks);
    }

    public function test_existing_install_gateway_transports_the_complete_theme_contract(): void
    {
        config(['services.customer_provision_secret' => 'sleep-test-secret']);
        $this->seed(SleepShimanHomeBlueThemeSeeder::class);
        $theme = Theme::where('slug', SleepShimanHomeBlueThemeSeeder::SLUG)->sole();
        $customer = Customer::create(['name' => 'SleepShiman Test', 'mobile' => '09120000097']);
        $site = Site::create([
            'customer_id' => $customer->id,
            'title' => 'SleepShiman Test Site',
            'domain' => 'sleepshiman-install.test',
            'is_active' => true,
        ]);
        Http::fake([
            'https://sleepshiman-install.test/builder/api/theme/install' => Http::response([
                'success' => true, 'revision_id' => 1, 'revision_number' => 1, 'pages_installed' => 1,
            ]),
        ]);

        app(ThemeInstallGateway::class)->install($theme, $site);

        Http::assertSent(function ($request): bool {
            $payload = $request->data();

            return $request->hasHeader('X-Provision-Secret', 'sleep-test-secret')
                && ($payload['theme_id'] ?? null) === SleepShimanHomeBlueThemeSeeder::SLUG
                && count($payload['pages'] ?? []) === 1
                && count($payload['header_blocks'] ?? []) === 2
                && count($payload['footer_blocks'] ?? []) === 1
                && count($payload['theme_assets']['assets'] ?? []) >= 24;
        });
        self::assertSame(Theme::STATUS_DRAFT, $theme->fresh()->status, 'transport must not publish or assign the master theme');
    }
}
