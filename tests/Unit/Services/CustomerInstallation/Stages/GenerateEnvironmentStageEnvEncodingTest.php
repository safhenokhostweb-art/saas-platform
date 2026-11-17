<?php

namespace Tests\Unit\Services\CustomerInstallation\Stages;

use App\Models\CustomerInstallation;
use App\Models\CustomerRemoteServer;
use App\Services\CustomerInstallation\Drivers\FakeRemoteDriver;
use App\Services\CustomerInstallation\Dto\CustomerInstallRequest;
use App\Services\CustomerInstallation\Dto\InstallationContext;
use App\Services\CustomerInstallation\Env\DotenvValue;
use App\Services\CustomerInstallation\Stages\GenerateEnvironmentStage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Same bug class as installation 51's DB password (2026-11-17): user-supplied
 * values written raw into .env. An SMTP password with `#` was cut, and a
 * company name with `"` broke the line.
 */
class GenerateEnvironmentStageEnvEncodingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        $key = base64_encode(random_bytes(32));
        putenv("INSTALLER_MASTER_KEY={$key}");
        $_ENV['INSTALLER_MASTER_KEY'] = $key;

        parent::setUp();
    }

    public function test_user_supplied_values_read_back_exactly_from_the_written_env(): void
    {
        $company = 'شرکت "خورشید" #۱ $x';
        $owner = "O'Brien \\ \$HOME";
        $smtpUser = 'mail@solar.example.ir';
        $smtpPass = 'p#ss "w0rd" $y \\z';

        $server = CustomerRemoteServer::create([
            'label' => 'Fake', 'type' => 'fake', 'host' => 'localhost', 'port' => 22, 'username' => 'deploy', 'auth_method' => 'key',
        ]);
        $payload = [
            'company' => $company, 'owner_name' => $owner, 'email' => 'o@example.ir', 'phone' => '09120000000',
            'language' => 'fa', 'timezone' => 'Asia/Tehran', 'currency' => 'IRR', 'domain' => 'solar.example.ir',
            'license_type' => 'basic', 'theme_slug' => '', 'modules' => [], 'backup_policy' => 'daily',
            'smtp' => ['host' => 'smtp.example.ir', 'port' => '587', 'username' => $smtpUser, 'password' => $smtpPass, 'encryption' => 'tls'],
            'ssl_enabled' => false, 'remote_server_id' => $server->id, 'install_mode' => 'MODE_FRESH',
        ];
        $installation = CustomerInstallation::create([
            'remote_server_id' => $server->id, 'installer_user_id' => 1, 'install_mode' => 'MODE_FRESH', 'status' => 'running',
            'customer_company' => 'C', 'customer_email' => '', 'customer_domain' => 'solar.example.ir', 'request_payload' => $payload,
        ]);
        $driver = new FakeRemoteDriver();

        $result = app(GenerateEnvironmentStage::class)
            ->execute(new InstallationContext($installation, CustomerInstallRequest::fromArray($payload), $driver));
        $this->assertTrue($result->success, (string) $result->error);

        $write = collect($driver->getCommandLog())->first(fn ($c) => str_contains($c, 'base64 -d >'));
        $this->assertNotNull($write);
        preg_match('/echo\s+["\']([A-Za-z0-9+\/=]+)["\']/', $write, $m);
        $env = DotenvValue::parse(base64_decode($m[1]));

        $this->assertSame($company, $env['APP_NAME']);
        $this->assertSame($owner, $env['ADMIN_NAME']);
        $this->assertSame($smtpUser, $env['MAIL_USERNAME']);
        $this->assertSame($smtpPass, $env['MAIL_PASSWORD']);
        $this->assertSame('__DB_PASS__', $env['DB_PASSWORD'], 'placeholder until CREATE_DATABASE');
    }
}
