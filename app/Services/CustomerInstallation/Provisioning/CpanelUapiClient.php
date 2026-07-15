<?php

namespace App\Services\CustomerInstallation\Provisioning;

use App\Models\CustomerRemoteServer;
use App\Services\CustomerInstallation\CredentialVault;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Phase 14-F2 — thin, reusable HTTP client for cPanel's UAPI.
 *
 * Deliberately NOT part of CpanelDeploymentDriver: the Driver's job (Phase
 * 14-C) is generic shell/file transport only; this is a separate, narrowly
 * scoped HTTP concern consumed by CpanelDatabaseProvisioner (and any future
 * cPanel-API-based provisioner — cron, domains, etc.).
 *
 * Authentication: cPanel UAPI accepts "Authorization: cpanel
 * {username}:{credential}" where the credential is either the account
 * password or an API token — same header shape either way. This client
 * prefers an API token when present (Phase 14-C's SSH transport rejects
 * api_token entirely; UAPI has no such limitation) and falls back to the
 * password.
 */
class CpanelUapiClient
{
    public function __construct(private readonly CredentialVault $vault) {}

    /**
     * @return array The decoded "data" payload on success.
     * @throws RuntimeException on HTTP failure or a UAPI-level error (status=0).
     */
    public function call(CustomerRemoteServer $server, string $module, string $function, array $params = []): array
    {
        $url = $this->endpoint($server, $module, $function);

        $response = Http::withHeaders([
            'Authorization' => 'cpanel ' . $server->username . ':' . $this->credential($server),
        ])
            ->withOptions(['verify' => (bool) config('customer_installation.cpanel.uapi_verify_ssl', true)])
            ->timeout((int) config('customer_installation.cpanel.uapi_timeout', 30))
            ->asForm()
            ->post($url, $params);

        if (!$response->successful()) {
            throw new RuntimeException(
                "cPanel UAPI {$module}::{$function} HTTP {$response->status()}: " . $response->body()
            );
        }

        $json = $response->json();

        if (!is_array($json) || (int) ($json['status'] ?? 0) !== 1) {
            $errors = $json['errors'] ?? ['Unknown UAPI error'];
            throw new RuntimeException(
                "cPanel UAPI {$module}::{$function} failed: " . implode('; ', (array) $errors)
            );
        }

        return $json['data'] ?? [];
    }

    private function endpoint(CustomerRemoteServer $server, string $module, string $function): string
    {
        $host = $this->bareHost($server->host);
        $port = (int) config('customer_installation.cpanel.uapi_port', 2083);

        return "https://{$host}:{$port}/execute/{$module}/{$function}";
    }

    /**
     * @throws RuntimeException if neither credential is available.
     */
    private function credential(CustomerRemoteServer $server): string
    {
        if (!empty($server->api_token_encrypted)) {
            return $this->vault->decrypt($server->api_token_encrypted);
        }

        if (!empty($server->password_encrypted)) {
            return $this->vault->decrypt($server->password_encrypted);
        }

        throw new RuntimeException('No cPanel credential (API token or password) available for UAPI authentication.');
    }

    /**
     * Defensive against a host value stored with a scheme/port already
     * embedded (e.g. "https://box106.parsvds.com:2083" — the exact shape
     * behind the Installation #44 root-cause audit). Strips both so the
     * URL this client builds is never doubled-up.
     */
    private function bareHost(string $host): string
    {
        $stripped = preg_replace('#^https?://#i', '', trim($host));

        return explode(':', $stripped, 2)[0];
    }
}
