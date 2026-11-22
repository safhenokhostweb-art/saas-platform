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

    /**
     * Normalize subdomain relative to root domain:
     * - empty, "@", or identical to root domain => "@"
     * - trim trailing/leading dots and lower-case
     */
    public function normalizeRecordName(string $subdomain): string
    {
        $name = strtolower(trim($subdomain, " \t\n\r\0\x0B."));
        $root = strtolower(trim($this->rootDomain, " \t\n\r\0\x0B."));

        if ($name === '' || $name === '@' || $name === $root) {
            return '@';
        }

        if (str_ends_with($name, '.' . $root)) {
            $name = substr($name, 0, -strlen('.' . $root));
        }

        return trim($name, '.');
    }

    /**
     * Endpoint for records on the configured root domain.
     */
    private function recordsUrl(): string
    {
        return "{$this->baseUrl}/{$this->rootDomain}/dns-records";
    }

    public function createARecord(string $subdomain, string $ip, int $ttl = 120): array
    {
        $normalizedName = $this->normalizeRecordName($subdomain);
        $ttl = $ttl ?: $this->defaultTtl;

        $payload = [
            'type'  => 'A',
            'name'  => $normalizedName,
            'value' => [
                ['ip' => trim($ip)],
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
                    'subdomain' => $normalizedName,
                    'ip'        => $ip,
                    'record_id' => $recordId,
                    'domain'    => $this->rootDomain,
                ]);

                return ['success' => true, 'record_id' => $recordId, 'message' => "A record created for {$normalizedName}.{$this->rootDomain}"];
            }

            $error = $this->extractError($response);

            // If creation failed due to duplicate/already existing record or validation error on duplicate name
            if ($response->status() === 422 || $response->status() === 409 || str_contains(strtolower($error), 'already') || str_contains(strtolower($error), 'exist') || str_contains(strtolower($error), 'invalid')) {
                Log::warning('ArvanDNS: Record creation indicated collision or invalid state, looking up existing record', [
                    'subdomain' => $normalizedName,
                    'status'    => $response->status(),
                    'error'     => $error,
                ]);

                $existing = $this->findRecordDetails($normalizedName, 'A');
                if ($existing && !empty($existing['id'])) {
                    $existingId = (string) $existing['id'];
                    $currentIp = $this->extractIpFromRecord($existing);

                    if ($currentIp === trim($ip)) {
                        Log::info('ArvanDNS: Existing A record already points to target IP, accepting as idempotent', [
                            'record_id' => $existingId,
                            'subdomain' => $normalizedName,
                            'ip'        => $ip,
                        ]);
                        return [
                            'success'   => true,
                            'record_id' => $existingId,
                            'message'   => "A record already exists and points to {$ip}",
                        ];
                    }

                    Log::info('ArvanDNS: Existing A record points to different IP, updating', [
                        'record_id'  => $existingId,
                        'subdomain'  => $normalizedName,
                        'current_ip' => $currentIp,
                        'target_ip'  => $ip,
                    ]);
                    $updateRes = $this->updateARecord($existingId, $normalizedName, $ip, $ttl);
                    if ($updateRes['success']) {
                        return [
                            'success'   => true,
                            'record_id' => $existingId,
                            'message'   => $updateRes['message'],
                        ];
                    }
                }
            }

            Log::error('ArvanDNS: Failed to create A record', ['subdomain' => $normalizedName, 'error' => $error, 'status' => $response->status()]);
            return ['success' => false, 'record_id' => null, 'message' => $error];

        } catch (\Throwable $e) {
            Log::error('ArvanDNS: createARecord exception', ['error' => $e->getMessage()]);
            return ['success' => false, 'record_id' => null, 'message' => $e->getMessage()];
        }
    }

    public function updateARecord(string $recordId, string $subdomain, string $ip, int $ttl = 120): array
    {
        $normalizedName = $this->normalizeRecordName($subdomain);
        $ttl = $ttl ?: $this->defaultTtl;

        $payload = [
            'type'  => 'A',
            'name'  => $normalizedName,
            'value' => [
                ['ip' => trim($ip)],
            ],
            'ttl'   => $ttl,
            'cloud' => $this->cloudProxy,
        ];

        try {
            $response = $this->http()->put("{$this->recordsUrl()}/{$recordId}", $payload);

            if ($response->successful()) {
                Log::info('ArvanDNS: A record updated', ['record_id' => $recordId, 'subdomain' => $normalizedName, 'ip' => $ip]);
                return ['success' => true, 'message' => "A record updated for {$normalizedName}.{$this->rootDomain}"];
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
        $record = $this->findRecordDetails($subdomain, 'A');
        return $record && !empty($record['id']) ? (string) $record['id'] : null;
    }

    /**
     * Find full details of a record by normalized name and type, handling pagination.
     *
     * @return array<string, mixed>|null
     */
    public function findRecordDetails(string $subdomain, string $type = 'A'): ?array
    {
        $normalizedName = $this->normalizeRecordName($subdomain);

        try {
            $page = 1;
            do {
                $response = $this->http()->get($this->recordsUrl(), [
                    'search'   => $normalizedName === '@' ? '' : $normalizedName,
                    'page'     => $page,
                    'per_page' => 100,
                ]);

                if (!$response->successful()) {
                    Log::warning('ArvanDNS: Failed to fetch records list', [
                        'status' => $response->status(),
                        'name'   => $normalizedName,
                    ]);
                    return null;
                }

                $records = $response->json('data', []);
                if (!is_array($records) || empty($records)) {
                    break;
                }

                foreach ($records as $record) {
                    $recordType = strtoupper((string) ($record['type'] ?? ''));
                    $recordName = $this->normalizeRecordName((string) ($record['name'] ?? ''));

                    if ($recordType === strtoupper($type) && $recordName === $normalizedName) {
                        return $record;
                    }
                }

                $meta = $response->json('meta', []);
                $lastPage = (int) ($meta['last_page'] ?? ($response->json('links.next') ? $page + 1 : $page));
                $page++;
            } while ($page <= $lastPage);

            return null;

        } catch (\Throwable $e) {
            Log::warning('ArvanDNS: findRecordDetails exception', ['subdomain' => $normalizedName, 'error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Helper to extract target IP from Arvan record object.
     */
    public function extractIpFromRecord(array $record): ?string
    {
        $val = $record['value'] ?? null;
        if (is_array($val)) {
            if (isset($val['ip'])) {
                return trim((string) $val['ip']);
            }
            if (isset($val[0]['ip'])) {
                return trim((string) $val[0]['ip']);
            }
        } elseif (is_string($val)) {
            return trim($val);
        }

        return null;
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
