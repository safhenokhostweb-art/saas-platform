<?php

namespace Tests\Unit\Services\CustomerInstallation;

use App\Models\CustomerInstallation;
use App\Models\CustomerRemoteServer;
use App\Models\Infrastructure\DnsProviderSetting;
use App\Services\CustomerInstallation\Dns\DnsLookup;
use App\Services\CustomerInstallation\Dns\DnsResolver;
use App\Services\CustomerInstallation\Dns\DomainDnsPreflight;
use App\Services\CustomerInstallation\Drivers\FakeRemoteDriver;
use App\Services\CustomerInstallation\Dto\CustomerInstallRequest;
use App\Services\CustomerInstallation\Dto\InstallationContext;
use App\Services\CustomerInstallation\Dto\StageResult;
use App\Services\CustomerInstallation\Ssl\ArvanCdnDomainDecider;
use App\Services\CustomerInstallation\Ssl\CpanelSslProvisioner;
use App\Services\CustomerInstallation\Ssl\Dto\SslResult;
use App\Services\CustomerInstallation\Ssl\NginxVhostTemplate;
use App\Services\CustomerInstallation\Ssl\NginxVhostWriter;
use App\Services\CustomerInstallation\Ssl\SslProvisionerFactory;
use App\Services\CustomerInstallation\Ssl\VpsSslProvisioner;
use App\Services\CustomerInstallation\Stages\IssueSslStage;
use App\Services\CustomerInstallation\Stages\ValidateDomainDnsStage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\FakeDnsResolver;
use Tests\TestCase;

/**
 * Phase 3 — custom domains: awaiting-DNS registration, AAAA preflight,
 * www in the certificate and vhost, HTTPS redirects and the certbot
 * deploy hook.
 */
class CustomDomainFlowTest extends TestCase
{
    use RefreshDatabase;

    private const IP = '87.107.12.81';

    protected function setUp(): void
    {
        parent::setUp();
        $key = base64_encode(random_bytes(32));
        putenv("INSTALLER_MASTER_KEY={$key}");
        $_ENV['INSTALLER_MASTER_KEY'] = $key;
    }

    private function dns(): FakeDnsResolver
    {
        $dns = new FakeDnsResolver(DnsLookup::notFound());
        $this->app->instance(DnsResolver::class, $dns);

        return $dns;
    }

    private function context(string $domain, bool $dnsPending = false, string $email = 'owner@example.com', ?FakeRemoteDriver $driver = null): InstallationContext
    {
        $server = CustomerRemoteServer::create([
            'label' => 'S', 'type' => 'fake', 'host' => self::IP, 'port' => 22, 'username' => 'root', 'auth_method' => 'key',
        ]);
        $payload = [
            'company' => 'Domain Co', 'owner_name' => 'O', 'email' => $email, 'phone' => '09120000000', 'language' => 'fa',
            'timezone' => 'Asia/Tehran', 'currency' => 'IRR', 'domain' => $domain, 'license_type' => 'basic',
            'theme_slug' => '', 'modules' => [], 'backup_policy' => 'daily', 'smtp' => [], 'ssl_enabled' => true,
            'remote_server_id' => $server->id, 'install_mode' => 'MODE_FRESH', 'dns_pending' => $dnsPending,
        ];
        $installation = CustomerInstallation::create([
            'remote_server_id' => $server->id, 'installer_user_id' => 1, 'install_mode' => 'MODE_FRESH', 'status' => 'running',
            'customer_company' => 'Domain Co', 'customer_email' => $email, 'customer_domain' => $domain, 'request_payload' => $payload,
        ]);

        return new InstallationContext($installation, CustomerInstallRequest::fromArray($payload), $driver ?? new FakeRemoteDriver());
    }

    private function platformRoot(string $root = 'safhenokhost.ir'): void
    {
        DnsProviderSetting::create(['provider' => 'arvan', 'root_domain' => $root, 'is_active' => true, 'api_token_encrypted' => 'token']);
    }

    // ── DomainDnsPreflight ───────────────────────────────────────────────

    public function test_a_foreign_aaaa_record_blocks_ssl_and_is_an_awaiting_dns_code(): void
    {
        $this->dns()->set('shop.ir', DnsLookup::resolved([self::IP]))->setAaaa('shop.ir', DnsLookup::resolved(['2001:db8::1']));

        $result = app(DomainDnsPreflight::class)->check('shop.ir', self::IP, true);

        $this->assertFalse($result->ok);
        $this->assertSame(DomainDnsPreflight::FAIL_AAAA_CONFLICT, $result->code);
        $this->assertStringContainsString('2001:db8::1', $result->error);
        $this->assertTrue(DomainDnsPreflight::isAwaitingDnsCode($result->code));
    }

    public function test_aaaa_is_irrelevant_without_ssl_or_behind_the_cdn_edge(): void
    {
        $this->dns()
            ->set('shop.ir', DnsLookup::resolved([self::IP]))->setAaaa('shop.ir', DnsLookup::resolved(['2001:db8::1']))
            ->set('cdn-shop.ir', DnsLookup::resolved(['185.143.232.10']))->setAaaa('cdn-shop.ir', DnsLookup::resolved(['2a0b:4d80::1']));
        $preflight = app(DomainDnsPreflight::class);

        $this->assertTrue($preflight->check('shop.ir', self::IP, false)->ok);
        $this->assertTrue($preflight->check('cdn-shop.ir', self::IP, true)->ok);
    }

    public function test_www_alias_only_for_customer_domains(): void
    {
        $this->dns();
        $this->platformRoot();
        $preflight = app(DomainDnsPreflight::class);

        $this->assertSame('www.shop.ir', $preflight->wwwAlias('shop.ir'));
        $this->assertSame('www.example.com', $preflight->wwwAlias('EXAMPLE.com.'));
        $this->assertNull($preflight->wwwAlias('www.shop.ir'));
        $this->assertNull($preflight->wwwAlias('tenant.safhenokhost.ir'));
        $this->assertFalse(DomainDnsPreflight::isAwaitingDnsCode(DomainDnsPreflight::FAIL_NO_SERVER_IP));
    }

    // ── VALIDATE_DOMAIN_DNS ──────────────────────────────────────────────

    public function test_validate_stage_passes_an_awaiting_dns_installation_with_a_warning(): void
    {
        $this->dns();

        $result = app(ValidateDomainDnsStage::class)->execute($this->context('shop.ir', dnsPending: true));

        $this->assertTrue($result->success, (string) $result->error);
        $this->assertTrue($result->output['awaiting_dns']);
        $this->assertSame(DomainDnsPreflight::FAIL_NOT_REGISTERED, $result->output['code']);
        $this->assertStringContainsString('هنوز در DNS ثبت نشده است', $result->output['warning']);
    }

    public function test_validate_stage_still_fails_when_not_registered_as_pending(): void
    {
        $this->dns();

        $this->assertFalse(app(ValidateDomainDnsStage::class)->execute($this->context('shop.ir'))->success);
    }

    // ── ISSUE_SSL ────────────────────────────────────────────────────────

    /** @param list<list<string>> $seenAliases */
    private function sslStage(?SslResult $result, array &$seenAliases): IssueSslStage
    {
        $vps = new class($result, $seenAliases) extends VpsSslProvisioner {
            public function __construct(private readonly ?SslResult $result, private array &$seen) {}
            public function issueCertificate(InstallationContext $ctx, string $domain): SslResult
            {
                $this->seen[] = $ctx->certificateAliases;

                return $this->result;
            }
        };
        $writer = new class extends NginxVhostWriter {
            public function __construct() {}
            public function write(InstallationContext $ctx): StageResult
            {
                return StageResult::success('CONFIGURE_WEB_SERVER', [], [], 0);
            }
        };

        return new IssueSslStage(new SslProvisionerFactory($vps, app(CpanelSslProvisioner::class)), new ArvanCdnDomainDecider(), $writer);
    }

    public function test_issue_ssl_waits_without_certbot_while_dns_is_still_pending(): void
    {
        $this->dns();
        $seen = [];

        $result = $this->sslStage(SslResult::issued('/c', '/k'), $seen)->execute($this->context('shop.ir', dnsPending: true));

        $this->assertTrue($result->success);
        $this->assertSame(IssueSslStage::STATUS_AWAITING_DNS, $result->output['status']);
        $this->assertSame([], $seen, 'certbot must not run before DNS points here');
    }

    public function test_a_failed_issuance_of_a_pending_dns_site_is_a_warning(): void
    {
        $this->dns()->set('shop.ir', DnsLookup::resolved([self::IP]));
        $seen = [];

        $result = $this->sslStage(SslResult::failed('rate limited'), $seen)->execute($this->context('shop.ir', dnsPending: true));

        $this->assertTrue($result->success);
        $this->assertSame('ssl_failed', $result->output['status']);
    }

    public function test_www_joins_the_certificate_only_when_it_points_here(): void
    {
        $dns = $this->dns()->set('shop.ir', DnsLookup::resolved([self::IP]));
        $seen = [];
        $stage = $this->sslStage(SslResult::issued('/c', '/k'), $seen);

        $stage->execute($this->context('shop.ir'));
        $dns->set('www.shop.ir', DnsLookup::resolved([self::IP]));
        $ok = $stage->execute($this->context('shop.ir'));

        $this->assertSame([[], ['www.shop.ir']], $seen);
        $this->assertSame(['shop.ir', 'www.shop.ir'], $ok->output['certificate_names']);
    }

    // ── certbot ──────────────────────────────────────────────────────────

    public function test_certbot_issues_one_lineage_for_domain_and_www_with_the_reload_hook(): void
    {
        $driver = new FakeRemoteDriver();
        $driver->addResponse('command -v', 0, 'present');
        $driver->addResponse('certonly', 0, 'Successfully received certificate');
        $driver->addResponse('test -f', 0, 'yes');
        $ctx = $this->context('shop.ir', email: '', driver: $driver);
        $ctx->certificateAliases = ['www.shop.ir'];

        $this->assertTrue((new VpsSslProvisioner())->issueCertificate($ctx, 'shop.ir')->success);

        $cmd = collect($driver->getCommandLog())->first(fn ($c) => str_contains($c, 'certonly'));
        $this->assertStringContainsString('--cert-name ' . escapeshellarg('shop.ir') . ' -d ' . escapeshellarg('shop.ir') . ' -d ' . escapeshellarg('www.shop.ir') . ' --expand', $cmd);
        $this->assertStringContainsString('--deploy-hook ' . escapeshellarg('systemctl reload nginx'), $cmd);
        $this->assertStringContainsString('--register-unsafely-without-email', $cmd);
        $this->assertStringNotContainsString('-m ' . escapeshellarg(''), $cmd);
    }

    public function test_renewal_also_reloads_nginx_and_certificate_names_are_read_from_the_san(): void
    {
        $driver = new FakeRemoteDriver();
        $driver->addResponse('command -v', 0, 'present');
        $driver->addResponse('grep -o', 0, "DNS:shop.ir\nDNS:WWW.shop.ir");
        $driver->addResponse('test -f', 0, 'yes');
        $ctx = $this->context('shop.ir', driver: $driver);
        $provisioner = new VpsSslProvisioner();

        $provisioner->renewCertificate($ctx, 'shop.ir');

        $renew = collect($driver->getCommandLog())->first(fn ($c) => str_contains($c, ' renew '));
        $this->assertStringContainsString('--deploy-hook ' . escapeshellarg('systemctl reload nginx'), $renew);
        $this->assertSame(['shop.ir', 'www.shop.ir'], $provisioner->certificateNames($ctx, 'shop.ir'));
    }

    // ── nginx ────────────────────────────────────────────────────────────

    public function test_template_without_certificate_redirects_www_over_http_but_serves_acme(): void
    {
        $config = (new NginxVhostTemplate())->render('shop.ir', '/var/www/shop', '/run/php.sock', null, ['www.shop.ir']);

        $this->assertStringContainsString('server_name shop.ir;', $config);
        $this->assertStringContainsString('server_name www.shop.ir;', $config);
        $this->assertStringContainsString('return 301 http://shop.ir$request_uri;', $config);
        $this->assertStringContainsString('location ^~ /.well-known/acme-challenge/', $config);
        $this->assertSame(1, substr_count($config, 'fastcgi_pass'));
    }

    public function test_template_with_certificate_forces_https_and_canonicalises_www(): void
    {
        $config = (new NginxVhostTemplate())->render(
            'shop.ir', '/var/www/shop', '/run/php.sock',
            ['cert' => '/le/shop.ir/fullchain.pem', 'key' => '/le/shop.ir/privkey.pem'],
            ['www.shop.ir', 'old.example.ir'], true,
            [['names' => ['www.shop.ir'], 'cert' => '/le/shop.ir/fullchain.pem', 'key' => '/le/shop.ir/privkey.pem']],
        );

        $this->assertStringContainsString('server_name shop.ir www.shop.ir old.example.ir;', $config);
        $this->assertStringContainsString('return 301 https://shop.ir$request_uri;', $config);
        $this->assertSame(1, substr_count($config, 'fastcgi_pass'), 'the application is served on 443 only');
        $this->assertSame(2, substr_count($config, 'listen 443 ssl;'));
        $this->assertMatchesRegularExpression('/listen 443 ssl;\s+server_name www\.shop\.ir;/', $config);
        $this->assertStringNotContainsString('server_name old.example.ir;', $config, 'no certificate covers it: port 80 only');
        $this->assertStringContainsString('location ^~ /.well-known/acme-challenge/', $config);
    }

    public function test_default_template_output_is_unchanged(): void
    {
        $config = (new NginxVhostTemplate())->render('t.example.test', '/var/www/t', '/run/php.sock', ['cert' => '/c', 'key' => '/k']);

        $this->assertSame(2, substr_count($config, 'fastcgi_pass'));
        $this->assertStringNotContainsString('return 301', $config);
        $this->assertStringNotContainsString('acme-challenge', $config);
    }

    private function writtenVhost(FakeRemoteDriver $driver): string
    {
        // escapeshellarg() quotes with ' on Linux and " on Windows.
        $cmd = collect($driver->getCommandLog())->first(fn ($c) => str_contains($c, 'base64 -d > ') && str_contains($c, '/etc/nginx/sites-available/'));
        $this->assertNotNull($cmd, 'vhost was not written');
        preg_match('/^echo ["\']([^"\']+)["\']/', $cmd, $m);

        return base64_decode($m[1]);
    }

    private function writerDriver(bool $certExists, string $san = ''): FakeRemoteDriver
    {
        $driver = new FakeRemoteDriver();
        $driver->addResponse('nginx -t', 0, 'syntax is ok, test is successful');
        $driver->addResponse('grep -o', 0, $san);
        $driver->addResponse('test -f', 0, $certExists ? 'yes' : 'no');
        $driver->addResponse('openssl x509', 0, 'notAfter=Sep  7 00:00:00 2027 GMT');

        return $driver;
    }

    public function test_writer_adds_www_and_forces_https_once_the_certificate_covers_it(): void
    {
        $this->dns();
        $driver = $this->writerDriver(true, "DNS:shop.ir\nDNS:www.shop.ir");

        $result = app(NginxVhostWriter::class)->write($this->context('shop.ir', driver: $driver));

        $this->assertTrue($result->success, (string) $result->error);
        $this->assertTrue($result->output['force_https']);
        $this->assertSame(['www.shop.ir'], $result->output['https_redirects']);
        $vhost = $this->writtenVhost($driver);
        $this->assertStringContainsString('server_name shop.ir www.shop.ir;', $vhost);
        $this->assertStringContainsString('return 301 https://shop.ir$request_uri;', $vhost);
    }

    public function test_writer_before_the_certificate_serves_www_as_an_http_redirect(): void
    {
        $this->dns();
        $driver = $this->writerDriver(false);

        $result = app(NginxVhostWriter::class)->write($this->context('shop.ir', driver: $driver));

        $this->assertFalse($result->output['force_https']);
        $this->assertSame(['www.shop.ir'], $result->output['redirect_hosts']);
        $this->assertStringContainsString('return 301 http://shop.ir$request_uri;', $this->writtenVhost($driver));
    }

    public function test_writer_gives_a_platform_subdomain_no_www_and_no_origin_redirect(): void
    {
        $this->dns();
        DnsProviderSetting::create(['provider' => 'arvan', 'root_domain' => 'safhenokhost.ir', 'is_active' => true, 'api_token_encrypted' => 'token', 'cloud_proxy' => true]);
        $driver = $this->writerDriver(false);

        $result = app(NginxVhostWriter::class)->write($this->context('tenant.safhenokhost.ir', driver: $driver));

        $this->assertSame([], $result->output['redirect_hosts']);
        $this->assertFalse($result->output['force_https'], 'the CDN may reach the origin over HTTP: an origin redirect would loop');
        $this->assertStringNotContainsString('return 301', $this->writtenVhost($driver));
    }
}
