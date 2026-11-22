<?php

namespace Tests\Unit\Services\CustomerInstallation\Stages;

use App\Models\CustomerInstallation;
use App\Models\CustomerRemoteServer;
use App\Models\Infrastructure\DnsProviderSetting;
use App\Services\CustomerInstallation\CredentialVault;
use App\Services\CustomerInstallation\Dns\DnsLookup;
use App\Services\CustomerInstallation\Dns\DnsResolver;
use App\Services\CustomerInstallation\Drivers\FakeRemoteDriver;
use App\Services\CustomerInstallation\Dto\CustomerInstallRequest;
use App\Services\CustomerInstallation\Dto\InstallationContext;
use App\Services\CustomerInstallation\Stages\ConfigureDnsStage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\Support\FakeDnsResolver;
use Tests\TestCase;

/**
 * Installation 50 (2026-11-17): the A record for solar.safhenokhost.ir was
 * created with the server's outbound NAT address 205.252.135.188 (from
 * `curl api.ipify.org`), so ISSUE_SSL timed out 18 stages later.
 */
class ConfigureDnsStageTest extends TestCase
{
    use RefreshDatabase;

    private const SERVER_IP = '87.107.12.81';
    private const FQDN = 'solar.safhenokhost.ir';

    protected function setUp(): void
    {
        parent::setUp();
        $key = base64_encode(random_bytes(32));
        putenv("INSTALLER_MASTER_KEY={$key}");
        $_ENV['INSTALLER_MASTER_KEY'] = $key;
        config(['customer_installation.dns.propagation_max_wait_sec' => 30, 'customer_installation.dns.propagation_poll_sec' => 15]);

        DnsProviderSetting::create([
            'provider' => 'arvan', 'root_domain' => 'safhenokhost.ir', 'is_active' => true, 'cloud_proxy' => false,
            'api_token_encrypted' => (new CredentialVault())->encrypt('arvan-token'),
        ]);
    }

    private function fakeArvan(): void
    {
        Http::fake([
            '*/dns-records?*' => Http::response(['data' => []]),
            '*/dns-records/*' => Http::response(['data' => []]),
            '*/dns-records'   => Http::response(['data' => ['id' => 'rec-new-1']], 201),
        ]);
    }

    private function stage(FakeDnsResolver $dns, array &$sleeps = []): ConfigureDnsStage
    {
        $this->app->instance(DnsResolver::class, $dns);

        return $this->app->make(ConfigureDnsStage::class, ['sleeper' => function (int $s) use (&$sleeps) { $sleeps[] = $s; }]);
    }

    private function context(FakeRemoteDriver $driver, bool $ssl = true): InstallationContext
    {
        $server = CustomerRemoteServer::create([
            'label' => 'S', 'type' => 'ssh', 'host' => self::SERVER_IP, 'port' => 22, 'username' => 'root', 'auth_method' => 'key',
        ]);
        $payload = [
            'company' => 'C', 'owner_name' => 'O', 'email' => '', 'phone' => '09120000000', 'language' => 'fa',
            'timezone' => 'Asia/Tehran', 'currency' => 'IRR', 'domain' => self::FQDN, 'license_type' => 'basic',
            'theme_slug' => '', 'modules' => [], 'backup_policy' => 'daily', 'smtp' => [], 'ssl_enabled' => $ssl,
            'remote_server_id' => $server->id, 'install_mode' => 'MODE_FRESH',
        ];
        $installation = CustomerInstallation::create([
            'remote_server_id' => $server->id, 'installer_user_id' => 1, 'install_mode' => 'MODE_FRESH', 'status' => 'running',
            'customer_company' => 'C', 'customer_email' => '', 'customer_domain' => self::FQDN, 'request_payload' => $payload,
        ]);

        return new InstallationContext($installation, CustomerInstallRequest::fromArray($payload), $driver);
    }

    public function test_record_targets_the_inbound_server_ip_not_the_outbound_nat_address(): void
    {
        $this->fakeArvan();
        $driver = new FakeRemoteDriver();
        $driver->addResponse('api.ipify.org', 0, '205.252.135.188');
        $dns = (new FakeDnsResolver(DnsLookup::notFound()))->set(self::FQDN, DnsLookup::resolved([self::SERVER_IP]));

        $result = $this->stage($dns)->execute($this->context($driver));

        $this->assertTrue($result->success, (string) $result->error);
        $this->assertSame(self::SERVER_IP, $result->output['server_ip']);
        $this->assertTrue($result->output['dns_propagated']);
        Http::assertSent(fn (Request $r) => $r->method() === 'POST'
            && str_ends_with($r->url(), '/safhenokhost.ir/dns-records')
            && $r['name'] === 'solar'
            && $r['value'][0]['ip'] === self::SERVER_IP);
        Http::assertNotSent(fn (Request $r) => str_contains(json_encode($r->data()), '205.252.135.188'));
        $this->assertEmpty(array_filter($driver->getCommandLog(), fn ($c) => str_contains($c, 'ipify')));
    }

    public function test_unpropagated_record_with_ssl_fails_early_and_deletes_the_record_it_created(): void
    {
        $this->fakeArvan();
        $dns = (new FakeDnsResolver(DnsLookup::notFound()))->set(self::FQDN, DnsLookup::resolved(['205.252.135.188']));
        $sleeps = [];

        $result = $this->stage($dns, $sleeps)->execute($this->context(new FakeRemoteDriver()));

        $this->assertFalse($result->success);
        $this->assertStringContainsString('به IP این سرور (87.107.12.81) نرسید', $result->error);
        $this->assertStringContainsString('205.252.135.188', $result->error);
        $this->assertSame([15, 15], $sleeps, '30s / 15s = 3 lookups, 2 waits');
        $this->assertCount(3, array_filter($dns->queries, fn ($q) => $q === self::FQDN));
        Http::assertSent(fn (Request $r) => $r->method() === 'DELETE' && str_ends_with($r->url(), '/dns-records/rec-new-1'));
    }

    public function test_unpropagated_record_without_ssl_continues_as_before(): void
    {
        $this->fakeArvan();
        $dns = new FakeDnsResolver(DnsLookup::notFound());

        $result = $this->stage($dns)->execute($this->context(new FakeRemoteDriver(), ssl: false));

        $this->assertTrue($result->success, (string) $result->error);
        $this->assertFalse($result->output['dns_propagated']);
        $this->assertSame('rec-new-1', $result->rollbackSnapshot['record_id']);
        Http::assertNotSent(fn (Request $r) => $r->method() === 'DELETE');
    }

    public function test_propagation_succeeds_once_the_name_reaches_the_server(): void
    {
        $this->fakeArvan();
        $dns = (new FakeDnsResolver(DnsLookup::notFound()))
            ->set(self::FQDN, [DnsLookup::notFound(), DnsLookup::resolved([self::SERVER_IP])]);
        $sleeps = [];

        $result = $this->stage($dns, $sleeps)->execute($this->context(new FakeRemoteDriver()));

        $this->assertTrue($result->success, (string) $result->error);
        $this->assertTrue($result->output['dns_propagated']);
        $this->assertSame([15], $sleeps);
    }

    public function test_existing_record_with_same_ip_is_idempotent_and_does_not_call_post_or_put(): void
    {
        Http::fake([
            '*/dns-records?*' => Http::response([
                'data' => [
                    [
                        'id' => 'rec-existing-1',
                        'type' => 'A',
                        'name' => 'solar',
                        'value' => [['ip' => self::SERVER_IP]],
                    ],
                ],
            ]),
        ]);

        $dns = (new FakeDnsResolver(DnsLookup::notFound()))->set(self::FQDN, DnsLookup::resolved([self::SERVER_IP]));
        $result = $this->stage($dns)->execute($this->context(new FakeRemoteDriver()));

        $this->assertTrue($result->success, (string) $result->error);
        $this->assertSame('rec-existing-1', $result->output['record_id']);
        Http::assertNotSent(fn (Request $r) => $r->method() === 'POST' && str_contains($r->url(), 'dns-records'));
        Http::assertNotSent(fn (Request $r) => $r->method() === 'PUT' && str_contains($r->url(), 'dns-records'));
    }

    public function test_existing_record_with_different_ip_triggers_update(): void
    {
        Http::fake([
            '*/dns-records?*' => Http::response([
                'data' => [
                    [
                        'id' => 'rec-existing-2',
                        'type' => 'A',
                        'name' => 'solar',
                        'value' => [['ip' => '1.2.3.4']],
                    ],
                ],
            ]),
            '*/dns-records/rec-existing-2' => Http::response(['data' => ['id' => 'rec-existing-2']], 200),
        ]);

        $dns = (new FakeDnsResolver(DnsLookup::notFound()))->set(self::FQDN, DnsLookup::resolved([self::SERVER_IP]));
        $result = $this->stage($dns)->execute($this->context(new FakeRemoteDriver()));

        $this->assertTrue($result->success, (string) $result->error);
        $this->assertSame('rec-existing-2', $result->output['record_id']);
        Http::assertSent(fn (Request $r) => $r->method() === 'PUT'
            && str_ends_with($r->url(), '/dns-records/rec-existing-2')
            && $r['value'][0]['ip'] === self::SERVER_IP);
    }

    public function test_create_collision_422_recovers_gracefully_and_updates_or_accepts(): void
    {
        // First GET returns empty, POST returns 422 "The provided data is invalid"
        // Then fallback findRecordDetails GET returns the existing record
        Http::fake([
            '*/dns-records?*page=1*' => Http::sequence()
                ->push(['data' => []]) // Initial check: nothing found
                ->push([               // Recovery check: found!
                    'data' => [
                        [
                            'id' => 'rec-collided-1',
                            'type' => 'A',
                            'name' => 'solar',
                            'value' => [['ip' => '1.2.3.4']],
                        ],
                    ],
                ]),
            '*/dns-records' => Http::response(['message' => 'The provided data is invalid'], 422),
            '*/dns-records/rec-collided-1' => Http::response(['data' => ['id' => 'rec-collided-1']], 200),
        ]);

        $dns = (new FakeDnsResolver(DnsLookup::notFound()))->set(self::FQDN, DnsLookup::resolved([self::SERVER_IP]));
        $result = $this->stage($dns)->execute($this->context(new FakeRemoteDriver()));

        $this->assertTrue($result->success, (string) $result->error);
        $this->assertSame('rec-collided-1', $result->output['record_id']);
        Http::assertSent(fn (Request $r) => $r->method() === 'PUT' && str_ends_with($r->url(), '/dns-records/rec-collided-1'));
    }
}
