<?php

namespace App\Services\TenantMedia;

use App\Models\CustomerRemoteServer;
use App\Services\CustomerInstallation\Contracts\DeploymentDriverInterface;
use App\Services\CustomerInstallation\Env\RemoteEnvFile;

/**
 * Phase 4 — the customer-site .env lines that move a site's uploads to the
 * tenant media bucket (config resource_governance.tenant_media).
 *
 * Written only for a platform-managed server, and only with a media key
 * that differs from the platform's own backup key, so a customer site can
 * never reach safhenokhost-backups. ARVAN_BUCKET (the customer-site default
 * bucket of its old arvan_backup disk) is never written. Each site gets
 * its own prefix: sites/{site_id}/media (private files: sites/{id}/private).
 *
 * The variable names are the ones customer-site's config/filesystems.php
 * `arvan` disk already reads (ARVAN_ACCESS_KEY, ARVAN_MEDIA_BUCKET, ...).
 */
class TenantMediaEnvironment
{
    public const SKIP_NO_SITE = 'site_id_unknown';
    public const SKIP_NOT_PLATFORM_MANAGED = 'server_not_platform_managed';
    public const SKIP_DISABLED = 'tenant_media_disabled';
    public const SKIP_INCOMPLETE = 'tenant_media_config_incomplete';
    public const SKIP_BACKUP_KEY = 'media_key_equals_backup_key';

    /** @return string|null why the site must stay on local disk, or null when eligible */
    public function ineligibility(?CustomerRemoteServer $server, ?int $siteId): ?string
    {
        if ($siteId === null || $siteId <= 0) {
            return self::SKIP_NO_SITE;
        }
        if ($server === null || ! $server->is_platform_managed) {
            return self::SKIP_NOT_PLATFORM_MANAGED;
        }

        $cfg = (array) config('resource_governance.tenant_media', []);
        if (empty($cfg['enabled'])) {
            return self::SKIP_DISABLED;
        }
        foreach (['access_key', 'secret_key', 'bucket', 'endpoint'] as $required) {
            if (trim((string) ($cfg[$required] ?? '')) === '') {
                return self::SKIP_INCOMPLETE;
            }
        }

        $backupKey = (string) config('filesystems.disks.arvan_backup.key', '');
        if ($backupKey !== '' && hash_equals($backupKey, (string) $cfg['access_key'])) {
            return self::SKIP_BACKUP_KEY;
        }

        return null;
    }

    /**
     * The variables, credentials first and MEDIA_DISK last: a site switches
     * to object storage only once everything it needs is in place.
     *
     * @return array<string, string>
     */
    public function variables(int $siteId): array
    {
        $cfg = (array) config('resource_governance.tenant_media', []);
        $endpoint = rtrim((string) $cfg['endpoint'], '/');
        $bucket = (string) $cfg['bucket'];

        return [
            'ARVAN_ACCESS_KEY'           => (string) $cfg['access_key'],
            'ARVAN_SECRET_KEY'           => (string) $cfg['secret_key'],
            'ARVAN_REGION'               => (string) ($cfg['region'] ?? ''),
            'ARVAN_ENDPOINT'             => $endpoint,
            'ARVAN_MEDIA_BUCKET'         => $bucket,
            'ARVAN_MEDIA_PREFIX'         => "sites/{$siteId}/media",
            'ARVAN_MEDIA_PRIVATE_PREFIX' => "sites/{$siteId}/private",
            'ARVAN_MEDIA_URL'            => rtrim((string) ($cfg['url'] ?: "{$endpoint}/{$bucket}"), '/'),
            'MEDIA_DISK'                 => 'arvan',
        ];
    }

    /**
     * Upsert the variables into {webRoot}/.env over the site's connection,
     * then clear its config cache.
     *
     * @return array{applied: bool, reason: ?string, written: list<string>}
     */
    public function apply(DeploymentDriverInterface $connection, string $webRoot, ?CustomerRemoteServer $server, ?int $siteId): array
    {
        if (($reason = $this->ineligibility($server, $siteId)) !== null) {
            return ['applied' => false, 'reason' => $reason, 'written' => []];
        }

        $envPath = rtrim($webRoot, '/') . '/.env';
        $written = [];
        foreach ($this->variables((int) $siteId) as $key => $value) {
            $result = $connection->execute(RemoteEnvFile::set($envPath, $key, $value));
            if (! str_contains((string) $result->stdout, RemoteEnvFile::SET_OK)) {
                // MEDIA_DISK is last: stopping here leaves the site on local disk.
                return ['applied' => false, 'reason' => "env_write_failed:{$key}", 'written' => $written];
            }
            $written[] = $key;
        }

        $connection->execute('cd ' . escapeshellarg($webRoot) . ' && php artisan config:clear 2>&1');

        return ['applied' => true, 'reason' => null, 'written' => $written];
    }
}
