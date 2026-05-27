<?php

namespace SaaSPlatform\CoreUtils\Console\Commands\Enterprise;

use Illuminate\Console\Command;
use SaaSPlatform\CoreUtils\Enterprise\EnterpriseProductExperienceService;

class ValidateProductExperienceCommand extends Command
{
    protected $signature = 'product:validate-experience
                            {--strict : Fail on warnings, not just errors}
                            {--json   : Output results as JSON}
                            {--file=  : Validate a single blade file}';

    protected $description = 'Validate enterprise product experience standards';

    public function handle(EnterpriseProductExperienceService $service): int
    {
        $strict  = $this->option('strict');
        $asJson  = $this->option('json');
        $singleFile = $this->option('file');

        if ($singleFile) {
            $result = $service->validateView($singleFile);
            if ($asJson) {
                $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                return $result['issue_count'] > 0 ? self::FAILURE : self::SUCCESS;
            }
            $this->table(['Issue', 'Type', 'Severity'], array_map(fn ($i) => [
                $i['message'], $i['type'], $i['severity'] ?? 'error',
            ], $result['issues']));
            return $result['issue_count'] > 0 ? self::FAILURE : self::SUCCESS;
        }

        $this->info('🔍 Validating enterprise product experience...');
        $result = $service->validate(['strict' => $strict]);

        if ($asJson) {
            $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            return $result['passed'] ? self::SUCCESS : self::FAILURE;
        }

        $this->newLine();
        $grade = $result['grade'];
        $score = $result['score'];
        $color = $score >= 90 ? 'green' : ($score >= 70 ? 'yellow' : 'red');

        $this->line("  <fg=white;options=bold>Product Experience Score</> <fg={$color};options=bold>{$score}/100 ({$grade})</>");
        $this->newLine();

        $rows = [];
        foreach ($result['findings'] as $f) {
            $icon = match($f['level']) { 'pass' => '✓', 'info' => 'ℹ', 'warning' => '⚠', 'error' => '✗', default => '?' };
            $color = match($f['level']) { 'pass' => 'green', 'info' => 'blue', 'warning' => 'yellow', 'error' => 'red', default => 'white' };
            $rows[] = [
                "<fg={$color}>{$icon}</>",
                "<fg=gray>{$f['category']}</>",
                $f['message'],
            ];
        }
        $this->table(['', 'Category', 'Finding'], $rows);

        $summary = $result['summary'];
        $this->newLine();
        $this->line("  <fg=green>✓ Pass: {$summary['pass']}</> | <fg=blue>ℹ Info: {$summary['info']}</> | <fg=yellow>⚠ Warning: {$summary['warning']}</> | <fg=red>✗ Error: {$summary['error']}</>");
        $this->newLine();

        if ($result['passed']) {
            $this->info('  ✅ Product experience validation PASSED');
        } else {
            $this->error('  ❌ Product experience validation FAILED');
        }

        return $result['passed'] ? self::SUCCESS : self::FAILURE;
    }
}
