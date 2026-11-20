<?php

namespace App\Services\CustomerInstallation\Stages;

use App\Services\CustomerInstallation\Dns\DomainDnsPreflight;
use App\Services\CustomerInstallation\Dns\ServerPublicIpResolver;
use App\Services\CustomerInstallation\Dto\InstallationContext;
use App\Services\CustomerInstallation\Dto\StageResult;

/**
 * Runs before PROVISION_DOMAIN, so before any upload, database or vhost.
 * Stops an installation whose domain cannot reach the target server.
 * Installation 50 (2026-11-17) ran 18 stages first and only failed at
 * ISSUE_SSL. The wizard performs the same DomainDnsPreflight on submit; this
 * stage repeats it because DNS can change between submit and run, and
 * because the legacy start paths and resumes enter here directly.
 */
class ValidateDomainDnsStage extends AbstractStage
{
    public function __construct(
        private readonly DomainDnsPreflight $preflight,
        private readonly ServerPublicIpResolver $serverIps,
    ) {}

    public function name(): string { return 'VALIDATE_DOMAIN_DNS'; }
    public function label(): string { return 'بررسی DNS دامنه'; }
    public function estimatedSeconds(): int { return 5; }

    public function execute(InstallationContext $ctx): StageResult
    {
        return $this->run($ctx, function (InstallationContext $ctx) {
            $serverIp = $this->serverIps->forContext($ctx);
            $result = $this->preflight->check($ctx->request->domain, $serverIp, $ctx->request->sslEnabled);

            if (!$result->ok) {
                // A custom domain registered before its DNS is ready: build
                // the site anyway; ISSUE_SSL waits and the site's domain
                // panel finishes the connection later.
                if ($ctx->request->dnsPending && !$result->managedByPlatform && DomainDnsPreflight::isAwaitingDnsCode($result->code)) {
                    return StageResult::success($this->name(), $result->toArray() + [
                        'awaiting_dns' => true,
                        'warning'      => $result->error,
                    ], [], $this->elapsed());
                }

                return StageResult::failure($this->name(), (string) $result->error, $this->elapsed());
            }

            return StageResult::success($this->name(), $result->toArray() + ['awaiting_dns' => false], [], $this->elapsed());
        });
    }
}
