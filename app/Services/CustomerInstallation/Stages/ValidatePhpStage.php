<?php

namespace App\Services\CustomerInstallation\Stages;

use App\Services\CustomerInstallation\Dto\InstallationContext;
use App\Services\CustomerInstallation\Dto\StageResult;

class ValidatePhpStage extends AbstractStage
{
    public function name(): string { return 'VALIDATE_PHP'; }
    public function label(): string { return 'اعتبارسنجی PHP'; }
    public function estimatedSeconds(): int { return 3; }

    public function execute(InstallationContext $ctx): StageResult
    {
        return $this->run($ctx, function (InstallationContext $ctx) {
            $result = $ctx->connection->execute('php -v 2>&1 | head -1');

            if ($result->failed() || !str_contains($result->stdout, 'PHP')) {
                return StageResult::failure($this->name(), 'PHP CLI not found on remote server', $this->elapsed());
            }

            if (!preg_match('/PHP ([\d.]+)/', $result->stdout, $m)) {
                return StageResult::failure($this->name(), 'Cannot parse PHP version', $this->elapsed());
            }

            $version = $m[1];
            if (version_compare($version, '8.1.0', '<')) {
                return StageResult::failure($this->name(), "PHP {$version} is below minimum 8.1", $this->elapsed());
            }

            $fpmResult = $ctx->connection->execute('systemctl is-active php*-fpm 2>/dev/null || echo "unknown"');

            return StageResult::success($this->name(), [
                'php_version' => $version,
                'fpm_status'  => trim($fpmResult->stdout),
            ], [], $this->elapsed());
        });
    }
}
