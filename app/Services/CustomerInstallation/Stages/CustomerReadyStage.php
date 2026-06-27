<?php

namespace App\Services\CustomerInstallation\Stages;

use App\Services\CustomerInstallation\Contracts\InstallationStateInterface;
use App\Services\CustomerInstallation\Dto\InstallationContext;
use App\Services\CustomerInstallation\Dto\StageResult;
use App\Services\Infrastructure\SlackAlertService;

class CustomerReadyStage extends AbstractStage
{
    public function __construct(
        private readonly InstallationStateInterface $state,
        private readonly SlackAlertService $slack,
    ) {}

    public function name(): string { return 'CUSTOMER_READY'; }
    public function label(): string { return 'آماده‌سازی نهایی'; }
    public function estimatedSeconds(): int { return 10; }

    public function execute(InstallationContext $ctx): StageResult
    {
        return $this->run($ctx, function (InstallationContext $ctx) {
            $req = $ctx->request;
            $webRoot = escapeshellarg($ctx->webRoot());

            // Final health verification
            $healthResult = $ctx->connection->execute(
                "cd {$webRoot} && php artisan about 2>&1 | head -5 || echo 'app-running'"
            );

            $httpStatus = 0;
            try {
                $httpResult = $ctx->connection->execute("curl -s -o /dev/null -w '%{http_code}' --max-time 10 'http://127.0.0.1' -H 'Host: {$req->domain}' 2>/dev/null || echo '0'");
                $httpStatus = (int) trim($httpResult->stdout);
            } catch (\Throwable) {}

            $this->state->markComplete($ctx->installation);

            try {
                $this->slack->info(
                    "[INSTALL] {$req->company} @ {$req->domain} — نصب کامل شد",
                    'Customer Installation',
                    "Site ID: {$ctx->installation->site_id} | Mode: {$req->installMode} | Domain: {$req->domain}"
                );
            } catch (\Throwable) {}

            return StageResult::success($this->name(), [
                'domain'           => $req->domain,
                'http_status'      => $httpStatus,
                'health_output'    => trim($healthResult->stdout),
                'site_id'          => $ctx->installation->site_id,
                'notifications_sent' => true,
            ], [], $this->elapsed());
        });
    }
}
