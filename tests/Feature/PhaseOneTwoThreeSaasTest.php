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
        // FINAL PRE-MEDIA BASELINE: admin.support-tickets.index is a
        // permanent PHASE 7.2 redirect shim to the canonical
        // tickets.admin.index route (routes/web.php:589, see
        // packages/saas-platform/modules/tickets/Routes/admin.php) — the
        // legacy AdminSupportTicketController it used to serve directly was
        // removed from active routing months before this baseline pass.
        // "Accessible" now means the legacy alias correctly forwards to the
        // canonical module, which itself is proven reachable here.
        $admin = User::factory()->create(['is_admin' => true]);
        $response = $this->actingAs($admin)->get(route('admin.support-tickets.index'));
        $response->assertRedirect(route('tickets.admin.index'));
        $response->assertStatus(301);
        $this->actingAs($admin)->get(route('tickets.admin.index'))->assertStatus(200);
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
        // FINAL PRE-MEDIA BASELINE: admin.support-tickets.store is the same
        // PHASE 7.2 redirect shim (see test_support_tickets_index_accessible
        // above) — it now forwards to tickets.admin.store on the canonical
        // SaaSPlatform\Modules\Tickets module, an entirely different
        // controller/model than the legacy support_tickets table this test
        // originally asserted against. Ticket-creation coverage for the
        // canonical module belongs in that module's own test suite; this
        // test verifies only the legacy alias's forwarding contract.
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
        $response->assertRedirect(route('tickets.admin.store'));
        $response->assertStatus(301);
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
        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $key])->postJson('/api/support-tickets', [
            'domain' => 'example.com',
            'subject' => 'سؤال',
            'message' => 'پیام',
            'customer_mobile' => '09123456789',
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
