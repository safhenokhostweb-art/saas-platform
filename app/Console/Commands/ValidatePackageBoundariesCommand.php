<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * PHASE 8.0 — Scans package source files for illegal App\ imports.
 * Fails (exit 1) if any cross-boundary violations are found.
 * Wire into CI to catch regressions before merge.
 */
class ValidatePackageBoundariesCommand extends Command
{
    protected $signature   = 'package:validate-boundaries {--json : Output as JSON}';
    protected $description = 'Scan package source files for illegal App\\ namespace imports';

    /** Packages that must not import from App\ */
    private const PACKAGE_ROOTS = [
        'packages/saas-platform/core-utils/src',
        'packages/saas-platform/modules/tickets/src',
        'packages/saas-platform/modules/forms/src',
    ];

    /** Allowed App\ imports (bridge / compatibility shims only) */
    private const ALLOWED_PATTERNS = [
        // base controller — will be replaced by SaaSPlatform\CoreUtils\Http\Controllers\BaseController
        'App\Http\Controllers\Controller',
    ];

    private array $violations = [];
    private array $clean      = [];

    public function handle(): int
    {
        foreach (static::PACKAGE_ROOTS as $root) {
            $absoluteRoot = base_path($root);
            if (!File::isDirectory($absoluteRoot)) {
                continue;
            }

            $files = File::allFiles($absoluteRoot);
            foreach ($files as $file) {
                if ($file->getExtension() !== 'php') {
                    continue;
                }

                $this->scanFile($file->getPathname(), $root);
            }
        }

        if ($this->option('json')) {
            $this->line(json_encode([
                'violations' => $this->violations,
                'clean_files' => count($this->clean),
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            return empty($this->violations) ? self::SUCCESS : self::FAILURE;
        }

        $this->printResults();

        return empty($this->violations) ? self::SUCCESS : self::FAILURE;
    }

    private function scanFile(string $filePath, string $packageRoot): void
    {
        $content  = File::get($filePath);
        $lines    = explode("\n", $content);
        $relative = ltrim(str_replace(base_path(), '', $filePath), DIRECTORY_SEPARATOR . '/');

        $fileViolations = [];

        foreach ($lines as $lineNo => $line) {
            // Match: use App\...  or  \App\...  in code
            if (!preg_match('/\buse\s+App\\\\/', $line) && !preg_match('/\bApp\\\\[A-Z]/', $line)) {
                continue;
            }

            // Check against allowlist
            $isAllowed = false;
            foreach (static::ALLOWED_PATTERNS as $allowed) {
                if (str_contains($line, $allowed)) {
                    $isAllowed = true;
                    break;
                }
            }

            if (!$isAllowed) {
                $fileViolations[] = [
                    'line'    => $lineNo + 1,
                    'content' => trim($line),
                ];
            }
        }

        if (empty($fileViolations)) {
            $this->clean[] = $relative;
        } else {
            $this->violations[] = [
                'file'       => $relative,
                'violations' => $fileViolations,
            ];
        }
    }

    private function printResults(): void
    {
        $this->newLine();

        if (!empty($this->violations)) {
            $this->error(sprintf('Package boundary violations found (%d files):', count($this->violations)));
            foreach ($this->violations as $v) {
                $this->warn("  {$v['file']}");
                foreach ($v['violations'] as $violation) {
                    $this->line("    Line {$violation['line']}: {$violation['content']}");
                }
            }
        }

        $this->newLine();
        $this->line(sprintf(
            '%d files scanned — %d clean, %d with violations',
            count($this->clean) + count($this->violations),
            count($this->clean),
            count($this->violations)
        ));

        if (empty($this->violations)) {
            $this->info('✓ All package boundaries are clean.');
        }
    }
}
