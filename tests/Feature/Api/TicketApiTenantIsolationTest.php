<?php

namespace Tests\Feature\Api;

use App\Models\Customer;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use SaaSPlatform\Modules\Tickets\Models\Ticket;
use Tests\TestCase;

/**
 * TICKET-API-TENANT-01 regression coverage.
 *
 * Before this fix, every ID-based action in TicketApiController resolved
 * tickets via Ticket::withoutTenantIsolation()->find()/findOrFail() (an
 * intentional "admin-context" lookup meant only for platform staff) but
 * never actually called the pre-existing, already-registered TicketPolicy
 * (SaaSPlatform\Modules\Tickets\Policies\TicketPolicy, bound in
 * TicketsServiceProvider) to check whether the *authenticated caller* was
 * allowed to see that specific ticket. Any authenticated sanctum user —
 * including an ordinary tenant site-admin token issued by
 * MobileAuthController — could therefore read, reply to, vote on, or
 * change the status of any OTHER tenant's ticket by ID.
 *
 * The fix wires Gate::authorize() using the existing TicketPolicy into
 * every action, and force-scopes index()/stats() to the caller's own
 * site_id for non-platform-admin callers. No second authorization system
 * was introduced — TicketPolicy already had the correct
 * platform-admin-sees-all / site-admin-sees-own-site logic; it just was
 * never invoked.
 */
class TicketApiTenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    private function makeSite(string $title): Site
    {
        $customer = Customer::create([
            'name'   => $title . ' Customer',
            'mobile' => '09' . str_pad((string) random_int(0, 999999999), 9, '0', STR_PAD_LEFT),
        ]);

        return Site::create(['customer_id' => $customer->id, 'title' => $title]);
    }

    private function makeSiteAdmin(Site $site): User
    {
        return User::factory()->create([
            'is_admin'      => false,
            'is_site_admin' => true,
            'site_id'       => $site->id,
        ]);
    }

    private function makePlatformAdmin(): User
    {
        return User::factory()->create([
            'is_admin'      => true,
            'is_site_admin' => false,
        ]);
    }

    private function makeTicket(Site $site, array $overrides = []): Ticket
    {
        return Ticket::withoutTenantIsolation()->create(array_merge([
            'ticket_number' => 'T-' . uniqid(),
            'subject'       => 'Test ticket',
            'description'   => 'Test description',
            'site_id'       => $site->id,
        ], $overrides));
    }

    public function test_site_admin_can_read_own_site_ticket(): void
    {
        $siteA = $this->makeSite('Site A');
        $admin = $this->makeSiteAdmin($siteA);
        $ticket = $this->makeTicket($siteA);

        $response = $this->actingAs($admin, 'sanctum')->getJson("/api/tickets/{$ticket->id}");

        $response->assertOk();
        $response->assertJsonPath('data.id', $ticket->id);
    }

    public function test_site_admin_cannot_read_other_site_ticket(): void
    {
        $siteA = $this->makeSite('Site A');
        $siteB = $this->makeSite('Site B');
        $admin = $this->makeSiteAdmin($siteA);
        $ticket = $this->makeTicket($siteB);

        $response = $this->actingAs($admin, 'sanctum')->getJson("/api/tickets/{$ticket->id}");

        $response->assertStatus(403);
    }

    public function test_unknown_ticket_id_returns_not_found_not_forbidden(): void
    {
        $siteA = $this->makeSite('Site A');
        $admin = $this->makeSiteAdmin($siteA);

        $response = $this->actingAs($admin, 'sanctum')->getJson('/api/tickets/999999');

        $response->assertStatus(404);
    }

    public function test_platform_admin_can_read_any_site_ticket(): void
    {
        $siteB = $this->makeSite('Site B');
        $platformAdmin = $this->makePlatformAdmin();
        $ticket = $this->makeTicket($siteB);

        $response = $this->actingAs($platformAdmin, 'sanctum')->getJson("/api/tickets/{$ticket->id}");

        $response->assertOk();
        $response->assertJsonPath('data.id', $ticket->id);
    }

    public function test_site_admin_cannot_reply_to_other_site_ticket(): void
    {
        $siteA = $this->makeSite('Site A');
        $siteB = $this->makeSite('Site B');
        $admin = $this->makeSiteAdmin($siteA);
        $ticket = $this->makeTicket($siteB);

        $response = $this->actingAs($admin, 'sanctum')->postJson("/api/tickets/{$ticket->id}/replies", [
            'message' => 'cross tenant reply attempt',
        ]);

        $response->assertStatus(403);
        $this->assertDatabaseMissing('ticket_replies', ['ticket_id' => $ticket->id]);
    }

    public function test_site_admin_can_reply_to_own_site_ticket(): void
    {
        $siteA = $this->makeSite('Site A');
        $admin = $this->makeSiteAdmin($siteA);
        $ticket = $this->makeTicket($siteA);

        $response = $this->actingAs($admin, 'sanctum')->postJson("/api/tickets/{$ticket->id}/replies", [
            'message' => 'legit reply',
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('ticket_replies', ['ticket_id' => $ticket->id, 'message' => 'legit reply']);
    }

    public function test_site_admin_cannot_vote_on_other_site_ticket(): void
    {
        $siteA = $this->makeSite('Site A');
        $siteB = $this->makeSite('Site B');
        $admin = $this->makeSiteAdmin($siteA);
        $ticket = $this->makeTicket($siteB, ['type' => Ticket::TYPE_FEATURE_REQUEST]);

        $response = $this->actingAs($admin, 'sanctum')->postJson("/api/tickets/{$ticket->id}/vote");

        $response->assertStatus(403);
        $this->assertDatabaseMissing('ticket_votes', ['ticket_id' => $ticket->id]);
    }

    public function test_site_admin_cannot_change_status_even_of_own_site_ticket(): void
    {
        // updateStatus is admin-only per the existing, unchanged TicketPolicy
        // (checks $user->is_admin only). This documents pre-existing policy
        // behavior, not a new restriction introduced by this fix.
        $siteA = $this->makeSite('Site A');
        $admin = $this->makeSiteAdmin($siteA);
        $ticket = $this->makeTicket($siteA);

        $response = $this->actingAs($admin, 'sanctum')->patchJson("/api/tickets/{$ticket->id}/status", [
            'status' => 'closed',
        ]);

        $response->assertStatus(403);
    }

    public function test_platform_admin_can_change_status_of_any_ticket(): void
    {
        $siteB = $this->makeSite('Site B');
        $platformAdmin = $this->makePlatformAdmin();
        $ticket = $this->makeTicket($siteB);

        $response = $this->actingAs($platformAdmin, 'sanctum')->patchJson("/api/tickets/{$ticket->id}/status", [
            'status' => 'closed',
        ]);

        $response->assertOk();
        $this->assertSame('closed', $ticket->fresh()->status);
    }

    public function test_index_ignores_client_supplied_site_id_for_non_admin_caller(): void
    {
        $siteA = $this->makeSite('Site A');
        $siteB = $this->makeSite('Site B');
        $admin = $this->makeSiteAdmin($siteA);
        $ticketA = $this->makeTicket($siteA);
        $ticketB = $this->makeTicket($siteB);

        $response = $this->actingAs($admin, 'sanctum')->getJson('/api/tickets?site_id=' . $siteB->id);

        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertContains($ticketA->id, $ids);
        $this->assertNotContains($ticketB->id, $ids);
    }

    public function test_index_returns_all_sites_for_platform_admin(): void
    {
        $siteA = $this->makeSite('Site A');
        $siteB = $this->makeSite('Site B');
        $platformAdmin = $this->makePlatformAdmin();
        $ticketA = $this->makeTicket($siteA);
        $ticketB = $this->makeTicket($siteB);

        $response = $this->actingAs($platformAdmin, 'sanctum')->getJson('/api/tickets');

        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertContains($ticketA->id, $ids);
        $this->assertContains($ticketB->id, $ids);
    }
}
