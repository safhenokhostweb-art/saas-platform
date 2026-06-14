<?php
namespace App\Services\Infrastructure\Monitoring;

use App\Models\Site;
use App\Models\Infrastructure\InfraSiteHealthLog;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Performs health check on a single customer site via HTTP.
 * Checks connectivity, /api/health JSON, and SSL certificate.
 */
class SiteHealthChecker
{
    public function check(Site $site): InfraSiteHealthLog
    {
        $domain = $this->resolveDomain($site);
        $baseUrl = $this->buildBaseUrl($domain);

        $log = new InfraSiteHealthLog([
            'site_id'    => $site->id,
            'domain'     => $domain,
            'status'     => 'unknown',
            'checked_at' => now(),
        ]);

        try {
            [$httpStatus, $responseTimeMs, $healthPayload] = $this->fetchHealth($baseUrl);
            $log->http_status      = $httpStatus;
            $log->response_time_ms = $responseTimeMs;

            if ($healthPayload !== null) {
                $log->db_status    = $healthPayload['db']    ?? 'unknown';
                $log->redis_status = $healthPayload['redis'] ?? 'unknown';
                $log->queue_status = $healthPayload['queue'] ?? 'unknown';
            } else {
                $log->db_status    = 'unknown';
                $log->redis_status = 'unknown';
                $log->queue_status = 'unknown';
            }

            $sslInfo = $this->checkSsl($domain);
            $log->ssl_status        = $sslInfo['status'];
            $log->ssl_expires_at    = $sslInfo['expires_at'] ?? null;
            $log->ssl_days_remaining = $sslInfo['days_remaining'] ?? null;

            $log->status = $this->computeStatus($httpStatus, $log->db_status, $log->redis_status, $sslInfo);
        } catch (\Throwable $e) {
            $log->status        = 'critical';
            $log->error_message = $e->getMessage();
            Log::warning("SiteHealthChecker: site {$site->id} ({$domain}) check failed", [
                'error' => $e->getMessage(),
            ]);
        }

        $log->save();
        return $log;
    }

    private function fetchHealth(string $baseUrl): array
    {
        $start = microtime(true);
        try {
            $response = Http::timeout(10)
                ->withoutVerifying()
                ->get("{$baseUrl}/api/health");

            $ms = (int) ((microtime(true) - $start) * 1000);
            $payload = null;

            if ($response->successful()) {
                $decoded = $response->json();
                if (is_array($decoded) && isset($decoded['app'])) {
                    $payload = $decoded;
                }
            }

            return [$response->status(), $ms, $payload];
        } catch (\Throwable $e) {
            $ms = (int) ((microtime(true) - $start) * 1000);
            throw $e;
        }
    }

    private function checkSsl(string $domain): array
    {
        if (str_starts_with($domain, 'localhost') || str_starts_with($domain, '127.') || str_starts_with($domain, '192.168.')) {
            return ['status' => 'unknown'];
        }

        try {
            $context = stream_context_create([
                'ssl' => [
                    'capture_peer_cert' => true,
                    'verify_peer'       => false,
                    'verify_peer_name'  => false,
                ],
            ]);

            $conn = @stream_socket_client(
                "ssl://{$domain}:443",
                $errno, $errstr, 5,
                STREAM_CLIENT_CONNECT,
                $context
            );

            if (! $conn) {
                return ['status' => 'invalid', 'error' => $errstr];
            }

            $cert = stream_context_get_params($conn)['options']['ssl']['peer_certificate'] ?? null;
            fclose($conn);

            if (! $cert) {
                return ['status' => 'unknown'];
            }

            $certInfo  = openssl_x509_parse($cert);
            $expiresAt = \Carbon\Carbon::createFromTimestamp($certInfo['validTo_time_t']);
            $daysLeft  = (int) now()->diffInDays($expiresAt, false);

            $status = match (true) {
                $daysLeft < 0   => 'invalid',
                $daysLeft < 10  => 'critical',
                $daysLeft < 30  => 'expiring_soon',
                default         => 'valid',
            };

            return [
                'status'          => $status,
                'expires_at'      => $expiresAt->toDateString(),
                'days_remaining'  => $daysLeft,
            ];
        } catch (\Throwable $e) {
            return ['status' => 'unknown', 'error' => $e->getMessage()];
        }
    }

    private function computeStatus(int $httpStatus, string $dbStatus, string $redisStatus, array $sslInfo): string
    {
        if ($httpStatus >= 500) {
            return 'critical';
        }
        if ($dbStatus === 'error' || $redisStatus === 'error') {
            return 'critical';
        }
        if ($httpStatus === 0 || $httpStatus >= 400) {
            return 'warning';
        }
        if (in_array($sslInfo['status'] ?? '', ['critical', 'invalid'], true)) {
            return 'critical';
        }
        if (($sslInfo['status'] ?? '') === 'expiring_soon') {
            return 'warning';
        }
        return 'healthy';
    }

    private function resolveDomain(Site $site): string
    {
        return $site->domain ?: 'localhost';
    }

    private function buildBaseUrl(string $domain): string
    {
        if (str_starts_with($domain, 'http://') || str_starts_with($domain, 'https://')) {
            return rtrim($domain, '/');
        }
        $scheme = (str_contains($domain, 'localhost') || str_starts_with($domain, '127.')) ? 'http' : 'https';
        return "{$scheme}://{$domain}";
    }
}
