<?php
namespace App\Services\Infrastructure\Monitoring;

use App\Models\Site;
use App\Models\Infrastructure\InfraSiteHealthLog;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Performs health check on a single customer site via HTTP.
 * Checks connectivity, /api/health JSON (db/redis/queue/failed_jobs/disk/memory), SSL certificate,
 * and domain expiry from the Site model.
 */
class SiteHealthChecker
{
    public function check(Site $site): InfraSiteHealthLog
    {
        $domain  = $this->resolveDomain($site);
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
                // Extended fields from enhanced /api/health
                $log->failed_jobs        = isset($healthPayload['failed_jobs']) ? (int) $healthPayload['failed_jobs'] : null;
                $log->disk_used_percent  = isset($healthPayload['disk_used_percent']) ? (int) $healthPayload['disk_used_percent'] : null;
                $log->memory_used_percent= isset($healthPayload['memory_used_percent']) ? (int) $healthPayload['memory_used_percent'] : null;
            } else {
                $log->db_status    = 'unknown';
                $log->redis_status = 'unknown';
                $log->queue_status = 'unknown';
            }

            $sslInfo = $this->checkSsl($domain);
            $log->ssl_status         = $sslInfo['status'];
            $log->ssl_expires_at     = $sslInfo['expires_at'] ?? null;
            $log->ssl_days_remaining = $sslInfo['days_remaining'] ?? null;

            // Domain expiry from Site model (manually maintained by admin)
            if ($site->domain_expires_at) {
                $daysLeft = (int) now()->diffInDays($site->domain_expires_at, false);
                $log->domain_expires_at     = $site->domain_expires_at;
                $log->domain_days_remaining = $daysLeft;
            }

            $log->status = $this->computeStatus($httpStatus, $log->db_status, $log->redis_status, $sslInfo, $log->domain_days_remaining);
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

        $candidates = [$baseUrl];

        if (str_starts_with($baseUrl, 'https://')) {
            $candidates[] = 'http://' . substr($baseUrl, 8);
        }

        $lastException = null;
        foreach ($candidates as $url) {
            try {
                $response = Http::timeout(10)
                    ->withoutVerifying()
                    ->get("{$url}/api/health");

                $ms      = (int) ((microtime(true) - $start) * 1000);
                $payload = null;

                if ($response->successful()) {
                    $decoded = $response->json();
                    if (is_array($decoded) && isset($decoded['app'])) {
                        $payload = $decoded;
                    }
                }

                return [$response->status(), $ms, $payload];
            } catch (\Throwable $e) {
                $lastException = $e;
            }
        }

        $ms = (int) ((microtime(true) - $start) * 1000);
        throw $lastException;
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
                return ['status' => 'unknown'];
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
                'status'         => $status,
                'expires_at'     => $expiresAt->toDateString(),
                'days_remaining' => $daysLeft,
            ];
        } catch (\Throwable $e) {
            return ['status' => 'unknown', 'error' => $e->getMessage()];
        }
    }

    private function computeStatus(int $httpStatus, string $dbStatus, string $redisStatus, array $sslInfo, ?int $domainDaysLeft = null): string
    {
        if ($httpStatus >= 500) return 'critical';
        if ($dbStatus === 'error' || $redisStatus === 'error') return 'critical';
        if ($httpStatus === 0 || $httpStatus >= 400) return 'warning';
        if (in_array($sslInfo['status'] ?? '', ['critical', 'invalid'], true)) return 'critical';
        if (($sslInfo['status'] ?? '') === 'expiring_soon') return 'warning';
        if ($domainDaysLeft !== null && $domainDaysLeft < 14) return 'warning';
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
