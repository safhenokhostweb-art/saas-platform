<?php

namespace Tests\Unit\CustomerInstallation;

use App\Models\Customer;
use App\Models\CustomerInstallation;
use App\Models\CustomerRemoteServer;
use App\Models\InstallationCredential;
use App\Models\Site;
use App\Services\CustomerInstallation\CredentialVault;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CredentialVaultTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Sets the resolved config value directly rather than putenv(), which
     * only takes effect on the next Application boot (too late — the app for
     * the current test method already booted in setUp()) and otherwise makes
     * tests order-dependent on whatever a previous test's putenv() left in
     * the process environment.
     */
    private function makeVault(): CredentialVault
    {
        $key = base64_encode(random_bytes(32));
        config(['installer.master_key' => $key]);
        return new CredentialVault();
    }

    public function test_encrypt_and_decrypt_roundtrip(): void
    {
        $vault = $this->makeVault();
        $plain = 'super-secret-ssh-private-key-content';

        $encrypted = $vault->encrypt($plain);
        $decrypted = $vault->decrypt($encrypted);

        $this->assertNotEquals($plain, $encrypted);
        $this->assertEquals($plain, $decrypted);
    }

    public function test_encrypt_produces_different_ciphertext_each_time(): void
    {
        $vault = $this->makeVault();
        $plain = 'same-plaintext';

        $enc1 = $vault->encrypt($plain);
        $enc2 = $vault->encrypt($plain);

        $this->assertNotEquals($enc1, $enc2, 'Each encryption must use a unique nonce');
    }

    public function test_decrypt_fails_on_tampered_ciphertext(): void
    {
        $vault = $this->makeVault();
        $encrypted = $vault->encrypt('secret');
        $tampered = base64_encode(str_repeat('X', 50));

        $this->expectException(\RuntimeException::class);
        $vault->decrypt($tampered);
    }

    public function test_decrypt_fails_on_short_input(): void
    {
        $vault = $this->makeVault();

        $this->expectException(\RuntimeException::class);
        $vault->decrypt(base64_encode('short'));
    }

    public function test_missing_master_key_throws(): void
    {
        config(['installer.master_key' => '']);

        // The constructor is deliberately lazy — it must not throw (so
        // artisan cache/migrate commands work even when
        // INSTALLER_MASTER_KEY is absent). The missing-key error only
        // surfaces once encrypt()/decrypt() actually resolves the key.
        $this->expectException(\RuntimeException::class);
        (new CredentialVault())->encrypt('anything');
    }

    public function test_encrypts_empty_string(): void
    {
        $vault = $this->makeVault();
        $encrypted = $vault->encrypt('');
        $this->assertEquals('', $vault->decrypt($encrypted));
    }

    public function test_encrypts_long_private_key(): void
    {
        $vault = $this->makeVault();
        $longKey = str_repeat('A', 4096);
        $encrypted = $vault->encrypt($longKey);
        $this->assertEquals($longKey, $vault->decrypt($encrypted));
    }

    /**
     * Phase 13-O9c — CredentialVault::rotate() must also cover
     * InstallationCredential rows: encrypt under the old master key, rotate
     * to a new one, and confirm the value still decrypts under the new key.
     */
    public function test_rotation_keeps_installation_credential_decryptable(): void
    {
        $vault = $this->makeVault();

        $server = CustomerRemoteServer::create([
            'label' => 'Fake', 'type' => 'fake', 'host' => 'localhost',
            'port' => 22, 'username' => 'deploy', 'auth_method' => 'key',
        ]);
        $installation = CustomerInstallation::create([
            'remote_server_id'  => $server->id,
            'installer_user_id' => 1,
            'install_mode'      => 'MODE_FRESH',
            'status'            => 'running',
            'customer_company'  => 'Rotation Co',
            'customer_email'    => 'rotation@example.com',
            'customer_domain'   => 'rotation.example.com',
        ]);

        $plain = 'admin-password-before-rotation';
        $credential = InstallationCredential::create([
            'installation_id' => $installation->id,
            'type'            => 'admin_password',
            'encrypted_value' => $vault->encrypt($plain),
        ]);

        $newKey = base64_encode(random_bytes(32));
        $rotated = $vault->rotate($newKey);

        $this->assertGreaterThanOrEqual(1, $rotated);

        config(['installer.master_key' => $newKey]);
        $vaultAfterRotation = new CredentialVault();

        $decrypted = $vaultAfterRotation->decrypt($credential->fresh()->encrypted_value);
        $this->assertSame($plain, $decrypted);
    }

    /**
     * Phase 13-O12a — CredentialVault::rotate() must also cover
     * Site::db_password_encrypted: encrypt under the old master key, rotate
     * to a new one, and confirm the value still decrypts under the new key.
     */
    public function test_rotation_keeps_site_db_password_decryptable(): void
    {
        $vault = $this->makeVault();

        $customer = Customer::create(['name' => 'Rotation Site Co', 'mobile' => '09121234567']);
        $plain = 'site-db-password-before-rotation';
        $site = Site::create([
            'customer_id'           => $customer->id,
            'title'                 => 'Rotation Site',
            'db_password_encrypted' => $vault->encrypt($plain),
        ]);

        $newKey = base64_encode(random_bytes(32));
        $rotated = $vault->rotate($newKey);

        $this->assertGreaterThanOrEqual(1, $rotated);

        config(['installer.master_key' => $newKey]);
        $vaultAfterRotation = new CredentialVault();

        $decrypted = $vaultAfterRotation->decrypt($site->fresh()->db_password_encrypted);
        $this->assertSame($plain, $decrypted);
    }
}
