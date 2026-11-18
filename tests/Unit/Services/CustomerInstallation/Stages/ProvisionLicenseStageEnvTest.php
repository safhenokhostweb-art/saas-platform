<?php

namespace Tests\Unit\Services\CustomerInstallation\Stages;

use App\Models\Customer;
use App\Models\CustomerInstallation;
use App\Models\CustomerRemoteServer;
use App\Models\Site;
use App\Services\CustomerInstallation\Drivers\FakeRemoteDriver;
use App\Services\CustomerInstallation\Dto\CommandResult;
use App\Services\CustomerInstallation\Dto\CustomerInstallRequest;
use App\Services\CustomerInstallation\Dto\InstallationContext;
use App\Services\CustomerInstallation\Env\DotenvValue;
use App\Services\CustomerInstallation\Env\RemoteEnvFile;
use App\Services\CustomerInstallation\Stages\ProvisionLicenseStage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\RunsPosixShell;
use Tests\TestCase;

/**
 * Installation 52 (2026-11-17): "LICENSE_KEY placeholder replacement failed".
 * The check grepped `^LICENSE_KEY=CIE-`, but LicenseServerService::issue()
 * has returned XXXX-XXXX-XXXX-XXXX keys since 67cb40ed (2026-06-29), so the
 * stage could never pass. These tests run the stage's .env commands in a
 * real shell against a real .env and decode it as Laravel will.
 */
class ProvisionLicenseStageEnvTest extends TestCase
{
    use RefreshDatabase;
    use RunsPosixShell;

    /** A driver whose .env commands run for real against a local copy of the remote file. */
    private function shellDriver(string $file): FakeRemoteDriver
    {
        return new class($file, fn (string $c) => $this->runInShell($c)) extends FakeRemoteDriver {
            public function __construct(private string $file, private \Closure $run) { parent::__construct(); }

            public function execute(string $command, array $env = []): CommandResult
            {
                parent::execute($command, $env);
                if (str_contains($command, "/.env'")) {
                    [$code, $out] = ($this->run)(preg_replace("#'[^']*/\.env'#", "'" . $this->file . "'", $command));

                    return new CommandResult(exitCode: $code, stdout: $out, stderr: '', durationMs: 1);
                }

                return new CommandResult(exitCode: 0, stdout: '', stderr: '', durationMs: 1);
            }
        };
    }

    private function context(FakeRemoteDriver $driver): InstallationContext
    {
        $customer = Customer::create(['name' => 'صفحه نخست', 'mobile' => '09927530317']);
        $server = CustomerRemoteServer::create([
            'label' => 'S', 'type' => 'fake', 'host' => '87.107.12.81', 'port' => 22, 'username' => 'root', 'auth_method' => 'key',
        ]);
        $payload = [
            'company' => 'سولار', 'owner_name' => 'صفحه نخست', 'email' => '', 'phone' => '09927530317', 'language' => 'fa',
            'timezone' => 'Asia/Tehran', 'currency' => 'IRR', 'domain' => 'solar.example.ir', 'license_type' => 'professional',
            'theme_slug' => '', 'modules' => [], 'backup_policy' => 'daily', 'smtp' => [], 'ssl_enabled' => true,
            'remote_server_id' => $server->id, 'install_mode' => 'MODE_FRESH', 'customer_id' => $customer->id,
        ];
        $installation = CustomerInstallation::create([
            'remote_server_id' => $server->id, 'installer_user_id' => 1, 'install_mode' => 'MODE_FRESH', 'status' => 'running',
            'customer_company' => 'سولار', 'customer_email' => '', 'customer_domain' => 'solar.example.ir', 'request_payload' => $payload,
        ]);

        return new InstallationContext($installation, CustomerInstallRequest::fromArray($payload), $driver);
    }

    /** @return iterable<string, array{string}> */
    public static function envFiles(): iterable
    {
        yield 'GENERATE_ENVIRONMENT placeholder' => ["APP_NAME=\"سولار\"\nPLATFORM_URL=https://panel.example.ir\nLICENSE_KEY=__LICENSE_KEY__\nLICENSE_VERIFY_SSL=false\n"];
        yield 'no LICENSE_KEY line at all' => ["APP_NAME=\"سولار\"\nPLATFORM_URL=https://panel.example.ir\n"];
        yield 'empty LICENSE_KEY' => ["LICENSE_KEY=\nAPP_ENV=production\n"];
        yield 'quoted old key' => ["LICENSE_KEY=\"CIE-OLD\"\nAPP_ENV=production\n"];
    }

    /** @dataProvider envFiles */
    public function test_the_issued_key_ends_up_in_env_exactly_as_laravel_reads_it(string $envBefore): void
    {
        $this->requirePosixShell();
        $file = $this->tempEnvFile($envBefore);

        try {
            $ctx = $this->context($this->shellDriver($file));
            $result = app(ProvisionLicenseStage::class)->execute($ctx);

            $this->assertTrue($result->success, (string) $result->error);
            $site = Site::query()->where('domain', 'solar.example.ir')->sole();
            $this->assertMatchesRegularExpression('/^[A-Z0-9]{4}(-[A-Z0-9]{4}){3}$/', $site->license_key, 'LicenseServerService key format (no CIE- prefix)');

            $env = DotenvValue::parse(file_get_contents($file));
            $this->assertSame($site->license_key, $env['LICENSE_KEY']);
            $this->assertSame((string) $site->id, $env['PLATFORM_SITE_ID'] ?? null);
            $this->assertSame(1, substr_count(file_get_contents($file), 'LICENSE_KEY='));
        } finally {
            @unlink($file);
        }
    }

    public function test_a_failed_write_fails_the_stage_with_a_clear_message(): void
    {
        $driver = new FakeRemoteDriver();
        $driver->addResponse(RemoteEnvFile::SET_OK, 1, RemoteEnvFile::SET_FAILED);

        $result = app(ProvisionLicenseStage::class)->execute($this->context($driver));

        $this->assertFalse($result->success);
        $this->assertStringContainsString('LICENSE_KEY', $result->error);
        $this->assertStringContainsString('نوشته نشد', $result->error);
    }

    public function test_a_read_back_mismatch_fails_the_stage(): void
    {
        $driver = new FakeRemoteDriver();
        $driver->addResponse("grep -E '^(LICENSE_KEY)='", 0, "LICENSE_KEY=SOMETHING-ELSE\n");

        $result = app(ProvisionLicenseStage::class)->execute($this->context($driver));

        $this->assertFalse($result->success);
        $this->assertStringContainsString('یکسان نیست', $result->error);
    }

    public function test_the_old_cie_prefix_check_is_gone(): void
    {
        $driver = new FakeRemoteDriver();

        $this->assertTrue(app(ProvisionLicenseStage::class)->execute($this->context($driver))->success);
        foreach ($driver->getCommandLog() as $command) {
            $this->assertStringNotContainsString('LICENSE_KEY=CIE-', $command);
            $this->assertStringNotContainsString('__LICENSE_KEY__', $command);
        }
    }
}
