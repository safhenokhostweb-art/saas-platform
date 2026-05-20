<?php
namespace Tests\Feature;

use App\Models\Customer;
use App\Models\PlatformLicense;
use App\Models\Site;
use App\Models\SupportPlan;
use App\Models\SupportTicket;
use App\Models\SupportTicketReply;
use App\Models\User;
use App\Services\SupportLicenseService;
use App\Services\SupportSlaService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupportTicketModuleTest extends TestCase
{
    use RefreshDatabase;

    private function makeSite(array $overrides = []): Site
    {
        $customer = Customer::create([
            'name' => 'site customer',
            'email' => 'c@test.com',
            'mobile' => '09123456789',
        ]);
        return Site::create(array_merge([
            'customer_id' => $customer->id,
            'title' => 'test site',
            'domain' => 'example.com',
            'is_active' => true,
        ], $overrides));
    }

    private function admin(): User
    {
        return User::factory()->create(['is_admin' => true]);
    }

    public function test_ticket_auto_generates_number(): void
    {
        $site = $this->makeSite();
        $t = SupportTicket::factory()->create(['site_id' => $site->id]);
        $this->assertNotNull($t->ticket_number);
        $this->assertStringStartsWith('ST-' . now()->format('Y') . '-', $t->ticket_number);
    }

    public function test_ticket_number_incremental(): void
    {
        $site = $this->makeSite();
        $t1 = SupportTicket::factory()->create(['site_id' => $site->id, 'created_at' => now()->startOfYear()]);
        $t2 = SupportTicket::factory()->create(['site_id' => $site->id, 'created_at' => now()->startOfYear()]);
        $this->assertSame('ST-' . now()->format('Y') . '-00001', $t1->ticket_number);
        $this->assertSame('ST-' . now()->format('Y') . '-00002', $t2->ticket_number);
    }

    public function test_ticket_relations(): void
    {
        $site = $this->makeSite();
        $admin = $this->admin();
        $t = SupportTicket::factory()->create(['site_id' => $site->id, 'assigned_to' => $admin->id]);
        $this->assertEquals($site->id, $t->site->id);
        $this->assertEquals($admin->id, $t->assignee->id);
        SupportTicketReply::factory()->count(2)->create(['support_ticket_id' => $t->id]);
        $this->assertCount(2, $t->replies);
    }

    public function test_ticket_factory_states(): void
    {
        $site = $this->makeSite();
        $p = SupportTicket::factory()->inProgress()->create(['site_id' => $site->id]);
        $this->assertEquals(SupportTicket::STATUS_IN_PROGRESS, $p->status);
        $this->assertNotNull($p->first_response_at);

        $c = SupportTicket::factory()->closed()->create(['site_id' => $site->id]);
        $this->assertEquals(SupportTicket::STATUS_CLOSED, $c->status);
        $this->assertNotNull($c->closed_at);
        $this->assertNotNull($c->resolved_at);

        $h = SupportTicket::factory()->highPriority()->create(['site_id' => $site->id]);
        $this->assertEquals(SupportTicket::PRIORITY_HIGH, $h->priority);
    }
    public function test_reply_relations(): void
    {
        $site = $this->makeSite();
        $user = User::factory()->create();
        $t = SupportTicket::factory()->create(['site_id' => $site->id]);
        $r = SupportTicketReply::factory()->create(['support_ticket_id' => $t->id, 'user_id' => $user->id]);
        $this->assertEquals($t->id, $r->ticket->id);
        $this->assertEquals($user->id, $r->user->id);
    }

    public function test_reply_is_from_staff(): void
    {
        $site = $this->makeSite();
        $t = SupportTicket::factory()->create(['site_id' => $site->id]);
        $r1 = SupportTicketReply::factory()->create(['support_ticket_id' => $t->id, 'author_type' => SupportTicketReply::AUTHOR_STAFF]);
        $this->assertTrue($r1->isFromStaff());
        $r2 = SupportTicketReply::factory()->fromCustomer()->create(['support_ticket_id' => $t->id]);
        $this->assertFalse($r2->isFromStaff());
    }

    // ==================== License Service ====================

    public function test_license_active(): void
    {
        $s = app(SupportLicenseService::class);
        $site = $this->makeSite(['support_expires_at' => now()->addDays(30)]);
        $this->assertTrue($s->isEligibleForUpdates($site));
        $this->assertEquals('active', $s->getStatus($site));
    }

    public function test_license_expired(): void
    {
        $s = app(SupportLicenseService::class);
        $site = $this->makeSite(['support_expires_at' => now()->subDays(10)]);
        $this->assertFalse($s->isEligibleForUpdates($site));
        $this->assertEquals('expired', $s->getStatus($site));
    }

    public function test_license_grace(): void
    {
        $s = app(SupportLicenseService::class);
        $site = $this->makeSite(['support_expires_at' => now()->subDays(5)]);
        $this->assertTrue($s->isInGracePeriod($site));
        $this->assertEquals('grace', $s->getStatus($site));
        $this->assertTrue($s->isEligibleForUpdates($site));
    }

    public function test_license_past_grace(): void
    {
        $s = app(SupportLicenseService::class);
        $site = $this->makeSite(['support_expires_at' => now()->subDays(10)]);
        $this->assertFalse($s->isInGracePeriod($site));
        $this->assertEquals('expired', $s->getStatus($site));
    }

    public function test_license_none(): void
    {
        $s = app(SupportLicenseService::class);
        $site = $this->makeSite(['support_expires_at' => null]);
        $this->assertFalse($s->isEligibleForUpdates($site));
        $this->assertNull($s->getEffectiveSupportEnd($site));
        $this->assertEquals('none', $s->getStatus($site));
    }

    public function test_license_level(): void
    {
        $s = app(SupportLicenseService::class);
        $this->assertEquals('basic', $s->getLevel($this->makeSite(['support_level' => 'basic'])));
        $this->assertEquals('pro', $s->getLevel($this->makeSite(['support_level' => 'pro'])));
        $this->assertEquals('enterprise', $s->getLevel($this->makeSite(['support_level' => 'enterprise'])));
        $this->assertEquals('basic', $s->getLevel($this->makeSite(['support_level' => 'invalid'])));
    }

    public function test_license_started(): void
    {
        $s = app(SupportLicenseService::class);
        $this->assertTrue($s->hasSupportStarted($this->makeSite(['support_starts_at' => now()->subDay()])));
        $this->assertFalse($s->hasSupportStarted($this->makeSite(['support_starts_at' => now()->addDay()])));
        $this->assertTrue($s->hasSupportStarted($this->makeSite(['support_starts_at' => null])));
    }

    public function test_license_can_access(): void
    {
        $s = app(SupportLicenseService::class);
        $this->assertTrue($s->canAccessSupport($this->makeSite(['support_expires_at' => now()->addDays(30), 'support_starts_at' => now()->subDay()])));
        $this->assertFalse($s->canAccessSupport($this->makeSite(['support_expires_at' => now()->addDays(30), 'support_starts_at' => now()->addDay()])));
        $this->assertFalse($s->canAccessSupport($this->makeSite(['support_expires_at' => now()->subDays(10), 'support_starts_at' => now()->subDay()])));
    }

    // ==================== SLA Service ====================

    public function test_sla_hours(): void
    {
        $s = app(SupportSlaService::class);
        $this->assertEquals(24, $s->getResponseHours('basic'));
        $this->assertEquals(72, $s->getResolutionHours('basic'));
        $this->assertEquals(12, $s->getResponseHours('pro'));
        $this->assertEquals(4, $s->getResponseHours('enterprise'));
    }

    public function test_sla_response_breach(): void
    {
        $s = app(SupportSlaService::class);
        $site = $this->makeSite();
        $t = SupportTicket::factory()->create(['site_id' => $site->id, 'created_at' => now()->subHours(48), 'first_response_at' => null, 'support_level' => 'basic']);
        $this->assertTrue($s->isResponseBreached($t));
        $t2 = SupportTicket::factory()->create(['site_id' => $site->id, 'created_at' => now()->subHours(48), 'first_response_at' => now()->subHours(10), 'support_level' => 'basic']);
        $this->assertFalse($s->isResponseBreached($t2));
    }

    public function test_sla_resolution_breach(): void
    {
        $s = app(SupportSlaService::class);
        $site = $this->makeSite();
        $t = SupportTicket::factory()->create(['site_id' => $site->id, 'created_at' => now()->subDays(10), 'status' => SupportTicket::STATUS_OPEN, 'support_level' => 'basic']);
        $this->assertTrue($s->isResolutionBreached($t));
        $t2 = SupportTicket::factory()->closed()->create(['site_id' => $site->id, 'created_at' => now()->subDays(10), 'support_level' => 'basic']);
        $this->assertFalse($s->isResolutionBreached($t2));
    }

    public function test_sla_any_breach(): void
    {
        $s = app(SupportSlaService::class);
        $site = $this->makeSite();
        $b = SupportTicket::factory()->create(['site_id' => $site->id, 'created_at' => now()->subDays(5), 'first_response_at' => null, 'support_level' => 'basic']);
        $this->assertTrue($s->hasAnyBreach($b));
        $o = SupportTicket::factory()->create(['site_id' => $site->id, 'created_at' => now()->subHours(5), 'first_response_at' => now()->subHours(2), 'support_level' => 'basic']);
        $this->assertFalse($s->hasAnyBreach($o));
    }

    public function test_sla_deadlines(): void
    {
        $s = app(SupportSlaService::class);
        $site = $this->makeSite();
        $t = SupportTicket::factory()->create(['site_id' => $site->id, 'created_at' => now()->subHours(10), 'support_level' => 'basic']);
        $this->assertInstanceOf(Carbon::class, $s->getResponseDeadline($t));
        $this->assertInstanceOf(Carbon::class, $s->getResolutionDeadline($t));
    }

    // ==================== Admin Controller ====================

    public function test_admin_ticket_index(): void
    {
        $admin = $this->admin();
        $site = $this->makeSite();
        SupportTicket::factory()->count(3)->create(['site_id' => $site->id]);
        $this->actingAs($admin)->get(route('admin.support-tickets.index'))->assertStatus(200);
        $this->actingAs($admin)->get(route('admin.support-tickets.index', ['status' => 'open']))->assertStatus(200);
    }

    public function test_admin_ticket_create_and_store(): void
    {
        $admin = $this->admin();
        $site = $this->makeSite();
        $this->actingAs($admin)->get(route('admin.support-tickets.create'))->assertStatus(200);
        $this->actingAs($admin)->post(route('admin.support-tickets.store'), [
            'site_id' => $site->id, 'subject' => 'issue', 'message' => 'details',
            'customer_email' => 'a@b.com', 'priority' => 'high',
        ])->assertRedirect(route('admin.support-tickets.index'));
        $this->assertDatabaseHas('support_tickets', ['subject' => 'issue']);
    }

    public function test_admin_ticket_show_and_status(): void
    {
        $admin = $this->admin();
        $site = $this->makeSite();
        $t = SupportTicket::factory()->create(['site_id' => $site->id]);
        $this->actingAs($admin)->get(route('admin.support-tickets.show', $t))->assertStatus(200)->assertSee($t->subject);
        $this->actingAs($admin)->patch(route('admin.support-tickets.update-status', $t), ['status' => 'closed'])->assertSessionHas('success');
        $t->refresh();
        $this->assertNotNull($t->closed_at);
        $this->actingAs($admin)->patch(route('admin.support-tickets.update-status', $t), ['status' => 'open'])->assertSessionHas('success');
        $t->refresh();
        $this->assertNull($t->resolved_at);
    }

    public function test_admin_ticket_reply(): void
    {
        $admin = $this->admin();
        $site = $this->makeSite();
        $t = SupportTicket::factory()->create(['site_id' => $site->id, 'status' => SupportTicket::STATUS_OPEN]);
        $this->actingAs($admin)->post(route('admin.support-tickets.replies.store', $t), ['message' => 'reply'])->assertSessionHas('success');
        $this->assertDatabaseHas('support_ticket_replies', ['message' => 'reply', 'author_type' => SupportTicketReply::AUTHOR_STAFF]);
        $t->refresh();
        $this->assertNotNull($t->first_response_at);
        $this->assertEquals(SupportTicket::STATUS_IN_PROGRESS, $t->status);
    }

    public function test_admin_ticket_assign(): void
    {
        $admin = $this->admin();
        $staff = User::factory()->create(['is_admin' => true]);
        $site = $this->makeSite();
        $t = SupportTicket::factory()->create(['site_id' => $site->id]);
        $this->actingAs($admin)->patch(route('admin.support-tickets.assign', $t), ['assigned_to' => $staff->id])->assertSessionHas('success');
        $t->refresh();
        $this->assertEquals($staff->id, $t->assigned_to);
        $this->actingAs($admin)->patch(route('admin.support-tickets.assign', $t), ['assigned_to' => ''])->assertSessionHas('success');
        $t->refresh();
        $this->assertNull($t->assigned_to);
    }

    public function test_unauthorized_user_blocked(): void
    {
        $user = User::factory()->create(['is_admin' => false]);
        $this->actingAs($user)->get(route('admin.support-tickets.index'))->assertStatus(403);
    }

    // ==================== Support Plans ====================

    public function test_support_plan_crud(): void
    {
        $admin = $this->admin();
        SupportPlan::factory()->count(2)->create();
        $this->actingAs($admin)->get(route('admin.support-plans.index'))->assertStatus(200);
        $this->actingAs($admin)->post(route('admin.support-plans.store'), [
            'title' => 'new plan', 'price' => 500000, 'duration_days' => 30, 'has_ticket' => '1', 'has_sms' => '0',
        ])->assertRedirect(route('admin.support-plans.index'));
        $this->assertDatabaseHas('support_plans', ['title' => 'new plan']);
        $plan = SupportPlan::where('title', 'new plan')->first();
        $this->actingAs($admin)->get(route('admin.support-plans.edit', $plan))->assertStatus(200);
        $this->actingAs($admin)->put(route('admin.support-plans.update', $plan), [
            'title' => 'updated', 'price' => 600000, 'duration_days' => 60, 'has_ticket' => '1', 'has_sms' => '1',
        ])->assertRedirect(route('admin.support-plans.index'));
        $plan->refresh();
        $this->assertEquals('updated', $plan->title);
        $this->assertEquals(600000, $plan->price);
        $this->actingAs($admin)->delete(route('admin.support-plans.destroy', $plan))->assertRedirect(route('admin.support-plans.index'));
        $this->assertModelMissing($plan);
    }

    // ==================== API ====================

    public function test_api_ticket_success(): void
    {
        $customer = Customer::create(['name' => 'api', 'email' => 'api@t.com', 'mobile' => '09111111111']);
        $site = Site::create(['customer_id' => $customer->id, 'title' => 'api site', 'domain' => 'api-site.com', 'is_active' => true]);
        $site->support_expires_at = now()->addDays(30);
        $site->support_starts_at = now()->subDay();
        $site->save();

        $key = 'TEST-KEY';
        PlatformLicense::create([
            'site_id' => $site->id, 'license_key_prefix' => 'T', 'license_key_hash' => hash('sha256', $key),
            'domain_allowed' => $site->domain, 'issued_at' => now(),
        ]);

        $r = $this->postJson(route('api.support-tickets.store'), [
            'license_key' => $key, 'domain' => $site->domain,
            'subject' => 'API', 'message' => 'msg', 'customer_email' => 'a@b.com',
        ]);
        $r->assertStatus(200)->assertJson(['success' => true]);
        $this->assertDatabaseHas('support_tickets', ['site_id' => $site->id, 'subject' => 'API']);
    }

    public function test_api_ticket_invalid_key(): void
    {
        $this->postJson(route('api.support-tickets.store'), [
            'license_key' => 'BAD', 'domain' => 'x.com', 'subject' => 't', 'message' => 'm', 'customer_email' => 'a@b.com',
        ])->assertStatus(403)->assertJson(['success' => false]);
    }

    public function test_api_ticket_expired_support(): void
    {
        $customer = Customer::create(['name' => 'exp', 'email' => 'e@t.com', 'mobile' => '09222222222']);
        $site = Site::create(['customer_id' => $customer->id, 'title' => 'exp site', 'domain' => 'exp.com', 'is_active' => true]);
        $site->support_expires_at = now()->subDays(30);
        $site->support_starts_at = now()->subYear();
        $site->save();

        $key = 'EXP-KEY';
        PlatformLicense::create([
            'site_id' => $site->id, 'license_key_prefix' => 'E', 'license_key_hash' => hash('sha256', $key),
            'domain_allowed' => $site->domain, 'issued_at' => now(),
        ]);

        $this->postJson(route('api.support-tickets.store'), [
            'license_key' => $key, 'domain' => $site->domain,
            'subject' => 't', 'message' => 'm', 'customer_email' => 'a@b.com',
        ])->assertStatus(403)->assertJson(['success' => false, 'error' => 'support_expired']);
    }
}

