<?php

namespace App\Services\CustomerDeletion\Dto;

use App\Models\CustomerDeletion;
use App\Models\CustomerInstallation;
use App\Models\CustomerInstallationLog;
use App\Models\CustomerInstallRollback;
use App\Models\CustomerRemoteServer;
use App\Models\Site;
use App\Services\CustomerInstallation\Contracts\DeploymentDriverInterface;
use App\Services\CustomerInstallation\Dto\CustomerInstallRequest;
use App\Services\CustomerInstallation\Dto\InstallationContext;
use App\Services\CustomerInstallation\Enums\DriverType;
use App\Services\CustomerInstallation\WebRootResolver;

/**
 * Immutable context for a customer deletion pipeline run.
 *
 * All resource identifiers (web root, DB name, supervisor config path, etc.)
 * are resolved dynamically from the CustomerInstallation record and its
 * stored stage outputs. Nothing is hardcoded.
 *
 * Phase 14-G3:
 * - $connection widened from RemoteConnectionInterface to
 *   DeploymentDriverInterface so deletion stages can consult capabilities()
 *   — the exact mirror of InstallationContext's Phase 14-E widening.
 *   RemoteConnectionService::buildDriver() (the only producer) has always
 *   returned a DeploymentDriverInterface.
 * - webRoot resolved via WebRootResolver instead of a hardcoded
 *   '/var/www/{slug}' — VPS resolution is byte-identical (same default),
 *   cPanel resolves to the account's real document root (path override or
 *   /home/{username}/public_html).
 * - schedulerRemovalSnapshot / queueRemovalSnapshot: the ORIGINAL install
 *   snapshots (cron_line for cPanel UAPI entries, cron_path/config_path for
 *   VPS files), resolved from the immutable installation log with the
 *   rollback checkpoint as fallback — never reconstructed from scratch. An
 *   empty array means "nothing recorded and no safe fallback" (cPanel with
 *   no stored cron_line), which the Remove stages treat as a safe skip.
 */
class DeleteContext
{
    /** Stage-keyed output map populated during the pipeline run. */
    public array $stageOutputs = [];

    private ?InstallationContext $installationContext = null;

    private function __construct(
        public readonly CustomerDeletion          $deletion,
        public readonly CustomerInstallation      $installation,
        public readonly DeploymentDriverInterface $connection,
        public readonly DeletePolicy              $policy,
        // Resolved identifiers
        public readonly string  $slug,
        public readonly string  $webRoot,
        public readonly string  $domain,
        public readonly ?string $dbName,
        public readonly ?string $dbUser,
        public readonly string  $supervisorConfigPath,
        public readonly string  $supervisorLogPath,
        public readonly string  $supervisorProgramName,
        public readonly string  $cronPath,
        public readonly string  $nginxConfigPath,
        public readonly string  $nginxEnabledPath,
        public readonly ?int    $siteId,
        public readonly ?string $licenseKey,
        public readonly ?string $arvanBaselineKey,
        public readonly array   $schedulerRemovalSnapshot,
        public readonly array   $queueRemovalSnapshot,
    ) {}

    /**
     * Build a DeleteContext by resolving all resource identifiers from the
     * installation record and its stored stage outputs. No hardcoded paths.
     */
    public static function fromDeletion(
        CustomerDeletion          $deletion,
        DeploymentDriverInterface $connection,
    ): self {
        $installation = $deletion->installation;
        $policy       = DeletePolicy::from($deletion->policy);

        $slug   = self::resolveSlug($installation);
        $domain = $installation->customer_domain;
        $server = CustomerRemoteServer::find($installation->remote_server_id);

        [$dbName, $dbUser] = self::resolveDbCredentials($installation);

        $siteId     = $installation->site_id;
        $licenseKey = $siteId ? Site::find($siteId)?->license_key : null;
        $arvanKey   = $installation->initial_baseline_arvan_key;

        $cronPath             = "/etc/cron.d/{$slug}";
        $supervisorConfigPath = "/etc/supervisor/conf.d/{$slug}_worker.conf";

        return new self(
            deletion:              $deletion,
            installation:          $installation,
            connection:            $connection,
            policy:                $policy,
            slug:                  $slug,
            webRoot:               (new WebRootResolver())->resolve($connection, $slug, $server),
            domain:                $domain,
            dbName:                $dbName,
            dbUser:                $dbUser,
            supervisorConfigPath:  $supervisorConfigPath,
            supervisorLogPath:     "/var/log/supervisor/{$slug}_worker.log",
            supervisorProgramName: "{$slug}_worker",
            cronPath:              $cronPath,
            nginxConfigPath:       "/etc/nginx/sites-available/{$slug}",
            nginxEnabledPath:      "/etc/nginx/sites-enabled/{$slug}",
            siteId:                $siteId,
            licenseKey:            $licenseKey,
            arvanBaselineKey:      $arvanKey,
            schedulerRemovalSnapshot: self::resolveRemovalSnapshot(
                $installation, $connection, 'CONFIGURE_SCHEDULER',
                ['cron_line', 'cron_path'],
                ['cron_path' => $cronPath],
            ),
            queueRemovalSnapshot: self::resolveRemovalSnapshot(
                $installation, $connection, 'CONFIGURE_QUEUE',
                ['cron_line', 'config_path'],
                ['config_path' => $supervisorConfigPath],
            ),
        );
    }

    public function recordOutput(string $stage, array $output): void
    {
        $this->stageOutputs[$stage] = $output;
    }

    public function getOutput(string $stage): array
    {
        return $this->stageOutputs[$stage] ?? [];
    }

    /**
     * Phase 14-G3 — the shared provisioner layer (DatabaseProvisionerInterface,
     * CronProvisionerInterface, QueueProvisionerInterface) is typed to
     * InstallationContext. Rebuild one from the installation record exactly
     * the way CustomerInstallationRollbackService::rollback() does, so the
     * deletion stages can hand provisioners a properly-typed context.
     * Memoized — one rebuild per pipeline run.
     */
    public function installationContext(): InstallationContext
    {
        if ($this->installationContext === null) {
            $this->installationContext = new InstallationContext(
                $this->installation,
                CustomerInstallRequest::fromArray($this->installation->request_payload ?? []),
                $this->connection,
            );
        }

        return $this->installationContext;
    }

    /**
     * Derive the customer slug using exactly the same algorithm as
     * InstallationContext::customerSlug() so the delete engine finds
     * the correct paths without any hardcoding.
     */
    private static function resolveSlug(CustomerInstallation $installation): string
    {
        $ascii   = preg_replace('/[^\x20-\x7E]/', '', $installation->customer_company ?? '');
        $clean   = strtolower(preg_replace('/[^a-z0-9]+/', '_', $ascii));
        $clean   = trim($clean, '_');
        $segment = ($clean === '') ? 'site' : substr($clean, 0, 20);
        return 'cust_' . $segment . '_' . $installation->id;
    }

    /**
     * Resolve DB name and user from:
     * 1. Stage output log (CREATE_DATABASE completed event output)
     * 2. Rollback checkpoint snapshot (fallback)
     * Returns [dbName, dbUser].
     */
    private static function resolveDbCredentials(CustomerInstallation $installation): array
    {
        // Primary: stage output from immutable log
        $log = CustomerInstallationLog::where('installation_id', $installation->id)
            ->where('stage', 'CREATE_DATABASE')
            ->where('event', 'completed')
            ->first();

        if ($log && !empty($log->output['db_name'])) {
            return [$log->output['db_name'], $log->output['db_user'] ?? null];
        }

        // Fallback: rollback snapshot (stored before stage execution)
        $rollback = CustomerInstallRollback::where('installation_id', $installation->id)
            ->where('stage', 'CREATE_DATABASE')
            ->first();

        if ($rollback && !empty($rollback->snapshot['db_name'])) {
            return [$rollback->snapshot['db_name'], $rollback->snapshot['db_user'] ?? null];
        }

        return [null, null];
    }

    /**
     * Phase 14-G3 — resolve the snapshot the cron/queue provisioner's
     * remove*() method needs, from what the INSTALL actually recorded
     * (immutable log output first, rollback checkpoint as fallback),
     * filtered to the keys the removal path understands.
     *
     * When nothing was recorded (legacy install, or the configure stage was
     * skipped/failed): VPS-family drivers fall back to the deterministic
     * legacy path — byte-identical to the pre-14-G3 deletion behavior, and
     * harmless because the provisioner's removal is an idempotent `rm -f`.
     * cPanel gets NO fallback: a UAPI cron_line must never be reconstructed
     * by guesswork (removing a mismatched line is a silent no-op at best and
     * the wrong line at worst), so the stage sees [] and skips safely.
     */
    private static function resolveRemovalSnapshot(
        CustomerInstallation      $installation,
        DeploymentDriverInterface $connection,
        string                    $stage,
        array                     $allowedKeys,
        array                     $vpsFallback,
    ): array {
        $log = CustomerInstallationLog::where('installation_id', $installation->id)
            ->where('stage', $stage)
            ->where('event', 'completed')
            ->first();

        $fromLog = array_intersect_key($log?->output ?? [], array_flip($allowedKeys));
        if (!empty($fromLog)) {
            return $fromLog;
        }

        $rollback = CustomerInstallRollback::where('installation_id', $installation->id)
            ->where('stage', $stage)
            ->first();

        $fromCheckpoint = array_intersect_key($rollback?->snapshot ?? [], array_flip($allowedKeys));
        if (!empty($fromCheckpoint)) {
            return $fromCheckpoint;
        }

        return $connection->driverType() === DriverType::CPANEL ? [] : $vpsFallback;
    }
}
