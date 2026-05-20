<?php
namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PhaseOneTwoThreeSaasTest extends TestCase
{
    use RefreshDatabase;

    public function test_support_plan_crud(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $response = $this->actingAs($admin)->get(route('admin.support-plans.index'));
        $response->assertStatus(200);

        $response = $this->actingAs($admin)->post(route('admin.support-plans.store'), [
            'title' => 'پشتیبانی یک‌ماهه',
            'price' => 500000,
            'duration_days' => 30,
            'has_ticket' => '1',
            'has_sms' => '0',
        ]);
        $response->assertRedirect(route('admin.support-plans.index'));
        $response->assertSessionHas('success');
        $this->assertDatabaseHas('support_plans', ['title' => 'پشتیبانی یک‌ماهه']);
    }

    public function test_payout_request_index_accessible(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $response = $this->actingAs($admin)->get(route('admin.payout-requests.index'));
        $response->assertStatus(200);
    }

    public function test_support_tickets_index_accessible(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $response = $this->actingAs($admin)->get(route('admin.support-tickets.index'));
        $response->assertStatus(200);
    }

    public function test_reseller_form_has_wholesale_discount(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $user = User::factory()->create();
        $reseller = \App\Models\Reseller::create([
            'user_id' => $user->id,
            'company_name' => 'نماینده',
            'commission_rate' => 10,
        ]);
        $response = $this->actingAs($admin)->get(route('admin.resellers.edit', $reseller));
        $response->assertStatus(200);
        $response->assertSee('تخفیف عمده');
    }

    public function test_admin_can_create_support_ticket(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $customer = \App\Models\Customer::create([
            'name' => 'مشتری',
            'email' => 'c@example.com',
            'mobile' => '09123456789',
        ]);
        $site = \App\Models\Site::create([
            'customer_id' => $customer->id,
            'title' => 'سایت',
            'domain' => 'example.com',
            'is_active' => true,
        ]);
        $response = $this->actingAs($admin)->post(route('admin.support-tickets.store'), [
            'site_id' => $site->id,
            'subject' => 'مشکل',
            'message' => 'پیام',
            'customer_email' => 'c@example.com',
            'customer_name' => 'مشتری',
            'priority' => 'normal',
        ]);
        $response->assertRedirect(route('admin.support-tickets.index'));
        $response->assertSessionHas('success');
        $this->assertDatabaseHas('support_tickets', ['subject' => 'مشکل']);
    }

    public function test_support_ticket_api_valid_license(): void
    {
        $customer = \App\Models\Customer::create([
            'name' => 'مشتری',
            'email' => 'c@example.com',
            'mobile' => '09123456789',
        ]);
        $site = \App\Models\Site::create([
            'customer_id' => $customer->id,
            'title' => 'سایت',
            'domain' => 'example.com',
            'is_active' => true,
            'support_expires_at' => now()->addDays(30),
            'support_level' => 'basic',
        ]);
        $key = app(\App\Services\LicenseServerService::class)->issue($site, 'example.com', null, 'site_template');
        $response = $this->postJson('/api/support-tickets', [
            'license_key' => $key,
            'domain' => 'example.com',
            'subject' => 'سؤال',
            'message' => 'پیام',
            'customer_email' => 'c@example.com',
        ]);
        $response->assertStatus(200);
        $response->assertJson(['success' => true]);
    }

    public function test_support_ticket_api_invalid_license(): void
    {
        $response = $this->postJson('/api/support-tickets', [
            'license_key' => 'INVALID',
            'domain' => 'example.com',
            'subject' => 'سؤال',
            'message' => 'پیام',
            'customer_email' => 'c@example.com',
        ]);
        $response->assertStatus(403);
        $response->assertJson(['success' => false]);
    }
}
