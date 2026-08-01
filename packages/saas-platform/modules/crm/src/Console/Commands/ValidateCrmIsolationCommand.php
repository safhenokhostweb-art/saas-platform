<?php

namespace SaaSPlatform\Modules\Crm\Console\Commands;

use Illuminate\Console\Command;
use SaaSPlatform\Modules\Crm\Services\CrmIsolationValidationService;

class ValidateCrmIsolationCommand extends Command
{
    protected $signature = 'crm:validate-isolation {--fail-on-warning}';

    protected $description = 'Validate that the CRM module is fully isolated from App\\ namespace';

    public function handle(CrmIsolationValidationService $service): int
    {
        $result = $service->run();

        foreach ($result['checks'] as $check) {
            $icon = $check['passed'] ? '✅' : '❌';
            $this->line("{$icon} [{$check['id']}] {$check['label']}: {$check['detail']}");
        }

        $this->line("Score: {$result['score']}%");

        return $result['passed'] ? self::SUCCESS : self::FAILURE;
    }
}
