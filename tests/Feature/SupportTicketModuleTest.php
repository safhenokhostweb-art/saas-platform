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
        // FINAL PRE-MEDIA BASELINE: SupportSlaService::isBusinessHoursEnabled()
        // defaults to true (config('support.business_hours.enabled', true))
        // — addBusinessHours() then only counts 09:00-17:00 weekdays, so a
        // ticket created exactly 48 real-world hours ago may not yet have
        // accumulated the 24 SLA hours needed to breach, depending on
        // which weekday/time the suite happens to run. This test's own
        // intent (per its name/assertions) is the raw threshold-crossing
        // logic, not business-hours calendar math — disabling it here
        // makes the fixture's plain 24x7 hour assumption correct again,
        // same as it always was before business-hours SLA calculation was
        // added.
        config(['support.business_hours.enabled' => false]);
        $s = app(SupportSlaService::class);
        $site = $this->makeSite();
        $t = SupportTicket::factory()->create(['site_id' => $site->id, 'created_at' => now()->subHours(48), 'first_response_at' => null, 'support_level' => 'basic']);
        $this->assertTrue($s->isResponseBreached($t));
        $t2 = SupportTicket::factory()->create(['site_id' => $site->id, 'created_at' => now()->subHours(48), 'first_response_at' => now()->subHours(10), 'support_level' => 'basic']);
        $this->assertFalse($s->isResponseBreached($t2));
    }

    public function test_sla_resolution_breach(): void
    {
        // See test_sla_response_breach()'s comment — same business-hours
        // default.
        config(['support.business_hours.enabled' => false]);
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
    //
    // FINAL PRE-MEDIA BASELINE: these 5 tests previously exercised
    // App\Http\Controllers\Admin\SupportTicketController directly, asserting
    // 200/session-flash responses. That controller was removed from active
    // routing under PHASE 7.2 (routes/web.php:587-595, present since commit
    // 2d5017e "Phase EDU", months before this baseline pass) — every
    // admin.support-tickets.* route is now a permanent 301 redirect to the
    // canonical SaaSPlatform\Modules\Tickets admin module
    // (packages/saas-platform/modules/tickets/Routes/admin.php, tickets.admin.*),
    // which operates on an entirely different Ticket model, not SupportTicket.
    // The old assertions could never pass — not a regression, a stale
    // expectation against intentionally decommissioned code. Replaced with a
    // single test asserting the actual, current, intentional contract: the
    // legacy routes permanently redirect to their canonical replacements.
    // Coverage of the canonical admin ticket CRUD flow itself belongs to the
    // Tickets module's own test suite, not this legacy-model file.
    public function test_admin_legacy_support_ticket_routes_redirect_to_canonical(): void
    {
        $admin = $this->admin();
        $site = $this->makeSite();
        $t = SupportTicket::factory()->create(['site_id' => $site->id]);

        $this->actingAs($admin)->get(route('admin.support-tickets.index'))
            ->assertRedirect(route('tickets.admin.index'))->assertStatus(301);
        $this->actingAs($admin)->get(route('admin.support-tickets.create'))
            ->assertRedirect(route('tickets.admin.create'))->assertStatus(301);
        $this->actingAs($admin)->post(route('admin.support-tickets.store'))
            ->assertRedirect(route('tickets.admin.store'))->assertStatus(301);
        $this->actingAs($admin)->get(route('admin.support-tickets.show', $t))
            ->assertRedirect(route('tickets.admin.show', $t->id))->assertStatus(301);
        $this->actingAs($admin)->patch(route('admin.support-tickets.update-status', $t))
            ->assertRedirect(route('tickets.admin.status.update', $t->id))->assertStatus(301);
        $this->actingAs($admin)->post(route('admin.support-tickets.replies.store', $t))
            ->assertRedirect(route('tickets.admin.replies.store', $t->id))->assertStatus(301);
        $this->actingAs($admin)->patch(route('admin.support-tickets.assign', $t))
            ->assertRedirect(route('tickets.admin.assign', $t->id))->assertStatus(301);
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

        // SAAS ZERO-FAILURE PUNCH LIST: LicenseServerService::findByKey()
        // requires a real key (>= 12 chars after whitespace-stripping) and
        // derives license_key_prefix itself — uppercase, non-alnum
        // stripped, first 8 chars — which 'TEST-KEY'/'T' never matched
        // (findByKey() always returned null, "invalid_key", regardless of
        // how the key reached the middleware). All-uppercase-alnum, no
        // separators, avoids any mismatch between the stored prefix and
        // the one findByKey() computes from the raw key.
        $key = 'TESTKEY12345';
        PlatformLicense::create([
            'site_id' => $site->id, 'license_key_prefix' => substr($key, 0, 8), 'license_key_hash' => hash('sha256', $key),
            'domain_allowed' => $site->domain, 'issued_at' => now(),
        ]);

        // FINAL PRE-MEDIA BASELINE: real Bearer-header auth
        // (AuthenticateTenantApi's documented, non-deprecated Priority 1
        // path) plus `customer_mobile` (StoreSupportTicketApiRequest's real
        // required field). SupportPortalService now resolves site identity
        // solely from SiteContextContract (established by this middleware)
        // instead of re-deriving it from a raw license_key — see that
        // class's own docblock for the full single-authority-convergence
        // rationale and caller audit. `license_key` in the body below is
        // still accepted (nullable, backward-compatible) but no longer
        // required or consulted for site resolution.
        $r = $this->withHeaders(['Authorization' => 'Bearer ' . $key])->postJson(route('api.support-tickets.store'), [
            'license_key' => $key, 'domain' => $site->domain,
            'subject' => 'API', 'message' => 'msg', 'customer_mobile' => '09111111111',
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

        $key = 'EXPKEY123456';
        PlatformLicense::create([
            'site_id' => $site->id, 'license_key_prefix' => substr($key, 0, 8), 'license_key_hash' => hash('sha256', $key),
            'domain_allowed' => $site->domain, 'issued_at' => now(),
        ]);

        // See test_support_ticket_api_valid_license()'s comment: real
        // Bearer-header auth (the middleware's non-deprecated Priority 1
        // path), and customer_mobile (the request's real required field).
        $this->withHeaders(['Authorization' => 'Bearer ' . $key])->postJson(route('api.support-tickets.store'), [
            'license_key' => $key, 'domain' => $site->domain,
            'subject' => 't', 'message' => 'm', 'customer_mobile' => '09222222222',
        ])->assertStatus(403)->assertJson(['success' => false, 'error' => 'support_expired']);
    }
}

