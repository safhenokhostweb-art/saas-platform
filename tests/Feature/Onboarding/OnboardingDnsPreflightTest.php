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

    public function test_unregistered_custom_domain_is_rejected_before_anything_is_created(): void
    {
        $this->dns(new FakeDnsResolver(DnsLookup::notFound()));

        $this->submitCustom('solar-shop.ir', $this->publicServer())
            ->assertSessionHasErrors(['domain' => 'دامنه یا زیردامنهٔ «solar-shop.ir» هنوز در DNS ثبت نشده است (هیچ رکورد A یا CNAME معتبری ندارد). ابتدا رکورد A آن را به IP این سرور (87.107.12.81) تنظیم کنید و پس از انتشار DNS دوباره تلاش کنید.']);

        $this->assertSame(0, CustomerInstallation::query()->count());
        Queue::assertNotPushed(CustomerInstallationPipelineJob::class);
    }

    public function test_custom_domain_pointing_to_another_ip_is_rejected_when_ssl_is_requested(): void
    {
        $this->dns((new FakeDnsResolver(DnsLookup::notFound()))->set('solar-shop.ir', DnsLookup::resolved(['205.252.135.188'])));

        $response = $this->submitCustom('solar-shop.ir', $this->publicServer());

        $response->assertSessionHasErrors('domain');
        $this->assertStringContainsString('به IP این سرور متصل نیست', session('errors')->first('domain'));
        $this->assertSame(0, CustomerInstallation::query()->count());
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

    public function test_live_domain_check_flags_a_wildcard_only_custom_subdomain(): void
    {
        $this->dns((new FakeDnsResolver(DnsLookup::notFound()))->setWildcard('example.ir', DnsLookup::resolved(['5.6.7.8'])));

        $json = $this->actingAs($this->admin())
            ->getJson(route('admin.onboarding.check-domain', ['mode' => 'custom', 'domain' => 'solar.example.ir']))
            ->json();

        $this->assertFalse($json['ok']);
        $this->assertStringContainsString('Wildcard', $json['error']);
    }
}
