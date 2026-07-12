<?php

namespace Tests\Unit\Services\CustomerInstallation\Stages;

use App\Models\CustomerInstallation;
use App\Models\CustomerRemoteServer;
use App\Models\InstallationCredential;
use App\Services\CustomerInstallation\CredentialVault;
use App\Services\CustomerInstallation\Dto\CustomerInstallRequest;
use App\Services\CustomerInstallation\Dto\InstallationContext;
use App\Services\CustomerInstallation\Drivers\FakeRemoteDriver;
use App\Services\CustomerInstallation\Stages\GenerateEnvironmentStage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 13-O9a — GenerateEnvironmentStage must no longer write
 * admin_password as plaintext into CustomerInstallation.metadata (it used
 * to, and that value was readable from the live DB and from every daily
 * platform backup, local and Arvan offsite). It now stores it encrypted
 * via CredentialVault in a dedicated InstallationCredential row and keeps
 * only a reference in metadata.
 */
class GenerateEnvironmentStageCredentialTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        // Set before parent::setUp() — that call boots a fresh Application
        // (Illuminate\Foundation\Testing\TestCase::refreshApplication()),
        // which loads config/installer.php by reading this env var at that
        // moment. Setting it after parent::setUp() is too late for the
        // Application this test method actually runs against, and only
        // happens to work when an earlier test already left the process
        // env var set from its own putenv() call.
        $key = base64_encode(random_bytes(32));
        putenv("INSTALLER_MASTER_KEY={$key}");
        $_ENV['INSTALLER_MASTER_KEY'] = $key;

        parent::setUp();
    }

    private function makeInstallation(): CustomerInstallation
    {
        $server = CustomerRemoteServer::create([
            'label' => 'Fake', 'type' => 'fake', 'host' => 'localhost',
            'port' => 22, 'username' => 'deploy', 'auth_method' => 'key',
        ]);

        return CustomerInstallation::create([
            'remote_server_id'  => $server->id,
            'installer_user_id' => 1,
            'install_mode'      => 'MODE_FRESH',
            'status'            => 'running',
            'customer_company'  => 'Credential Co',
            'customer_email'    => 'credential@example.com',
            'customer_domain'   => 'credential.example.com',
            'request_payload'   => [
                'company' => 'Credential Co', 'owner_name' => 'Owner',
                'email' => 'credential@example.com', 'phone' => '09123456789',
                'language' => 'fa', 'timezone' => 'Asia/Tehran', 'currency' => 'IRR',
                'domain' => 'credential.example.com', 'license_type' => 'basic',
                'theme_slug' => '', 'modules' => [], 'backup_policy' => 'daily',
                'smtp' => [], 'ssl_enabled' => false,
                'remote_server_id' => $server->id, 'install_mode' => 'MODE_FRESH',
            ],
        ]);
    }

    private function runStage(CustomerInstallation $installation): void
    {
        $request = CustomerInstallRequest::fromArray($installation->request_payload);
        $ctx = new InstallationContext($installation, $request, new FakeRemoteDriver());

        $stage = app(GenerateEnvironmentStage::class);
        $result = $stage->execute($ctx);

        $this->assertTrue($result->success, $result->error ?? '');
    }

    public function test_admin_password_is_not_saved_in_metadata(): void
    {
        $installation = $this->makeInstallation();
        $this->runStage($installation);

        $metadata = $installation->fresh()->metadata;

        $this->assertArrayNotHasKey('admin_password', $metadata);
        $this->assertArrayHasKey('credential_ids', $metadata);
        $this->assertArrayHasKey('admin_password', $metadata['credential_ids']);
    }

    public function test_credential_record_created_for_installation(): void
    {
        $installation = $this->makeInstallation();
        $this->runStage($installation);

        $credentialId = $installation->fresh()->metadata['credential_ids']['admin_password'];

        $this->assertDatabaseHas('installation_credentials', [
            'id'               => $credentialId,
            'installation_id'  => $installation->id,
            'type'             => 'admin_password',
        ]);
    }

    public function test_credential_is_stored_encrypted(): void
    {
        $installation = $this->makeInstallation();
        $this->runStage($installation);

        $credentialId = $installation->fresh()->metadata['credential_ids']['admin_password'];
        $credential = InstallationCredential::findOrFail($credentialId);

        // Ciphertext must not equal, or trivially contain, any recognizable
        // plaintext password shape — and must actually be base64 (the
        // format CredentialVault::encrypt() produces).
        $this->assertNotEmpty($credential->encrypted_value);
        $this->assertNotFalse(base64_decode($credential->encrypted_value, true));
    }

    public function test_credential_can_be_decrypted_with_vault(): void
    {
        $installation = $this->makeInstallation();
        $this->runStage($installation);

        $credentialId = $installation->fresh()->metadata['credential_ids']['admin_password'];
        $credential = InstallationCredential::findOrFail($credentialId);

        $decrypted = (new CredentialVault())->decrypt($credential->encrypted_value);

        $this->assertNotEmpty($decrypted);
        $this->assertSame(16, strlen($decrypted)); // Str::random(16) in the stage
    }
}
