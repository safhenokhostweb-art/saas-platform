<?php
namespace App\Services\Infrastructure;

use App\Models\RemoteAgent;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * HMAC-signed HTTP client for the Remote Agent Layer (INFRA-6).
 *
 * Each request is signed with:
 *   X-Agent-Timestamp  — Unix timestamp (seconds)
 *   X-Agent-Signature  — HMAC-SHA256 of "{timestamp}.{METHOD}.{path}.{body}"
 *
 * Supported agent commands:
 *   health   — GET  /agent/health
 *   backup   — POST /agent/backup
 *   deploy   — POST /agent/deploy
 *   restart  — POST /agent/restart
 *
 * The agent on the tenant server verifies the signature before executing.
 * Requests older than 5 minutes (clock drift) are rejected by the agent.
 */
class RemoteAgentClient
{
    private const TIMEOUT = 15;
    private const REPLAY_WINDOW = 300; // seconds

    public function health(RemoteAgent $agent): array
    {
        return $this->send($agent, 'GET', '/agent/health');
    }

    public function triggerBackup(RemoteAgent $agent): array
    {
        return $this->send($agent, 'POST', '/agent/backup');
    }

    public function triggerDeploy(RemoteAgent $agent, array $options = []): array
    {
        return $this->send($agent, 'POST', '/agent/deploy', $options);
    }

    public function triggerRestart(RemoteAgent $agent, string $service = 'php-fpm'): array
    {
        return $this->send($agent, 'POST', '/agent/restart', ['service' => $service]);
    }

    private function send(RemoteAgent $agent, string $method, string $path, array $body = []): array
    {
        $timestamp = (string) time();
        $bodyJson  = empty($body) ? '' : json_encode($body, JSON_UNESCAPED_UNICODE);
        $signature = $this->sign($agent->getDecryptedSecret(), $timestamp, $method, $path, $bodyJson);

        $url = rtrim($agent->base_url, '/') . $path;

        try {
            $req = Http::timeout(self::TIMEOUT)
                ->withHeaders([
                    'X-Agent-Timestamp' => $timestamp,
                    'X-Agent-Signature' => $signature,
                    'Content-Type'      => 'application/json',
                    'Accept'            => 'application/json',
                ]);

            $response = $method === 'GET'
                ? $req->get($url)
                : $req->withBody($bodyJson, 'application/json')->post($url);

            $data = $response->json() ?? [];

            $this->updateHealth($agent, $response->successful() ? 'healthy' : 'degraded', $data);

            return [
                'success' => $response->successful(),
                'status'  => $response->status(),
                'data'    => $data,
            ];
        } catch (\Throwable $e) {
            Log::warning("RemoteAgentClient: {$agent->name} failed", [
                'path'  => $path,
                'error' => $e->getMessage(),
            ]);

            $this->updateHealth($agent, 'offline', ['error' => $e->getMessage()]);

            return [
                'success' => false,
                'status'  => 0,
                'error'   => $e->getMessage(),
            ];
        }
    }

    private function sign(string $secret, string $timestamp, string $method, string $path, string $body): string
    {
        $payload = implode('.', [strtoupper($method), $path, $timestamp, $body]);
        return hash_hmac('sha256', $payload, $secret);
    }

    private function updateHealth(RemoteAgent $agent, string $status, array $data): void
    {
        $agent->update([
            'last_health_status'     => $status,
            'last_health_checked_at' => now(),
            'last_health_data'       => $data,
        ]);
    }
}
