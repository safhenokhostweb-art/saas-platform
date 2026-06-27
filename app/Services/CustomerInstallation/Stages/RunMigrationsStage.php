<?php

namespace App\Services\CustomerInstallation\Stages;

use App\Services\CustomerInstallation\Dto\InstallationContext;
use App\Services\CustomerInstallation\Dto\StageResult;

class RunMigrationsStage extends AbstractStage
{
    public function name(): string { return 'RUN_MIGRATIONS'; }
    public function label(): string { return 'اجرای مایگریشن‌ها'; }
    public function estimatedSeconds(): int { return 20; }

    public function execute(InstallationContext $ctx): StageResult
    {
        return $this->run($ctx, function (InstallationContext $ctx) {
            $webRoot = escapeshellarg($ctx->webRoot());

            $result = $ctx->connection->execute("cd {$webRoot} && php artisan migrate --force 2>&1");
            if ($result->failed()) {
                return StageResult::failure($this->name(), 'Migrations failed: ' . $result->output(), $this->elapsed());
            }

            $statusResult = $ctx->connection->execute("cd {$webRoot} && php artisan migrate:status 2>&1 | tail -5");

            return StageResult::success($this->name(), [
                'output'       => $result->stdout,
                'final_status' => $statusResult->stdout,
            ], [], $this->elapsed());
        });
    }
}
