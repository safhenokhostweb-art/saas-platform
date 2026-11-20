<?php

namespace Tests\Feature\Onboarding;

use App\Jobs\CustomerInstallationPipelineJob;
use App\Models\CustomerInstallation;
use App\Models\CustomerRemoteServer;
use App\Models\Infrastructure\DnsProviderSetting;
use App\Services\CustomerInstallation\Dns\DnsLookup;
use App\Services\CustomerInstallation\Dns\DnsResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\Support\FakeDnsResolver;
use Tests\TestCase;

/**
 * 2026-11-17, installation 50: the wizard accepted solar.safhenokhost.ir and
 * the pipeline only failed at ISSUE_SSL. The wizard now runs the same DNS
 * preflight as VALIDATE_DOMAIN_DNS on submit, against the chosen server.
 */
class OnboardingDnsPreflightTest extends TestCase
{
    use OnboardingFixtures;
    use RefreshDatabase;

    private const SERVER_IP = '87.107.12.81';

    protected function setUp(): void
    {
        parent::setUp();
        // InstallationTargetGuard::packageError() (17648113) refuses to start
        // without an active install package; the wizard GET used to fabricate one.
        \App\Models\CustomerInstallationPackage::create([
            'version' => '1.1.0', 'arvan_key' => 'packages/customer-site-v1.1.0.zip',
            'sha256' => str_repeat('a', 64), 'size_bytes' => 1024, 'is_active' => true, 'released_at' => now(),
        ]);
        Queue::fake();
        $this->platformRootDomain();
    }

    private function publicServer(string $host = self::SERVER_IP): CustomerRemoteServer
    {
        return CustomerRemoteServer::create([
            'label' => 'Prod', 'type' => 'fake', 'host' => $host, 'port' => 22, 'username' => 'deploy', 'auth_method' => 'key',
        ]);
    }

    private function dns(FakeDnsResolver $dns): void
    {
        $this->app->instance(DnsResolver::class, $dns);
    }

    private function submitCustom(string $domain, CustomerRemoteServer $server, bool $ssl = true)
    {
        return $this->actingAs($this->admin())->post(route('admin.onboarding.store'), $this->submission($server, [
            'domain_mode' => 'custom', 'domain' => $domain, 'ssl_enabled' => $ssl ? 1 : 0,
        ]));
    }

    public function test_unregistered_custom_domain_is_built_in_the_awaiting_dns_state(): void
    {
        $this->dns(new FakeDnsResolver(DnsLookup::notFound()));

        $this->submitCustom('solar-shop.ir', $this->publicServer())
            ->assertSessionHasNoErrors()
            ->assertSessionHas('warning', fn (string $w) => str_contains($w, 'در انتظار DNS'));

        $installation = CustomerInstallation::query()->sole();
        $this->assertSame('solar-shop.ir', $installation->customer_domain);
        $this->assertTrue($installation->request_payload['dns_pending']);
        Queue::assertPushed(CustomerInstallationPipelineJob::class);
    }

    public function test_custom_domain_pointing_to_another_ip_is_built_in_the_awaiting_dns_state(): void
    {
        $this->dns((new FakeDnsResolver(DnsLookup::notFound()))->set('solar-shop.ir', DnsLookup::resolved(['205.252.135.188'])));

        $this->submitCustom('solar-shop.ir', $this->publicServer())->assertSessionHasNoErrors();

        $this->assertTrue(CustomerInstallation::query()->sole()->request_payload['dns_pending']);
    }

    public function test_custom_domain_with_a_foreign_aaaa_record_is_built_in_the_awaiting_dns_state(): void
    {
        $this->dns((new FakeDnsResolver(DnsLookup::notFound()))
            ->set('solar-shop.ir', DnsLookup::resolved([self::SERVER_IP]))
            ->setAaaa('solar-shop.ir', DnsLookup::resolved(['2001:db8::1'])));

        $this->submitCustom('solar-shop.ir', $this->publicServer())->assertSessionHasNoErrors();

        $this->assertTrue(CustomerInstallation::query()->sole()->request_payload['dns_pending']);
    }

    public function test_a_connected_custom_domain_is_not_marked_pending(): void
    {
        $this->dns((new FakeDnsResolver(DnsLookup::notFound()))->set('solar-shop.ir', DnsLookup::resolved([self::SERVER_IP])));

        $this->submitCustom('solar-shop.ir', $this->publicServer())->assertSessionHasNoErrors()->assertSessionMissing('warning');

        $this->assertFalse(CustomerInstallation::query()->sole()->request_payload['dns_pending']);
    }

    public function test_step5_dns_check_reports_apex_and_www_against_the_chosen_server(): void
    {
        $server = $this->publicServer();
        $this->dns((new FakeDnsResolver(DnsLookup::notFound()))
            ->set('solar-shop.ir', DnsLookup::resolved([self::SERVER_IP]))
            ->set('www.solar-shop.ir', DnsLookup::resolved(['5.6.7.8'])));

        $json = $this->actingAs($this->admin())
            ->getJson(route('admin.onboarding.check-dns', ['mode' => 'custom', 'domain' => 'www.solar-shop.ir', 'server_id' => $server->id, 'ssl' => 1]))
            ->assertOk()->json();

        $this->assertTrue($json['ok']);
        $this->assertSame(self::SERVER_IP, $json['server_ip']);
        $this->assertSame(['@', 'www'], array_column($json['records'], 'name'));
        $this->assertSame([true, false], array_column($json['records'], 'ok'));
        $this->assertSame(['5.6.7.8'], $json['records'][1]['resolved']);
    }

    public function test_wizard_options_expose_the_servers_public_ip_for_the_dns_guide(): void
    {
        $server = $this->publicServer();

        $servers = app(\App\Services\Onboarding\SiteOnboardingService::class)->wizardOptions()['servers'];

        $this->assertSame(self::SERVER_IP, collect($servers)->firstWhere('id', $server->id)['public_ip']);
    }

    public function test_custom_domain_pointing_to_the_server_starts_the_pipeline(): void
    {
        $this->dns((new FakeDnsResolver(DnsLookup::notFound()))->set('solar-shop.ir', DnsLookup::resolved([self::SERVER_IP])));

        $this->submitCustom('solar-shop.ir', $this->publicServer())->assertSessionHasNoErrors();

        $this->assertSame('solar-shop.ir', CustomerInstallation::query()->sole()->customer_domain);
        Queue::assertPushed(CustomerInstallationPipelineJob::class);
    }

    public function test_new_platform_subdomain_is_accepted_without_a_public_lookup(): void
    {
        DnsProviderSetting::query()->update(['api_token_encrypted' => 'token-present']);
        $dns = new FakeDnsResolver(DnsLookup::notFound());
        $this->dns($dns);

        $this->actingAs($this->admin())->post(route('admin.onboarding.store'), $this->submission($this->publicServer()))
            ->assertSessionHasNoErrors();

        $this->assertSame('khorshid.' . self::ROOT, CustomerInstallation::query()->sole()->customer_domain);
        $this->assertSame([], $dns->queries, 'CONFIGURE_DNS creates and verifies the record; no negative answer may be cached first');
    }

    public function test_platform_subdomain_on_a_server_without_a_public_host_is_left_to_the_pipeline_stage(): void
    {
        DnsProviderSetting::query()->update(['api_token_encrypted' => 'token-present']);
        $this->dns(new FakeDnsResolver(DnsLookup::notFound()));

        $this->actingAs($this->admin())->post(route('admin.onboarding.store'), $this->submission($this->publicServer('10.0.0.9')))
            ->assertSessionHasNoErrors();
    }

    public function test_live_domain_check_warns_but_accepts_a_wildcard_only_custom_subdomain(): void
    {
        $this->dns((new FakeDnsResolver(DnsLookup::notFound()))->setWildcard('example.ir', DnsLookup::resolved(['5.6.7.8'])));

        $json = $this->actingAs($this->admin())
            ->getJson(route('admin.onboarding.check-domain', ['mode' => 'custom', 'domain' => 'solar.example.ir']))
            ->json();

        $this->assertTrue($json['ok']);
        $this->assertTrue($json['dns_pending']);
        $this->assertNull($json['error']);
        $this->assertStringContainsString('Wildcard', $json['warning']);
    }

    public function test_live_domain_check_still_rejects_an_invalid_custom_domain(): void
    {
        $json = $this->actingAs($this->admin())
            ->getJson(route('admin.onboarding.check-domain', ['mode' => 'custom', 'domain' => 'not a domain']))
            ->json();

        $this->assertFalse($json['ok']);
        $this->assertFalse($json['dns_pending']);
    }
}
