<?php

namespace SaaSPlatform\CoreUtils\Console\Commands\AI;

use Illuminate\Console\Command;
use SaaSPlatform\CoreUtils\AI\AiProviderManager;
use SaaSPlatform\CoreUtils\AI\Observability\AiProviderDiagnostics;

class TestAiProviderCommand extends Command
{
    protected $signature = 'ai:test-provider
        {provider? : Provider name to test (openai|claude). Omit to test all.}
        {--json : Output as JSON}';

    protected $description = 'Test AI provider connectivity and response quality';

    public function handle(AiProviderManager $manager, AiProviderDiagnostics $diagnostics): int
    {
        $providerArg = $this->argument('provider');
        $jsonMode    = (bool) $this->option('json');

        if ($jsonMode) {
            $results = $providerArg
                ? [$providerArg => $diagnostics->testProvider($providerArg)]
                : array_map(fn ($name) => $diagnostics->testProvider($name), array_keys($manager->all()));
            $this->line(json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            return Command::SUCCESS;
        }

        $providers = $providerArg ? [$providerArg] : array_keys($manager->all());

        if (empty($providers)) {
            $this->warn('No AI providers registered. Configure AI_PROVIDER and register providers in CoreUtilsServiceProvider.');
            return Command::FAILURE;
        }

        $this->newLine();
        $this->line('<fg=bright-blue;options=bold>AI Provider Test</>');
        $this->line(str_repeat('─', 50));

        $anyFailed = false;
        foreach ($providers as $name) {
            $this->line("Testing <fg=cyan>{$name}</>...");
            $result = $diagnostics->testProvider($name);

            if ($result['success']) {
                $this->line(" <fg=green>✓</> Connected — {$result['latency']}ms — response: \"{$result['response']}\"");
                if (! empty($result['meta']['model'])) {
                    $this->line("   Model: {$result['meta']['model']}");
                }
                if (! empty($result['meta']['total_tokens'])) {
                    $this->line("   Tokens: {$result['meta']['total_tokens']}");
                }
            } else {
                $this->line(" <fg=red>✗</> Failed — {$result['error']}");
                $anyFailed = true;
            }
            $this->newLine();
        }

        return $anyFailed ? Command::FAILURE : Command::SUCCESS;
    }
}
