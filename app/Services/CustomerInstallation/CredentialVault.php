<?php

namespace App\Services\CustomerInstallation;

use App\Models\CustomerRemoteServer;
use RuntimeException;

class CredentialVault
{
    private ?string $key = null;
    private string $algorithm = 'aes-256-gcm';

    public function __construct()
    {
        // Key is loaded lazily — constructor does NOT throw so artisan cache/migrate
        // commands work even when INSTALLER_MASTER_KEY is absent from the environment.
    }

    private function resolveKey(): string
    {
        if ($this->key !== null) {
            return $this->key;
        }

        $raw = env('INSTALLER_MASTER_KEY', '');
        if (empty($raw)) {
            throw new RuntimeException('INSTALLER_MASTER_KEY environment variable is not set.');
        }
        $key = base64_decode($raw, true) ?: $raw;
        if (strlen($key) < 16) {
            throw new RuntimeException('INSTALLER_MASTER_KEY must be at least 16 bytes after base64 decode.');
        }

        return $this->key = $key;
    }

    public function encrypt(string $plaintext): string
    {
        $nonce = random_bytes(12);
        $tag = '';
        $ciphertext = openssl_encrypt(
            $plaintext,
            $this->algorithm,
            $this->resolveKey(),
            OPENSSL_RAW_DATA,
            $nonce,
            $tag
        );

        if ($ciphertext === false) {
            throw new RuntimeException('Encryption failed: ' . openssl_error_string());
        }

        return base64_encode($nonce . $ciphertext . $tag);
    }

    public function decrypt(string $encrypted): string
    {
        $raw = base64_decode($encrypted, true);
        if ($raw === false) {
            throw new RuntimeException('Invalid encrypted value: base64 decode failed.');
        }

        // nonce = 12 bytes, tag = 16 bytes
        if (strlen($raw) < 28) {
            throw new RuntimeException('Invalid encrypted value: too short.');
        }

        $nonce = substr($raw, 0, 12);
        $tag = substr($raw, -16);
        $ciphertext = substr($raw, 12, -16);

        $plaintext = openssl_decrypt(
            $ciphertext,
            $this->algorithm,
            $this->resolveKey(),
            OPENSSL_RAW_DATA,
            $nonce,
            $tag
        );

        if ($plaintext === false) {
            throw new RuntimeException('Decryption failed: authentication tag mismatch or wrong key.');
        }

        return $plaintext;
    }

    public function rotate(string $newMasterKeyBase64): int
    {
        $this->resolveKey(); // ensure current key is loaded before cloning
        $newVault = clone $this;
        $newKey = base64_decode($newMasterKeyBase64, true) ?: $newMasterKeyBase64;
        if (strlen($newKey) < 16) {
            throw new RuntimeException('New master key too short.');
        }
        $newVault->key = $newKey;

        $rotated = 0;

        // Rotate all encrypted fields on CustomerRemoteServer
        CustomerRemoteServer::all()->each(function (CustomerRemoteServer $server) use ($newVault, &$rotated) {
            $updates = [];

            foreach (['private_key_encrypted', 'password_encrypted', 'api_token_encrypted'] as $field) {
                if (!empty($server->{$field})) {
                    $plain = $this->decrypt($server->{$field});
                    $updates[$field] = $newVault->encrypt($plain);
                }
            }

            if (!empty($updates)) {
                $server->updateQuietly($updates);
                $rotated++;
            }
        });

        // Rotate all SshKey encrypted fields
        \App\Models\SshKey::all()->each(function (\App\Models\SshKey $key) use ($newVault, &$rotated) {
            $updates = [];

            if (!empty($key->private_key_encrypted)) {
                $plain = $this->decrypt($key->private_key_encrypted);
                $updates['private_key_encrypted'] = $newVault->encrypt($plain);
            }
            if (!empty($key->passphrase_encrypted)) {
                $plain = $this->decrypt($key->passphrase_encrypted);
                $updates['passphrase_encrypted'] = $newVault->encrypt($plain);
            }

            if (!empty($updates)) {
                $key->updateQuietly($updates);
                $rotated++;
            }
        });

        return $rotated;
    }
}
