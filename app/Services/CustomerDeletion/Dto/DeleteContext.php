<?php

namespace App\Services\CustomerDeletion\Dto;

use App\Models\CustomerDeletion;
use App\Models\CustomerInstallation;
use App\Models\CustomerInstallationLog;
use App\Models\CustomerInstallRollback;
use App\Models\Site;
use App\Services\CustomerInstallation\Contracts\RemoteConnectionInterface;

/**
 * Immutable context for a customer deletion pipeline run.
 *
 * All resource identifiers (web root, DB name, supervisor config path, etc.)
 * are resolved dynamically from the CustomerInstallation record and its
 * stored stage outputs. Nothing is hardcoded.
 */
class DeleteContext
{
    /** Stage-keyed output map populated during the pipeline run. */
    public array $stageOutputs = [];

    private function __construct(
        public readonly CustomerDeletion          $deletion,
        public readonly CustomerInstallation      $installation,
        public readonly RemoteConnectionInterface $connection,
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
    ) {}

    /**
     * Build a DeleteContext by resolving all resource identifiers from the
     * installation record and its stored stage outputs. No hardcoded paths.
     */
    public static function fromDeletion(
        CustomerDeletion          $deletion,
        RemoteConnectionInterface $connection,
    ): self {
        $installation = $deletion->installation;
        $policy       = DeletePolicy::from($deletion->policy);

        $slug   = self::resolveSlug($installation);
        $domain = $installation->customer_domain;

        [$dbName, $dbUser] = self::resolveDbCredentials($installation);

        $siteId     = $installation->site_id;
        $licenseKey = $siteId ? Site::find($siteId)?->license_key : null;
        $arvanKey   = $installation->initial_baseline_arvan_key;

        return new self(
            deletion:              $deletion,
            installation:          $installation,
            connection:            $connection,
            policy:                $policy,
            slug:                  $slug,
            webRoot:               '/var/www/' . $slug,
            domain:                $domain,
            dbName:                $dbName,
            dbUser:                $dbUser,
            supervisorConfigPath:  "/etc/supervisor/conf.d/{$slug}_worker.conf",
            supervisorLogPath:     "/var/log/supervisor/{$slug}_worker.log",
            supervisorProgramName: "{$slug}_worker",
            cronPath:              "/etc/cron.d/{$slug}",
            nginxConfigPath:       "/etc/nginx/sites-available/{$slug}",
            nginxEnabledPath:      "/etc/nginx/sites-enabled/{$slug}",
            siteId:                $siteId,
            licenseKey:            $licenseKey,
            arvanBaselineKey:      $arvanKey,
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
}
