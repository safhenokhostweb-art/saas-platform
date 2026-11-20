<?php

namespace App\Services\CustomerInstallation\Stages;

use App\Services\CustomerInstallation\Dns\DomainDnsPreflight;
use App\Services\CustomerInstallation\Dns\ServerPublicIpResolver;
use App\Services\CustomerInstallation\Dto\InstallationContext;
use App\Services\CustomerInstallation\Dto\StageResult;
use App\Services\CustomerInstallation\Ssl\ArvanCdnDomainDecider;
use App\Services\CustomerInstallation\Ssl\NginxVhostWriter;
use App\Services\CustomerInstallation\Ssl\SslProvisionerFactory;

/**
 * Phase 15-B1/15-B2 — runs after CONFIGURE_WEB_SERVER (nginx must already be
 * serving the domain's webroot on port 80 for Certbot's webroot challenge)
 * and before PROVISION_LICENSE.
 *
 * Phase 15-B2 — no longer depends on ConfigureWebServerStage. On a
 * successful issuance it calls NginxVhostWriter::write() directly — the same
 * shared collaborator ConfigureWebServerStage itself calls — instead of
 * re-invoking another Stage's execute(). See NginxVhostWriter's docblock.
 *
 * Optional stage by design (isCapabilityRequiredToProceed() = false): this
 * governs the pre-execute DRIVER-CAPABILITY gate only (does the connected
 * driver structurally support SSL at all — false for pure SFTP, the
 * DirectAdmin stub, Docker). It is a separate question from whether a
 * REQUESTED issuance that the driver does support then fails at runtime —
 * see the per-driver severity decision below.
 *
 * Four possible outcomes, in order:
 *   1. SSL not requested for this installation ($req->sslEnabled === false) -> skip.
 *   2. Domain is ArvanCloud-managed (ArvanCdnDomainDecider) -> skip-success;
 *      SSL is issued and terminated at the Arvan edge, not by this pipeline.
 *   3. Issuance fails or is skipped by the provisioner:
 *      - VPS/Docker: this pipeline is the ONLY path to a valid certificate
 *        for the domain (no CDN, no hosting-panel AutoSSL) — a requested-
 *        but-unissued certificate is a customer-facing HTTPS failure, not an
 *        acceptable degraded state. Reported as a real StageResult::failure()
 *        — this DOES abort the installation and dispatch rollback, the same
 *        as CreateDatabaseStage's required-capability failure path.
 *      - cPanel: AutoSSL enablement/availability is a hosting-provider/WHM
 *        decision entirely outside this pipeline's control (see
 *        CpanelSslProvisioner's docblock) — degrades to a warning, the
 *        installation still completes.
 *   4. Issuance succeeds -> NginxVhostWriter::write() is called again so the
 *      real certificate path is written into the HTTPS server block.
 *
 * Phase 3 (custom domains): the www name is added to the certificate when it
 * resolves to this server (InstallationContext::$certificateAliases). For an
 * installation registered in the awaiting-DNS state ($req->dnsPending) the
 * DNS is checked again here: still not pointing -> skip-success with status
 * awaiting_dns, and a failed issuance is a warning rather than a failure.
 * The site's domain panel (SiteDomainProvisioner) issues it later.
 */
class IssueSslStage extends AbstractStage
{
    public const STATUS_AWAITING_DNS = 'awaiting_dns';

    private readonly DomainDnsPreflight $preflight;

    private readonly ServerPublicIpResolver $serverIps;

    public function __construct(
        private readonly SslProvisionerFactory $provisioners,
        private readonly ArvanCdnDomainDecider  $arvanDecider,
        private readonly NginxVhostWriter       $vhostWriter,
        ?DomainDnsPreflight $preflight = null,
        ?ServerPublicIpResolver $serverIps = null,
    ) {
        $this->preflight = $preflight ?? app(DomainDnsPreflight::class);
        $this->serverIps = $serverIps ?? app(ServerPublicIpResolver::class);
    }

    public function name(): string { return 'ISSUE_SSL'; }
    public function label(): string { return 'صدور گواهی SSL'; }
    public function estimatedSeconds(): int { return 45; }
    public function canRollback(): bool { return false; }

    /**
     * Phase 15-B1 — reuses the existing supportsSSL flag rather than
     * introducing a new supportsSslProvisioning capability: supportsSSL was
     * declared on every driver already (Phase 14) but never actually
     * consulted by any Stage — this is that flag's first real consumer, not
     * a second, overlapping one. SFTP/DirectAdmin(stub)/Docker already
     * declare supportsSSL=false for reasons unrelated to this stage (no
     * transport, no root; reverse-proxy-owned SSL) so the existing metadata
     * is already correct for this purpose without any DriverCapabilities change.
     */
    public function requiredCapabilities(): array
    {
        return ['supportsSSL'];
    }

    public function isCapabilityRequiredToProceed(): bool
    {
        return false;
    }

    public function execute(InstallationContext $ctx): StageResult
    {
        return $this->run($ctx, function (InstallationContext $ctx) {
            $req    = $ctx->request;
            $domain = $req->domain;

            if (!$req->sslEnabled) {
                return StageResult::skipped($this->name(), 'SSL not requested for this installation');
            }

            if ($this->arvanDecider->isManagedByArvan($domain)) {
                return StageResult::success($this->name(), [
                    'ssl_managed_by' => 'arvan_cdn',
                    'status'         => 'skipped_success',
                    'message'        => 'Domain is proxied through ArvanCloud CDN — SSL is issued and '
                        . 'terminated at the edge, not by this pipeline.',
                ], [], $this->elapsed());
            }

            $serverIp = $this->serverIps->forContext($ctx);

            if ($req->dnsPending) {
                $dns = $this->preflight->check($domain, $serverIp, true);
                if (!$dns->ok) {
                    return StageResult::success($this->name(), [
                        'ssl_managed_by' => $ctx->connection->driverType()->value,
                        'status'         => self::STATUS_AWAITING_DNS,
                        'dns_code'       => $dns->code,
                        'message'        => (string) $dns->error,
                    ], [], $this->elapsed());
                }
            }

            // www joins the certificate only when it already points here: an
            // unreachable name would make Let's Encrypt refuse the whole cert.
            $www = $this->preflight->wwwAlias($domain);
            $ctx->certificateAliases = [];
            if ($www !== null && $this->preflight->check($www, $serverIp, true)->code === DomainDnsPreflight::PASS_POINTS_HERE) {
                $ctx->certificateAliases = [$www];
            }

            $driverType  = $ctx->connection->driverType();
            $provisioner = $this->provisioners->forDriverType($driverType);
            $result      = $provisioner->issueCertificate($ctx, $domain);

            if (!$result->success && $req->dnsPending) {
                // DNS can still be half-propagated on a site built before it
                // was ready; the domain panel retries without blocking it.
                return StageResult::success($this->name(), [
                    'ssl_managed_by' => $driverType->value,
                    'status'         => 'ssl_failed',
                    'message'        => $result->message,
                ], [], $this->elapsed());
            }

            if (!$result->success) {
                if ($driverType->isVpsFamily()) {
                    // VPS/Docker: no fallback SSL owner exists for this
                    // domain (no CDN, no hosting-panel AutoSSL) — a
                    // requested-and-unissued certificate is a real
                    // installation failure, matching CreateDatabaseStage's
                    // "essential, must fail" posture rather than the
                    // optional-infra "skip" posture used elsewhere in this
                    // pipeline.
                    return StageResult::failure(
                        $this->name(),
                        "SSL was requested for {$domain} but could not be issued: {$result->message}",
                        $this->elapsed()
                    );
                }

                // cPanel (and any other driver reaching this point): AutoSSL
                // enablement is a hosting-provider/WHM decision this pipeline
                // cannot make — degrade to a warning, installation completes.
                return StageResult::success($this->name(), [
                    'ssl_managed_by' => $driverType->value,
                    'status'         => $result->status,
                    'message'        => $result->message,
                ], [], $this->elapsed());
            }

            // Real per-domain certificate now exists — refresh the nginx
            // vhost so it picks up the real path instead of leaving the
            // HTTP-only vhost in place. Best-effort: a failure here still
            // leaves the certificate issued and the site reachable over HTTP.
            //
            // Phase 15-G — Part 5: guarded by supportsWebServerConfiguration.
            // cPanel/WHM already declares this capability false (it manages
            // its own vhost/SSL binding — see CONFIGURE_WEB_SERVER's
            // capability-gate skip) yet this call was unconditional, so every
            // cPanel SSL issuance still attempted an SSH write to
            // /etc/nginx/... that could only ever fail. VPS/Docker: unchanged.
            $vhostReconfigured = false;

            if ($ctx->connection->capabilities()->supportsWebServerConfiguration) {
                $vhostReconfigured = $this->vhostWriter->write($ctx)->success;
            }

            return StageResult::success($this->name(), [
                'ssl_managed_by'          => $driverType->value,
                'status'                  => $result->status,
                'cert_path'               => $result->certPath,
                'certificate_names'       => array_values(array_unique([$domain, ...$ctx->certificateAliases])),
                'web_server_reconfigured' => $vhostReconfigured,
            ], [], $this->elapsed());
        });
    }
}
