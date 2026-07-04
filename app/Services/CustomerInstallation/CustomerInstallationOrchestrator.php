<?php

namespace App\Services\CustomerInstallation;

use App\Models\CustomerInstallation;
use App\Models\CustomerRemoteServer;
use App\Models\Site;
use App\Services\CustomerInstallation\Contracts\InstallationStateInterface;
use App\Services\CustomerInstallation\Contracts\InstallationStageInterface;
use App\Services\CustomerInstallation\Dto\CustomerInstallRequest;
use App\Services\CustomerInstallation\Dto\InstallationContext;
use App\Services\CustomerInstallation\Enums\DeploymentCapability;
use App\Services\CustomerInstallation\Enums\InstallationMode;
use App\Services\CustomerInstallation\Operations\PackageUploadOperation;
use App\Services\CustomerInstallation\Operations\UnsupportedOperationResult;
use App\Services\CustomerInstallation\State\InstallationStateBridge;
use App\Services\CustomerInstallation\Stages\ValidateServerStage;
use App\Services\CustomerInstallation\Stages\ValidatePhpStage;
use App\Services\CustomerInstallation\Stages\ValidateExtensionsStage;
use App\Services\CustomerInstallation\Stages\ValidateDiskStage;
use App\Services\CustomerInstallation\Stages\ValidatePermissionsStage;
use App\Services\CustomerInstallation\Stages\UploadPackageStage;
use App\Services\CustomerInstallation\Stages\VerifyPackageStage;
use App\Services\CustomerInstallation\Stages\ExtractPackageStage;
use App\Services\CustomerInstallation\Stages\GenerateEnvironmentStage;
use App\Services\CustomerInstallation\Stages\CreateDatabaseStage;
use App\Services\CustomerInstallation\Stages\RunMigrationsStage;
use App\Services\CustomerInstallation\Stages\RunSeederStage;
use App\Services\CustomerInstallation\Stages\ConfigureStorageStage;
use App\Services\CustomerInstallation\Stages\ConfigureQueueStage;
use App\Services\CustomerInstallation\Stages\ConfigureSchedulerStage;
use App\Services\CustomerInstallation\Stages\ConfigureDnsStage;
use App\Services\CustomerInstallation\Stages\ConfigureWebServerStage;
use App\Services\CustomerInstallation\Stages\ProvisionLicenseStage;
use App\Services\CustomerInstallation\Stages\InitializeBackupStage;
use App\Services\CustomerInstallation\Stages\ConfigureMonitoringStage;
use App\Services\CustomerInstallation\Stages\InstallThemeStage;
use App\Services\CustomerInstallation\Stages\CustomerReadyStage;
use App\Jobs\CustomerInstallationRollbackJob;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class CustomerInstallationOrchestrator
{
    private const DB_PENDING = 'DB_PENDING';
    private const DB_VALIDATING = 'DB_VALIDATING';
    private const DB_READY = 'DB_READY';
    private const DB_FAILED = 'DB_FAILED';
    private const DB_PAUSED_RESULT = 'INSTALLATION_PAUSED_DB_PENDING';
    private const DEPLOYMENT_BLOCKED_RESULT = 'DEPLOYMENT_BLOCKED_PRECONDITIONS_FAILED';
    private const INSTALLATION_READY = 'INSTALLATION_READY';
    private const INSTALLATION_BLOCKED = 'INSTALLATION_BLOCKED';
    private const VERIFY_SUCCESS = 'VERIFY_SUCCESS';
    private const VERIFY_FAILED = 'VERIFY_FAILED';
    private const CUSTOMER_READY = 'CUSTOMER_READY';
    private const FAILED = 'FAILED';

    private readonly InstallationStateBridge $installationStates;

    public function __construct(
        private readonly InstallationStateInterface    $state,
        private readonly RemoteConnectionService       $connectionService,
        private readonly CustomerInstallationRollbackService $rollback,
        private readonly PackageUploadOperation $packageUpload,
        ?InstallationStateBridge $installationStates = null,
    ) {
        $this->installationStates = $installationStates ?? new InstallationStateBridge();
    }

    /**
     * Execute the full installation pipeline for a given installation record.
     * Called from CustomerInstallationPipelineJob.
     */
    public function run(CustomerInstallation $installation): void
    {
        $request = CustomerInstallRequest::fromArray($installation->request_payload ?? []);
        $server = CustomerRemoteServer::findOrFail($installation->remote_server_id);

        if (InstallationMode::forDriver((string) $server->type) === InstallationMode::HOSTING_PANEL_INSTALL) {
            $this->runHostingPanelPipeline($installation, $server);
            return;
        }

        $stages = $this->buildStageList($request->installMode);

        $this->state->markRunning($installation);

        $connection = $this->connectionService->buildDriver($server);

        try {
            $connection->connect();
        } catch (\Throwable $e) {
            $this->state->markFailed($installation, 'Cannot connect to remote server: ' . $e->getMessage());
            return;
        }

        $ctx = new InstallationContext($installation, $request, $connection);

        try {
            foreach ($stages as $stage) {
                $stageName = $stage->name();

                // Resume: skip already-completed stages
                if ($installation->hasCompletedStage($stageName)) {
                    Log::info("CIE: Skipping completed stage {$stageName}");
                    continue;
                }

                $this->state->recordStageStart($installation, $stageName);

                // Build rollback snapshot BEFORE execution wherever possible.
                // Stages override buildPreSnapshot() to return their resource paths so
                // that rollback has actionable data even when the stage fails mid-way.
                if ($stage->canRollback()) {
                    $preSnapshot = $stage->buildPreSnapshot($ctx);
                    $this->state->recordRollbackCheckpoint($installation, $stageName, $preSnapshot);
                }

                $startMs = (int) round(microtime(true) * 1000);
                $result = $stage->execute($ctx);
                $durationMs = (int) round(microtime(true) * 1000) - $startMs;

                if (!$result->success) {
                    $this->state->recordStageFailure($installation, $stageName, $result->error ?? 'Unknown error', $durationMs);
                    $this->state->markFailed($installation, "Stage {$stageName} failed: " . ($result->error ?? 'Unknown error'));
                    $this->dispatchRollback($installation);
                    return;
                }

                // Update the checkpoint with the actual snapshot from the stage result.
                // This upserts — no second record is created (see InstallationStateManager).
                if ($stage->canRollback() && !empty($result->rollbackSnapshot)) {
                    $ctx->pushRollback($stageName, $result->rollbackSnapshot);
                    $this->state->recordRollbackCheckpoint($installation, $stageName, $result->rollbackSnapshot);
                }

                $ctx->recordOutput($stageName, $result->output);
                $this->state->recordStageComplete($installation, $stageName, $result->output, $durationMs);
            }

        } catch (\Throwable $e) {
            Log::error("CIE: Unexpected error in pipeline", ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            $this->state->markFailed($installation, 'Unexpected pipeline error: ' . $e->getMessage());
            $this->dispatchRollback($installation);
        } finally {
            try {
                $connection->disconnect();
            } catch (\Throwable) {}
        }
    }

    /**
     * Build ordered stage list for the given installation mode.
     */
    public function buildStageList(string $mode): array
    {
        $stages = $this->resolveAllStages();

        return match($mode) {
            'MODE_RESTORE' => $this->modeRestoreStages($stages),
            default        => $stages,
        };
    }

    private function resolveAllStages(): array
    {
        return [
            app(ValidateServerStage::class),
            app(ValidatePhpStage::class),
            app(ValidateExtensionsStage::class),
            app(ValidateDiskStage::class),
            app(ValidatePermissionsStage::class),
            app(UploadPackageStage::class),
            app(VerifyPackageStage::class),
            app(ExtractPackageStage::class),
            app(GenerateEnvironmentStage::class),
            app(CreateDatabaseStage::class),
            app(RunMigrationsStage::class),
            app(RunSeederStage::class),
            app(ConfigureStorageStage::class),
            app(ConfigureQueueStage::class),
            app(ConfigureSchedulerStage::class),
            app(ConfigureDnsStage::class),
            app(ConfigureWebServerStage::class),
            app(ProvisionLicenseStage::class),
            app(InitializeBackupStage::class),
            app(ConfigureMonitoringStage::class),
            app(InstallThemeStage::class),
            app(CustomerReadyStage::class),
        ];
    }

    private function modeRestoreStages(array $allStages): array
    {
        // For restore mode: skip CREATE_DATABASE, RUN_MIGRATIONS, RUN_SEEDER
        $skipInRestore = ['CREATE_DATABASE', 'RUN_MIGRATIONS', 'RUN_SEEDER'];

        return array_values(array_filter(
            $allStages,
            fn(InstallationStageInterface $s) => !in_array($s->name(), $skipInRestore)
        ));
    }

    /**
     * Run the guarded hosting-panel skeleton without entering the SSH pipeline.
     */
    private function runHostingPanelPipeline(
        CustomerInstallation $installation,
        CustomerRemoteServer $server,
    ): void {
        $installationState = $this->installationStates->read($installation);
        if (in_array($installationState, [
            self::INSTALLATION_BLOCKED,
            self::CUSTOMER_READY,
            self::FAILED,
        ], true)) {
            return;
        }

        if (in_array($installationState, [self::VERIFY_SUCCESS, self::VERIFY_FAILED], true)) {
            $this->persistVerifiedInstallationState($installation);
            return;
        }

        if ($installationState === self::INSTALLATION_READY) {
            $this->runHostingHttpVerification($installation);
            $this->persistVerifiedInstallationState($installation);
            return;
        }

        $this->state->markRunning($installation);
        $this->state->recordStageStart($installation, 'VALIDATE_HOSTING_TARGET');

        try {
            $driver = $this->connectionService->buildDriver($server);
            $validation = $driver->validate();
        } catch (\Throwable $e) {
            $this->failHostingStage($installation, 'VALIDATE_HOSTING_TARGET', $e->getMessage());
            return;
        }

        if (!$validation->passed) {
            $this->failHostingStage(
                $installation,
                'VALIDATE_HOSTING_TARGET',
                implode('; ', $validation->errors),
            );
            return;
        }

        if (!method_exists($driver, 'probeCapabilities')) {
            $this->failHostingStage(
                $installation,
                'VALIDATE_HOSTING_TARGET',
                'capability probing is not implemented for this hosting driver.',
            );
            return;
        }

        try {
            $probe = $driver->probeCapabilities();
        } catch (\Throwable $e) {
            $this->failHostingStage($installation, 'VALIDATE_HOSTING_TARGET', $e->getMessage());
            return;
        }

        $this->state->recordStageComplete(
            $installation,
            'VALIDATE_HOSTING_TARGET',
            ['mode' => InstallationMode::HOSTING_PANEL_INSTALL->value, 'driver' => (string) $server->type],
            0,
        );

        $stages = config('customer_installation.install_modes.HOSTING_PANEL_INSTALL.stages', []);
        if ($stages === []) {
            $this->state->markFailed($installation, 'HOSTING_PANEL_INSTALL blocked: stage configuration is missing.');
            return;
        }

        foreach ($stages as $stage) {
            $stageName = $stage['name'];
            if ($stageName === 'VALIDATE_HOSTING_TARGET') {
                continue;
            }
            if ($installation->hasCompletedStage($stageName)) {
                Log::info("CIE: Skipping completed hosting stage {$stageName}");
                continue;
            }

            if ($stageName === 'UPLOAD_PACKAGE') {
                $uploadedPackagePath = $this->runHostingPackageUpload($installation, $driver);
                if ($uploadedPackagePath === null) {
                    return;
                }

                if (!$this->runHostingPackageExtraction($installation, $driver, $uploadedPackagePath)) {
                    return;
                }

                if (!$this->runHostingEnvironmentGeneration($installation, $driver)) {
                    return;
                }

                continue;
            }

            if ($stageName === 'PREPARE_DATABASE') {
                if (!$this->runHostingDatabaseGate($installation)) {
                    return;
                }

                continue;
            }

            if ($stageName === 'EXECUTE_REMOTE_DEPLOYMENT') {
                if (!$this->finalizeRemoteDeployment($installation)) {
                    return;
                }

                $this->runHostingHttpVerification($installation);
                $this->persistVerifiedInstallationState($installation);
                return;
            }

            if ($stageName === 'VERIFY_HTTP') {
                $this->runHostingHttpVerification($installation);
                $this->persistVerifiedInstallationState($installation);
                return;
            }

            $missing = $this->missingHostingCapabilities($probe, $stage);
            if ($missing !== []) {
                $message = sprintf(
                    'missing %s capability: %s.',
                    ($stage['requires_any'] ?? []) !== [] ? 'one of the required' : 'mandatory',
                    implode(', ', $missing),
                );

                if ($stage['optional'] ?? false) {
                    $this->state->recordStageStart($installation, $stageName);
                    $this->state->recordStageComplete(
                        $installation,
                        $stageName,
                        ['skipped' => true, 'reason' => "{$stageName} skipped: {$message}"],
                        0,
                    );
                    continue;
                }

                $this->state->recordStageStart($installation, $stageName);
                $this->failHostingStage($installation, $stageName, $message);
                return;
            }

            $this->state->recordStageStart($installation, $stageName);
            $this->failHostingStage(
                $installation,
                $stageName,
                'hosting-panel operation is not implemented; no deployment action was attempted.',
            );
            return;
        }
    }

    private function runHostingPackageUpload(
        CustomerInstallation $installation,
        object $driver,
    ): ?string {
        $stageName = 'UPLOAD_PACKAGE';
        $this->state->recordStageStart($installation, $stageName);

        try {
            $sourcePath = method_exists($installation, 'packages')
                ? (string) ($installation->packages()
                    ->whereNotNull('local_path')
                    ->latest('id')
                    ->value('local_path') ?? '')
                : '';
            $documentRoot = rtrim((string) config('customer_installation.hosting.document_root'), '/\\');
            $targetPath = $documentRoot === ''
                ? ''
                : $documentRoot . '/' . basename($sourcePath ?: 'package.zip');
            $result = $this->packageUpload->execute($driver, $sourcePath, $targetPath);
        } catch (\Throwable $e) {
            $this->failHostingStage($installation, $stageName, $e->getMessage());
            return null;
        }

        if ($result instanceof UnsupportedOperationResult) {
            $this->failHostingStage($installation, $stageName, $result->reason);
            return null;
        }

        $this->state->recordStageComplete(
            $installation,
            $stageName,
            ['target_path' => $targetPath],
            0,
        );

        return $targetPath;
    }

    private function runHostingPackageExtraction(
        CustomerInstallation $installation,
        object $driver,
        string $uploadedPackagePath,
    ): bool {
        $stageName = 'EXTRACT_PACKAGE';
        $this->state->recordStageStart($installation, $stageName);

        if (!method_exists($driver, 'extractPackage')) {
            $this->failHostingStage(
                $installation,
                $stageName,
                'the hosting driver has no native package-extraction operation.',
            );
            return false;
        }

        try {
            $documentRoot = rtrim((string) config('customer_installation.hosting.document_root'), '/\\');
            $driver->extractPackage($uploadedPackagePath, $documentRoot);
        } catch (\Throwable $e) {
            $this->failHostingStage($installation, $stageName, $e->getMessage());
            return false;
        }

        $this->state->recordStageComplete(
            $installation,
            $stageName,
            ['document_root' => $documentRoot, 'archive_deleted' => true],
            0,
        );

        return true;
    }

    private function runHostingEnvironmentGeneration(
        CustomerInstallation $installation,
        object $driver,
    ): bool {
        $stageName = 'GENERATE_ENVIRONMENT';
        $this->state->recordStageStart($installation, $stageName);

        if (!method_exists($driver, 'mergeEnvironment')) {
            $this->failHostingStage(
                $installation,
                $stageName,
                'the hosting driver has no native environment-file operation.',
            );
            return false;
        }

        $payload = is_array($installation->request_payload) ? $installation->request_payload : [];
        $metadata = is_array($installation->metadata) ? $installation->metadata : [];
        $manualDatabase = data_get($payload, 'manual_db_credentials', []);
        $databaseValidated = $this->manualDatabaseValidationErrors($manualDatabase) === [];
        $domain = trim((string) data_get($payload, 'domain', ''));
        $appUrl = trim((string) data_get($payload, 'app_url', ''));

        if ($appUrl === '') {
            $appUrl = ($payload['ssl_enabled'] ?? false ? 'https' : 'http') . '://' . $domain;
        }

        $values = [
            'APP_NAME' => (string) data_get($payload, 'company', config('app.name', 'Laravel')),
            'APP_ENV' => 'production',
            'APP_KEY' => 'base64:' . base64_encode(random_bytes(32)),
            'APP_URL' => $appUrl,
            'LICENSE_KEY' => (string) ($metadata['license_key'] ?? data_get($payload, 'license_key', '__LICENSE_KEY__')),
            'PLATFORM_URL' => rtrim((string) config('app.url'), '/'),
            'CACHE_DRIVER' => 'file',
            'SESSION_DRIVER' => 'file',
            'QUEUE_CONNECTION' => 'sync',
            'LOG_CHANNEL' => 'stack',
            'DB_HOST' => $databaseValidated ? (string) $manualDatabase['host'] : '__DB_HOST__',
            'DB_PORT' => $databaseValidated ? (string) $manualDatabase['port'] : '__DB_PORT__',
            'DB_DATABASE' => $databaseValidated ? (string) $manualDatabase['database'] : '__DB_DATABASE__',
            'DB_USERNAME' => $databaseValidated ? (string) $manualDatabase['username'] : '__DB_USERNAME__',
            'DB_PASSWORD' => $databaseValidated ? (string) $manualDatabase['password'] : '__DB_PASSWORD__',
        ];

        try {
            $result = $driver->mergeEnvironment(
                rtrim((string) config('customer_installation.hosting.document_root'), '/\\') . '/.env',
                $values,
            );
        } catch (\Throwable $e) {
            $this->failHostingStage($installation, $stageName, $e->getMessage());
            return false;
        }

        $this->state->recordStageComplete(
            $installation,
            $stageName,
            [
                'generated_keys' => $result['generated_keys'],
                'placeholder_keys' => $databaseValidated ? [] : [
                    'DB_HOST', 'DB_PORT', 'DB_DATABASE', 'DB_USERNAME', 'DB_PASSWORD',
                ],
                'merge_performed' => $result['merge_performed'],
                'database_status' => $databaseValidated ? 'CredentialsProvided' : 'Pending',
            ],
            0,
        );

        return true;
    }

    /**
     * Validate manual credentials and update installation state only.
     * This gate never connects to the database or executes SQL.
     */
    private function runHostingDatabaseGate(CustomerInstallation $installation): bool
    {
        $stageName = 'PREPARE_DATABASE';
        $this->state->recordStageStart($installation, $stageName);
        $this->transitionDatabaseState($installation, self::DB_PENDING);
        $this->transitionDatabaseState($installation, self::DB_VALIDATING);

        $credentials = data_get($installation->request_payload, 'manual_db_credentials', []);
        $errors = $this->manualDatabaseValidationErrors($credentials);

        if ($errors !== []) {
            $this->pauseHostingForDatabase($installation, $errors);
            return false;
        }

        $this->transitionDatabaseState($installation, self::DB_READY);
        $this->state->recordStageComplete(
            $installation,
            $stageName,
            ['database_state' => self::DB_READY],
            0,
        );

        return true;
    }

    private function manualDatabaseValidationErrors(mixed $credentials): array
    {
        if (!is_array($credentials)) {
            return ['manual_db_credentials_required'];
        }

        $errors = [];
        foreach (['host', 'port', 'database', 'username', 'password'] as $field) {
            $value = $credentials[$field] ?? null;
            if (!is_scalar($value) || trim((string) $value) === '') {
                $errors[] = $field . '_required';
                continue;
            }

            if ($this->isPlaceholderOrUnresolvedToken((string) $value)) {
                $errors[] = $field . '_invalid';
            }
        }

        $port = $credentials['port'] ?? null;
        if (is_scalar($port)
            && trim((string) $port) !== ''
            && (!ctype_digit((string) $port) || (int) $port < 1 || (int) $port > 65535)) {
            $errors[] = 'port_invalid';
        }

        if (($credentials['validated'] ?? null) !== true) {
            $errors[] = 'validated_flag_required';
        }

        return array_values(array_unique($errors));
    }

    private function isPlaceholderOrUnresolvedToken(string $value): bool
    {
        $trimmed = trim($value);
        if (preg_match('/(?:\{\{[^}]+\}\}|\$\{[^}]+\}|__[A-Z0-9_.-]+__|%[A-Z0-9_.-]+%|<[^>]+>)/i', $trimmed)) {
            return true;
        }

        $normalized = strtolower(preg_replace('/[\s-]+/', '_', $trimmed));

        return in_array($normalized, [
            'placeholder', 'change_me', 'changeme', 'replace_me', 'replace_this',
            'todo', 'tbd', 'undefined', 'null', 'your_host', 'your_port',
            'your_database', 'your_username', 'your_password',
        ], true);
    }

    private function transitionDatabaseState(CustomerInstallation $installation, string $state): void
    {
        $metadata = is_array($installation->metadata) ? $installation->metadata : [];
        $metadata['database_state'] = $state;
        unset($metadata['database_validation_errors']);
        $installation->update(['metadata' => $metadata]);
    }

    private function pauseHostingForDatabase(CustomerInstallation $installation, array $errors): void
    {
        $this->installationStates->transition($installation, 'pending');

        $metadata = is_array($installation->metadata) ? $installation->metadata : [];
        $metadata['database_state'] = self::DB_FAILED;
        $metadata['database_validation_errors'] = $errors;

        $installation->update([
            'status' => 'pending',
            'current_stage' => 'PREPARE_DATABASE',
            'failure_reason' => self::DB_PAUSED_RESULT,
            'failed_at' => null,
            'metadata' => $metadata,
        ]);

        $this->state->recordStageFailure(
            $installation,
            'PREPARE_DATABASE',
            self::DB_PAUSED_RESULT,
            0,
        );
    }

    /**
     * Finalize the centrally initiated remote deployment without exposing customer execution.
     */
    private function finalizeRemoteDeployment(CustomerInstallation $installation): bool
    {
        $stageName = 'EXECUTE_REMOTE_DEPLOYMENT';
        $this->state->recordStageStart($installation, $stageName);

        try {
            $errors = $this->remoteDeploymentPreconditionErrors($installation);
            if ($errors !== []) {
                $this->failRemoteDeployment($installation, $errors);
                return false;
            }

            $this->installationStates->transition($installation, self::INSTALLATION_READY);

            $metadata = is_array($installation->metadata) ? $installation->metadata : [];
            $metadata['installation_state'] = self::INSTALLATION_READY;
            unset($metadata['precondition_errors']);

            $installation->update([
                'status' => self::INSTALLATION_READY,
                'failure_reason' => null,
                'failed_at' => null,
                'metadata' => $metadata,
            ]);

            $this->state->recordStageComplete(
                $installation,
                $stageName,
                [
                    'installation_state' => self::INSTALLATION_READY,
                    'customer_execution' => false,
                    'execution_origin' => 'admin_queue',
                    'next_stage' => 'VERIFY_HTTP',
                ],
                0,
            );

            $installation->update(['current_stage' => 'VERIFY_HTTP']);

            return true;
        } catch (\Throwable) {
            $this->failRemoteDeployment($installation, ['deployment_state_update_failed']);

            return false;
        }
    }

    private function remoteDeploymentPreconditionErrors(CustomerInstallation $installation): array
    {
        $errors = [];

        foreach (['UPLOAD_PACKAGE', 'EXTRACT_PACKAGE'] as $requiredStage) {
            if (!$installation->hasCompletedStage($requiredStage)) {
                $errors[] = strtolower($requiredStage) . '_not_completed';
            }
        }

        if (!$installation->hasCompletedStage('GENERATE_ENVIRONMENT')) {
            $errors[] = 'environment_not_generated';
        } else {
            $environmentLog = $installation->logs()
                ->where('stage', 'GENERATE_ENVIRONMENT')
                ->where('event', 'completed')
                ->latest('id')
                ->first();
            $environmentOutput = is_array($environmentLog?->output) ? $environmentLog->output : [];

            if (($environmentOutput['merge_performed'] ?? null) !== true
                || ($environmentOutput['placeholder_keys'] ?? null) !== []) {
                $errors[] = 'environment_generation_partial';
            }
        }

        $databaseState = data_get($installation->metadata, 'database_state');
        if ($databaseState !== self::DB_READY) {
            $errors[] = 'database_not_ready';
        }

        if ($installation->failure_reason === self::DB_PAUSED_RESULT) {
            $errors[] = 'database_pause_active';
        }

        return $errors;
    }

    private function failRemoteDeployment(
        CustomerInstallation $installation,
        array $errors,
    ): void {
        $this->installationStates->transition($installation, self::INSTALLATION_BLOCKED);

        $metadata = is_array($installation->metadata) ? $installation->metadata : [];
        $metadata['installation_state'] = self::INSTALLATION_BLOCKED;
        $metadata['precondition_errors'] = $errors;

        $installation->update([
            'status' => self::INSTALLATION_BLOCKED,
            'current_stage' => 'EXECUTE_REMOTE_DEPLOYMENT',
            'failure_reason' => self::DEPLOYMENT_BLOCKED_RESULT,
            'metadata' => $metadata,
        ]);

        $this->state->recordStageFailure(
            $installation,
            'EXECUTE_REMOTE_DEPLOYMENT',
            self::DEPLOYMENT_BLOCKED_RESULT,
            0,
        );
    }

    /**
     * Perform one HTTP request only; no retries, redirects, or installer actions.
     */
    private function runHostingHttpVerification(CustomerInstallation $installation): void
    {
        $stageName = 'VERIFY_HTTP';
        $this->state->recordStageStart($installation, $stageName);

        if ($this->installationStates->read($installation) !== self::INSTALLATION_READY) {
            $this->failRemoteDeployment($installation, ['verification_state_not_ready']);
            return;
        }

        $url = $this->hostingVerificationUrl($installation);
        if ($url === null) {
            $this->failHostingHttpVerification($installation, 'invalid_verification_url');
            return;
        }

        try {
            $response = Http::connectTimeout(5)
                ->timeout(10)
                ->withOptions(['allow_redirects' => false])
                ->get($url);
            $statusCode = $response->status();
        } catch (\Throwable) {
            $this->failHostingHttpVerification($installation, 'http_request_failed');
            return;
        }

        if (!in_array($statusCode, [200, 302], true)) {
            $this->failHostingHttpVerification($installation, 'unexpected_http_status', $statusCode);
            return;
        }

        $this->installationStates->transition($installation, self::VERIFY_SUCCESS);

        $metadata = is_array($installation->metadata) ? $installation->metadata : [];
        $metadata['installation_state'] = self::VERIFY_SUCCESS;
        $metadata['verify_status_code'] = $statusCode;
        unset($metadata['verify_failure_reason']);

        $installation->update([
            'status' => self::VERIFY_SUCCESS,
            'failure_reason' => null,
            'failed_at' => null,
            'metadata' => $metadata,
        ]);
        $this->state->recordStageComplete(
            $installation,
            $stageName,
            [
                'installation_state' => self::VERIFY_SUCCESS,
                'status_code' => $statusCode,
            ],
            0,
        );
    }

    private function failHostingHttpVerification(
        CustomerInstallation $installation,
        string $reason,
        ?int $statusCode = null,
    ): void {
        $this->installationStates->transition($installation, self::VERIFY_FAILED);

        $metadata = is_array($installation->metadata) ? $installation->metadata : [];
        $metadata['installation_state'] = self::VERIFY_FAILED;
        $metadata['verify_failure_reason'] = $reason;
        $metadata['verify_status_code'] = $statusCode;

        $installation->update([
            'status' => self::VERIFY_FAILED,
            'current_stage' => 'VERIFY_HTTP',
            'failure_reason' => self::VERIFY_FAILED,
            'metadata' => $metadata,
        ]);
        $this->state->recordStageFailure(
            $installation,
            'VERIFY_HTTP',
            self::VERIFY_FAILED,
            0,
        );
    }

    private function hostingVerificationUrl(CustomerInstallation $installation): ?string
    {
        $payload = is_array($installation->request_payload) ? $installation->request_payload : [];
        $url = trim((string) data_get($payload, 'app_url', ''));

        if ($url === '') {
            $domain = trim((string) data_get($payload, 'domain', ''));
            if ($domain === '') {
                return null;
            }

            $url = (($payload['ssl_enabled'] ?? false) ? 'https' : 'http') . '://' . $domain;
        }

        $parts = parse_url($url);
        if (filter_var($url, FILTER_VALIDATE_URL) === false
            || !is_array($parts)
            || !in_array($parts['scheme'] ?? null, ['http', 'https'], true)
            || empty($parts['host'])
            || isset($parts['user'])
            || isset($parts['pass'])) {
            return null;
        }

        return $url;
    }

    private function persistVerifiedInstallationState(CustomerInstallation $installation): void
    {
        $verificationState = $this->installationStates->read($installation);

        if ($verificationState === self::VERIFY_SUCCESS) {
            $this->persistCustomerReady($installation);
            return;
        }

        if ($verificationState === self::VERIFY_FAILED) {
            $this->persistVerificationFailure($installation);
        }
    }

    /**
     * Persist the existing installation and site records atomically and idempotently.
     */
    private function persistCustomerReady(CustomerInstallation $installation): void
    {
        if ($this->installationStates->read($installation) === self::CUSTOMER_READY) {
            return;
        }

        try {
            DB::transaction(function () use ($installation): void {
                $lockedInstallation = CustomerInstallation::query()
                    ->lockForUpdate()
                    ->findOrFail($installation->getKey());
                $state = $this->installationStates->read($lockedInstallation);

                if ($state === self::CUSTOMER_READY) {
                    return;
                }

                if ($state !== self::VERIFY_SUCCESS) {
                    throw new \RuntimeException('Installation is not verified.');
                }

                $this->installationStates->transition($lockedInstallation, self::CUSTOMER_READY);

                $site = Site::query()
                    ->lockForUpdate()
                    ->find($lockedInstallation->site_id);
                if ($site === null) {
                    throw new \RuntimeException('Installation site was not found.');
                }

                $metadata = is_array($lockedInstallation->metadata)
                    ? $lockedInstallation->metadata
                    : [];
                $metadata['installation_state'] = self::CUSTOMER_READY;
                unset($metadata['verify_failure_reason'], $metadata['precondition_errors']);

                $completedStages = $lockedInstallation->completedStagesList();
                if (!in_array(self::CUSTOMER_READY, $completedStages, true)) {
                    $completedStages[] = self::CUSTOMER_READY;
                }

                $lockedInstallation->update([
                    'status' => self::CUSTOMER_READY,
                    'current_stage' => self::CUSTOMER_READY,
                    'completed_stages' => $completedStages,
                    'completed_at' => $lockedInstallation->completed_at ?? now(),
                    'failed_at' => null,
                    'failure_reason' => null,
                    'metadata' => $metadata,
                ]);

                if (!$site->is_active) {
                    $site->update(['is_active' => true]);
                }
            });

            $installation->refresh();
        } catch (\Throwable) {
            $this->persistCustomerReadyFailure($installation);
        }
    }

    private function persistVerificationFailure(CustomerInstallation $installation): void
    {
        if ($this->installationStates->read($installation) === self::FAILED) {
            return;
        }

        DB::transaction(function () use ($installation): void {
            $lockedInstallation = CustomerInstallation::query()
                ->lockForUpdate()
                ->findOrFail($installation->getKey());
            $state = $this->installationStates->read($lockedInstallation);

            if ($state === self::FAILED) {
                return;
            }

            if ($state !== self::VERIFY_FAILED) {
                throw new \RuntimeException('Installation verification has not failed.');
            }

            $this->installationStates->transition($lockedInstallation, self::FAILED);

            $metadata = is_array($lockedInstallation->metadata)
                ? $lockedInstallation->metadata
                : [];
            $metadata['installation_state'] = self::FAILED;

            $lockedInstallation->update([
                'status' => self::FAILED,
                'current_stage' => 'VERIFY_HTTP',
                'completed_at' => null,
                'failed_at' => $lockedInstallation->failed_at ?? now(),
                'failure_reason' => self::VERIFY_FAILED,
                'metadata' => $metadata,
            ]);
        });

        $installation->refresh();
    }

    private function persistCustomerReadyFailure(CustomerInstallation $installation): void
    {
        $this->installationStates->transition($installation, self::FAILED);

        $metadata = is_array($installation->metadata) ? $installation->metadata : [];
        $metadata['installation_state'] = self::FAILED;
        $metadata['persistence_failure_reason'] = 'CUSTOMER_READY_PERSISTENCE_FAILED';

        $installation->update([
            'status' => self::FAILED,
            'failed_at' => now(),
            'failure_reason' => 'CUSTOMER_READY_PERSISTENCE_FAILED',
            'metadata' => $metadata,
        ]);
    }

    private function missingHostingCapabilities(object $probe, array $stage): array
    {
        $missing = [];

        foreach ($stage['requires_all'] ?? [] as $capability) {
            if (!$probe->hasFreshEvidenceFor([DeploymentCapability::from($capability)])) {
                $missing[] = $capability;
            }
        }

        $requiresAny = $stage['requires_any'] ?? [];
        if ($requiresAny !== []) {
            foreach ($requiresAny as $capability) {
                if ($probe->hasFreshEvidenceFor([DeploymentCapability::from($capability)])) {
                    return $missing;
                }
            }

            return $requiresAny;
        }

        return $missing;
    }

    private function failHostingStage(
        CustomerInstallation $installation,
        string $stageName,
        string $reason,
    ): void {
        $message = "{$stageName} blocked: {$reason}";
        $this->state->recordStageFailure($installation, $stageName, $message, 0);
        $this->state->markFailed($installation, $message);
    }

    private function dispatchRollback(CustomerInstallation $installation): void
    {
        try {
            CustomerInstallationRollbackJob::dispatch($installation->id)
                ->onConnection('customer-install')
                ->onQueue('customer-install');
        } catch (\Throwable $e) {
            Log::error("CIE: Failed to dispatch rollback job", ['error' => $e->getMessage()]);
        }
    }
}
