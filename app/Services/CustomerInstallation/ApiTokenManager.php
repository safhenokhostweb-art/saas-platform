<?php

namespace App\Services\CustomerInstallation;

use App\Models\CustomerRemoteServer;
use RuntimeException;

/**
 * Manages API tokens for panel-type deployment targets (cPanel, DirectAdmin).
 *
 * Tokens are NEVER returned or logged in plaintext.
 * All storage goes through CredentialVault (AES-256-GCM).
 * Metadata (created_at, last_used_at) tracked for audit purposes.
 */
class ApiTokenManager
{
    public function __construct(
        private readonly CredentialVault $vault,
    ) {}

    // ─── CRUD ─────────────────────────────────────────────────────────────────

    public function store(CustomerRemoteServer $server, string $token): void
    {
        if (empty(trim($token))) {
            throw new RuntimeException('API token cannot be empty.');
        }

        $server->update([
            'api_token_encrypted'  => $this->vault->encrypt($token),
            'api_token_created_at' => now(),
            'api_token_last_used_at' => null,
            'auth_method'          => 'api_token',
        ]);
    }

    public function replace(CustomerRemoteServer $server, string $newToken): void
    {
        if (empty(trim($newToken))) {
            throw new RuntimeException('Replacement API token cannot be empty.');
        }

        $server->update([
            'api_token_encrypted'    => $this->vault->encrypt($newToken),
            'api_token_created_at'   => now(),
            'api_token_last_used_at' => null,
        ]);
    }

    public function rotate(CustomerRemoteServer $server, string $newToken): void
    {
        $this->replace($server, $newToken);
    }

    public function delete(CustomerRemoteServer $server): void
    {
        $server->update([
            'api_token_encrypted'    => null,
            'api_token_created_at'   => null,
            'api_token_last_used_at' => null,
        ]);
    }

    // ─── Queries ──────────────────────────────────────────────────────────────

    public function hasToken(CustomerRemoteServer $server): bool
    {
        return !empty($server->api_token_encrypted);
    }

    public function recordUsage(CustomerRemoteServer $server): void
    {
        $server->updateQuietly(['api_token_last_used_at' => now()]);
    }

    /**
     * Returns a masked preview of the token for display ("••••••••••••xxxx").
     * Never returns the plaintext token.
     */
    public function maskedPreview(CustomerRemoteServer $server): string
    {
        if (!$this->hasToken($server)) {
            return '—';
        }

        try {
            $plain = $this->vault->decrypt($server->api_token_encrypted);
            $len   = strlen($plain);
            if ($len <= 4) {
                return str_repeat('•', $len);
            }
            return str_repeat('•', $len - 4) . substr($plain, -4);
        } catch (\Throwable) {
            return '••••••[خطای رمزگشایی]';
        }
    }

    // ─── Token Health ─────────────────────────────────────────────────────────

    /**
     * Returns 'ok', 'stale' (>30 days since last use), 'unused', or 'missing'.
     */
    public function tokenHealth(CustomerRemoteServer $server): string
    {
        if (!$this->hasToken($server)) {
            return 'missing';
        }

        if (!$server->api_token_last_used_at) {
            return 'unused';
        }

        if ($server->api_token_last_used_at->lt(now()->subDays(30))) {
            return 'stale';
        }

        return 'ok';
    }
}
