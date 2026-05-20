<?php
namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Module;
use App\Models\PaymentGateway;
use App\Models\Site;
use App\Models\User;
use App\Services\Payment\PaymentGatewayResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PaymentGatewayModuleTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::create([
            'mobile' => '09121111111',
            'password' => Hash::make('password'),
            'is_admin' => true,
            'is_active' => true,
        ]);
    }

    public function test_admin_can_create_payment_gateway(): void
    {
        $this->actingAs($this->admin);

        $response = $this->post(route('admin.payment-gateways.store'), [
            'name' => 'زرین‌پال تست',
            'driver' => PaymentGateway::DRIVER_ZARINPAL,
            'merchant_id' => 'xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx',
            'sandbox' => true,
            'is_active' => true,
            'is_default' => true,
        ]);

        $response->assertRedirect(route('admin.payment-gateways.index'));

        $gw = PaymentGateway::where('name', 'زرین‌پال تست')->first();
        $this->assertNotNull($gw);
        $this->assertSame(PaymentGateway::DRIVER_ZARINPAL, $gw->driver);
        $this->assertSame('xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx', $gw->config['merchant_id']);
        $this->assertTrue($gw->config['sandbox']);
        $this->assertTrue($gw->is_default);
    }

    public function test_admin_can_assign_gateway_to_site(): void
    {
        $gateway = PaymentGateway::create([
            'name' => 'درگاه اصلی',
            'driver' => PaymentGateway::DRIVER_ZARINPAL,
            'config' => ['merchant_id' => 'test', 'sandbox' => true],
            'is_active' => true,
            'is_default' => true,
        ]);

        $bankGatewayModule = Module::firstOrCreate(
            ['slug' => 'bank_gateway'],
            ['title' => 'درگاه بانکی', 'description' => null, 'is_active' => true]
        );

        $customer = Customer::create([
            'name' => 'مشتری',
            'email' => 'c@test.local',
            'mobile' => '09122222222',
        ]);

        $site = Site::create([
            'customer_id' => $customer->id,
            'title' => 'سایت تست',
            'domain' => 'site.test',
            'is_active' => true,
        ]);
        $site->modules()->sync([$bankGatewayModule->id => ['is_active' => true]]);

        $this->assertNull($site->payment_gateway_id);

        $this->actingAs($this->admin);
        $response = $this->put(route('admin.sites.update', $site), [
            'customer_id' => $customer->id,
            'title' => $site->title,
            'domain' => $site->domain,
            'is_active' => true,
            'payment_gateway_id' => $gateway->id,
            'modules' => [$bankGatewayModule->id],
        ]);

        $response->assertRedirect(route('admin.sites.index'));

        $site->refresh();
        $this->assertSame($gateway->id, $site->payment_gateway_id);
    }

    public function test_resolver_returns_site_gateway_when_assigned(): void
    {
        $gateway = PaymentGateway::create([
            'name' => 'درگاه سایت',
            'driver' => PaymentGateway::DRIVER_ZARINPAL,
            'config' => ['merchant_id' => 'site-merchant', 'sandbox' => true],
            'is_active' => true,
            'is_default' => false,
        ]);

        $customer = Customer::create([
            'name' => 'مشتری',
            'email' => 'c@test.local',
            'mobile' => '09123333333',
        ]);

        $site = Site::create([
            'customer_id' => $customer->id,
            'title' => 'سایت با درگاه',
            'domain' => 'gw.test',
            'is_active' => true,
            'payment_gateway_id' => $gateway->id,
        ]);

        $resolver = app(PaymentGatewayResolver::class);
        $resolved = $resolver->resolveForSite($site->id);

        $this->assertSame('zarinpal', $resolved->name());
    }

    public function test_resolver_returns_default_when_site_has_no_gateway(): void
    {
        $defaultGw = PaymentGateway::create([
            'name' => 'پیش‌فرض',
            'driver' => PaymentGateway::DRIVER_ZARINPAL,
            'config' => ['merchant_id' => 'default', 'sandbox' => true],
            'is_active' => true,
            'is_default' => true,
        ]);

        $customer = Customer::create([
            'name' => 'مشتری',
            'email' => 'c2@test.local',
            'mobile' => '09124444444',
        ]);

        $site = Site::create([
            'customer_id' => $customer->id,
            'title' => 'سایت بدون درگاه',
            'domain' => 'no-gw.test',
            'is_active' => true,
            'payment_gateway_id' => null,
        ]);

        $resolver = app(PaymentGatewayResolver::class);
        $resolved = $resolver->resolveForSite($site->id);

        $this->assertSame('zarinpal', $resolved->name());
    }

    public function test_admin_can_create_idpay_gateway(): void
    {
        $this->actingAs($this->admin);

        $response = $this->post(route('admin.payment-gateways.store'), [
            'name' => 'آی‌دی‌پی تست',
            'driver' => PaymentGateway::DRIVER_IDPAY,
            'api_key' => 'test-api-key-36-chars-long-xxxx',
            'sandbox' => true,
            'is_active' => true,
            'is_default' => false,
        ]);

        $response->assertRedirect(route('admin.payment-gateways.index'));

        $gw = PaymentGateway::where('name', 'آی‌دی‌پی تست')->first();
        $this->assertNotNull($gw);
        $this->assertSame(PaymentGateway::DRIVER_IDPAY, $gw->driver);
        $this->assertSame('test-api-key-36-chars-long-xxxx', $gw->config['api_key']);
        $this->assertTrue($gw->config['sandbox']);
    }

    public function test_site_has_bank_gateway_module(): void
    {
        $bankGatewayModule = \App\Models\Module::where('slug', 'bank_gateway')->first();
        $this->assertNotNull($bankGatewayModule, 'ماژول bank_gateway باید از مایگریشن seed شده باشد');

        $customer = Customer::create([
            'name' => 'مشتری',
            'email' => 'bg@test.local',
            'mobile' => '09125555555',
        ]);

        $site = Site::create([
            'customer_id' => $customer->id,
            'title' => 'سایت با ماژول',
            'domain' => 'bg.test',
            'is_active' => true,
        ]);

        $this->assertFalse($site->hasBankGatewayModule());

        $site->modules()->attach($bankGatewayModule->id, ['is_active' => true]);

        $site->refresh();
        $this->assertTrue($site->hasBankGatewayModule());
    }
}
