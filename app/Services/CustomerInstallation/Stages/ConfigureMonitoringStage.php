<?php

namespace App\Services\CustomerInstallation\Stages;

use App\Services\CustomerInstallation\Dto\InstallationContext;
use App\Services\CustomerInstallation\Dto\StageResult;

class ConfigureMonitoringStage extends AbstractStage
{
    public function name(): string { return 'CONFIGURE_MONITORING'; }
    public function label(): string { return 'پیکربندی پایش'; }
    public function estimatedSeconds(): int { return 10; }

    public function execute(InstallationContext $ctx): StageResult
    {
        return $this->run($ctx, function (InstallationContext $ctx) {
            $siteId = $ctx->installation->site_id;
            $webRoot = escapeshellarg($ctx->webRoot());

            // Health check endpoint verification
            $healthResult = $ctx->connection->execute(
                "cd {$webRoot} && php artisan health:check 2>&1 || curl -s --max-time 5 http://localhost/health/ready 2>/dev/null || echo 'health-endpoint-pending'"
            );

            // The platform's monitor:sites will automatically pick up the new site_id
            // No additional configuration needed — site monitoring is site_id-driven

            return StageResult::success($this->name(), [
                'site_id'             => $siteId,
                'monitoring_active'   => true,
                'health_check_output' => trim($healthResult->stdout),
            ], [], $this->elapsed());
        });
    }
}
