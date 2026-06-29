<?php

namespace App\Services\CustomerDeletion\Stages;

use App\Services\CustomerDeletion\Dto\DeleteContext;
use App\Services\CustomerDeletion\Dto\DeleteStageResult;

/**
 * STAGE 1: PRE_DELETE_AUDIT
 *
 * Catalogs everything that currently exists on the remote server and in the
 * platform DB before any removal takes place. The audit snapshot is stored on
 * the CustomerDeletion record and serves as the authoritative record of what
 * was deleted.
 */
class PreDeleteAuditStage extends AbstractDeleteStage
{
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

            // Probe remote server for what actually exists
            $probes = [];

            if ($ctx->connection) {
                $probes['web_root_exists']      = $this->remoteExists($ctx, $ctx->webRoot);
                $probes['supervisor_exists']    = $this->remoteExists($ctx, $ctx->supervisorConfigPath);
                $probes['cron_exists']          = $this->remoteExists($ctx, $ctx->cronPath);
                $probes['nginx_config_exists']  = $this->remoteExists($ctx, $ctx->nginxConfigPath);
                $probes['nginx_enabled_exists'] = $this->remoteExists($ctx, $ctx->nginxEnabledPath);
                $probes['supervisor_running']   = $this->supervisorProgramRunning($ctx, $ctx->supervisorProgramName);

                if ($ctx->dbName) {
                    $dbCheck = $this->remoteExec($ctx,
                        'mysql -u root -e ' . escapeshellarg("SHOW DATABASES LIKE '{$ctx->dbName}';") . ' 2>/dev/null | wc -l'
                    );
                    $probes['database_exists'] = (int) trim($dbCheck) > 1;
                }
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
