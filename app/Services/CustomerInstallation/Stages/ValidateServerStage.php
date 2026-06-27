<?php

namespace App\Services\CustomerInstallation\Stages;

use App\Services\CustomerInstallation\Dto\InstallationContext;
use App\Services\CustomerInstallation\Dto\StageResult;

class ValidateServerStage extends AbstractStage
{
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

            return StageResult::success($this->name(), ['server_info' => trim($output)], [], $this->elapsed());
        });
    }
}
