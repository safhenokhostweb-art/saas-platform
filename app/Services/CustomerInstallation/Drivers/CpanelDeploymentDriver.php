<?php

namespace App\Services\CustomerInstallation\Drivers;

use App\Models\CustomerRemoteServer;
use App\Models\DeploymentTarget;
use App\Services\CustomerInstallation\CredentialVault;
use App\Services\CustomerInstallation\Contracts\DeploymentDriverInterface;
use App\Services\CustomerInstallation\Dto\CapabilityProbeResult;
use App\Services\CustomerInstallation\Dto\CommandResult;
use App\Services\CustomerInstallation\Dto\ConnectionTestResult;
use App\Services\CustomerInstallation\Dto\DriverCapabilities;
use App\Services\CustomerInstallation\Dto\InstallabilityResult;
use App\Services\CustomerInstallation\Dto\ValidationResult;
use App\Services\CustomerInstallation\Enums\DeploymentCapability;
use App\Services\CustomerInstallation\Enums\DriverType;
use DateTimeImmutable;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

/**
 * cPanel shared hosting driver — ARCHITECTURE STUB.
 *
 * Status: NOT IMPLEMENTED. Architecture only.
 * To implement: wire cPanel UAPI + WHM API v1 HTTP calls.
 *
 * Capabilities: cron, scheduler, SSL, database creation.
 * Limitations: no supervisor, no redis, no storage symlinks.
 *
 * Authentication: cPanel API Token (preferred) or Username/Password.
 * API Docs: https://api.docs.cpanel.net/
 */
class CpanelDeploymentDriver implements DeploymentDriverInterface
{
    public function __construct(
        private readonly DeploymentTarget|CustomerRemoteServer $server,
        private readonly CredentialVault  $vault,
    ) {}

    public function driverType(): DriverType
    {
        return DriverType::CPANEL;
    }

    public function capabilities(): DriverCapabilities
    {
        return new DriverCapabilities();
    }

    public function probeCapabilities(): CapabilityProbeResult
    {
        $checkedAt = new DateTimeImmutable();
        $expiresAt = $checkedAt->modify(sprintf(
            '+%d seconds',
            max(1, (int) config('customer_installation.cpanel.evidence_ttl_seconds', 900))
        ));

        if (!$this->server instanceof DeploymentTarget || !$this->validate()->passed) {
            return $this->failedProbe($checkedAt, $expiresAt, 'cPanel API token is missing or configuration is invalid.');
        }

        try {
            $token = $this->vault->decrypt((string) $this->server->api_token_encrypted);
            $client = $this->apiClient($token);
            $identityResponse = $client->get('/execute/Variables/get_user_information');

            if (!$this->uapiSucceeded($identityResponse->status(), $identityResponse->json())) {
                $reason = in_array($identityResponse->status(), [401, 403], true)
                    ? 'cPanel API token was rejected.'
                    : 'cPanel account identity could not be verified.';

                return $this->failedProbe($checkedAt, $expiresAt, $reason);
            }

            $identity = $identityResponse->json('result.data');
            if (is_array($identity)
                && isset($identity['user'])
                && !hash_equals((string) $this->server->username, (string) $identity['user'])) {
                return $this->failedProbe($checkedAt, $expiresAt, 'cPanel account identity did not match the deployment target.');
            }

            $checks = [
                DeploymentCapability::FILE_UPLOAD->value => [
                    '/execute/Fileman/list_files?dir=' . rawurlencode($this->documentRoot()),
                    'Authenticated cPanel File Manager access is available.',
                ],
                DeploymentCapability::DOMAIN_MANAGEMENT->value => ['/execute/DomainInfo/list_domains', 'Domain listing is available.'],
                DeploymentCapability::DISK_QUOTA->value => ['/execute/Quota/get_quota_info', 'Disk quota information is available.'],
                DeploymentCapability::PHP_VERSION->value => ['/execute/LangPHP/php_get_vhost_versions', 'PHP version information is available.'],
                DeploymentCapability::SSL_MANAGEMENT->value => ['/execute/SSL/installed_hosts', 'SSL status information is available.'],
                DeploymentCapability::CRON_MANAGEMENT->value => ['/execute/Cron/list_cron', 'Cron feature is available.'],
                DeploymentCapability::DATABASE_MANAGEMENT->value => ['/execute/Mysql/list_databases', 'Database feature is available.'],
            ];

            $evidence = [$this->evidence('account_identity', true, $checkedAt, $expiresAt, 'Account identity was authenticated.')];
            $available = [];
            $failed = [];

            foreach ($checks as $capabilityValue => [$endpoint, $successReason]) {
                $capability = DeploymentCapability::from($capabilityValue);

                try {
                    $response = $client->get($endpoint);
                    $isAvailable = $this->uapiSucceeded($response->status(), $response->json());
                } catch (Throwable) {
                    $isAvailable = false;
                }

                $evidence[] = $this->evidence(
                    $capability->value,
                    $isAvailable,
                    $checkedAt,
                    $expiresAt,
                    $isAvailable ? $successReason : 'Feature is unavailable or could not be verified.'
                );
                $isAvailable ? $available[] = $capability : $failed[] = $capability;
            }

            foreach ([
                DeploymentCapability::FILE_EXTRACT,
                DeploymentCapability::BACKUP_RESTORE,
            ] as $unprovenCapability) {
                $failed[] = $unprovenCapability;
                $evidence[] = $this->evidence(
                    $unprovenCapability->value,
                    false,
                    $checkedAt,
                    $expiresAt,
                    'Capability is not proven by this read-only probe.'
                );
            }

            return new CapabilityProbeResult(
                true,
                true,
                $available,
                $failed,
                $checkedAt,
                $expiresAt,
                null,
                $evidence,
            );
        } catch (Throwable) {
            return $this->failedProbe($checkedAt, $expiresAt, 'cPanel authentication failed or the API was unreachable.');
        }
    }

    public function isInstallable(?CapabilityProbeResult $probeResult = null): InstallabilityResult
    {
        $probeResult ??= $this->probeCapabilities();
        $reasons = [];

        if (!$this->server instanceof DeploymentTarget
            || $this->server->connection_driver !== DriverType::CPANEL->value) {
            $reasons[] = 'Deployment target driver must be cpanel.';
        }

        if (!$this->server instanceof DeploymentTarget || !$this->server->isActive()) {
            $reasons[] = 'Deployment target must be active.';
        }

        if (!$this->validate()->passed) {
            $reasons[] = 'Deployment target credentials are missing or invalid.';
        }

        if (!$probeResult->completed) {
            $reasons[] = $probeResult->reason ?? 'Capability probe is pending.';
        }

        if (!$probeResult->credentialsValidated) {
            $reasons[] = 'Credentials have not been validated by an authenticated probe.';
        }

        if ($probeResult->isStale()) {
            $reasons[] = 'Capability evidence is stale.';
        }


        if (!$probeResult->hasFreshEvidenceFor(DeploymentCapability::mandatoryForInstallation())) {
            $reasons[] = 'Fresh evidence for all mandatory cPanel capabilities is unavailable.';
        }

        return $reasons === []
            ? InstallabilityResult::installable()
            : InstallabilityResult::blocked($reasons);
    }

    public function validate(): ValidationResult
    {
        $errors = [];

        if (empty($this->server->host)) {
            $errors[] = 'Host is required';
        }

        if (empty($this->server->username)) {
            $errors[] = 'cPanel username is required';
        }

        if (empty($this->server->api_token_encrypted)) {
            $errors[] = 'cPanel API token is required';
        }

        try {
            $this->documentRoot();
        } catch (\InvalidArgumentException $exception) {
            $errors[] = $exception->getMessage();
        }

        return empty($errors) ? ValidationResult::pass() : ValidationResult::fail($errors);
    }

    public function rollbackStage(string $stageName, array $snapshot): void
    {
        throw new \BadMethodCallException(
            'CpanelDeploymentDriver::rollbackStage() is not yet implemented. ' .
            'Driver is architecture stub only.'
        );
    }

    // ─── RemoteConnectionInterface (stubs) ────────────────────────────────────

    public function connect(): void
    {
        $this->notImplemented(__METHOD__);
    }

    public function testConnection(): ConnectionTestResult
    {
        $validation = $this->validate();

        if (!$validation->passed) {
            return ConnectionTestResult::failure(
                'Invalid cPanel configuration: ' . implode('; ', $validation->errors)
            );
        }

        return ConnectionTestResult::failure(
            'cPanel capability not implemented: testConnection. '
            . 'Implement authenticated cPanel UAPI/WHM API connectivity.'
        );
    }

    public function execute(string $command, array $env = []): CommandResult
    {
        $this->notImplemented(__METHOD__);
    }

    public function upload(string $localPath, string $remotePath, ?callable $progress = null): void
    {
        $this->notImplemented(__METHOD__);
    }

    public function uploadPackage(string $localPath, string $remotePath): void
    {
        if (!$this->validate()->passed) {
            throw new RuntimeException('cPanel package upload configuration is invalid.');
        }

        $sourcePath = realpath($localPath);
        $fileName = basename($localPath);
        $documentRoot = $this->documentRoot();
        $expectedPath = $documentRoot . '/' . $fileName;

        if ($sourcePath === false
            || !is_file($sourcePath)
            || strtolower(pathinfo($fileName, PATHINFO_EXTENSION)) !== 'zip'
            || !$this->isZipArchive($sourcePath)
            || str_replace('\\', '/', trim($remotePath)) !== $expectedPath) {
            throw new RuntimeException('cPanel package upload path is invalid.');
        }

        $stream = fopen($sourcePath, 'rb');

        if ($stream === false) {
            throw new RuntimeException('The release ZIP could not be opened for upload.');
        }

        try {
            $token = $this->vault->decrypt((string) $this->server->api_token_encrypted);
            $response = $this->apiClient($token)
                ->attach('file-1', $stream, $fileName)
                ->post('/execute/Fileman/upload_files', [
                    'dir' => $documentRoot,
                    'overwrite' => 1,
                ]);

            if (!$this->uapiSucceeded($response->status(), $response->json())) {
                throw new RuntimeException('cPanel rejected the package upload.');
            }
        } catch (Throwable $exception) {
            throw new RuntimeException('cPanel package upload failed.', 0, $exception);
        } finally {
            fclose($stream);
        }
    }

    public function extractPackage(string $remoteZipPath, string $targetDirectory): void
    {
        if (!$this->validate()->passed) {
            throw new RuntimeException('cPanel package extraction configuration is invalid.');
        }

        $documentRoot = $this->documentRoot();
        $archivePath = str_replace('\\', '/', trim($remoteZipPath));
        $extractionTarget = str_replace('\\', '/', trim($targetDirectory));
        $archiveName = basename($archivePath);

        if (str_contains($archivePath, chr(0))
            || str_contains($extractionTarget, chr(0))
            || preg_match('#(^|/)\.\.(/|$)#', $archivePath) === 1
            || preg_match('#(^|/)\.\.(/|$)#', $extractionTarget) === 1
            || $extractionTarget !== $documentRoot
            || $archivePath !== $documentRoot . '/' . $archiveName
            || $archiveName === ''
            || strtolower(pathinfo($archiveName, PATHINFO_EXTENSION)) !== 'zip') {
            throw new RuntimeException('cPanel package extraction path is invalid.');
        }

        try {
            $token = $this->vault->decrypt((string) $this->server->api_token_encrypted);
            $client = $this->apiClient($token);
            $api2Parameters = [
                'cpanel_jsonapi_user' => (string) $this->server->username,
                'cpanel_jsonapi_apiversion' => 2,
                'cpanel_jsonapi_module' => 'Fileman',
                'cpanel_jsonapi_func' => 'fileop',
                'sourcefiles' => $archivePath,
                'doubledecode' => 0,
            ];
            $extractResponse = $client->post('/json-api/cpanel', $api2Parameters + [
                'op' => 'extract',
            ]);

            if (!$this->api2FileOperationSucceeded($extractResponse->status(), $extractResponse->json())) {
                throw new RuntimeException('cPanel rejected the package extraction.');
            }

            $deleteResponse = $client->post('/json-api/cpanel', $api2Parameters + [
                'op' => 'trash',
            ]);

            if (!$this->api2FileOperationSucceeded($deleteResponse->status(), $deleteResponse->json())) {
                throw new RuntimeException('cPanel could not remove the extracted package archive.');
            }
        } catch (Throwable $exception) {
            throw new RuntimeException('cPanel package extraction failed safely.', 0, $exception);
        }
    }

    public function download(string $remotePath, string $localPath): void
    {
        $this->notImplemented(__METHOD__);
    }

    /**
     * @param array<string, string> $values
     * @return array{generated_keys: list<string>, merge_performed: bool}
     */
    public function mergeEnvironment(string $remotePath, array $values): array
    {
        if (!$this->validate()->passed) {
            throw new RuntimeException('cPanel environment configuration is invalid.');
        }

        $documentRoot = $this->documentRoot();
        $normalizedPath = str_replace('\\', '/', trim($remotePath));

        if (str_contains($normalizedPath, chr(0))
            || preg_match('#(^|/)\.\.(/|$)#', $normalizedPath) === 1
            || $normalizedPath !== $documentRoot . '/.env') {
            throw new RuntimeException('cPanel environment target path is invalid.');
        }

        try {
            $token = $this->vault->decrypt((string) $this->server->api_token_encrypted);
            $client = $this->apiClient($token);
            $listing = $client->get('/execute/Fileman/list_files', ['dir' => $documentRoot]);

            if (!$this->uapiSucceeded($listing->status(), $listing->json())) {
                throw new RuntimeException('cPanel could not inspect the environment target.');
            }

            $existingFile = collect($listing->json('result.data', []))->contains(
                static fn (mixed $entry): bool => is_array($entry)
                    && ($entry['file'] ?? $entry['name'] ?? null) === '.env'
            );
            $existingContent = '';

            if ($existingFile) {
                $read = $client->get('/execute/Fileman/get_file_content', [
                    'dir' => $documentRoot,
                    'file' => '.env',
                ]);

                if (!$this->uapiSucceeded($read->status(), $read->json())) {
                    throw new RuntimeException('cPanel could not safely read the existing environment file.');
                }

                $existingContent = (string) $read->json('result.data.content', '');
            }

            $existingKeys = $this->environmentKeys($existingContent);
            $missingValues = array_diff_key($values, array_flip($existingKeys));

            if ($missingValues === []) {
                return ['generated_keys' => [], 'merge_performed' => false];
            }

            $lines = [];
            foreach ($missingValues as $key => $value) {
                if (preg_match('/^[A-Z][A-Z0-9_]*$/', $key) !== 1) {
                    throw new RuntimeException('An environment key is invalid.');
                }

                $lines[] = $key . '=' . $this->quoteEnvironmentValue($value);
            }

            $mergedContent = $existingContent;
            $newline = chr(10);
            if ($mergedContent !== '' && !str_ends_with($mergedContent, $newline)) {
                $mergedContent .= $newline;
            }
            $mergedContent .= implode($newline, $lines) . $newline;

            $write = $client->post('/execute/Fileman/save_file_content', [
                'dir' => $documentRoot,
                'file' => '.env',
                'content' => $mergedContent,
                'from_charset' => 'UTF-8',
                'to_charset' => 'UTF-8',
            ]);

            if (!$this->uapiSucceeded($write->status(), $write->json())) {
                throw new RuntimeException('cPanel rejected the environment-file write.');
            }

            return [
                'generated_keys' => array_keys($missingValues),
                'merge_performed' => $existingFile,
            ];
        } catch (Throwable $exception) {
            throw new RuntimeException('cPanel environment generation failed safely.', 0, $exception);
        }
    }

    public function fileExists(string $remotePath): bool
    {
        $this->notImplemented(__METHOD__);
    }

    public function diskFree(string $path): int
    {
        $this->notImplemented(__METHOD__);
    }

    public function disconnect(): void
    {
        // No-op for stub
    }

    private function apiClient(string $token): PendingRequest
    {
        $hostInput = trim((string) $this->server->host);
        $parsedHost = parse_url(str_contains($hostInput, '://') ? $hostInput : "https://{$hostInput}", PHP_URL_HOST);

        if (!is_string($parsedHost) || $parsedHost === '') {
            throw new \InvalidArgumentException('Invalid cPanel host.');
        }

        return Http::baseUrl(sprintf(
            'https://%s:%d',
            $parsedHost,
            (int) config('customer_installation.cpanel.api_port', 2083)
        ))
            ->acceptJson()
            ->withHeaders(['Authorization' => sprintf('cpanel %s:%s', $this->server->username, $token)])
            ->connectTimeout((int) config('customer_installation.cpanel.timeout_connect', 5))
            ->timeout((int) config('customer_installation.cpanel.timeout_request', 15));
    }

    private function uapiSucceeded(int $httpStatus, mixed $payload): bool
    {
        return $httpStatus >= 200
            && $httpStatus < 300
            && is_array($payload)
            && data_get($payload, 'result.status') === 1;
    }

    private function api2FileOperationSucceeded(int $httpStatus, mixed $payload): bool
    {
        $results = data_get($payload, 'cpanelresult.data');

        return $httpStatus >= 200
            && $httpStatus < 300
            && data_get($payload, 'cpanelresult.event.result') === 1
            && is_array($results)
            && $results !== []
            && collect($results)->every(
                static fn (mixed $result): bool => is_array($result) && ($result['result'] ?? 0) === 1
            );
    }

    private function isZipArchive(string $path): bool
    {
        if (!class_exists(\ZipArchive::class)) {
            return false;
        }

        $archive = new \ZipArchive();
        $opened = @$archive->open($path, \ZipArchive::RDONLY);

        if ($opened !== true) {
            return false;
        }

        for ($index = 0; $index < $archive->numFiles; $index++) {
            $entry = $archive->getNameIndex($index);
            $normalizedEntry = is_string($entry) ? str_replace('\\', '/', $entry) : '';

            if ($normalizedEntry === ''
                || str_contains($normalizedEntry, chr(0))
                || str_starts_with($normalizedEntry, '/')
                || preg_match('#^[a-z]:/#i', $normalizedEntry) === 1
                || preg_match('#(^|/)\.\.(/|$)#', $normalizedEntry) === 1) {
                $archive->close();
                return false;
            }
        }

        $archive->close();

        return true;
    }

    /** @return list<string> */
    private function environmentKeys(string $content): array
    {
        preg_match_all('/^(?:export\s+)?([A-Za-z_][A-Za-z0-9_]*)\s*=/m', $content, $matches);

        return array_values(array_unique($matches[1] ?? []));
    }

    private function quoteEnvironmentValue(string $value): string
    {
        $slash = chr(92);
        $quote = chr(34);
        $escaped = str_replace(
            [$slash, $quote, chr(13), chr(10)],
            [$slash . $slash, $slash . $quote, $slash . 'r', $slash . 'n'],
            $value,
        );

        return $quote . $escaped . $quote;
    }

    private function documentRoot(): string
    {
        $documentRoot = str_replace('\\', '/', trim((string) config('customer_installation.hosting.document_root')));

        if ($documentRoot === ''
            || str_contains($documentRoot, chr(0))
            || preg_match('#(^|/)\.\.(/|$)#', $documentRoot) === 1
            || preg_match('#^/?var/www(?:/|$)#i', $documentRoot) === 1) {
            throw new \InvalidArgumentException('The configured hosting document_root is invalid.');
        }

        return rtrim($documentRoot, '/');
    }


    /** @return array{capability: string, available: bool, checked_at: string, expires_at: string, provider: string, reason: string} */
    private function evidence(
        string $capability,
        bool $available,
        DateTimeImmutable $checkedAt,
        DateTimeImmutable $expiresAt,
        string $reason,
    ): array {
        return [
            'capability' => $capability,
            'available' => $available,
            'checked_at' => $checkedAt->format(DATE_ATOM),
            'expires_at' => $expiresAt->format(DATE_ATOM),
            'provider' => DriverType::CPANEL->value,
            'reason' => $reason,
        ];
    }

    private function failedProbe(
        DateTimeImmutable $checkedAt,
        DateTimeImmutable $expiresAt,
        string $reason,
    ): CapabilityProbeResult {
        $failed = DeploymentCapability::mandatoryForInstallation();
        $evidence = [$this->evidence('account_identity', false, $checkedAt, $expiresAt, $reason)];

        foreach ($failed as $capability) {
            $evidence[] = $this->evidence(
                $capability->value,
                false,
                $checkedAt,
                $expiresAt,
                'Capability could not be verified without authenticated API access.'
            );
        }

        return new CapabilityProbeResult(
            true,
            false,
            [],
            $failed,
            $checkedAt,
            $expiresAt,
            $reason,
            $evidence,
        );
    }

    private function notImplemented(string $method): never
    {
        $validation = $this->validate();

        if (!$validation->passed) {
            throw new \InvalidArgumentException(
                'Invalid cPanel configuration: ' . implode('; ', $validation->errors)
            );
        }

        throw new \BadMethodCallException(
            "cPanel capability not implemented: {$method}. " .
            'Implement the corresponding authenticated cPanel UAPI/WHM API operation.'
        );
    }
}
