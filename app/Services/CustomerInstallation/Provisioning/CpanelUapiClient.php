<?php

namespace App\Services\CustomerInstallation\Provisioning;

use App\Models\CustomerRemoteServer;
use App\Services\CustomerInstallation\CredentialVault;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
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
     * @throws ConnectionException if every attempt exhausts on a transport failure.
     */
    public function call(CustomerRemoteServer $server, string $module, string $function, array $params = []): array
    {
        $url = $this->endpoint($server, $module, $function);

        $maxAttempts = max(1, (int) config('customer_installation.cpanel.uapi_retry_attempts', 2));
        $delayMs     = max(0, (int) config('customer_installation.cpanel.uapi_retry_delay', 300));

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            try {
                $response = Http::withHeaders([
                    'Authorization' => 'cpanel ' . $server->username . ':' . $this->credential($server),
                ])
                    ->withOptions(['verify' => (bool) config('customer_installation.cpanel.uapi_verify_ssl', true)])
                    ->timeout((int) config('customer_installation.cpanel.uapi_timeout', 30))
                    ->asForm()
                    ->post($url, $params);
            } catch (ConnectionException $e) {
                if ($attempt < $maxAttempts) {
                    $this->wait($delayMs);
                    continue;
                }
                throw $e;
            }

            // Retry ONLY a transient server-side failure (5xx) — a 4xx
            // (invalid token, permission denied, bad parameters) is a
            // permanent client-side failure and must never be retried.
            if ($response->serverError() && $attempt < $maxAttempts) {
                $this->wait($delayMs);
                continue;
            }

            if (!$response->successful()) {
                throw new RuntimeException(
                    "cPanel UAPI {$module}::{$function} HTTP {$response->status()}: " . $this->summarize($response)
                );
            }

            $json = $response->json();

            // A UAPI-level error (status=0) is a business/API failure, not a
            // transport failure — it is returned on a normal 200 response and
            // is never retried, even on the very first attempt.
            if (!is_array($json) || (int) ($json['status'] ?? 0) !== 1) {
                $errors = $json['errors'] ?? ['Unknown UAPI error'];
                throw new RuntimeException(
                    "cPanel UAPI {$module}::{$function} failed: " . implode('; ', (array) $errors)
                );
            }

            return $json['data'] ?? [];
        }

        // Unreachable in practice (the loop above always returns or throws
        // on its final iteration) but keeps the method's return type honest.
        throw new RuntimeException("cPanel UAPI {$module}::{$function}: retries exhausted");
    }

    private function wait(int $ms): void
    {
        if ($ms > 0) {
            usleep($ms * 1000);
        }
    }

    /**
     * Phase 15-G — Part 6: never embed the full raw response body in an
     * exception message. The body is attacker/host-controlled content that
     * previously flowed straight into StageResult output and
     * customer_installation_logs verbatim; a short, truncated summary is
     * enough to diagnose a failure without persisting arbitrary remote
     * content indefinitely.
     */
    private function summarize(Response $response): string
    {
        $body = trim((string) $response->body());

        if ($body === '') {
            return '(empty response body)';
        }

        return mb_strlen($body) > 200 ? mb_substr($body, 0, 200) . '…' : $body;
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
