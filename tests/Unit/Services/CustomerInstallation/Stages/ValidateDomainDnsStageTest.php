<?php

namespace Tests\Unit\Services\CustomerInstallation\Stages;

use App\Models\CustomerInstallation;
use App\Models\CustomerRemoteServer;
use App\Services\CustomerInstallation\CustomerInstallationOrchestrator;
use App\Services\CustomerInstallation\Dns\DnsLookup;
use App\Services\CustomerInstallation\Dns\DnsResolver;
use App\Services\CustomerInstallation\Drivers\FakeRemoteDriver;
use App\Services\CustomerInstallation\Dto\CustomerInstallRequest;
use App\Services\CustomerInstallation\Dto\InstallationContext;
use App\Services\CustomerInstallation\Stages\ValidateDomainDnsStage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\FakeDnsResolver;
use Tests\TestCase;

class ValidateDomainDnsStageTest extends TestCase
{
    use RefreshDatabase;

    private function context(string $domain, bool $ssl): InstallationContext
    {
        $server = CustomerRemoteServer::create([
            'label' => 'S', 'type' => 'ssh', 'host' => '87.107.12.81', 'port' => 22, 'username' => 'root', 'auth_method' => 'key',
        ]);
        $payload = [
            'company' => 'C', 'owner_name' => 'O', 'email' => '', 'phone' => '09120000000', 'language' => 'fa',
            'timezone' => 'Asia/Tehran', 'currency' => 'IRR', 'domain' => $domain, 'license_type' => 'basic',
            'theme_slug' => '', 'modules' => [], 'backup_policy' => 'daily', 'smtp' => [], 'ssl_enabled' => $ssl,
            'remote_server_id' => $server->id, 'install_mode' => 'MODE_FRESH',
        ];
        $installation = CustomerInstallation::create([
            'remote_server_id' => $server->id, 'installer_user_id' => 1, 'install_mode' => 'MODE_FRESH', 'status' => 'running',
            'customer_company' => 'C', 'customer_email' => '', 'customer_domain' => $domain, 'request_payload' => $payload,
        ]);

        return new InstallationContext($installation, CustomerInstallRequest::fromArray($payload), new FakeRemoteDriver());
    }

    public function test_unregistered_domain_fails_the_stage_with_the_persian_reason(): void
    {
        $this->app->instance(DnsResolver::class, new FakeDnsResolver(DnsLookup::notFound()));

        $result = app(ValidateDomainDnsStage::class)->execute($this->context('shop.example.ir', true));

        $this->assertFalse($result->success);
        $this->assertSame('VALIDATE_DOMAIN_DNS', $result->stage);
        $this->assertStringContainsString('هنوز در DNS ثبت نشده است', $result->error);
    }

    public function test_domain_pointing_to_the_server_passes_and_reports_what_was_checked(): void
    {
        $this->app->instance(DnsResolver::class, (new FakeDnsResolver(DnsLookup::notFound()))
            ->set('shop.example.ir', DnsLookup::resolved(['87.107.12.81'])));

        $result = app(ValidateDomainDnsStage::class)->execute($this->context('shop.example.ir', true));

        $this->assertTrue($result->success, (string) $result->error);
        $this->assertSame('87.107.12.81', $result->output['server_ip']);
        $this->assertSame(['87.107.12.81'], $result->output['resolved_ips']);
        $this->assertSame('points_to_server', $result->output['code']);
    }

    public function test_dns_is_checked_and_created_before_any_upload_or_database_stage(): void
    {
        $names = array_map(fn ($s) => $s->name(), app(CustomerInstallationOrchestrator::class)->buildStageList('MODE_FRESH'));
        $pos = array_flip($names);

        $this->assertArrayHasKey('VALIDATE_DOMAIN_DNS', $pos);
        $this->assertLessThan($pos['PROVISION_DOMAIN'], $pos['VALIDATE_DOMAIN_DNS']);
        $this->assertLessThan($pos['VALIDATE_DOMAIN_DNS'], $pos['VALIDATE_PERMISSIONS']);
        foreach (['UPLOAD_PACKAGE', 'EXTRACT_PACKAGE', 'CREATE_DATABASE', 'RUN_MIGRATIONS', 'CONFIGURE_WEB_SERVER', 'ISSUE_SSL'] as $later) {
            $this->assertLessThan($pos[$later], $pos['VALIDATE_DOMAIN_DNS'], "VALIDATE_DOMAIN_DNS before {$later}");
            $this->assertLessThan($pos[$later], $pos['CONFIGURE_DNS'], "CONFIGURE_DNS before {$later}");
        }
        $this->assertLessThan($pos['CONFIGURE_DNS'], $pos['PROVISION_DOMAIN']);
        $this->assertSame(1, count(array_keys($names, 'CONFIGURE_DNS')));
    }
}
