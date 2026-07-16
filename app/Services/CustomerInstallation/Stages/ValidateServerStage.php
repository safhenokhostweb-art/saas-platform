<?php

namespace App\Services\CustomerInstallation\Stages;

use App\Services\CustomerInstallation\Dto\InstallationContext;
use App\Services\CustomerInstallation\Dto\StageResult;
use App\Services\CustomerInstallation\Ssl\ArvanCdnDomainDecider;

class ValidateServerStage extends AbstractStage
{
    public function __construct(private readonly ArvanCdnDomainDecider $arvanDecider) {}

    public function name(): string { return 'VALIDATE_SERVER'; }
    public function label(): string { return 'اعتبارسنجی سرور'; }
    public function estimatedSeconds(): int { return 5; }

    public function execute(InstallationContext $ctx): StageResult
    {
        return $this->run($ctx, function (InstallationContext $ctx) {
            $result = $ctx->connection->execute('uname -m && lsb_release -ds 2>/dev/null || cat /etc/os-release | head -3');

            if ($result->failed()) {
                return StageResult::failure($this->name(), 'Cannot execute commands on remote server', $this->elapsed());
            }

            $output = $result->stdout;
            if (!str_contains(strtolower($output), 'linux') && !str_contains($output, 'x86_64') && !str_contains($output, 'Ubuntu') && !str_contains($output, 'Debian')) {
                // Accept anyway — some systems output differently
            }

            $certbotCheck = $this->checkCertbotPreflight($ctx);
            if ($certbotCheck !== null) {
                return $certbotCheck;
            }

            return StageResult::success($this->name(), ['server_info' => trim($output)], [], $this->elapsed());
        });
    }

    /**
     * Phase 15-D — was previously discoverable only at the very last stage
     * (IssueSslStage), after upload/extract/database/env/webserver had all
     * already run — wasting the entire pipeline's work before failing. Fails
     * here instead, before a single byte is uploaded.
     *
     * VPS/Docker only, and only when SSL is actually requested for a domain
     * this pipeline is actually responsible for issuing a certificate for
     * (not Arvan-managed — see ArvanCdnDomainDecider, and not cPanel, which
     * uses AutoSSL, not certbot). Returns null when the check does not apply
     * (nothing to report); a real StageResult only on an actual failure.
     */
    private function checkCertbotPreflight(InstallationContext $ctx): ?StageResult
    {
        $req = $ctx->request;

        if (!$req->sslEnabled) {
            return null;
        }

        if (!$ctx->connection->driverType()->isVpsFamily()) {
            return null; // cPanel (AutoSSL) and any other non-VPS driver: not applicable here.
        }

        if ($this->arvanDecider->isManagedByArvan($req->domain)) {
            return null; // Arvan terminates SSL at its edge — certbot is never invoked for this domain.
        }

        $bin    = config('customer_installation.ssl.certbot_bin', 'certbot');
        $result = $ctx->connection->execute('command -v ' . escapeshellarg($bin) . ' >/dev/null 2>&1 && echo present || echo missing');

        if (!str_contains($result->stdout, 'present')) {
            return StageResult::failure(
                $this->name(),
                "Certbot is required for VPS SSL provisioning. SSL was requested for {$req->domain} "
                . "but certbot is not installed on this server (checked for '{$bin}' on PATH). "
                . 'Install certbot before starting an SSL-enabled installation for a custom domain, '
                . 'or disable SSL for this install.',
                $this->elapsed()
            );
        }

        return null;
    }
}
