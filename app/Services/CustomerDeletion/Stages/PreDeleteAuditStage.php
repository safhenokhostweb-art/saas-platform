<?php

namespace App\Services\CustomerDeletion\Stages;

use App\Services\CustomerDeletion\Dto\DeleteContext;
use App\Services\CustomerDeletion\Dto\DeleteStageResult;
use App\Services\CustomerInstallation\Provisioning\DatabaseProvisionerFactory;

/**
 * STAGE 1: PRE_DELETE_AUDIT
 *
 * Catalogs everything that currently exists on the remote server and in the
 * platform DB before any removal takes place. The audit snapshot is stored on
 * the CustomerDeletion record and serves as the authoritative record of what
 * was deleted.
 *
 * Phase 14-G3 — probes are capability-aware: Supervisor/nginx paths are only
 * probed on drivers that actually manage them (a cPanel account would always
 * report false for /etc/supervisor and /etc/nginx — noise at best, misleading
 * at worst), and the database probe goes through the hosting-appropriate
 * provisioner instead of a raw root-mysql query.
 */
class PreDeleteAuditStage extends AbstractDeleteStage
{
    public function __construct(private readonly DatabaseProvisionerFactory $provisioners) {}

    public function name(): string { return 'PRE_DELETE_AUDIT'; }
    public function label(): string { return 'ممیزی پیش از حذف'; }
    public function isCritical(): bool { return true; }
    public function estimatedSeconds(): int { return 10; }

    public function execute(DeleteContext $ctx): DeleteStageResult
    {
        return $this->run($ctx, function (DeleteContext $ctx) {
            $snapshot = [
                'slug'                  => $ctx->slug,
                'domain'                => $ctx->domain,
                'web_root'              => $ctx->webRoot,
                'db_name'               => $ctx->dbName,
                'db_user'               => $ctx->dbUser,
                'supervisor_config'     => $ctx->supervisorConfigPath,
                'supervisor_program'    => $ctx->supervisorProgramName,
                'cron_path'             => $ctx->cronPath,
                'nginx_config_path'     => $ctx->nginxConfigPath,
                'nginx_enabled_path'    => $ctx->nginxEnabledPath,
                'site_id'               => $ctx->siteId,
                'license_key_prefix'    => $ctx->licenseKey ? substr($ctx->licenseKey, 0, 8) : null,
                'arvan_baseline_key'    => $ctx->arvanBaselineKey,
                'policy'                => $ctx->policy->value,
                'audited_at'            => now()->toIso8601String(),
            ];

            // Probe remote server for what actually exists — only the
            // resources this driver can genuinely manage (Phase 14-G3).
            $probes = [];
            $capabilities = $ctx->connection->capabilities();

            $probes['web_root_exists'] = $this->remoteExists($ctx, $ctx->webRoot);

            if ($capabilities->supportsSupervisor) {
                $probes['supervisor_exists']  = $this->remoteExists($ctx, $ctx->supervisorConfigPath);
                $probes['supervisor_running'] = $this->supervisorProgramRunning($ctx, $ctx->supervisorProgramName);
            }

            if (!empty($ctx->schedulerRemovalSnapshot['cron_path'])) {
                $probes['cron_exists'] = $this->remoteExists($ctx, $ctx->schedulerRemovalSnapshot['cron_path']);
            }

            if ($capabilities->supportsWebServerConfiguration) {
                $probes['nginx_config_exists']  = $this->remoteExists($ctx, $ctx->nginxConfigPath);
                $probes['nginx_enabled_exists'] = $this->remoteExists($ctx, $ctx->nginxEnabledPath);
            }

            if ($ctx->dbName && $capabilities->supportsDatabaseDeletion) {
                // null = unverifiable on this driver/transport (recorded as-is)
                $probes['database_exists'] = $this->provisioners
                    ->forDriverType($ctx->connection->driverType())
                    ->databaseExists($ctx->installationContext(), $ctx->dbName);
            }

            $snapshot['remote_probes'] = $probes;

            // Store on deletion record
            $ctx->deletion->update(['audit_snapshot' => $snapshot]);

            return DeleteStageResult::success($this->name(), $snapshot);
        });
    }

    public function verify(DeleteContext $ctx): array
    {
        // Nothing to verify — this stage only reads state
        return [
            ['check' => 'Audit snapshot stored', 'passed' => !empty($ctx->deletion->audit_snapshot), 'detail' => 'snapshot in DB'],
        ];
    }
}
