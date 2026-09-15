<?php
namespace Tests\Feature;

/**
 * تست جریان: مشتری → سایت → ماژول‌ها → توزیع بروزرسانی.
 * دیتابیس تست در صورت نبودن به‌صورت خودکار ساخته می‌شود.
 */
use App\Models\Customer;
use App\Models\Module;
use App\Models\Site;
use App\Models\SiteUpdate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CustomerSiteModulesUpdateFlowTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        $this->ensureTestDatabaseExists();
        parent::setUp();
        $this->admin = User::create([
            'mobile' => '09121234567',
            'password' => Hash::make('password'),
            'is_admin' => true,
            'is_active' => true,
        ]);
    }

    public function test_full_flow_customer_to_site_to_modules_to_update_distribution(): void
    {
        // ۱) ایجاد مشتری
        $this->actingAs($this->admin);
        $customerResponse = $this->post(route('admin.customers.store'), [
            'name' => 'مشتری تست',
            'company_name' => 'شرکت تست',
            'email' => 'customer@test.local',
            'mobile' => '09123456789',
            'notes' => null,
        ]);
        $customerResponse->assertRedirect(route('admin.customers.index'));

        $customer = Customer::where('mobile', '09123456789')->first();
        $this->assertNotNull($customer);
        $this->assertSame('مشتری تست', $customer->name);
        $this->assertSame('شرکت تست', $customer->company_name);

        // ۲) ایجاد ماژول (برای اتصال به سایت)
        $module = Module::create([
            'slug' => 'test-module',
            'title' => 'ماژول تست',
            'description' => null,
            'is_active' => true,
        ]);

        // ۳) ایجاد سایت برای این مشتری
        // Phase 17-D Part 5 — module selection at creation time is no
        // longer processed by store() at all (a brand-new site has no
        // completed CustomerInstallation yet, so it can never resolve a
        // remote server to deploy a module to); assignment now happens
        // exclusively via the dedicated site-modules page
        // (SiteModuleController), after initial deployment completes — see
        // SiteController::store()'s own docblock. The 'modules' input
        // below is therefore inert; kept only to prove it's safely ignored.
        $siteResponse = $this->post(route('admin.sites.store'), [
            'customer_id' => $customer->id,
            'title' => 'سایت تست',
            'domain' => 'test.example.com',
            'hosting_company' => null,
            'db_host' => null,
            'db_name' => null,
            'db_user' => null,
            'db_password' => null,
            'support_expires_at' => null,
            'is_active' => true,
            'modules' => [$module->id],
        ]);
        if ($siteResponse->status() === 500) {
            echo "\n--- Response 500 body (first 3000 chars) ---\n" . substr($siteResponse->getContent(), 0, 3000) . "\n---\n";
        }
        $site = Site::where('domain', 'test.example.com')->first();
        $this->assertNotNull($site);
        // GAP-L09 — store() redirects to the edit page (with a "just
        // created" checklist), not the index, so the admin sees next
        // steps immediately.
        $siteResponse->assertRedirect(route('admin.sites.edit', $site));

        $this->assertSame($customer->id, $site->customer_id);

        // ۴) ثبت توزیع بروزرسانی برای این سایت
        $updateResponse = $this->post(route('admin.sites.updates.store', $site), [
            'version_label' => '1.0.0',
            'distributed_at' => now()->format('Y-m-d'),
            'notes' => 'توزیع تست',
        ]);
        $updateResponse->assertRedirect();
        $updateResponse->assertSessionHas('success');

        $siteUpdate = SiteUpdate::where('site_id', $site->id)->first();
        $this->assertNotNull($siteUpdate);
        $this->assertSame('1.0.0', $siteUpdate->version_label);
        $this->assertSame('توزیع تست', $siteUpdate->notes);

        // ۵) بررسی رابطه‌ها
        $customer->load('sites.modules', 'sites.siteUpdates');
        $this->assertCount(1, $customer->sites);
        // Module assignment no longer happens at site creation (see above);
        // a fresh site has none until assigned via SiteModuleController.
        $this->assertCount(0, $customer->sites->first()->modules);
        $this->assertCount(1, $customer->sites->first()->siteUpdates);
    }

    public function test_customer_list_shows_sites_count(): void
    {
        $customer = Customer::create([
            'name' => 'مشتری الف',
            'mobile' => '09121111111',
            'email' => null,
            'company_name' => null,
            'notes' => null,
        ]);
        Site::create(['customer_id' => $customer->id, 'title' => 'سایت یک', 'domain' => 'a.test.com', 'is_active' => true]);
        Site::create(['customer_id' => $customer->id, 'title' => 'سایت دو', 'domain' => 'b.test.com', 'is_active' => true]);

        $this->actingAs($this->admin);
        $response = $this->get(route('admin.customers.index'));
        $response->assertStatus(200);
        $response->assertSee('مشتری الف');
        // عدد در ویو به‌صورت لاتین (2) رندر می‌شود
        $response->assertSee('2 سایت');
    }

    public function test_site_support_status_expired(): void
    {
        $customer = Customer::create(['name' => 'c', 'mobile' => '09122222222', 'email' => null, 'company_name' => null, 'notes' => null]);
        $site = Site::create([
            'customer_id' => $customer->id,
            'title' => 'سایت',
            'domain' => 'x.test.com',
            'support_expires_at' => now()->subDay(),
            'is_active' => true,
        ]);
        $this->assertSame('expired', $site->supportStatus());
        $this->assertTrue($site->isSupportExpired());
    }

    public function test_site_support_status_expiring_soon(): void
    {
        $customer = Customer::create(['name' => 'c', 'mobile' => '09123333333', 'email' => null, 'company_name' => null, 'notes' => null]);
        $site = Site::create([
            'customer_id' => $customer->id,
            'title' => 'سایت',
            'domain' => 'y.test.com',
            'support_expires_at' => now()->addDays(15),
            'is_active' => true,
        ]);
        $this->assertSame('expiring_soon', $site->supportStatus());
    }

    /**
     * اگر دیتابیس تست در MySQL وجود نداشته باشد آن را می‌سازد.
     */
    protected function ensureTestDatabaseExists(): void
    {
        $this->createApplication();
        $db = config('database.connections.mysql.database');
        if (! $db) {
            return;
        }
        try {
            $pdo = new \PDO(
                'mysql:host=' . config('database.connections.mysql.host') . ';port=' . (config('database.connections.mysql.port') ?: 3306),
                config('database.connections.mysql.username'),
                config('database.connections.mysql.password'),
                [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]
            );
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `" . str_replace('`', '``', $db) . "`");
        } catch (\Throwable $e) {
            // در صورت خطا (مثلاً دسترسی نداشتن) تست‌ها اجرا می‌شوند؛ اگر دیتابیس از قبل ساخته شده باشد
        }
    }
}
