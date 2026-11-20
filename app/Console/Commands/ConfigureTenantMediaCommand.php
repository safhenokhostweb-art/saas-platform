<?php

namespace App\Console\Commands;

use App\Models\CustomerInstallation;
use App\Models\CustomerRemoteServer;
use App\Models\Site;
use App\Services\Sites\SiteRemoteContextFactory;
use App\Services\TenantMedia\TenantMediaEnvironment;
use Illuminate\Console\Command;

/**
 * Phase 4 — the existing-sites path for CONFIGURE_MEDIA_STORAGE: upserts
 * the tenant media variables (RemoteEnvFile::set) into already-installed
 * sites on platform-managed servers. Dry run unless --execute. Secrets are
 * never printed. Moving the existing files is the site's own
 * `php artisan media:migrate-to-remote --site=<id> --execute`.
 */
class ConfigureTenantMediaCommand extends Command
{
    protected $signature = 'media:configure-tenant-env
                            {--site=* : Site id(s); default all installed sites}
                            {--execute : Write the .env lines (otherwise only report)}';

    protected $description = 'Point installed sites on platform-managed servers at the tenant media bucket (.env upsert)';

    public function handle(TenantMediaEnvironment $media, SiteRemoteContextFactory $contexts): int
    {
        $execute = (bool) $this->option('execute');
        $ids = array_map('intval', (array) $this->option('site'));
        $siteIds = CustomerInstallation::query()->where('status', 'complete')->whereNotNull('site_id')
            ->when($ids !== [], fn ($q) => $q->whereIn('site_id', $ids))
            ->distinct()->orderBy('site_id')->pluck('site_id');

        $this->info(($execute ? 'EXECUTE' : 'DRY RUN') . ' — tenant media .env for ' . $siteIds->count() . ' site(s)');
        $failed = 0;
        $rows = [];

        foreach (Site::query()->whereIn('id', $siteIds)->orderBy('id')->get() as $site) {
            $installation = CustomerInstallation::query()->where('site_id', $site->id)->where('status', 'complete')->latest('id')->first();
            $server = CustomerRemoteServer::find($installation?->remote_server_id);
            $reason = $media->ineligibility($server, $site->id);

            if ($reason !== null || ! $execute) {
                $rows[] = [$site->id, $site->domain, $server?->label ?? '-', $reason ?? 'would write: ' . implode(', ', array_keys($media->variables($site->id)))];
                continue;
            }

            try {
                $ctx = $contexts->open($site);
                try {
                    $result = $media->apply($ctx->connection, $ctx->webRoot(), $server, $site->id);
                } finally {
                    $ctx->connection->disconnect();
                }
                $rows[] = [$site->id, $site->domain, $server->label, $result['applied'] ? 'written (sites/' . $site->id . '/media)' : 'FAILED ' . $result['reason']];
                $failed += $result['applied'] ? 0 : 1;
            } catch (\Throwable $e) {
                $rows[] = [$site->id, $site->domain, $server->label, 'FAILED ' . $e->getMessage()];
                $failed++;
            }
        }

        $this->table(['site', 'domain', 'server', 'result'], $rows);
        if ($execute) {
            $this->line('Then, on each written site: php artisan media:migrate-to-remote --site=<id> (dry run), then add --execute.');
        } else {
            $this->info('Nothing was written; re-run with --execute.');
        }

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
