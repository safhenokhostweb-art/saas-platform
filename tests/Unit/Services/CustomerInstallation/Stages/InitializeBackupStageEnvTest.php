<?php

namespace Tests\Unit\Services\CustomerInstallation\Stages;

use App\Models\CustomerInstallation;
use App\Models\CustomerRemoteServer;
use App\Services\CustomerInstallation\Drivers\FakeRemoteDriver;
use App\Services\CustomerInstallation\Dto\CustomerInstallRequest;
use App\Services\CustomerInstallation\Dto\InstallationContext;
use App\Services\CustomerInstallation\Env\RemoteEnvFile;
use App\Services\CustomerInstallation\Stages\InitializeBackupStage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * INITIALIZE_BACKUP runs right after PROVISION_LICENSE. It used to append
 * BACKUP_* with `echo >>` (duplicated on retry) and read DB credentials with
 * `cut -d= -f2 | tr -d '"'` (wrong for any `=` or dotenv escape).
 */
class InitializeBackupStageEnvTest extends TestCase
{
    use RefreshDatabase;

    private function context(FakeRemoteDriver $driver): InstallationContext
    {
        $server = CustomerRemoteServer::create([
            'label' => 'S', 'type' => 'fake', 'host' => 'localhost', 'port' => 22, 'username' => 'root', 'auth_method' => 'key',
        ]);
        $payload = [
            'company' => 'C', 'owner_name' => 'O', 'email' => '', 'phone' => '09120000000', 'language' => 'fa',
            'timezone' => 'Asia/Tehran', 'currency' => 'IRR', 'domain' => 'solar.example.ir', 'license_type' => 'basic',
            'theme_slug' => '', 'modules' => [], 'backup_policy' => 'daily', 'smtp' => [], 'ssl_enabled' => false,
            'remote_server_id' => $server->id, 'install_mode' => 'MODE_FRESH',
        ];
        $installation = CustomerInstallation::create([
            'remote_server_id' => $server->id, 'installer_user_id' => 1, 'install_mode' => 'MODE_FRESH', 'status' => 'running',
            'customer_company' => 'C', 'customer_email' => '', 'customer_domain' => 'solar.example.ir', 'request_payload' => $payload,
        ]);

        return new InstallationContext($installation, CustomerInstallRequest::fromArray($payload), $driver);
    }

    public function test_backup_keys_are_upserted_and_db_credentials_are_decoded_like_laravel(): void
    {
        $driver = new FakeRemoteDriver();
        $driver->addResponse("grep -E '^(DB_DATABASE|DB_USERNAME|DB_PASSWORD)='", 0, "DB_DATABASE=cust_x_52\nDB_USERNAME=u_dM4WNoni\nDB_PASSWORD=\"p=a#ss\\\$x\"\n");
        $driver->addResponse('mysqldump', 0, 'dump-failed'); // stop before any upload

        $result = app(InitializeBackupStage::class)->execute($this->context($driver));

        $this->assertTrue($result->success, (string) $result->error);
        $log = $driver->getCommandLog();

        $sets = array_values(array_filter($log, fn ($c) => str_contains($c, RemoteEnvFile::SET_OK)));
        $this->assertCount(2, $sets, 'BACKUP_ENABLED and BACKUP_RETENTION go through the upsert');
        $this->assertStringContainsString(base64_encode('BACKUP_ENABLED=true'), $sets[0]);
        $this->assertStringContainsString(base64_encode('BACKUP_RETENTION=30'), $sets[1]);
        foreach ($log as $command) {
            $this->assertStringNotContainsString("echo 'BACKUP_", $command, 'no blind append');
            $this->assertStringNotContainsString('cut -d=', $command);
        }

        $dump = collect($log)->first(fn ($c) => str_contains($c, 'mysqldump'));
        $this->assertNotNull($dump);
        // The full decoded password ('=', '#' and an escaped '$' included) reaches mysqldump.
        $this->assertStringContainsString('p=a#ss$x', $dump);
        $this->assertStringContainsString('u_dM4WNoni', $dump);
        $this->assertStringContainsString('cust_x_52', $dump);
    }
}
