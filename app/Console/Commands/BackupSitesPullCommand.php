<?php

namespace App\Console\Commands;

use App\Models\CustomerInstallation;
use App\Models\Infrastructure\InfraBackupRun;
use App\Models\Site;
use App\Services\CustomerInstallation\Dto\InstallationContext;
use App\Services\CustomerInstallation\Env\RemoteEnvFile;
use App\Services\Infrastructure\ArvanBackupService;
use App\Services\Infrastructure\SlackAlertService;
use App\Services\Sites\SiteRemoteContextFactory;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Phase 2 — pull-model tenant database backups.
 *
 * For every installed site: read its database credentials from its own
 * .env over SSH, mysqldump that database on the site's server, verify the
 * gzip and its SHA-256, download it, check the hash again here, upload it
 * to the private backup bucket (filesystems.disks.arvan_backup) under
 * tenants/site-{id}/db/ and prune that prefix to the retention count. Both
 * temporary files are always removed.
 *
 * Unlike db:backup-tenants (rows of the old shared customer_site database)
 * this covers each site's OWN database, and the site needs no bucket key.
 */
class BackupSitesPullCommand extends Command
{
    protected $signature = 'db:backup-sites
                            {--site=* : Site id(s); default all installed sites}
                            {--retention= : Copies kept per site in the bucket (default resource_governance.tenant_backup.retention)}
                            {--dry-run : List what would be backed up}';

    protected $description = 'Pull each customer site\'s own database over SSH into the private backup bucket (tenants/site-{id}/db/)';

    public function __construct(
        private readonly SiteRemoteContextFactory $contexts,
        private readonly ArvanBackupService $arvan,
    ) {
        parent::__construct();
    }

    public static function remotePrefix(int $siteId): string
    {
        return "tenants/site-{$siteId}/db";
    }

    public function handle(): int
    {
        $retention = max(1, (int) ($this->option('retention') ?: config('resource_governance.tenant_backup.retention', 14)));
        $ids = array_map('intval', (array) $this->option('site'));
        $siteIds = CustomerInstallation::query()->where('status', 'complete')->whereNotNull('site_id')
            ->when($ids !== [], fn ($q) => $q->whereIn('site_id', $ids))
            ->distinct()->pluck('site_id');
        $sites = Site::query()->whereIn('id', $siteIds)
            ->where(fn ($q) => $q->whereNull('lifecycle_status')->orWhere('lifecycle_status', '!=', 'deleted'))
            ->orderBy('id')->get();

        if ($this->option('dry-run')) {
            foreach ($sites as $site) {
                $this->line("[dry-run] site {$site->id} ({$site->domain}) → " . self::remotePrefix($site->id) . "/ (keep {$retention})");
            }
            $this->info("[dry-run] {$sites->count()} site(s).");

            return self::SUCCESS;
        }

        $failures = [];
        foreach ($sites as $site) {
            $error = $this->backupSite($site, $retention);
            if ($error !== null) {
                $failures[$site->id] = $error;
                $this->error("  site {$site->id}: {$error}");
            }
        }

        $this->info('Backed up ' . ($sites->count() - count($failures)) . '/' . $sites->count() . ' site(s).');

        if ($failures !== []) {
            try {
                app(SlackAlertService::class)->warning(
                    'Tenant Database Backup Failed',
                    count($failures) . ' site database backup(s) failed: ' . implode(' | ', array_map(fn ($id, $e) => "#{$id}: {$e}", array_keys($failures), $failures)),
                    ['sites' => array_keys($failures)]
                );
            } catch (\Throwable) {
            }

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /** @return string|null the failure reason, or null on success */
    private function backupSite(Site $site, int $retention): ?string
    {
        $run = InfraBackupRun::startRun('tenant_database', $site->id);
        $started = microtime(true);
        $timestamp = now()->format('Ymd_His');
        $filename = "site-{$site->id}_{$timestamp}.sql.gz";
        $remoteFile = rtrim((string) config('resource_governance.tenant_backup.remote_tmp_dir', '/tmp'), '/') . "/sp_pull_{$site->id}_{$timestamp}.sql.gz";
        $localDir = storage_path('app/tmp-site-backups');
        $localFile = $localDir . DIRECTORY_SEPARATOR . $filename;
        $ctx = null;

        try {
            $ctx = $this->contexts->open($site);
            [$size, $remoteHash] = $this->dump($ctx, $remoteFile);

            if (! is_dir($localDir)) {
                mkdir($localDir, 0750, true);
            }
            $ctx->connection->download($remoteFile, $localFile);
            if (! is_file($localFile) || ! hash_equals($remoteHash, (string) hash_file('sha256', $localFile))) {
                throw new \RuntimeException('SHA-256 of the downloaded dump does not match the server copy.');
            }

            $remotePath = self::remotePrefix($site->id) . '/' . $filename;
            $upload = $this->arvan->upload($localFile, $remotePath);
            if (! ($upload['success'] ?? false)) {
                throw new \RuntimeException('upload to the backup bucket failed: ' . ($upload['message'] ?? ''));
            }

            $pruned = $this->arvan->prune(self::remotePrefix($site->id), $retention);
            $run->endSuccess($remotePath, round($size / 1048576, 2), (int) (microtime(true) - $started));
            $this->line("  site {$site->id}: {$remotePath} ({$size} B, kept {$pruned['kept']}, pruned {$pruned['pruned']})");
            Log::info('db:backup-sites ok', ['site_id' => $site->id, 'path' => $remotePath, 'bytes' => $size, 'sha256' => $remoteHash]);

            return null;
        } catch (\Throwable $e) {
            $run->endFailed(mb_substr($e->getMessage(), 0, 1000));
            Log::warning('db:backup-sites failed', ['site_id' => $site->id, 'error' => $e->getMessage()]);

            return $e->getMessage();
        } finally {
            @unlink($localFile);
            if ($ctx !== null) {
                try {
                    $ctx->connection->execute('rm -f ' . escapeshellarg($remoteFile));
                    $ctx->connection->disconnect();
                } catch (\Throwable) {
                }
            }
        }
    }

    /**
     * mysqldump the site's own database (credentials from its .env) into
     * $remoteFile, then verify the gzip on the server.
     *
     * @return array{0: int, 1: string} size in bytes, SHA-256
     */
    private function dump(InstallationContext $ctx, string $remoteFile): array
    {
        $env = RemoteEnvFile::decode((string) $ctx->connection->execute(
            RemoteEnvFile::read(rtrim($ctx->webRoot(), '/') . '/.env', ['DB_HOST', 'DB_PORT', 'DB_DATABASE', 'DB_USERNAME', 'DB_PASSWORD'])
        )->stdout);
        $db = (string) ($env['DB_DATABASE'] ?? '');
        $user = (string) ($env['DB_USERNAME'] ?? '');
        if ($db === '' || $user === '') {
            throw new \RuntimeException('DB_DATABASE / DB_USERNAME are missing from the site .env.');
        }

        $q = fn (string $v) => escapeshellarg($v);
        $host = trim((string) ($env['DB_HOST'] ?? '')) ?: '127.0.0.1';
        $port = trim((string) ($env['DB_PORT'] ?? '')) ?: '3306';
        $cmd = 'set -o pipefail 2>/dev/null; umask 077; '
            . 'MYSQL_PWD=' . $q((string) ($env['DB_PASSWORD'] ?? '')) . ' mysqldump --single-transaction --quick --lock-tables=false --no-tablespaces'
            . ' -h ' . $q($host) . ' -P ' . $q($port)
            . ' -u ' . $q($user) . ' ' . $q($db) . ' 2>/dev/null | gzip > ' . $q($remoteFile)
            . ' && gzip -t ' . $q($remoteFile)
            . ' && echo "size=$(stat -c %s ' . $q($remoteFile) . ')"'
            . ' && echo "sha256=$(sha256sum ' . $q($remoteFile) . ' | cut -d" " -f1)"'
            . ' || echo dump-failed';

        $out = (string) $ctx->connection->execute($cmd)->stdout;
        if (str_contains($out, 'dump-failed') || ! preg_match('/size=(\d+)/', $out, $s) || ! preg_match('/sha256=([0-9a-f]{64})/', $out, $h)) {
            throw new \RuntimeException('mysqldump on the site server failed or produced an invalid gzip.');
        }
        if ((int) $s[1] < 100) {
            throw new \RuntimeException('the dump is empty.');
        }

        return [(int) $s[1], $h[1]];
    }
}
