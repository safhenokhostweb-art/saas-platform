<?php

namespace App\Services\Dns;

use App\Contracts\DnsProviderInterface;
use App\Models\Infrastructure\DnsProviderSetting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class ArvanDnsProvider implements DnsProviderInterface
{
    private const DEFAULT_BASE_URL = 'https://napi.arvancloud.ir/cdn/4.0/domains';

    private string $token;
    private string $rootDomain;
    private string $baseUrl;
    private bool   $cloudProxy;
    private int    $defaultTtl;

    public function __construct(DnsProviderSetting $setting)
    {
        if (!$setting->isReady()) {
            throw new RuntimeException('Arvan DNS provider is not configured or not active.');
        }
        $this->token      = $setting->getDecryptedToken();
        $this->rootDomain = $setting->root_domain;
        $this->baseUrl    = rtrim($setting->api_base_url ?? self::DEFAULT_BASE_URL, '/');
        $this->cloudProxy = (bool) $setting->cloud_proxy;
        $this->defaultTtl = (int) ($setting->default_ttl ?: 120);
    }

    /** Build HTTP client with Arvan auth header and 30s timeout. */
    private function http(): \Illuminate\Http\Client\PendingRequest
    {
        return Http::withHeaders([
            'Authorization' => 'Apikey ' . $this->token,
            'Accept'        => 'application/json',
            'Content-Type'  => 'application/json',
        ])->timeout(30);
    }

    /** Endpoint for records on the configured root domain. */
    private function recordsUrl(): string
    {
        return "{$this->baseUrl}/{$this->rootDomain}/dns-records";
    }

    public function createARecord(string $subdomain, string $ip, int $ttl = 120): array
    {
        $ttl = $ttl ?: $this->defaultTtl;

        $payload = [
            'type'  => 'A',
            'name'  => $subdomain,
            'value' => [
                ['ip' => $ip, 'port' => null, 'weight' => 100, 'country' => ''],
            ],
            'ttl'   => $ttl,
            'cloud' => $this->cloudProxy,
        ];

        try {
            $response = $this->http()->post($this->recordsUrl(), $payload);

            if ($response->successful()) {
                $data = $response->json();
                $recordId = $data['data']['id'] ?? null;

                Log::info('ArvanDNS: A record created', [
                    'subdomain' => $subdomain,
                    'ip'        => $ip,
                    'record_id' => $recordId,
                    'domain'    => $this->rootDomain,
                ]);

                return ['success' => true, 'record_id' => $recordId, 'message' => "A record created for {$subdomain}.{$this->rootDomain}"];
            }

            $error = $this->extractError($response);
            Log::error('ArvanDNS: Failed to create A record', ['subdomain' => $subdomain, 'error' => $error, 'status' => $response->status()]);
            return ['success' => false, 'record_id' => null, 'message' => $error];

        } catch (\Throwable $e) {
            Log::error('ArvanDNS: createARecord exception', ['error' => $e->getMessage()]);
            return ['success' => false, 'record_id' => null, 'message' => $e->getMessage()];
        }
    }

    public function updateARecord(string $recordId, string $subdomain, string $ip, int $ttl = 120): array
    {
        $ttl = $ttl ?: $this->defaultTtl;

        $payload = [
            'type'  => 'A',
            'name'  => $subdomain,
            'value' => [
                ['ip' => $ip, 'port' => null, 'weight' => 100, 'country' => ''],
            ],
            'ttl'   => $ttl,
            'cloud' => $this->cloudProxy,
        ];

        try {
            $response = $this->http()->put("{$this->recordsUrl()}/{$recordId}", $payload);

            if ($response->successful()) {
                Log::info('ArvanDNS: A record updated', ['record_id' => $recordId, 'subdomain' => $subdomain, 'ip' => $ip]);
                return ['success' => true, 'message' => "A record updated for {$subdomain}.{$this->rootDomain}"];
            }

            $error = $this->extractError($response);
            Log::error('ArvanDNS: Failed to update A record', ['record_id' => $recordId, 'error' => $error]);
            return ['success' => false, 'message' => $error];

        } catch (\Throwable $e) {
            Log::error('ArvanDNS: updateARecord exception', ['error' => $e->getMessage()]);
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function deleteRecord(string $recordId): array
    {
        try {
            $response = $this->http()->delete("{$this->recordsUrl()}/{$recordId}");

            if ($response->successful()) {
                Log::info('ArvanDNS: Record deleted', ['record_id' => $recordId, 'domain' => $this->rootDomain]);
                return ['success' => true, 'message' => "Record {$recordId} deleted."];
            }

            // 404 = already gone — treat as success to make rollback idempotent
            if ($response->status() === 404) {
                Log::info('ArvanDNS: deleteRecord 404 (already gone)', ['record_id' => $recordId]);
                return ['success' => true, 'message' => 'Record not found (already deleted).'];
            }

            $error = $this->extractError($response);
            Log::error('ArvanDNS: Failed to delete record', ['record_id' => $recordId, 'error' => $error]);
            return ['success' => false, 'message' => $error];

        } catch (\Throwable $e) {
            Log::error('ArvanDNS: deleteRecord exception', ['error' => $e->getMessage()]);
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function findARecord(string $subdomain): ?string
    {
        try {
            $response = $this->http()->get($this->recordsUrl(), ['search' => $subdomain]);

            if (!$response->successful()) {
                return null;
            }

            $records = $response->json('data', []);

            foreach ($records as $record) {
                if (
                    ($record['type'] ?? '') === 'A'
                    && ($record['name'] ?? '') === $subdomain
                ) {
                    return (string) $record['id'];
                }
            }

            return null;

        } catch (\Throwable $e) {
            Log::warning('ArvanDNS: findARecord exception', ['subdomain' => $subdomain, 'error' => $e->getMessage()]);
            return null;
        }
    }

    public function waitForPropagation(string $fqdn, string $expectedIp, int $maxWaitSec = 300, int $pollIntervalSec = 15): bool
    {
        $deadline  = time() + $maxWaitSec;
        $attempts  = 0;

        Log::info('ArvanDNS: Waiting for DNS propagation', ['fqdn' => $fqdn, 'expected_ip' => $expectedIp, 'max_wait' => $maxWaitSec]);

        while (time() < $deadline) {
            $attempts++;

            // Use Google public DNS (8.8.8.8) to check propagation
            $records = $this->dnsLookup($fqdn);

            if (in_array($expectedIp, $records, true)) {
                Log::info('ArvanDNS: DNS propagated', ['fqdn' => $fqdn, 'attempts' => $attempts]);
                return true;
            }

            Log::debug('ArvanDNS: DNS not yet propagated', ['fqdn' => $fqdn, 'attempt' => $attempts, 'found' => $records]);

            if (time() + $pollIntervalSec >= $deadline) {
                break;
            }
            sleep($pollIntervalSec);
        }

        Log::warning('ArvanDNS: DNS propagation timed out', ['fqdn' => $fqdn, 'expected_ip' => $expectedIp, 'attempts' => $attempts]);
        return false;
    }

    public function healthCheck(): array
    {
        try {
            // List domains to verify token validity
            $response = $this->http()->get("{$this->baseUrl}");

            if ($response->successful()) {
                $count = count($response->json('data', []));
                return ['ok' => true, 'message' => 'Token valid. Domain count: ' . $count, 'domain_count' => $count];
            }

            if ($response->status() === 401 || $response->status() === 403) {
                return ['ok' => false, 'message' => 'Invalid or expired API token (HTTP ' . $response->status() . ').', 'domain_count' => null];
            }

            return ['ok' => false, 'message' => 'Unexpected response: HTTP ' . $response->status(), 'domain_count' => null];

        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => 'Connection error: ' . $e->getMessage(), 'domain_count' => null];
        }
    }

    /** Resolve a FQDN using Google DNS-over-HTTPS (avoids server-side resolvers returning stale cache). */
    private function dnsLookup(string $fqdn): array
    {
        try {
            $response = Http::timeout(10)
                ->withHeaders(['Accept' => 'application/dns-json'])
                ->get('https://dns.google/resolve', ['name' => $fqdn, 'type' => 'A']);

            if (!$response->successful()) {
                // Fallback to PHP native DNS lookup
                return $this->nativeDnsLookup($fqdn);
            }

            $answers = $response->json('Answer', []);
            return array_column(
                array_filter($answers, fn($a) => ($a['type'] ?? 0) === 1),
                'data'
            );

        } catch (\Throwable) {
            return $this->nativeDnsLookup($fqdn);
        }
    }

    private function nativeDnsLookup(string $fqdn): array
    {
        $records = @dns_get_record($fqdn, DNS_A);
        if (!is_array($records)) {
            return [];
        }
        return array_column($records, 'ip');
    }

    private function extractError(\Illuminate\Http\Client\Response $response): string
    {
        $body = $response->json();
        if (is_array($body)) {
            $msg = $body['message'] ?? $body['error'] ?? null;
            if ($msg) {
                return is_string($msg) ? $msg : json_encode($msg);
            }
        }
        return 'HTTP ' . $response->status() . ': ' . $response->body();
    }
}
