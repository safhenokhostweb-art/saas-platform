<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * PHASE 8.0 — Scans log files and cached exception dumps for plaintext secrets.
 */
class ScanSecretsCommand extends Command
{
    protected $signature   = 'security:scan-secrets {--json : Output results as JSON}';
    protected $description = 'Scan log files and cache dumps for exposed secrets (license_key, tokens, passwords)';

    private const PATTERNS = [
        'license_key'  => '/["\']license_key["\']\s*[=:>]+\s*["\']([\w\-]{10,})["\']/',
        'bearer_token' => '/Bearer\s+([\w\-\.]{20,})/',
        'password_log' => '/["\']password["\']\s*[=:>]+\s*["\']\S+["\']/',
        'api_key'      => '/["\']api_key["\']\s*[=:>]+\s*["\']([\w\-]{10,})["\']/',
        'private_key'  => '/-----BEGIN (RSA |EC )?PRIVATE KEY-----/',
    ];

    private array $findings = [];

    public function handle(): int
    {
        $this->scanDirectory(storage_path('logs'), '*.log');
        $this->scanDirectory(storage_path('framework/cache'), '*');

        if ($this->option('json')) {
            $this->line(json_encode($this->findings, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            return empty($this->findings) ? self::SUCCESS : self::FAILURE;
        }

        if (empty($this->findings)) {
            $this->info('✓ No plaintext secrets detected in logs or cache.');
            return self::SUCCESS;
        }

        $this->error('⚠ Potential secrets found:');
        foreach ($this->findings as $finding) {
            $this->warn(sprintf(
                '  [%s] %s:%d — %s',
                $finding['pattern'],
                basename($finding['file']),
                $finding['line'],
                Str::limit($finding['excerpt'], 80)
            ));
        }

        $this->newLine();
        $this->line(sprintf('Total findings: %d', count($this->findings)));
        $this->line('Action: rotate affected credentials immediately.');

        return self::FAILURE;
    }

    private function scanDirectory(string $dir, string $glob): void
    {
        if (!File::isDirectory($dir)) {
            return;
        }

        $files = File::glob(rtrim($dir, '/') . '/' . $glob);

        foreach ($files as $file) {
            if (!File::isFile($file) || File::size($file) > 50 * 1024 * 1024) {
                // Skip directories and files > 50 MB
                continue;
            }

            $this->scanFile($file);
        }
    }

    private function scanFile(string $file): void
    {
        try {
            $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            if ($lines === false) {
                return;
            }

            foreach ($lines as $lineNo => $line) {
                foreach (static::PATTERNS as $patternName => $regex) {
                    if (preg_match($regex, $line)) {
                        $this->findings[] = [
                            'pattern' => $patternName,
                            'file'    => $file,
                            'line'    => $lineNo + 1,
                            'excerpt' => Str::limit(trim($line), 120),
                        ];
                    }
                }
            }
        } catch (\Throwable) {
            // Unreadable files are silently skipped
        }
    }
}
