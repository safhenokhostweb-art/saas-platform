<?php

namespace SaaSPlatform\CoreUtils\Console\Commands\Governance;

use Illuminate\Console\Command;
use SaaSPlatform\CoreUtils\RuntimeGovernance\SelfHealing\RecoveryStrategyManager;
use SaaSPlatform\CoreUtils\RuntimeGovernance\SelfHealing\FailureClassificationEngine;

class TestRecoveryCommand extends Command
{
    protected $signature = 'governance:test-recovery
                            {strategy : Recovery strategy to test}
                            {--json   : Output as JSON}
                            {--dry-run: Show classification only, skip execution}';

    protected $description = 'Test a specific recovery strategy against the self-healing engine';

    private const SAFE_TEST_STRATEGIES = [
        'health_check_retry',
        'telemetry',
        'alert',
    ];

    public function handle(
        RecoveryStrategyManager    $manager,
        FailureClassificationEngine $classifier
    ): int {
        $strategy = $this->argument('strategy');

        if ($this->option('dry-run')) {
            $idempotent  = $manager->isIdempotentSafe($strategy);
            $autoSafe    = in_array($strategy, self::SAFE_TEST_STRATEGIES, true);
            $suggestions = $classifier->suggestedStrategies('TRANSIENT');

            $info = [
                'strategy'       => $strategy,
                'is_idempotent'  => $idempotent,
                'auto_safe'      => $autoSafe,
                'example_for_transient' => $suggestions,
            ];

            $this->option('json')
                ? $this->line(json_encode($info, JSON_PRETTY_PRINT))
                : $this->table(['Field', 'Value'], array_map(
                    fn($k, $v) => [$k, is_bool($v) ? ($v ? 'true' : 'false') : (is_array($v) ? implode(', ', $v) : $v)],
                    array_keys($info), array_values($info)
                ));

            return 0;
        }

        if (!in_array($strategy, self::SAFE_TEST_STRATEGIES, true)) {
            $this->error('Only safe strategies can be directly tested: ' . implode(', ', self::SAFE_TEST_STRATEGIES));
            $this->line('Use --dry-run to inspect any strategy without executing.');
            return 1;
        }

        $this->info('Testing recovery strategy: ' . $strategy);

        $result = $manager->execute($strategy, ['_test' => true]);

        if ($this->option('json')) {
            $this->line(json_encode($result, JSON_PRETTY_PRINT));
            return 0;
        }

        $this->line('  Status      : ' . ($result['status'] ?? 'unknown'));
        $this->line('  Recovery ID : ' . ($result['recovery_id'] ?? 'N/A'));

        if (!empty($result['error'])) {
            $this->error('  Error: ' . $result['error']);
            return 1;
        }

        $this->info('Strategy test completed.');
        return 0;
    }
}
