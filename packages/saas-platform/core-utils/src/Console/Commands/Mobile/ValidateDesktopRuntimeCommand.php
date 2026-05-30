<?php

namespace SaaSPlatform\CoreUtils\Console\Commands\Mobile;

use Illuminate\Console\Command;
use SaaSPlatform\CoreUtils\ClientRuntime\Desktop\DesktopRuntimeValidationService;

class ValidateDesktopRuntimeCommand extends Command
{
    protected $signature = 'mobile:validate-desktop-runtime
        {--platform=all  : Platform to validate: windows, linux, macos, all}
        {--json          : Output as JSON}
        {--strict        : Treat warnings as failures}
        {--fail-on-warning : Exit 1 if any warnings exist}';

    protected $description = 'Validate desktop client runtime: Windows/Linux compatibility, notifications, file attachments, presence, realtime';

    public function handle(DesktopRuntimeValidationService $validator): int
    {
        $platformOption = strtolower((string) $this->option('platform'));
        $strict         = (bool) $this->option('strict');
        $failWarn       = (bool) $this->option('fail-on-warning');

        if ($platformOption === 'all') {
            $results = $validator->validateAll();
        } else {
            $results = [$platformOption => $validator->validate($platformOption)];
        }

        if ($strict) {
            foreach ($results as &$result) {
                foreach ($result['checks'] as &$check) {
                    if ($check['status'] === 'warn') $check['status'] = 'fail';
                }
                unset($check);
                $result['passed'] = count(array_filter($result['checks'], fn ($c) => $c['status'] === 'pass'));
                $result['failed'] = count(array_filter($result['checks'], fn ($c) => $c['status'] === 'fail'));
                $result['warned'] = 0;
                $result['score']  = (int) round($result['passed'] / $result['total'] * 100);
                $result['grade']  = $this->grade($result['score']);
            }
            unset($result);
        }

        if ($this->option('json')) {
            $this->line(json_encode(
                count($results) === 1 ? array_values($results)[0] : $results,
                JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE,
            ));

            return $this->exitCode($results, $failWarn, $strict);
        }

        $this->renderHuman($results);

        return $this->exitCode($results, $failWarn, $strict);
    }

    private function renderHuman(array $results): void
    {
        $this->newLine();
        $this->line('<fg=bright-cyan;options=bold>Desktop Client Runtime Validation</>');
        $this->line(str_repeat('─', 60));

        foreach ($results as $platform => $result) {
            $score  = $result['score'];
            $color  = $score >= 90 ? 'green' : ($score >= 70 ? 'yellow' : 'red');
            $icon   = $result['desktop_ready'] ? '<fg=green>✓</>' : '<fg=red>✗</>';

            $this->newLine();
            $this->line("  {$icon} <options=bold>" . strtoupper($platform) . "</>");

            foreach ($result['checks'] as $check) {
                $statusIcon = match ($check['status']) {
                    'pass'  => '<fg=green>✓</>',
                    'warn'  => '<fg=yellow>⚠</>',
                    'fail'  => '<fg=red>✗</>',
                    default => '?',
                };
                $this->line("     {$statusIcon}  {$check['name']}: {$check['message']}");
            }

            $this->newLine();
            $grade = $result['grade'];
            $this->line("     <fg={$color};options=bold>Score: {$score}/100 (Grade {$grade})</> — {$result['passed']}/{$result['total']} passed, {$result['warned']} warnings, {$result['failed']} failed");

            if ($result['desktop_ready']) {
                $this->line('     <fg=green>Desktop-Ready ✓</>');
            } else {
                $this->line('     <fg=red>Not Desktop-Ready — resolve failures above</>');
            }
        }

        $this->newLine();
        $this->line(str_repeat('─', 60));
        $this->newLine();
    }

    private function exitCode(array $results, bool $failWarn, bool $strict): int
    {
        $anyFailed  = false;
        $anyWarned  = false;

        foreach ($results as $result) {
            if ($result['failed'] > 0) $anyFailed = true;
            if ($result['warned'] > 0) $anyWarned = true;
        }

        if ($anyFailed) return self::FAILURE;
        if ($failWarn && $anyWarned) return self::FAILURE;
        return self::SUCCESS;
    }

    private function grade(int $score): string
    {
        return match (true) {
            $score >= 95 => 'A',
            $score >= 85 => 'B',
            $score >= 70 => 'C',
            $score >= 55 => 'D',
            default      => 'F',
        };
    }
}
