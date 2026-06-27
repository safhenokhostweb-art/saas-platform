<?php

namespace Tests\Unit\CustomerInstallation;

use App\Services\CustomerInstallation\CredentialVault;
use Tests\TestCase;

class CredentialVaultTest extends TestCase
{
    private function makeVault(): CredentialVault
    {
        $key = base64_encode(random_bytes(32));
        putenv("INSTALLER_MASTER_KEY={$key}");
        $_ENV['INSTALLER_MASTER_KEY'] = $key;
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
        putenv('INSTALLER_MASTER_KEY=');
        $_ENV['INSTALLER_MASTER_KEY'] = '';

        $this->expectException(\RuntimeException::class);
        new CredentialVault();
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
}
