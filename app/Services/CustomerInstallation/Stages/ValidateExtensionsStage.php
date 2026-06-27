<?php

namespace App\Services\CustomerInstallation\Stages;

use App\Services\CustomerInstallation\Dto\InstallationContext;
use App\Services\CustomerInstallation\Dto\StageResult;

class ValidateExtensionsStage extends AbstractStage
{
    public function name(): string { return 'VALIDATE_EXTENSIONS'; }
    public function label(): string { return 'اعتبارسنجی افزونه‌های PHP'; }
    public function estimatedSeconds(): int { return 3; }

    public function execute(InstallationContext $ctx): StageResult
    {
        return $this->run($ctx, function (InstallationContext $ctx) {
            $required = config('customer_installation.required_php_extensions', []);
            $result = $ctx->connection->execute('php -m 2>/dev/null');

            if ($result->failed()) {
                return StageResult::failure($this->name(), 'Cannot list PHP extensions', $this->elapsed());
            }

            $present = array_map('strtolower', array_filter(
                array_map('trim', explode("\n", $result->stdout)),
                fn($e) => !empty($e) && $e[0] !== '['
            ));

            $missing = array_values(array_filter($required, fn($ext) => !in_array(strtolower($ext), $present)));

            if (!empty($missing)) {
                return StageResult::failure(
                    $this->name(),
                    'Missing PHP extensions: ' . implode(', ', $missing),
                    $this->elapsed()
                );
            }

            return StageResult::success($this->name(), [
                'present' => $required,
                'missing' => [],
            ], [], $this->elapsed());
        });
    }
}
