<?php

namespace SaaSPlatform\CoreUtils\Console\Commands\Plugins;

use Illuminate\Console\Command;
use SaaSPlatform\CoreUtils\PluginGovernance\Trust\ExtensionAuthenticityService;
use SaaSPlatform\CoreUtils\PluginGovernance\Trust\TrustedPublisherRegistry;
use SaaSPlatform\CoreUtils\PluginGovernance\Trust\PluginIntegrityValidator;

class VerifySignaturesCommand extends Command
{
    protected $signature = 'plugins:verify-signatures
                            {path : Path to module directory}
                            {--module=unknown : Module ID}
                            {--publisher= : Publisher ID to verify against}
                            {--hash= : Expected integrity hash}';

    protected $description = 'Verify plugin signatures, publisher trust, and integrity hashes';

    public function handle(
        ExtensionAuthenticityService $authenticityService,
        TrustedPublisherRegistry     $publisherRegistry,
        PluginIntegrityValidator     $integrityValidator
    ): int {
        $path        = $this->argument('path');
        $moduleId    = $this->option('module');
        $publisherId = $this->option('publisher') ?? 'unknown';
        $declaredHash = $this->option('hash') ?? '';

        $this->info("Signature Verification: [{$moduleId}]");
        $this->line(str_repeat('─', 50));

        $this->line('Publisher trusted : ' . ($publisherRegistry->isTrusted($publisherId) ? '<fg=green>YES</>' : '<fg=red>NO</>'));

        $computedHash = $integrityValidator->computeHash($path);
        $this->line('Computed hash     : ' . ($computedHash ?? '<fg=red>FAILED</>'));

        if ($declaredHash) {
            $hashValid = $declaredHash === $computedHash;
            $this->line('Hash match        : ' . ($hashValid ? '<fg=green>YES</>' : '<fg=red>NO</>'));
        }

        if ($declaredHash && $publisherId !== 'unknown') {
            $result = $authenticityService->authenticate($moduleId, $publisherId, $path, $declaredHash);

            $this->newLine();
            $this->line('Trust level       : ' . $result['trust_level']);
            $this->line('Can execute       : ' . ($result['can_execute'] ? '<fg=green>YES</>' : '<fg=red>NO</>'));

            return $result['can_execute'] ? self::SUCCESS : self::FAILURE;
        }

        $this->newLine();
        $this->warn('Provide --publisher and --hash for full verification.');
        return self::SUCCESS;
    }
}
