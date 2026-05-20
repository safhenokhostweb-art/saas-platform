<?php
namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Product;
use App\Models\Site;
use App\Models\SupportPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * تست فاز ۲: لینک خرید پشتیبانی و پیش‌انتخاب سایت در فروشگاه.
 */
class PhaseTwoStoreAndSiteTest extends TestCase
{
    use RefreshDatabase;

    public function test_site_edit_shows_buy_support_link_when_support_product_exists(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $customer = Customer::create([
            'name' => 'مشتری',
            'email' => 'c@example.com',
            'mobile' => '09123456789',
        ]);
        $site = Site::create([
            'customer_id' => $customer->id,
            'title' => 'سایت تست',
            'domain' => 'example.com',
            'is_active' => true,
        ]);
        $plan = SupportPlan::create([
            'title' => 'پشتیبانی یک‌ماهه',
            'price' => 500000,
            'duration_days' => 30,
        ]);
        Product::create([
            'sku' => 'support_1',
            'name_fa' => 'پشتیبانی یک‌ماهه',
            'type' => Product::TYPE_SUPPORT_PLAN,
            'support_plan_id' => $plan->id,
            'price_irr' => 500000,
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin)->get(route('admin.sites.edit', $site));
        $response->assertStatus(200);
        $response->assertSee('خرید پشتیبانی برای این سایت');
        $response->assertSee('site_id=' . $site->id);
    }

    public function test_store_index_accepts_site_id_param(): void
    {
        $user = User::factory()->create();
        $customer = Customer::create([
            'name' => 'مشتری',
            'email' => 'c@example.com',
            'mobile' => '09123456789',
            'user_id' => $user->id,
        ]);
        $site = Site::create([
            'customer_id' => $customer->id,
            'title' => 'سایت من',
            'domain' => 'mysite.com',
            'is_active' => true,
        ]);
        $plan = SupportPlan::create([
            'title' => 'پشتیبانی',
            'price' => 300000,
            'duration_days' => 30,
        ]);
        Product::create([
            'sku' => 'support_test',
            'name_fa' => 'پشتیبانی',
            'type' => Product::TYPE_SUPPORT_PLAN,
            'support_plan_id' => $plan->id,
            'price_irr' => 300000,
            'is_active' => true,
        ]);

        $response = $this->actingAs($user)->get(route('store.index') . '?site_id=' . $site->id);
        $response->assertStatus(200);
        $response->assertSee('سایت من');
        $response->assertSee('selected'); // preselected option in dropdown
    }
}
