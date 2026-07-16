<?php

namespace App\Services\CustomerInstallation\Stages;

use App\Services\CustomerInstallation\Dto\InstallationContext;
use App\Services\CustomerInstallation\Dto\StageResult;
use App\Services\CustomerInstallation\Ssl\NginxVhostWriter;

/**
 * Phase 15-B2 — execute()'s body now lives in NginxVhostWriter, the shared
 * collaborator this Stage and IssueSslStage both depend on (see
 * NginxVhostWriter's docblock for why: IssueSslStage previously re-invoked
 * this entire Stage after issuing a certificate, coupling two Stage classes
 * together). No behavior change — this class's public contract (name,
 * capability gate, rollback) is untouched.
 */
class ConfigureWebServerStage extends AbstractStage
{
    public function __construct(private readonly NginxVhostWriter $writer) {}

    public function name(): string { return 'CONFIGURE_WEB_SERVER'; }
    public function label(): string { return 'پیکربندی وب‌سرور'; }
    public function estimatedSeconds(): int { return 5; }
    public function canRollback(): bool { return true; }

    /**
     * Phase 14-E — this stage's actual mechanism writes /etc/nginx vhost
     * files and reloads the service, which no shared-hosting account can do.
     * Unlike CreateDatabaseStage, missing this capability does not fail the
     * installation: on cPanel/DirectAdmin the control panel already manages
     * vhosts automatically outside this pipeline (the domain/subdomain is
     * normally provisioned in WHM before this installer runs), so skipping
     * with a clear log is the correct behavior — there is no cPanel handler
     * implemented this phase (would require Driver changes, out of scope).
     */
    public function requiredCapabilities(): array
    {
        return ['supportsWebServerConfiguration'];
    }

    public function isCapabilityRequiredToProceed(): bool
    {
        return false;
    }

    public function execute(InstallationContext $ctx): StageResult
    {
        return $this->writer->write($ctx);
    }

    public function rollback(InstallationContext $ctx, array $snapshot): void
    {
        foreach (['config_path', 'enable_path'] as $key) {
            if (!empty($snapshot[$key])) {
                $ctx->connection->execute('rm -f ' . escapeshellarg($snapshot[$key]));
            }
        }
        try {
            $ctx->connection->execute('nginx -s reload 2>&1 || systemctl reload nginx 2>&1');
        } catch (\Throwable) {}
    }
}
