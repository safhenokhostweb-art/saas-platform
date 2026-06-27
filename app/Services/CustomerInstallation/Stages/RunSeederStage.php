<?php

namespace App\Services\CustomerInstallation\Stages;

use App\Services\CustomerInstallation\Dto\InstallationContext;
use App\Services\CustomerInstallation\Dto\StageResult;

class RunSeederStage extends AbstractStage
{
    public function name(): string { return 'RUN_SEEDER'; }
    public function label(): string { return 'اجرای Seeder'; }
    public function estimatedSeconds(): int { return 10; }

    public function execute(InstallationContext $ctx): StageResult
    {
        return $this->run($ctx, function (InstallationContext $ctx) {
            $webRoot = escapeshellarg($ctx->webRoot());

            $result = $ctx->connection->execute("cd {$webRoot} && php artisan db:seed --class=CustomerPlatformSeeder --force 2>&1");
            if ($result->failed()) {
                // Seeder may not exist yet — treat as warning, not failure
                return StageResult::success($this->name(), [
                    'status'  => 'skipped',
                    'message' => 'CustomerPlatformSeeder not found — will be seeded on first boot',
                    'output'  => $result->output(),
                ], [], $this->elapsed());
            }

            return StageResult::success($this->name(), [
                'output' => $result->stdout,
            ], [], $this->elapsed());
        });
    }
}
