<?php

namespace Tests\Feature\Domains;

use App\Jobs\SiteDomainJob;
use App\Models\CustomerInstallation;
use App\Models\CustomerInstallationLog;
use App\Models\CustomerRemoteServer;
use App\Models\PlatformLicense;
use App\Models\Site;
use App\Models\SiteDomain;
use App\Services\CustomerInstallation\Contracts\RemoteConnectionInterface;
use App\Services\CustomerInstallation\Dns\DnsLookup;
use App\Services\CustomerInstallation\Dns\DnsResolver;
use App\Services\CustomerInstallation\Drivers\FakeRemoteDriver;
use App\Services\CustomerInstallation\RemoteConnectionService;
use App\Services\Domains\SiteDomainProvisioner;
use App\Services\Domains\SiteDomainService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;
use Tests\Feature\Onboarding\OnboardingFixtures;
use Tests\Support\FakeDnsResolver;
use Tests\TestCase;

/**
 * Phase 3 — the site domain panel: state recorded from the installation,
 * DNS checks, the queued SSL / domain-change job and its remote steps.
 */
class SiteDomainPanelTest extends TestCase
{
    use OnboardingFixtures;
    use RefreshDatabase;

    private const IP = '87.107.12.81';

    private FakeDnsResolver $dns;

    protected function setUp(): void
    {
        parent::setUp();
        $key = base64_encode(random_bytes(32));
        putenv("INSTALLER_MASTER_KEY={$key}");
        $_ENV['INSTALLER_MASTER_KEY'] = $key;
        $this->dns = new FakeDnsResolver(DnsLookup::notFound());
        $this->app->instance(DnsResolver::class, $this->dns);
    }

    /** @return array{0: Site, 1: CustomerInstallation} */
    private function installedSite(string $domain = 'shop.ir', bool $dnsPending = false): array
    {
        $server = CustomerRemoteServer::create([
            'label' => 'Prod', 'type' => 'fake', 'host' => self::IP, 'port' => 22, 'username' => 'deploy', 'auth_method' => 'key',
        ]);
        $site = Site::create(['customer_id' => $this->customer()->id, 'title' => 'Shop', 'domain' => $domain]);
        $payload = [
            'company' => 'Shop Co', 'owner_name' => 'O', 'email' => '', 'phone' => '09120000000', 'language' => 'fa',
            'timezone' => 'Asia/Tehran', 'currency' => 'IRR', 'domain' => $domain, 'license_type' => 'basic',
            'theme_slug' => '', 'modules' => [], 'backup_policy' => 'daily', 'smtp' => [], 'ssl_enabled' => true,
            'remote_server_id' => $server->id, 'install_mode' => 'MODE_FRESH', 'dns_pending' => $dnsPending,
        ];
        $installation = CustomerInstallation::create([
            'remote_server_id' => $server->id, 'installer_user_id' => 1, 'install_mode' => 'MODE_FRESH', 'status' => 'complete',
            'customer_company' => 'Shop Co', 'customer_email' => '', 'customer_domain' => $domain, 'request_payload' => $payload,
            'site_id' => $site->id,
        ]);

        return [$site, $installation];
    }

    private function log(CustomerInstallation $installation, string $stage, array $output): void
    {
        CustomerInstallationLog::create([
            'installation_id' => $installation->id, 'stage' => $stage, 'event' => 'completed',
            'message' => 'done', 'output' => $output, 'duration_ms' => 1, 'created_at' => now(),
        ]);
    }

    private function bindDriver(FakeRemoteDriver $driver): void
    {
        $this->app->instance(RemoteConnectionService::class, new class($driver) extends RemoteConnectionService {
            public function __construct(private readonly FakeRemoteDriver $driver) {}
            public function buildDriver(CustomerRemoteServer $server): RemoteConnectionInterface
            {
                return $this->driver;
            }
        });
    }

    private function vpsDriver(): FakeRemoteDriver
    {
        $driver = new FakeRemoteDriver();
        // First match wins (insertion order): the .env upsert before generic patterns.
        $driver->addResponse('APP_URL', 0, 'env-set-ok');
        $driver->addResponse('nginx -t', 0, 'syntax is ok, test is successful');
        $driver->addResponse('command -v', 0, 'present');
        $driver->addResponse('certonly', 0, 'Successfully received certificate');
        $driver->addResponse('grep -o', 0, "DNS:new-shop.ir\nDNS:www.new-shop.ir");
        $driver->addResponse('test -f', 0, 'yes');
        $driver->addResponse('openssl x509', 0, 'notAfter=Feb 18 00:00:00 2027 GMT');

        return $driver;
    }

    /** The last vhost written to sites-available. */
    private function lastVhost(FakeRemoteDriver $driver): string
    {
        $cmd = collect($driver->getCommandLog())->last(fn ($c) => str_contains($c, 'base64 -d > ') && str_contains($c, '/etc/nginx/sites-available/'));
        $this->assertNotNull($cmd, 'no vhost was written');
        preg_match('/^echo ["\']([^"\']+)["\']/', $cmd, $m);

        return base64_decode($m[1]);
    }

    // ── state recorded from the installation ─────────────────────────────

    public function test_an_awaiting_dns_installation_is_recorded_and_holds_the_theme_push(): void
    {
        [$site, $installation] = $this->installedSite(dnsPending: true);
        $this->log($installation, 'VALIDATE_DOMAIN_DNS', ['awaiting_dns' => true, 'warning' => 'not registered']);
        $this->log($installation, 'ISSUE_SSL', ['status' => 'awaiting_dns', 'message' => 'DNS not ready']);

        $row = app(SiteDomainService::class)->recordFromInstallation($installation);

        $this->assertSame(SiteDomain::STATUS_AWAITING_DNS, $row->status);
        $this->assertSame(SiteDomain::ROLE_PRIMARY, $row->role);
        $this->assertSame('DNS not ready', $row->last_error);
        $this->assertTrue(app(SiteDomainService::class)->holdsTemplatePush($installation));
    }

    public function test_an_issued_certificate_is_recorded_as_active_with_www(): void
    {
        [, $installation] = $this->installedSite();
        $this->log($installation, 'ISSUE_SSL', ['status' => 'issued', 'certificate_names' => ['shop.ir', 'www.shop.ir']]);

        $row = app(SiteDomainService::class)->recordFromInstallation($installation);

        $this->assertSame(SiteDomain::STATUS_ACTIVE, $row->status);
        $this->assertTrue($row->www_in_certificate);
        $this->assertFalse(app(SiteDomainService::class)->holdsTemplatePush($installation));
    }

    // ── DNS check ────────────────────────────────────────────────────────

    public function test_check_dns_moves_an_awaiting_domain_to_connected_once_it_points_here(): void
    {
        [$site, $installation] = $this->installedSite(dnsPending: true);
        $this->log($installation, 'ISSUE_SSL', ['status' => 'awaiting_dns']);
        $service = app(SiteDomainService::class);
        $row = $service->primary($site);

        $report = $service->checkDns($row);
        $this->assertSame(SiteDomain::STATUS_AWAITING_DNS, $row->fresh()->status);
        $this->assertSame(self::IP, $report['server_ip']);

        $this->dns->set('shop.ir', DnsLookup::resolved([self::IP]));
        $service->checkDns($row->fresh());

        $this->assertSame(SiteDomain::STATUS_CONNECTED, $row->fresh()->status);
        $this->assertSame(['@', 'www'], array_column($row->fresh()->dns_report['records'], 'name'));
    }

    // ── pending custom domain ────────────────────────────────────────────

    public function test_a_custom_domain_is_registered_as_pending_without_touching_the_site(): void
    {
        [$site] = $this->installedSite('khorshid.safhenokhost.ir');

        $row = app(SiteDomainService::class)->addPending($site, 'https://www.New-Shop.ir/');

        $this->assertSame('new-shop.ir', $row->domain);
        $this->assertSame(SiteDomain::ROLE_PENDING, $row->role);
        $this->assertSame(SiteDomain::STATUS_AWAITING_DNS, $row->status);
        $this->assertNotEmpty($row->dns_report['records']);
        $this->assertSame('khorshid.safhenokhost.ir', $site->fresh()->domain);
    }

    public function test_a_pending_domain_is_refused_when_taken_or_identical(): void
    {
        [$site] = $this->installedSite('shop.ir');
        [$other] = $this->installedSite('other.ir');
        app(SiteDomainService::class)->primary($other);

        foreach (['shop.ir', 'other.ir', 'not a domain'] as $input) {
            try {
                app(SiteDomainService::class)->addPending($site, $input);
                $this->fail("{$input} must be refused");
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('domain', $e->errors());
            }
        }
    }

    public function test_queue_marks_issuing_and_dispatches_one_job(): void
    {
        Queue::fake();
        [$site] = $this->installedSite();
        $row = app(SiteDomainService::class)->primary($site);

        app(SiteDomainService::class)->queue($row, SiteDomainService::ACTION_SSL);

        $this->assertSame(SiteDomain::STATUS_ISSUING, $row->fresh()->status);
        Queue::assertPushed(SiteDomainJob::class, fn (SiteDomainJob $job) => $job->siteDomainId === $row->id && $job->action === 'ssl');

        $this->expectException(ValidationException::class);
        app(SiteDomainService::class)->queue($row->fresh(), SiteDomainService::ACTION_SSL); // already running
    }

    // ── panel routes ─────────────────────────────────────────────────────

    public function test_panel_renders_and_its_actions_are_scoped_to_the_site(): void
    {
        Queue::fake();
        [$site] = $this->installedSite();
        [$other] = $this->installedSite('other.ir');
        $admin = $this->admin();

        $this->actingAs($admin)->get(route('admin.sites.domain.show', $site))
            ->assertOk()->assertSee('shop.ir')->assertSee('بررسی اتصال DNS')->assertSee('اتصال و صدور SSL')->assertSee(self::IP);

        $this->actingAs($admin)->post(route('admin.sites.domain.store', $site), ['domain' => 'brand-new.ir'])->assertSessionHasNoErrors();
        $pending = SiteDomain::query()->where('domain', 'brand-new.ir')->sole();
        $this->actingAs($admin)->get(route('admin.sites.domain.show', $site))->assertSee('اتصال و جایگزینی دامنه');

        $this->actingAs($admin)->post(route('admin.sites.domain.activate', [$other, $pending]))->assertNotFound();
        $this->actingAs($admin)->post(route('admin.sites.domain.activate', [$site, $pending]))->assertSessionHasNoErrors();
        Queue::assertPushed(SiteDomainJob::class, fn (SiteDomainJob $job) => $job->action === 'activate');
    }

    // ── provisioning job ─────────────────────────────────────────────────

    public function test_issue_connects_an_awaiting_site_writes_www_and_releases_the_theme_push(): void
    {
        [$site, $installation] = $this->installedSite('new-shop.ir', dnsPending: true);
        $this->log($installation, 'ISSUE_SSL', ['status' => 'awaiting_dns']);
        $this->dns->set('new-shop.ir', DnsLookup::resolved([self::IP]))->set('www.new-shop.ir', DnsLookup::resolved([self::IP]));
        $driver = $this->vpsDriver();
        $this->bindDriver($driver);
        $row = app(SiteDomainService::class)->primary($site);

        app(SiteDomainProvisioner::class)->run($row, SiteDomainService::ACTION_SSL);

        $row->refresh();
        $this->assertSame(SiteDomain::STATUS_ACTIVE, $row->status, (string) $row->last_error);
        $this->assertTrue($row->www_in_certificate);
        $this->assertSame('2027-02-18', $row->ssl_expires_at->toDateString());
        $vhost = $this->lastVhost($driver);
        $this->assertStringContainsString('server_name new-shop.ir www.new-shop.ir;', $vhost);
        $this->assertStringContainsString('return 301 https://new-shop.ir$request_uri;', $vhost);
    }

    public function test_issue_keeps_the_domain_awaiting_when_dns_still_does_not_point_here(): void
    {
        [$site] = $this->installedSite();
        $driver = $this->vpsDriver();
        $this->bindDriver($driver);
        $row = app(SiteDomainService::class)->primary($site);

        app(SiteDomainProvisioner::class)->run($row, SiteDomainService::ACTION_SSL);

        $this->assertSame(SiteDomain::STATUS_AWAITING_DNS, $row->fresh()->status);
        $this->assertStringContainsString('هنوز در DNS ثبت نشده است', $row->fresh()->last_error);
        $this->assertFalse(collect($driver->getCommandLog())->contains(fn ($c) => str_contains($c, 'certonly')));
    }

    public function test_activate_switches_the_site_to_the_new_domain_and_keeps_the_old_one_as_a_redirect(): void
    {
        // The old domain is a platform subdomain: it gets no www name of its own.
        \App\Models\Infrastructure\DnsProviderSetting::create(['provider' => 'arvan', 'root_domain' => 'safhenokhost.ir', 'is_active' => true, 'api_token_encrypted' => 'token']);
        [$site] = $this->installedSite('khorshid.safhenokhost.ir');
        PlatformLicense::create(['site_id' => $site->id, 'license_key_prefix' => 'ABCD', 'license_key_hash' => str_repeat('a', 64), 'domain_allowed' => 'khorshid.safhenokhost.ir', 'issued_at' => now()]);
        $this->dns->set('new-shop.ir', DnsLookup::resolved([self::IP]))->set('www.new-shop.ir', DnsLookup::resolved([self::IP]));
        $driver = $this->vpsDriver();
        $this->bindDriver($driver);
        $pending = app(SiteDomainService::class)->addPending($site, 'new-shop.ir');

        app(SiteDomainProvisioner::class)->run($pending, SiteDomainService::ACTION_ACTIVATE);

        $pending->refresh();
        $this->assertSame(SiteDomain::STATUS_ACTIVE, $pending->status, (string) $pending->last_error);
        $this->assertSame(SiteDomain::ROLE_PRIMARY, $pending->role);
        $this->assertSame('new-shop.ir', $site->fresh()->domain);
        $old = SiteDomain::query()->where('domain', 'khorshid.safhenokhost.ir')->sole();
        $this->assertSame([SiteDomain::ROLE_ALIAS, SiteDomain::STATUS_REDIRECT], [$old->role, $old->status]);
        $this->assertSame('khorshid.safhenokhost.ir,new-shop.ir', PlatformLicense::query()->sole()->domain_allowed);

        $log = $driver->getCommandLog();
        $this->assertTrue(collect($log)->contains(fn ($c) => str_contains($c, 'APP_URL')), 'APP_URL was not updated');
        $vhost = $this->lastVhost($driver);
        $this->assertStringContainsString('server_name new-shop.ir www.new-shop.ir khorshid.safhenokhost.ir;', $vhost);
        $this->assertStringContainsString('return 301 https://new-shop.ir$request_uri;', $vhost);
    }

    public function test_a_failed_certificate_leaves_the_live_domain_untouched(): void
    {
        [$site] = $this->installedSite('khorshid.safhenokhost.ir');
        $this->dns->set('new-shop.ir', DnsLookup::resolved([self::IP]));
        $driver = new FakeRemoteDriver();
        $driver->addResponse('nginx -t', 0, 'syntax is ok, test is successful');
        $driver->addResponse('command -v', 0, 'missing'); // no certbot on the server
        $this->bindDriver($driver);
        $pending = app(SiteDomainService::class)->addPending($site, 'new-shop.ir');

        app(SiteDomainProvisioner::class)->run($pending, SiteDomainService::ACTION_ACTIVATE);

        $this->assertSame(SiteDomain::STATUS_FAILED, $pending->fresh()->status);
        $this->assertSame(SiteDomain::ROLE_PENDING, $pending->fresh()->role);
        $this->assertSame('khorshid.safhenokhost.ir', $site->fresh()->domain);
        $this->assertFalse(collect($driver->getCommandLog())->contains(fn ($c) => str_contains($c, 'APP_URL')));
        // The staging vhost keeps the current domain canonical.
        $this->assertStringContainsString('server_name khorshid.safhenokhost.ir;', $this->lastVhost($driver));
    }
}
