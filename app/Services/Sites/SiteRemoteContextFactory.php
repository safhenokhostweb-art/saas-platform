<?php

namespace App\Services\Sites;

use App\Models\CustomerRemoteServer;
use App\Models\Site;
use App\Services\CustomerInstallation\Dto\CustomerInstallRequest;
use App\Services\CustomerInstallation\Dto\InstallationContext;
use App\Services\CustomerInstallation\RemoteConnectionService;
use App\Services\Domains\SiteDomainService;
use App\Services\Onboarding\OnboardingDomainPolicy;
use RuntimeException;

/**
 * Opens an SSH-backed InstallationContext for an installed site: its
 * completed installation, server and web root, with the site's current
 * domain. Shared by the pull backups and the tenant media .env tooling.
 * The caller disconnects ($ctx->connection->disconnect()).
 */
class SiteRemoteContextFactory
{
    public function __construct(
        private readonly SiteDomainService $domains,
        private readonly RemoteConnectionService $connections,
    ) {}

    /** @throws RuntimeException with a Persian reason when the site cannot be reached */
    public function open(Site $site): InstallationContext
    {
        $installation = $this->domains->installationFor($site);
        if ($installation === null) {
            throw new RuntimeException(SiteDomainService::NO_INSTALLATION);
        }

        $server = CustomerRemoteServer::find($installation->remote_server_id);
        if ($server === null) {
            throw new RuntimeException("سرور نصب سایت #{$site->id} پیدا نشد.");
        }

        $connection = $this->connections->buildDriver($server);
        try {
            $connection->connect();
        } catch (\Throwable $e) {
            throw new RuntimeException('اتصال به سرور سایت ممکن نشد: ' . $e->getMessage(), 0, $e);
        }

        $request = CustomerInstallRequest::fromArray(array_merge($installation->request_payload ?? [], [
            'domain' => OnboardingDomainPolicy::normalizeHost((string) $site->domain) ?: (string) ($installation->request_payload['domain'] ?? ''),
        ]));

        return new InstallationContext($installation, $request, $connection);
    }
}
