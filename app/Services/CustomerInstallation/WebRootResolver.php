<?php

namespace App\Services\CustomerInstallation;

use App\Models\CustomerInstallationLog;
use App\Models\CustomerRemoteServer;
use App\Services\CustomerInstallation\Contracts\DeploymentDriverInterface;
use App\Services\CustomerInstallation\Domain\DomainProvisionerFactory;
use App\Services\CustomerInstallation\Dto\InstallationContext;
use App\Services\CustomerInstallation\Enums\DriverType;
use Illuminate\Support\Facades\Log;

/**
 * Phase 14-F1 — single place that knows how a web root path is derived per
 * hosting type. InstallationContext::webRoot() delegates here so that no
 * Stage ever needs to branch on driver type itself (they only ever call
 * $ctx->webRoot()) — the same "never hardcode hosting-type checks outside
 * the capability/path layer" principle DriverCapabilities already documents
 * for capabilities.
 *
 * resolve()'s resolution order (UNCHANGED since Phase 14-F1 — still used
 * as-is by CustomerDeletion's DeleteContext, out of scope for Phase 15-D):
 *   1. CustomerRemoteServer::$path, if the admin explicitly set one.
 *   2. Otherwise, a hosting-type default:
 *      - VPS/SSH/Docker/SFTP/unknown: /var/www/{slug}.
 *      - cPanel/DirectAdmin: /home/{cpanel_username}/public_html — a
 *        simplification, since an addon domain or subdomain may live at a
 *        different path. Phase 15-D's resolveForInstallation() (below)
 *        closes this gap for the installation pipeline specifically.
 */
class WebRootResolver
{
    /**
     * Phase 15-D — optional and lazily defaulted (app(DomainProvisionerFactory::class))
     * rather than a required constructor argument, specifically so every
     * existing `new WebRootResolver()` call site (DeleteContext, the
     * pre-15-D WebRootResolverTest, any future caller of the unchanged
     * resolve() method) keeps working with zero changes.
     */
    public function __construct(private ?DomainProvisionerFactory $domainProvisioners = null) {}

    public function resolve(DeploymentDriverInterface $connection, string $slug, ?CustomerRemoteServer $server): string
    {
        if ($server && !empty($server->path)) {
            return rtrim($server->path, '/');
        }

        return match ($connection->driverType()) {
            DriverType::CPANEL, DriverType::DIRECTADMIN => $this->sharedHostingDefault($server, $slug),
            default => "/var/www/{$slug}",
        };
    }

    /**
     * Phase 15-D — installation-pipeline-aware resolution. Priority:
     *   1. The real document root recorded by ProvisionDomainStage — from
     *      this run's in-memory context output, or (if this run resumed
     *      past PROVISION_DOMAIN, which CustomerInstallationOrchestrator
     *      skips re-executing) the persisted, immutable log.
     *   2. A live cPanel query (DomainProvisionerInterface::resolveDocumentRoot())
     *      — covers an installation created before Phase 15-D existed, where
     *      no PROVISION_DOMAIN stage ever ran at all.
     *   3. resolve()'s existing logic (explicit CustomerRemoteServer::$path,
     *      then the hosting-type default guess) — unchanged fallback.
     *
     * No wrong guess should ever cause a broken install: this method never
     * throws, and always terminates at tier 3's deterministic default.
     */
    public function resolveForInstallation(InstallationContext $ctx, string $slug, ?CustomerRemoteServer $server): string
    {
        $documentRoot = $this->resolveFromDomainProvisioning($ctx);

        if ($documentRoot === null && $ctx->connection->driverType() === DriverType::CPANEL) {
            try {
                $documentRoot = $this->domainProvisioners()
                    ->forDriverType(DriverType::CPANEL)
                    ->resolveDocumentRoot($ctx, $ctx->request->domain);
            } catch (\Throwable) {
                $documentRoot = null;
            }
        }

        if ($documentRoot === null) {
            return $this->resolve($ctx->connection, $slug, $server);
        }

        $resolved = rtrim($documentRoot, '/');
        $this->logPathSanityWarningIfNeeded($ctx, $resolved);

        return $resolved;
    }

    /**
     * Phase 15-E — Part 4: non-blocking diagnostic only, for a document root
     * that came from cPanel (tier 1/2 above) — NEVER for resolve()'s own
     * tier-3 deterministic guess, which is an intentional fallback with
     * nothing real to validate against yet. Logs a warning and returns
     * otherwise unchanged; never throws, never alters the resolved path.
     * ExtractPackageStage already creates the directory defensively
     * (`mkdir -p`) if it is genuinely missing, so a brand-new subdomain
     * whose directory does not exist yet is expected, not an error — this
     * is a signal for an operator to notice a persistently wrong path, not
     * a gate that can ever block or fail an installation.
     */
    private function logPathSanityWarningIfNeeded(InstallationContext $ctx, string $path): void
    {
        try {
            $result = $ctx->connection->execute(
                'test -d ' . escapeshellarg($path) . ' && test -w ' . escapeshellarg($path)
                . ' && echo sane || echo insane'
            );

            if (!str_contains($result->stdout, 'sane') || str_contains($result->stdout, 'insane')) {
                Log::warning(
                    'WebRootResolver: cPanel-resolved document root failed a sanity check '
                    . '(missing or not writable). Expected for a brand-new domain — '
                    . 'ExtractPackageStage creates the directory — but should be investigated '
                    . 'if it persists across a retry.',
                    ['installation_id' => $ctx->installation->id, 'document_root' => $path]
                );
            }
        } catch (\Throwable) {
            // Diagnostic only — a failed probe must never break resolution.
        }
    }

    private function resolveFromDomainProvisioning(InstallationContext $ctx): ?string
    {
        $output = $ctx->getOutput('PROVISION_DOMAIN');
        if (!empty($output['document_root'])) {
            return $output['document_root'];
        }

        $log = CustomerInstallationLog::where('installation_id', $ctx->installation->id)
            ->where('stage', 'PROVISION_DOMAIN')
            ->where('event', 'completed')
            ->first();

        return $log->output['document_root'] ?? null;
    }

    private function domainProvisioners(): DomainProvisionerFactory
    {
        return $this->domainProvisioners ??= app(DomainProvisionerFactory::class);
    }

    private function sharedHostingDefault(?CustomerRemoteServer $server, string $slug): string
    {
        $username = $server->username ?? null;

        // No username on record is not expected in practice (the admin form
        // requires it for cpanel/directadmin), but fail into a still-
        // deterministic, still-namespaced path rather than a generic one
        // that could collide across customers.
        return '/home/' . ($username !== null && $username !== '' ? $username : $slug) . '/public_html';
    }
}
