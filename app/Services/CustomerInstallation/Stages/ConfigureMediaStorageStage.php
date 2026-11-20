<?php

namespace App\Services\CustomerInstallation\Stages;

use App\Models\CustomerRemoteServer;
use App\Services\CustomerInstallation\Dto\InstallationContext;
use App\Services\CustomerInstallation\Dto\StageResult;
use App\Services\TenantMedia\TenantMediaEnvironment;

/**
 * Phase 4 — points a new site's uploads at the tenant media bucket
 * (TenantMediaEnvironment). Runs right after PROVISION_LICENSE because the
 * per-site prefix sites/{site_id}/media needs the Site row, which does not
 * exist yet when GENERATE_ENVIRONMENT writes the first .env.
 *
 * Never fails the installation: a site that is not eligible (customer-owned
 * server, feature off, incomplete or unsafe key) or whose .env write fails
 * simply keeps its files on the local disk.
 */
class ConfigureMediaStorageStage extends AbstractStage
{
    public function __construct(private readonly TenantMediaEnvironment $media) {}

    public function name(): string { return 'CONFIGURE_MEDIA_STORAGE'; }
    public function label(): string { return 'اتصال فایل‌های سایت به فضای ابری'; }
    public function estimatedSeconds(): int { return 5; }

    public function execute(InstallationContext $ctx): StageResult
    {
        return $this->run($ctx, function (InstallationContext $ctx) {
            $server = CustomerRemoteServer::find($ctx->installation->remote_server_id);
            $siteId = $ctx->installation->fresh()?->site_id;

            $result = $this->media->apply($ctx->connection, $ctx->webRoot(), $server, $siteId ? (int) $siteId : null);

            return StageResult::success($this->name(), [
                'status'  => $result['applied'] ? 'object_storage' : 'local_disk',
                'reason'  => $result['reason'],
                'written' => $result['written'],
                'prefix'  => $result['applied'] ? "sites/{$siteId}/media" : null,
            ], [], $this->elapsed());
        });
    }
}
