<?php

namespace App\Services\CustomerInstallation;

use App\Models\CustomerRemoteServer;
use App\Models\SshKey;
use phpseclib3\Crypt\EC;
use phpseclib3\Crypt\PublicKeyLoader;
use phpseclib3\Crypt\RSA;
use phpseclib3\Net\SSH2;
use RuntimeException;

/**
 * Manages the lifecycle of SSH key pairs:
 *   generate → store (encrypted) → install on target → test → activate → rotate → revoke
 *
 * Private keys are NEVER held in plaintext beyond the method call that generates them.
 * All storage goes through CredentialVault (AES-256-GCM).
 */
class SshKeyManager
{
    public function __construct(
        private readonly CredentialVault $vault,
    ) {}

    // ─── Generation ───────────────────────────────────────────────────────────

    public function generateEd25519(string $label, ?string $comment = null, ?int $targetId = null): SshKey
    {
        $comment ??= 'saas-platform-' . date('Y-m-d');
        $privateKeyObj = EC::createKey('Ed25519');

        $privateKeyPem = $privateKeyObj->toString('OpenSSH', ['comment' => $comment]);
        $publicKey     = $privateKeyObj->getPublicKey()->toString('OpenSSH', ['comment' => $comment]);
        $fingerprint   = $this->computeFingerprint($publicKey);

        return SshKey::create([
            'label'                  => $label,
            'key_type'               => 'ed25519',
            'public_key'             => $publicKey,
            'private_key_encrypted'  => $this->vault->encrypt($privateKeyPem),
            'fingerprint'            => $fingerprint,
            'deployment_target_id'   => $targetId,
            'status'                 => 'pending',
        ]);
    }

    public function generateRsa4096(string $label, ?string $comment = null, ?int $targetId = null): SshKey
    {
        $comment ??= 'saas-platform-' . date('Y-m-d');
        $privateKeyObj = RSA::createKey(4096);

        $privateKeyPem = $privateKeyObj->toString('OpenSSH', ['comment' => $comment]);
        $publicKey     = $privateKeyObj->getPublicKey()->toString('OpenSSH', ['comment' => $comment]);
        $fingerprint   = $this->computeFingerprint($publicKey);

        return SshKey::create([
            'label'                  => $label,
            'key_type'               => 'rsa4096',
            'public_key'             => $publicKey,
            'private_key_encrypted'  => $this->vault->encrypt($privateKeyPem),
            'fingerprint'            => $fingerprint,
            'deployment_target_id'   => $targetId,
            'status'                 => 'pending',
        ]);
    }

    // ─── Import ───────────────────────────────────────────────────────────────

    public function import(string $label, string $privateKeyPem, ?string $comment = null, ?int $targetId = null): SshKey
    {
        $comment ??= 'imported-' . date('Y-m-d');

        // Validate and extract public key
        try {
            $loaded    = RSA::loadPrivateKey($privateKeyPem);
            $publicKey = $loaded->getPublicKey()->toString('OpenSSH', ['comment' => $comment]);
            $keyType   = 'rsa4096';
        } catch (\Throwable) {
            try {
                $loaded    = EC::loadPrivateKey($privateKeyPem);
                $publicKey = $loaded->getPublicKey()->toString('OpenSSH', ['comment' => $comment]);
                $keyType   = 'ed25519';
            } catch (\Throwable $e) {
                throw new RuntimeException('Invalid private key: ' . $e->getMessage());
            }
        }

        $fingerprint = $this->computeFingerprint($publicKey);

        return SshKey::create([
            'label'                  => $label,
            'key_type'               => $keyType,
            'public_key'             => $publicKey,
            'private_key_encrypted'  => $this->vault->encrypt($privateKeyPem),
            'fingerprint'            => $fingerprint,
            'deployment_target_id'   => $targetId,
            'status'                 => 'pending',
        ]);
    }

    // ─── Installation ─────────────────────────────────────────────────────────

    /**
     * Install the public key into ~/.ssh/authorized_keys on the deployment target.
     * Uses the target's CURRENT credentials to connect.
     *
     * Returns true on success, throws RuntimeException on failure.
     */
    public function installOnTarget(SshKey $key, CustomerRemoteServer $server): bool
    {
        $ssh = $this->connectToServer($server);

        try {
            $publicKey = trim($key->public_key);

            // Idempotent: check if key already present
            $checkResult = $ssh->exec(
                'grep -qF ' . escapeshellarg($publicKey) . ' ~/.ssh/authorized_keys 2>/dev/null && echo EXISTS || echo MISSING'
            );
            if (trim($checkResult) === 'EXISTS') {
                $key->update(['installed_at' => now(), 'status' => 'active']);
                return true;
            }

            $installCmd =
                'mkdir -p ~/.ssh && ' .
                'chmod 700 ~/.ssh && ' .
                'echo ' . escapeshellarg($publicKey) . ' >> ~/.ssh/authorized_keys && ' .
                'chmod 600 ~/.ssh/authorized_keys && ' .
                'echo OK';

            $result = trim($ssh->exec($installCmd));
            if ($result !== 'OK') {
                throw new RuntimeException("Key installation failed. Server output: {$result}");
            }

            $key->update([
                'installed_at'         => now(),
                'deployment_target_id' => $server->id,
                'status'               => 'active',
            ]);

            $server->update(['key_installed_at' => now()]);

            return true;
        } finally {
            try { $ssh->disconnect(); } catch (\Throwable) {}
        }
    }

    // ─── Testing ──────────────────────────────────────────────────────────────

    /**
     * Test that a managed SSH key can authenticate against the server.
     */
    public function testKey(SshKey $key, CustomerRemoteServer $server): bool
    {
        try {
            $privateKeyPem = $this->vault->decrypt($key->private_key_encrypted);
            $loadedKey     = PublicKeyLoader::load($privateKeyPem);
        } catch (\Throwable $e) {
            throw new RuntimeException('Cannot load private key: ' . $e->getMessage());
        }

        $ssh = new SSH2($server->host, $server->port, 10);
        if (!$ssh->login($server->username, $loadedKey)) {
            return false;
        }

        $ssh->disconnect();
        $key->update(['last_used_at' => now()]);

        return true;
    }

    // ─── Activation ───────────────────────────────────────────────────────────

    /**
     * Activate a key on a server: stores the private key in the server's credential slot
     * and switches auth_method to 'key'.
     */
    public function activateOnServer(SshKey $key, CustomerRemoteServer $server): void
    {
        $privateKeyPem = $this->vault->decrypt($key->private_key_encrypted);

        $server->update([
            'auth_method'           => 'key',
            'private_key_encrypted' => $this->vault->encrypt($privateKeyPem),
            'key_fingerprint'       => $key->fingerprint,
            'ssh_key_id'            => $key->id,
        ]);

        $key->update(['status' => 'active', 'deployment_target_id' => $server->id]);
    }

    // ─── Rotation ─────────────────────────────────────────────────────────────

    /**
     * Generate a new key, install it, activate it, then mark the old key as rotated.
     * Returns the new SshKey.
     */
    public function rotateKey(SshKey $oldKey, CustomerRemoteServer $server): SshKey
    {
        $newLabel = $oldKey->label . ' (rotated ' . date('Y-m-d') . ')';
        $newKey   = $oldKey->key_type === 'ed25519'
            ? $this->generateEd25519($newLabel, null, $server->id)
            : $this->generateRsa4096($newLabel, null, $server->id);

        $this->installOnTarget($newKey, $server);
        $this->activateOnServer($newKey, $server);

        $oldKey->update(['status' => 'rotated', 'rotated_at' => now()]);

        // Optionally remove old key from authorized_keys
        try {
            $ssh = $this->connectToServer($server);
            $oldPublic = trim($oldKey->public_key);
            $ssh->exec('grep -vF ' . escapeshellarg($oldPublic) . ' ~/.ssh/authorized_keys > ~/.ssh/authorized_keys.tmp && mv ~/.ssh/authorized_keys.tmp ~/.ssh/authorized_keys');
            $ssh->disconnect();
        } catch (\Throwable) {
            // Non-fatal: old key remains in authorized_keys but is tracked as rotated
        }

        return $newKey;
    }

    // ─── Revocation ───────────────────────────────────────────────────────────

    public function revokeKey(SshKey $key, ?CustomerRemoteServer $server = null): void
    {
        if ($server) {
            try {
                $ssh       = $this->connectToServer($server);
                $oldPublic = trim($key->public_key);
                $ssh->exec('grep -vF ' . escapeshellarg($oldPublic) . ' ~/.ssh/authorized_keys > ~/.ssh/authorized_keys.tmp && mv ~/.ssh/authorized_keys.tmp ~/.ssh/authorized_keys 2>/dev/null || true');
                $ssh->disconnect();
            } catch (\Throwable) {}
        }

        $key->update(['status' => 'revoked']);
    }

    // ─── Validation ───────────────────────────────────────────────────────────

    public function validatePrivateKey(string $pem): bool
    {
        try {
            PublicKeyLoader::load($pem);
            return true;
        } catch (\Throwable) {}

        return false;
    }

    // ─── Fingerprint ──────────────────────────────────────────────────────────

    public function computeFingerprint(string $publicKeyOpenSsh): string
    {
        $parts = explode(' ', trim($publicKeyOpenSsh));
        if (count($parts) < 2) {
            throw new RuntimeException('Invalid OpenSSH public key format');
        }

        $keyBlob = base64_decode($parts[1], true);
        if ($keyBlob === false) {
            throw new RuntimeException('Cannot decode public key blob');
        }

        return 'SHA256:' . rtrim(base64_encode(hash('sha256', $keyBlob, true)), '=');
    }

    // ─── Internal helpers ─────────────────────────────────────────────────────

    private function connectToServer(CustomerRemoteServer $server): SSH2
    {
        $ssh = new SSH2($server->host, $server->port, 10);

        if ($server->auth_method === 'key' && $server->private_key_encrypted) {
            $pem = $this->vault->decrypt($server->private_key_encrypted);
            $key = PublicKeyLoader::load($pem);
            if (!$ssh->login($server->username, $key)) {
                throw new RuntimeException("SSH key auth failed for {$server->host}");
            }
        } elseif ($server->password_encrypted) {
            $password = $this->vault->decrypt($server->password_encrypted);
            if (!$ssh->login($server->username, $password)) {
                throw new RuntimeException("SSH password auth failed for {$server->host}");
            }
        } else {
            throw new RuntimeException("No credentials available for {$server->host}");
        }

        return $ssh;
    }
}
