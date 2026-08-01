<?php

namespace SaaSPlatform\Modules\Crm\Services;

/**
 * Phase 19 — Part 1/9: modeled directly on Tickets'
 * TicketsIsolationValidationService (packages/saas-platform/modules/
 * tickets/src/Services/TicketsIsolationValidationService.php) — same
 * mechanical enforcement that this module's own src/ never imports the
 * host's Eloquent models directly, resolving User/Site instead via
 * config('crm.*'). Two deliberate corrections versus the copied
 * precedent (see Phase 19 Part 1 audit finding):
 *  - config/ is excluded from the scan (the one sanctioned place this
 *    module declares host-model class-string bindings, see config/crm.php);
 *  - this service's own file (and its console command wrapper) are
 *    excluded too, since a regex that detects "the host namespace,
 *    backslash" cannot help but match its own pattern definition and its
 *    own explanatory comments — a self-referential false positive that
 *    the copied Tickets version has as well, just never triggered on
 *    itself before.
 */
class CrmIsolationValidationService
{
    /**
     * Relative to src/ — files that legitimately need to reference the
     * host namespace in order to describe or implement this very check.
     */
    private const SELF_EXCLUDED_FILES = [
        'Services/CrmIsolationValidationService.php',
        'Console/Commands/ValidateCrmIsolationCommand.php',
    ];

    /** @var array<int, array{id: string, label: string, passed: bool, detail: string}> */
    private array $checks = [];

    public function run(): array
    {
        $this->checkConfigExists('F01', 'crm config exists');
        $this->checkNoAppImports('S01', 'no host-namespace imports in package src/ files');

        $score = count($this->checks) > 0
            ? (int) round((count(array_filter($this->checks, fn ($c) => $c['passed'])) / count($this->checks)) * 100)
            : 0;

        return [
            'score' => $score,
            'passed' => $score === 100,
            'checks' => $this->checks,
        ];
    }

    private function checkConfigExists(string $id, string $label): void
    {
        $exists = config('crm') !== null;

        $this->checks[] = [
            'id' => $id,
            'label' => $label,
            'passed' => $exists,
            'detail' => $exists ? 'OK' : 'crm config not loaded',
        ];
    }

    private function checkNoAppImports(string $id, string $label): void
    {
        $srcPath = realpath(__DIR__ . '/../');
        $violations = [];
        $needle = 'App' . '\\'; // built at runtime — see class docblock for why

        if ($srcPath && is_dir($srcPath)) {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($srcPath, \FilesystemIterator::SKIP_DOTS)
            );

            foreach ($iterator as $file) {
                if ($file->getExtension() !== 'php') {
                    continue;
                }

                $relativePath = str_replace($srcPath . DIRECTORY_SEPARATOR, '', $file->getPathname());
                $relativePath = str_replace('\\', '/', $relativePath);

                if (in_array($relativePath, self::SELF_EXCLUDED_FILES, true)) {
                    continue;
                }

                $contents = file_get_contents($file->getPathname());

                if (preg_match('/\buse\s+' . preg_quote($needle, '/') . '/', $contents)
                    || preg_match('/[^a-zA-Z]' . preg_quote($needle, '/') . '(?!Adapters)/', $contents)
                ) {
                    $violations[] = "src/{$relativePath}";
                }
            }
        }

        $passed = count($violations) === 0;
        $this->checks[] = [
            'id' => $id,
            'label' => $label,
            'passed' => $passed,
            'detail' => $passed ? 'OK' : implode(', ', $violations),
        ];
    }
}
