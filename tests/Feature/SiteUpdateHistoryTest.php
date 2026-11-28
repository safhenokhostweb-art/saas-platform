<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Site;
use App\Models\SiteUpdate;
use App\Models\User;
use App\Services\LicenseServerService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SiteUpdateHistoryTest extends TestCase
{
    use DatabaseTransactions;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::firstOrCreate(
            ['mobile' => '09129998877'],
            [
                'name' => 'Admin Test',
                'password' => Hash::make('secret123'),
                'is_admin' => true,
                'is_active' => true,
            ]
        );
    }

    private function createSite(string $label): Site
    {
        $customer = Customer::query()->create([
            'name' => "Customer {$label}",
            'mobile' => '0912' . random_int(1000000, 9999999),
        ]);

        return Site::query()->create([
            'customer_id' => $customer->id,
            'title' => "Site {$label}",
            'domain' => strtolower("{$label}.tenant.test"),
            'is_active' => true,
            'support_expires_at' => now()->addMonth(),
            'support_level' => 'pro',
        ]);
    }

    private function credentialFor(Site $site): string
    {
        return app(LicenseServerService::class)->issue($site, $site->domain);
    }

    public function test_api_history_returns_fleet_and_site_updates_with_persian_details(): void
    {
        $site = $this->createSite('history');
        $credential = $this->credentialFor($site);

        // Fleet update
        SiteUpdate::create([
            'site_id' => null,
            'title' => 'هدر شناور شیشه‌ای مدرن و پلتفرم Canvas',
            'version' => 'v1.4.0',
            'version_label' => 'v1.4.0',
            'shamsi_date' => '۱۴۰۵/۰۹/۰۸',
            'release_date' => '2026-11-28',
            'update_type' => 'feature',
            'features' => [
                [
                    'category' => 'هدر شیشه‌ای مدرن',
                    'items' => ['پوسته جدید glass_nimbus'],
                ],
            ],
            'notes' => 'توضیحات تست هدر شیشه‌ای',
            'is_published' => true,
        ]);

        // Site-specific update
        SiteUpdate::create([
            'site_id' => $site->id,
            'title' => 'بروزرسانی اختصاصی سایت',
            'version' => 'v1.4.1-custom',
            'version_label' => 'v1.4.1-custom',
            'shamsi_date' => '۱۴۰۵/۰۹/۰۹',
            'release_date' => '2026-11-29',
            'update_type' => 'fix',
            'features' => null,
            'notes' => 'رفع ایراد اختصاصی',
            'is_published' => true,
        ]);

        $response = $this->withToken($credential)->withHeaders([
            'X-Tenant-Domain' => $site->domain,
        ])->getJson('/api/updates/history');

        $response->assertStatus(200);
        $response->assertJson([
            'allowed' => true,
        ]);

        $history = $response->json('history');
        $this->assertNotEmpty($history);
        $versions = array_column($history, 'version');
        $this->assertContains('v1.4.1-custom', $versions);
        $this->assertContains('v1.4.0', $versions);
    }

    public function test_api_check_detects_latest_fleet_update(): void
    {
        $site = $this->createSite('check');
        $credential = $this->credentialFor($site);

        SiteUpdate::create([
            'site_id' => null,
            'title' => 'هدر شناور شیشه‌ای مدرن و پلتفرم Canvas',
            'version' => 'v1.4.0',
            'version_label' => 'v1.4.0',
            'shamsi_date' => '۱۴۰۵/۰۹/۰۸',
            'release_date' => '2026-11-28',
            'distributed_at' => now(),
            'update_type' => 'feature',
            'notes' => 'توضیحات تست',
            'is_published' => true,
        ]);

        $response = $this->withToken($credential)->withHeaders([
            'X-Tenant-Domain' => $site->domain,
        ])->getJson('/api/updates/check?product=site_template&current_version=1.3.0');

        $response->assertStatus(200);
        $response->assertJson([
            'allowed' => true,
            'update_available' => true,
            'latest_version' => 'v1.4.0',
            'title' => 'هدر شناور شیشه‌ای مدرن و پلتفرم Canvas',
        ]);
    }

    public function test_admin_can_create_and_delete_fleet_update(): void
    {
        $site = $this->createSite('admin');

        $response = $this->actingAs($this->admin)->post(route('admin.sites.updates.store', $site), [
            'scope' => 'fleet',
            'version' => 'v1.5.0-test',
            'title' => 'بروزرسانی تستی ناوگان',
            'shamsi_date' => '۱۴۰۵/۰۹/۱۰',
            'update_type' => 'feature',
            'notes' => 'یادداشت تستی',
        ]);

        $response->assertSessionHas('success');

        $update = SiteUpdate::where('version', 'v1.5.0-test')->first();
        $this->assertNotNull($update);
        $this->assertNull($update->site_id);
        $this->assertTrue($update->isFleet());

        $deleteResponse = $this->actingAs($this->admin)->delete(route('admin.sites.updates.destroy', $update));
        $deleteResponse->assertSessionHas('success');
        $this->assertDatabaseMissing('site_updates', ['id' => $update->id]);
    }
}
